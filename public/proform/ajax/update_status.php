<?php
require_once '../../../app/config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$invoiceId = $_POST['invoice_id'] ?? null;
$newStatusName = $_POST['new_status'] ?? 'Pendente';

if (!$invoiceId) {
    echo json_encode(['success' => false, 'error' => 'ID da fatura inválido']);
    exit;
}

try {
    $pdo->beginTransaction();

    $invoiceStmt = $pdo->prepare("
        SELECT i.status, s.name AS status_name
        FROM invoices i
        LEFT JOIN invoice_status s ON s.id = i.status
        WHERE i.id = :id
        FOR UPDATE
    ");
    $invoiceStmt->execute([':id' => $invoiceId]);
    $invoice = $invoiceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) {
        throw new RuntimeException('Fatura não encontrada.', 404);
    }
    if (
        str_starts_with(strtolower(trim((string)($invoice['status_name'] ?? ''))), 'cancel')
        && !str_starts_with(strtolower(trim((string)$newStatusName)), 'cancel')
    ) {
        throw new RuntimeException('Uma fatura cancelada não pode voltar a outro estado.', 422);
    }

    // Busca o ID do status pelo nome
    $stmt = $pdo->prepare("SELECT id FROM invoice_status WHERE name = :name LIMIT 1");
    $stmt->execute([':name' => $newStatusName]);
    $statusId = $stmt->fetchColumn();

    if (!$statusId) {
        // Tenta buscar 'Emitida' como fallback caso 'Pendente' não exista
        $stmt->execute([':name' => 'Emitida']);
        $statusId = $stmt->fetchColumn();
    }

    if (!$statusId) {
        throw new Exception("Status '$newStatusName' não encontrado no sistema.");
    }

    // Atualiza a fatura
    $update = $pdo->prepare("UPDATE invoices SET status = :status WHERE id = :id");
    $update->execute([':status' => $statusId, ':id' => $invoiceId]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (in_array($e->getCode(), [404, 422], true)) {
        http_response_code($e->getCode());
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}