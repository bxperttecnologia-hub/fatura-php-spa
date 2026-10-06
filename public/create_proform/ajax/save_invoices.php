<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../app/helpers/anonymous_contact.php';

header('Content-Type: application/json');
session_start();

// 🔥 DEBUG (remove em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Recebe dados (mesmo formato da fatura: invoice[] = serializeArray, items[])
$invoiceData = $_POST['invoice'] ?? [];
$itemData = $_POST['items'] ?? [];

// Converte invoice array
$fatura = [];
foreach ($invoiceData as $field) {
    $fatura[$field['name']] = $field['value'];
}

// Detecta edição
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

    subscription_assert_active($pdo, $companyIdSession);

    if ($editInvoiceId <= 0) {
        subscription_check_limit($pdo, $companyIdSession, 'invoice');
    }

    // =========================
    // 📌 CONTACTO
    // =========================
    if (($fatura['anonymous_client'] ?? '0') === '1' && empty($fatura['contact_id'])) {
        // Cliente X (anónimo / consumidor final)
        $contactId = get_anonymous_contact_id($pdo, $companyIdSession);
    } elseif (!empty($fatura['contact_id'])) {
        $contactId = (int)$fatura['contact_id'];
    } else {

        if (empty($fatura['name']) || empty($fatura['email'])) {
            throw new Exception('Nome e e-mail do contato são obrigatórios.');
        }

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
    // 📌 PROFORMA
    // =========================
    $invoiceDbFields = [
        'contact_id' => $contactId,
        'company_id' => $companyIdSession,
        'user_id' => $userIdSession,
        'issue_date' => !empty($fatura['issue_date']) ? $fatura['issue_date'] : date('Y-m-d'),
        'due_date' => (int)($fatura['due_date'] ?? 0),
        'reference' => $fatura['reference'] ?? null,
        'observation' => $fatura['observation'] ?? null,
        'series' => $fatura['series'] ?? null,
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
        'status' => 1, // Rascunho
        'converted_invoice_id' => null
    ];

    // Colunas que a fatura tem mas que a tabela `proformas` pode ainda não ter:
    // só são gravadas se existirem (evita erro 500 até a migração ser aplicada).
    $proformaColumns = $pdo->query("SHOW COLUMNS FROM proformas")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['retention', 'retention_value', 'converted_total'] as $optionalCol) {
        if (!in_array($optionalCol, $proformaColumns, true)) {
            unset($invoiceDbFields[$optionalCol]);
        }
    }

    // =========================
    // 🔢 REFERÊNCIA PF (só na criação)
    // =========================
    if ($editInvoiceId <= 0) {

        $year = date('Y');

        $stmtRef = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED))
            FROM proformas
            WHERE company_id = ?
              AND reference LIKE ?
            FOR UPDATE
        ");

        $stmtRef->execute([$companyIdSession, "PF {$year}/%"]);

        $nextNumber = ((int)$stmtRef->fetchColumn()) + 1;

        $invoiceDbFields['reference'] = sprintf('PF %s/%06d', $year, $nextNumber);
    }

    // =========================
    // 🧾 INSERT / UPDATE
    // =========================
    if ($editInvoiceId > 0) {

        $stmtCheck = $pdo->prepare("
            SELECT id, converted_invoice_id
            FROM proformas
            WHERE id = ? AND company_id = ?
            LIMIT 1
        ");

        $stmtCheck->execute([$editInvoiceId, $companyIdSession]);

        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new Exception("Proforma não encontrada.");
        }

        if (!empty($existing['converted_invoice_id'])) {
            throw new Exception("Esta proforma já foi convertida e não pode ser alterada.");
        }

        $set = [];
        $values = [];

        foreach ($invoiceDbFields as $field => $value) {

            // nunca alterar a referência, nem o vínculo de conversão
            if (in_array($field, ['reference', 'converted_invoice_id'], true)) {
                continue;
            }

            $set[] = "{$field} = ?";
            $values[] = $value;
        }

        $values[] = $editInvoiceId;
        $values[] = $companyIdSession;

        $stmt = $pdo->prepare("
            UPDATE proformas
            SET " . implode(',', $set) . "
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute($values);

        $invoiceId = $editInvoiceId;

        $pdo->prepare("DELETE FROM proforma_items WHERE proforma_id = ?")->execute([$invoiceId]);
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO proformas (" . implode(",", array_keys($invoiceDbFields)) . ")
            VALUES (" . implode(",", array_fill(0, count($invoiceDbFields), "?")) . ")
        ");

        $stmt->execute(array_values($invoiceDbFields));

        $invoiceId = (int)$pdo->lastInsertId();
    }

    // =========================
    // 📦 ITENS
    // =========================
    if (empty($itemData)) {
        throw new Exception("Nenhum item enviado.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO proforma_items (proforma_id,item_id,quantity,unit_price,tax,discount)
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
        'proform_id' => $invoiceId
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => $e->getMessage()
    ]);
}
