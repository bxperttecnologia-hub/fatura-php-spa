<?php
require_once '../../../app/config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user']['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada ou inválida. Por favor, faça login novamente.']);
    exit;
}

$company_id = $_SESSION['user']['company_id'];

$response = ['success' => false, 'message' => 'Ocorreu um erro.'];

try {
    $id = $_POST['id'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$id || !$status) {
        throw new Exception('Dados insuficientes para atualizar o status.');
    }

    $allowed_statuses = ['Aprovado', 'Pendente', 'Rejeitado'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Status inválido.');
    }

    $stmt = $pdo->prepare("UPDATE vacations SET status = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$status, $id, $company_id]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Status atualizado com sucesso!';
    } else {
        throw new Exception('Não foi possível atualizar o status.');
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);