<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../app/helpers/anonymous_contact.php';

header('Content-Type: application/json');
session_start();

// 🔥 DEBUG (remove em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Recebe dados
$invoiceData = $_POST['invoice'] ?? [];
$itemData = $_POST['items'] ?? [];

// Converte invoice array
$fatura = [];
foreach ($invoiceData as $field) {
    $fatura[$field['name']] = $field['value'];
}

// ID da fatura a editar
$editInvoiceId = !empty($fatura['edit_invoice_id']) ? (int)$fatura['edit_invoice_id'] : 0;
unset($fatura['edit_invoice_id']);

try {
    $pdo->beginTransaction();

    // 🔐 Validação sessão — nunca confiar em company_id/user_id vindos do POST
    if (empty($_SESSION['user']['company_id']) || empty($_SESSION['user']['id'])) {
        throw new Exception("Sessão inválida.");
    }

    $companyIdSession = (int)$_SESSION['user']['company_id'];
    $userIdSession = (int)$_SESSION['user']['id'];

    if ($editInvoiceId <= 0) {
        throw new Exception("ID da fatura a editar não informado.");
    }

    // 🔐 Confirma que a fatura pertence mesmo a esta empresa antes de tocar nela
    $ownerCheck = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND company_id = ?");
    $ownerCheck->execute([$editInvoiceId, $companyIdSession]);
    if (!$ownerCheck->fetch()) {
        throw new Exception("Fatura não encontrada para esta empresa.");
    }

    subscription_assert_active($pdo, $companyIdSession);

    // =========================
    // 📌 CONTACTO
    // =========================
    $isAnonymousRequest = ($fatura['anonymous_client'] ?? '0') === '1';
    $hasNewContactData = trim((string)($fatura['email'] ?? '')) !== ''
        || trim((string)($fatura['name'] ?? '')) !== ''
        || trim((string)($fatura['contributor'] ?? '')) !== '';

    if (empty($fatura['contact_id']) && ($isAnonymousRequest || !$hasNewContactData)) {
        // Cliente X (anónimo / consumidor final) — inclui o caso em que a
        // flag não chegou mas também não há dados suficientes para criar
        // um contacto novo: nunca inserir um contacto vazio/"fantasma".
        $contactId = get_anonymous_contact_id($pdo, $companyIdSession);
    } elseif (!empty($fatura['contact_id'])) {
        $contactId = (int)$fatura['contact_id'];
    } else {

        $stmt = $pdo->prepare("SELECT id FROM contact WHERE email = ? AND company_id = ?");
        $stmt->execute([$fatura['email'], $companyIdSession]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $contactId = $existing['id'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO contact (name,email,contributor,address,po_box,country,city,company_id)
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
                $companyIdSession
            ]);

            $contactId = $pdo->lastInsertId();
        }
    }

    // Remove campos extras
    foreach (['contact_id', 'anonymous_client', 'name', 'email', 'telephone', 'address', 'contributor', 'po_box', 'country', 'city'] as $f) {
        unset($fatura[$f]);
    }

    // =========================
    // 📌 FATURA
    // =========================
    $invoiceDbFields = [
        'contact_id' => $contactId,
        'company_id' => $companyIdSession,
        'user_id' => $userIdSession,
        'issue_date' => $fatura['issue_date'],
        'due_date' => (int)$fatura['due_date'],
        'reference' => $fatura['reference'] ?? null,
        'observation' => $fatura['observation'] ?? null,
        'series' => $fatura['series'] ?? null,
        'retention' => (float)($fatura['retention'] ?? 0),
        'currency' => $fatura['currency'],
        'manual_exchange_rate' => (float)($fatura['manual_exchange_rate'] ?? 1),
        'total_sum' => (float)$fatura['total_sum'],
        'total_discount' => (float)($fatura['total_discount'] ?? 0),
        'subtotal_without_tax' => (float)($fatura['subtotal_without_tax'] ?? 0),
        'total_tax' => (float)($fatura['total_tax'] ?? 0),
        'retention_value' => (float)($fatura['retention_value'] ?? 0),
        'final_total' => (float)$fatura['final_total'],
        'converted_total' => (float)($fatura['converted_total'] ?? 0),
        'status' => 1
    ];

    // =========================
    // 🧾 UPDATE
    // =========================
    $set = [];
    $values = [];

    foreach ($invoiceDbFields as $k => $v) {
        if ($k === 'status') continue;
        $set[] = "$k = ?";
        $values[] = $v;
    }

    $values[] = $editInvoiceId;
    $values[] = $companyIdSession;

    $stmt = $pdo->prepare("UPDATE invoices SET " . implode(",", $set) . " WHERE id = ? AND company_id = ?");
    $stmt->execute($values);

    $invoiceId = $editInvoiceId;

    $pdo->prepare("
        DELETE ii FROM invoice_items ii
        INNER JOIN invoices i ON i.id = ii.invoice_id
        WHERE ii.invoice_id = ? AND i.company_id = ?
    ")->execute([$invoiceId, $companyIdSession]);

    // =========================
    // 📦 ITENS
    // =========================
    if (empty($itemData)) {
        throw new Exception("Nenhum item enviado.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO invoice_items (invoice_id,item_id,quantity,unit_price,tax,discount)
        VALUES (?,?,?,?,?,?)
    ");

    foreach ($itemData as $item) {

        if (empty($item['id'])) {
            throw new Exception("Item inválido.");
        }

        $stmt->execute([
            $invoiceId,
            (int)$item['id'],
            (float)$item['quantity'],
            (float)$item['unit_price'],
            (float)$item['tax'],
            (float)$item['discount']
        ]);
    }

    $pdo->commit();

    // 🔥 IMPORTANTE: retornar ID
    echo json_encode([
        'success' => true,
        'invoice_id' => $invoiceId
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
