<?php

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../app/helpers/invoice_helper.php';

header('Content-Type: application/json');
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

/*
==================================================
STATUS HELPER (SEGURO)
==================================================
*/
function getInvoiceStatusId(PDO $pdo, string $status): int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM invoice_status
        WHERE name = ?
        LIMIT 1
    ");

    $stmt->execute([$status]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int)$row['id'] : 1; // draft fallback
}

/*
==================================================
GERAR NÚMERO DA FATURA
==================================================
*/
function generateInvoiceNumber(PDO $pdo, int $companyId): string
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 AS next_number
        FROM invoices
        WHERE company_id = ?
        AND status != 1
    ");

    $stmt->execute([$companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return str_pad((string)$row['next_number'], 4, '0', STR_PAD_LEFT);
}

/*
==================================================
STOCK HELPER
==================================================
*/
function applyStock(PDO $pdo, int $itemId, int $stockId, int $qty)
{
    if ($itemId <= 0 || $stockId <= 0 || $qty <= 0) return;

    $stmt = $pdo->prepare("CALL update_stock_quantity(?, ?, ?)");
    $stmt->execute([$stockId, $itemId, -$qty]);

    while ($stmt->nextRowset()) {
    }
}

/*
==================================================
RECEBER DADOS
==================================================
*/
$invoiceRaw = $_POST['invoice'] ?? [];
$itemsRaw   = $_POST['items'] ?? [];

if (is_string($invoiceRaw)) $invoiceRaw = json_decode($invoiceRaw, true);
if (is_string($itemsRaw)) $itemsRaw = json_decode($itemsRaw, true);

/*
==================================================
NORMALIZAR FATURA
==================================================
*/
$fatura = [];

if (isset($invoiceRaw[0]['name'])) {
    foreach ($invoiceRaw as $field) {
        $fatura[$field['name']] = $field['value'];
    }
} else {
    $fatura = $invoiceRaw;
}

$editInvoiceId = (int)($fatura['edit_invoice_id'] ?? 0);
unset($fatura['edit_invoice_id']);

try {

    $pdo->beginTransaction();

    $companyId = (int)($_SESSION['user']['company_id'] ?? 0);

    if (!$companyId) {
        throw new Exception("Sessão inválida.");
    }

    subscription_assert_active($pdo, $companyId);

    if ($editInvoiceId <= 0) {
        subscription_check_limit($pdo, $companyId, 'invoice');
    }

    /*
    ==================================================
    STATUS CORRETO (TABLE BASED)
    ==================================================
    */
    $statusName = $fatura['status'] ?? 'draft';
    $statusId   = getInvoiceStatusId($pdo, $statusName);

    $isDraft = ($statusName === 'draft' || $statusId === 1);

    /*
    ==================================================
    NUMERO FATURA
    ==================================================
    */
    $reference = null;

    if (!$isDraft) {
        $reference = generateInvoiceNumber($pdo, $companyId);
    }

    /*
    ==================================================
    CONTACTO
    ==================================================
    */
    if (!empty($fatura['contact_id'])) {
        $contactId = (int)$fatura['contact_id'];
    } else {

        $stmt = $pdo->prepare("
            SELECT id FROM contact
            WHERE email = ? AND company_id = ?
        ");

        $stmt->execute([$fatura['email'], $companyId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $contactId = (int)$existing['id'];
        } else {

            $stmt = $pdo->prepare("
                INSERT INTO contact
                (name,email,contributor,address,po_box,country,city,company_id)
                VALUES (?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $fatura['name'],
                $fatura['email'],
                $fatura['contributor'] ?? null,
                $fatura['address'] ?? null,
                $fatura['po_box'] ?? null,
                $fatura['country'] ?? null,
                $fatura['city'] ?? null,
                $companyId
            ]);

            $contactId = (int)$pdo->lastInsertId();
        }
    }

    /*
    ==================================================
    FATURA
    ==================================================
    */
    $invoiceDbFields = [
        'contact_id' => $contactId,
        'company_id' => $companyId,
        'user_id' => (int)($fatura['user_id'] ?? 0),
        'issue_date' => $fatura['issue_date'] ?? date('Y-m-d'),
        'due_date' => (int)($fatura['due_date'] ?? 0),

        'reference' => $reference,
        'series' => $fatura['series'] ?? 'FT',

        'status' => $statusId,

        'retention' => (float)($fatura['retention'] ?? 0),
        'currency' => $fatura['currency'] ?? 'AOA',
        'manual_exchange_rate' => (float)($fatura['manual_exchange_rate'] ?? 1),

        'total_sum' => (float)($fatura['total_sum'] ?? 0),
        'total_discount' => (float)($fatura['total_discount'] ?? 0),
        'subtotal_without_tax' => (float)($fatura['subtotal_without_tax'] ?? 0),
        'total_tax' => (float)($fatura['total_tax'] ?? 0),
        'retention_value' => (float)($fatura['retention_value'] ?? 0),
        'final_total' => (float)($fatura['final_total'] ?? 0),
        'converted_total' => (float)($fatura['converted_total'] ?? 0),
    ];

    /*
    ==================================================
    INSERT / UPDATE
    ==================================================
    */
    if ($editInvoiceId > 0) {

        $set = [];
        $values = [];

        foreach ($invoiceDbFields as $k => $v) {
            $set[] = "$k = ?";
            $values[] = $v;
        }

        $values[] = $editInvoiceId;

        $stmt = $pdo->prepare("
            UPDATE invoices SET " . implode(',', $set) . "
            WHERE id = ?
        ");

        $stmt->execute($values);

        $invoiceId = $editInvoiceId;

        $pdo->prepare("
            DELETE FROM invoice_items WHERE invoice_id = ?
        ")->execute([$invoiceId]);
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO invoices (" . implode(',', array_keys($invoiceDbFields)) . ")
            VALUES (" . implode(',', array_fill(0, count($invoiceDbFields), '?')) . ")
        ");

        $stmt->execute(array_values($invoiceDbFields));

        $invoiceId = (int)$pdo->lastInsertId();
    }

    /*
    ==================================================
    ITEMS
    ==================================================
    */
    if (empty($itemsRaw)) {
        throw new Exception("Nenhum item enviado.");
    }

    $insertItem = $pdo->prepare("
        INSERT INTO invoice_items
        (invoice_id,item_id,quantity,unit_price,tax,discount)
        VALUES (?,?,?,?,?,?)
    ");

    $getItem = $pdo->prepare("
        SELECT id, code, stock_id
        FROM items
        WHERE id = ?
    ");

    foreach ($itemsRaw as $item) {

        $itemId = (int)($item['id'] ?? 0);
        $qty    = (int)($item['quantity'] ?? 0);

        if ($itemId <= 0) continue;

        $insertItem->execute([
            $invoiceId,
            $itemId,
            $qty,
            (float)($item['unit_price'] ?? 0),
            (string)($item['tax'] ?? '0'),
            (float)($item['discount'] ?? 0)
        ]);

        if ($isDraft) continue;

        $getItem->execute([$itemId]);
        $it = $getItem->fetch(PDO::FETCH_ASSOC);

        if (!$it) continue;

        $code = strtoupper(trim($it['code']));
        $stockId = (int)$it['stock_id'];

        if (strpos($code, 'SERV') === 0) continue;

        applyStock($pdo, $itemId, $stockId, $qty);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'invoice_id' => $invoiceId,
        'reference' => $reference,
        'status' => $statusName,
        'message' => 'Fatura processada com sucesso'
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) $pdo->rollBack();

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
