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
    if (!isset($_POST['id'])) {
        throw new Exception('ID do registro não fornecido.');
    }
    $id = $_POST['id'];

    $stmt = $pdo->prepare("DELETE FROM vacations WHERE id = ? AND company_id = ?");
    $stmt->execute([$id, $company_id]);

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Registro de férias excluído com sucesso!';
    } else {
        throw new Exception('Não foi possível excluir o registro. Ele pode não existir ou você não tem permissão.');
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
