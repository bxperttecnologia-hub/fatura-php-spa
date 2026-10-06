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

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}