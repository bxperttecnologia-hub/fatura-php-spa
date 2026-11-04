<?php
require_once '../../../app/helpers/require_login.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../app/helpers/anonymous_contact.php';
require_once '../../../app/helpers/document_tax.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

if (
    empty($_SESSION['csrf'])
    || !hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

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
    $ownerCheck = $pdo->prepare("SELECT id, status FROM invoices WHERE id = ? AND company_id = ? FOR UPDATE");
    $ownerCheck->execute([$editInvoiceId, $companyIdSession]);
    $existingInvoice = $ownerCheck->fetch(PDO::FETCH_ASSOC);
    if (!$existingInvoice) {
        throw new Exception("Fatura não encontrada para esta empresa.");
    }
    if ((int)$existingInvoice['status'] !== 1) {
        throw new RuntimeException("Só é permitido editar faturas em rascunho.", 409);
    }

    subscription_assert_active($pdo, $companyIdSession);
    $itemData = bx_document_assign_retention_rates($pdo, $itemData, $companyIdSession);
    $taxSummary = bx_document_company_tax_summary($pdo, $itemData, $companyIdSession);
    $taxWithholdingType = (string)($fatura['tax_withholding_type'] ?? 'none');
    $eligibilityConfirmed = (string)($fatura['tax_withholding_confirmed'] ?? '') === '1';
    $taxConfiguration = $taxWithholdingType === 'none'
        ? null
        : bx_document_active_tax_configuration($pdo, $companyIdSession, $taxWithholdingType, (string)$fatura['issue_date']);
    $regimeStmt = $pdo->prepare('SELECT vat_regime FROM companies WHERE id = ?');
    $regimeStmt->execute([$companyIdSession]);
    $taxRegime = $regimeStmt->fetchColumn();
    $withholdingSummary = bx_document_tax_withholding_summary(
        $itemData,
        $taxSummary,
        $taxWithholdingType,
        $taxConfiguration,
        $eligibilityConfirmed,
        $taxRegime === false ? null : (string)$taxRegime
    );

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
        require_owned('contact', $contactId);
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
        // FR é paga no acto: não tem vencimento.
        'due_date' => strtoupper((string)($fatura['document_type'] ?? '')) === 'FR' ? 0 : (int)$fatura['due_date'],
        'reference' => $fatura['reference'] ?? null,
        'observation' => $fatura['observation'] ?? null,
        'series' => $fatura['series'] ?? null,
        'retention' => 0.0,
        'currency' => $fatura['currency'],
        'manual_exchange_rate' => (float)($fatura['manual_exchange_rate'] ?? 1),
        'total_sum' => $taxSummary['total_sum'],
        'total_discount' => $taxSummary['total_discount'],
        'subtotal_without_tax' => $taxSummary['subtotal'],
        'total_tax' => $taxSummary['total_tax'],
        'retention_value' => $taxSummary['retention'],
        'tax_withholding_type' => $withholdingSummary['type'],
        'tax_withholding_rate' => $withholdingSummary['rate'],
        'tax_withholding_base' => $withholdingSummary['base'],
        'tax_withholding_amount' => $withholdingSummary['amount'],
        'tax_withholding_description' => $withholdingSummary['description'],
        'tax_withholding_confirmed' => $withholdingSummary['confirmed'] ? 1 : 0,
        'gross_total' => $withholdingSummary['gross_total'],
        'net_payable' => $withholdingSummary['net_payable'],
        'final_total' => $withholdingSummary['net_payable'],
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
        INSERT INTO invoice_items (invoice_id,item_id,quantity,unit_price,tax,discount,retention_rate)
        VALUES (?,?,?,?,?,?,?)
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
            (float)$item['discount'],
            (float)$item['retention_rate']
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

    $statusCode = $e instanceof InvalidArgumentException
        ? 422
        : (in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 500);
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
