<?php
require_once '../../../app/helpers/require_login.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../app/helpers/anonymous_contact.php';
require_once '../../../app/helpers/document_tax.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método inválido.']);
    exit;
}

if (
    empty($_SESSION['csrf'])
    || !hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

// 🔥 DEBUG (remove em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Recebe dados
$proformData = $_POST['proform'] ?? [];
$itemData = $_POST['items'] ?? [];

// Converte proform array
$proforma = [];
foreach ($proformData as $field) {
    $proforma[$field['name']] = $field['value'];
}

// ID da proforma a editar
$editProformId = !empty($proforma['edit_proform_id']) ? (int)$proforma['edit_proform_id'] : 0;
unset($proforma['edit_proform_id']);

try {
    $pdo->beginTransaction();

    // 🔐 Validação sessão — nunca confiar em company_id/user_id vindos do POST
    if (empty($_SESSION['user']['company_id']) || empty($_SESSION['user']['id'])) {
        throw new Exception("Sessão inválida.");
    }

    $companyIdSession = (int)$_SESSION['user']['company_id'];
    $userIdSession = (int)$_SESSION['user']['id'];

    $companyLock = $pdo->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE');
    $companyLock->execute([$companyIdSession]);
    if (!$companyLock->fetchColumn()) {
        throw new RuntimeException('Empresa não encontrada.', 404);
    }

    $documentType = strtoupper(trim((string)($proforma['document_type'] ?? 'PF')));
    if (!in_array($documentType, ['PF'], true)) {
        throw new Exception("Tipo de documento inválido.");
    }

    if ($editProformId <= 0) {
        throw new Exception("ID da proforma a editar não informado.");
    }

    // 🔐 Confirma que a proforma pertence mesmo a esta empresa antes de tocar nela
    $ownerCheck = $pdo->prepare("SELECT id, reference FROM proformas WHERE id = ? AND company_id = ? FOR UPDATE");
    $ownerCheck->execute([$editProformId, $companyIdSession]);
    $existingProforma = $ownerCheck->fetch(PDO::FETCH_ASSOC);
    if (!$existingProforma) {
        throw new Exception("Proforma não encontrada para esta empresa.");
    }
    $reference = trim((string)($proforma['reference'] ?? ''));
    if ($reference === '') {
        $reference = (string)$existingProforma['reference'];
    }
    if ($reference !== (string)$existingProforma['reference']) {
        $referenceCheck = $pdo->prepare(
            'SELECT 1 FROM proformas WHERE company_id = ? AND reference = ? AND id <> ? LIMIT 1'
        );
        $referenceCheck->execute([$companyIdSession, $reference, $editProformId]);
        if ($referenceCheck->fetchColumn()) {
            throw new RuntimeException('Já existe uma proforma com esta referência nesta empresa.', 409);
        }
    }

    subscription_assert_active($pdo, $companyIdSession);
    $itemData = bx_document_assign_retention_rates($pdo, $itemData, $companyIdSession);
    $taxSummary = bx_document_company_tax_summary($pdo, $itemData, $companyIdSession);
    $taxWithholdingType = (string)($proforma['tax_withholding_type'] ?? 'none');
    $eligibilityConfirmed = (string)($proforma['tax_withholding_confirmed'] ?? '') === '1';
    $taxConfiguration = $taxWithholdingType === 'none'
        ? null
        : bx_document_active_tax_configuration($pdo, $companyIdSession, $taxWithholdingType, (string)$proforma['issue_date']);
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
    $isAnonymousRequest = ($proforma['anonymous_client'] ?? '0') === '1';
    $hasNewContactData = trim((string)($proforma['email'] ?? '')) !== ''
        || trim((string)($proforma['name'] ?? '')) !== ''
        || trim((string)($proforma['contributor'] ?? '')) !== '';

    if (empty($proforma['contact_id']) && ($isAnonymousRequest || !$hasNewContactData)) {
        // Cliente X (anónimo / consumidor final) — inclui o caso em que a
        // flag não chegou mas também não há dados suficientes para criar
        // um contacto novo: nunca inserir um contacto vazio/"fantasma".
        $contactId = get_anonymous_contact_id($pdo, $companyIdSession);
    } elseif (!empty($proforma['contact_id'])) {
        $contactId = (int)$proforma['contact_id'];
        require_owned('contact', $contactId);
    } else {

        $stmt = $pdo->prepare("SELECT id FROM contact WHERE email = ? AND company_id = ?");
        $stmt->execute([$proforma['email'], $companyIdSession]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $contactId = $existing['id'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO contact (name,email,contributor,address,po_box,country,city,company_id)
                VALUES (?,?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $proforma['name'],
                $proforma['email'],
                $proforma['contributor'] ?? null,
                $proforma['address'] ?? null,
                $proforma['po_box'] ?? null,
                $proforma['country'] ?? null,
                $proforma['city'] ?? null,
                $companyIdSession
            ]);

            $contactId = $pdo->lastInsertId();
        }
    }

    // Remove campos extras
    foreach (['contact_id', 'anonymous_client', 'name', 'email', 'telephone', 'address', 'contributor', 'po_box', 'country', 'city'] as $f) {
        unset($proforma[$f]);
    }

    // =========================
    // 📌 PROFORMA
    // =========================
    $proformDbFields = [
        'contact_id' => $contactId,
        'company_id' => $companyIdSession,
        'user_id' => $userIdSession,
        'issue_date' => $proforma['issue_date'],
        'due_date' => (int)$proforma['due_date'],
        'reference' => $reference,
        'observation' => $proforma['observation'] ?? null,
        'series' => $proforma['series'] ?? null,
        'retention' => 0.0,
        'currency' => $proforma['currency'],
        'manual_exchange_rate' => (float)($proforma['manual_exchange_rate'] ?? 1),
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
        'converted_total' => (float)($proforma['converted_total'] ?? 0),
        'status' => 1
    ];

    // =========================
    // 🧾 UPDATE
    // =========================
    $set = [];
    $values = [];

    foreach ($proformDbFields as $k => $v) {
        if ($k === 'status') continue;
        $set[] = "$k = ?";
        $values[] = $v;
    }

    $values[] = $editProformId;
    $values[] = $companyIdSession;

    $stmt = $pdo->prepare("UPDATE proformas SET " . implode(",", $set) . " WHERE id = ? AND company_id = ?");
    $stmt->execute($values);

    $proformId = $editProformId;

    $pdo->prepare("
        DELETE pi FROM proforma_items pi
        INNER JOIN proformas p ON p.id = pi.proforma_id
        WHERE pi.proforma_id = ? AND p.company_id = ?
    ")->execute([$proformId, $companyIdSession]);

    // =========================
    // 📦 ITENS
    // =========================
    if (empty($itemData)) {
        throw new Exception("Nenhum item enviado.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO proforma_items (proforma_id,item_id,quantity,unit_price,tax,discount,retention_rate)
        VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($itemData as $item) {

        if (empty($item['id'])) {
            throw new Exception("Item inválido.");
        }

        $stmt->execute([
            $proformId,
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
        'proform_id' => $proformId
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
