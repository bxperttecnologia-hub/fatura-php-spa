<?php
declare(strict_types=1);

require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['user']['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Empresa não encontrada na sessão.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$companyId = (int)$_SESSION['user']['company_id'];

$invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);

if (!$invoiceId || $invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da fatura inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Notas de débito já emitidas para esta fatura (mais recente primeiro)
    $stmt = $pdo->prepare("
        SELECT id, serie, number, issue_date, final_total, currency
        FROM debit_notes
        WHERE invoice_id = :invoice_id
          AND company_id = :company_id
        ORDER BY id DESC
    ");
    $stmt->execute([':invoice_id' => $invoiceId, ':company_id' => $companyId]);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
