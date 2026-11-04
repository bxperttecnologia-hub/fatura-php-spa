<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
require_once '../../../app/helpers/anonymous_contact.php';

header('Content-Type: application/json');
session_start();

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

try {
    $pdo->beginTransaction();

    // 🔐 Validação sessão — nunca confiar em company_id/user_id vindos do POST
    if (empty($_SESSION['user']['company_id']) || empty($_SESSION['user']['id'])) {
        throw new Exception("Sessão inválida.");
    }

    $companyIdSession = (int)$_SESSION['user']['company_id'];
    $userIdSession = (int)$_SESSION['user']['id'];

    $reference = trim((string)($proforma['reference'] ?? ''));
    if ($reference === '') {
        $year = date('Y');
        $stmtReference = $pdo->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED))
            FROM proformas
            WHERE company_id = ?
              AND reference LIKE ?
            FOR UPDATE
        ");
        $stmtReference->execute([$companyIdSession, "PF {$year}/%"]);
        $reference = sprintf('PF %s/%06d', $year, (int)$stmtReference->fetchColumn() + 1);
    }

    $documentType = strtoupper(trim((string)($proforma['document_type'] ?? 'PF')));
    if (!in_array($documentType, ['PF'], true)) {
        throw new Exception("Tipo de documento inválido.");
    }

    subscription_assert_active($pdo, $companyIdSession);
    subscription_check_limit($pdo, $companyIdSession, 'proforma'); // confirma que 'proforma' existe em subscription.php

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
                $proforma['address'] ?? "Angola",
                $proforma['po_box'] ?? null,
                $proforma['country'] ?? "Angola",
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
        'retention' => (float)($proforma['retention'] ?? 0),
        'currency' => $proforma['currency'],
        'manual_exchange_rate' => (float)($proforma['manual_exchange_rate'] ?? 1),
        'total_sum' => (float)$proforma['total_sum'],
        'total_discount' => (float)($proforma['total_discount'] ?? 0),
        'subtotal_without_tax' => (float)($proforma['subtotal_without_tax'] ?? 0),
        'total_tax' => (float)($proforma['total_tax'] ?? 0),
        'final_total' => (float)$proforma['final_total'],
        'converted_total' => (float)($proforma['converted_total'] ?? 0),
        'status' => 1
    ];

    // =========================
    // 🧾 INSERT
    // =========================
    $stmt = $pdo->prepare("
        INSERT INTO proformas (" . implode(",", array_keys($proformDbFields)) . ")
        VALUES (" . implode(",", array_fill(0, count($proformDbFields), "?")) . ")
    ");

    $stmt->execute(array_values($proformDbFields));

    $proformId = $pdo->lastInsertId();

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
            $proformId,
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
        'proform_id' => $proformId
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
