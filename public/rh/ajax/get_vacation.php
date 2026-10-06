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

$response = ['success' => false, 'message' => 'Registro não encontrado.'];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID não fornecido.');
    }
    $id = $_GET['id'];

    $stmt = $pdo->prepare("SELECT v.id, v.employee_id, v.type, v.start_date, v.end_date, v.reason, e.name as employee_name 
                         FROM vacations v
                         JOIN employees e ON v.employee_id = e.id
                         WHERE v.id = ? AND v.company_id = ?");
    $stmt->execute([$id, $company_id]);
    $vacation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vacation) {
        $response['success'] = true;
        $response['data'] = $vacation;
        // Format for Select2
        $response['data']['employee'] = [
            'id' => $vacation['employee_id'],
            'text' => $vacation['employee_name']
        ];
    } else {
        http_response_code(404);
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
