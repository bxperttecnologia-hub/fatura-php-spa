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
    $employee_id = $_POST['employee_id'] ?? null;
    $type = $_POST['type'] ?? 'Férias';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $reason = $_POST['reason'] ?? null;
    
    if (!$start_date || !$end_date) {
        throw new Exception('As datas de início e fim são obrigatórias.');
    }

    if (strtotime($end_date) < strtotime($start_date)) {
        throw new Exception('A data de fim não pode ser anterior à data de início.');
    }

    if ($id) {
        // Update
        if (!$employee_id) {
            throw new Exception('O funcionário é obrigatório.');
        }
        $stmt = $pdo->prepare("UPDATE vacations SET employee_id = ?, type = ?, start_date = ?, end_date = ?, reason = ? 
                             WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $type, $start_date, $end_date, $reason, $id, $company_id]);
        $response['message'] = 'Período atualizado com sucesso!';
    } else {
        // Insert
        if (!$employee_id) {
            throw new Exception('O funcionário é obrigatório.');
        }
        $status = 'Pendente';
        $stmt = $pdo->prepare("INSERT INTO vacations (employee_id, company_id, type, start_date, end_date, reason, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employee_id, $company_id, $type, $start_date, $end_date, $reason, $status]);
        $response['message'] = 'Período registrado com sucesso!';
    }

    $response['success'] = true;

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
