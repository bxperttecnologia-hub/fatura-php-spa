<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');
session_start();

$invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
if (!$invoiceId) {
    echo json_encode(['success' => false, 'message' => 'invoice_id é obrigatório']);
    exit;
}

try {
    $st = $pdo->prepare('DELETE FROM invoices WHERE id = :id AND company_id = :company_id; DELETE FROM invoice_items WHERE invoice_id = :id;');
    $st->execute(['id' => $invoiceId, 'company_id' => $companyId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $r ?: null]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
