<?php
require_once '../../../app/config/db.php';
session_start();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$company_id = $_SESSION['user']['company_id'] ?? null; // Assumindo que o company_id está na sessão

if (!$company_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Empresa não identificada.']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->prepare("
                SELECT v.id, v.type, v.start_date, v.end_date, v.reason, v.status, 
                       COALESCE(e.name, 'Funcionário Excluído') as employee_name, v.employee_id
                FROM vacations v
                LEFT JOIN employees e ON v.employee_id = e.id
                WHERE v.company_id = ?
            ");
            $stmt->execute([$company_id]);
            $vacations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['data' => $vacations]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID de período não fornecido.');
            }
            $stmt = $pdo->prepare("SELECT * FROM vacations WHERE id = ? AND company_id = ?");
            $stmt->execute([$id, $company_id]);
            $vacation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$vacation) {
                throw new Exception('Período não encontrado.');
            }
            // Adiciona o nome do funcionário para o select2
            $stmt_employee = $pdo->prepare("SELECT id, name as text FROM employees WHERE id = ?");
            $stmt_employee->execute([$vacation['employee_id']]);
            $employee = $stmt_employee->fetch(PDO::FETCH_ASSOC);
            $vacation['employee'] = $employee;

            echo json_encode(['success' => true, 'data' => $vacation]);
            break;

        case 'save':
            $id = $_POST['id'] ?? null;
            $employee_id = $_POST['employee_id'] ?? null;
            $type = $_POST['type'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $reason = $_POST['reason'] ?? '';

            if (empty($employee_id) || empty($type) || empty($start_date) || empty($end_date)) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
            }

            if ($id) {
                // Update
                $stmt = $pdo->prepare(
                    "UPDATE vacations SET employee_id = ?, type = ?, start_date = ?, end_date = ?, reason = ? 
                     WHERE id = ? AND company_id = ?"
                );
                $stmt->execute([$employee_id, $type, $start_date, $end_date, $reason, $id, $company_id]);
                $message = 'Período atualizado com sucesso!';
            } else {
                // Insert
                $status = 'Pendente';
                $stmt = $pdo->prepare(
                    "INSERT INTO vacations (employee_id, company_id, type, start_date, end_date, reason, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$employee_id, $company_id, $type, $start_date, $end_date, $reason, $status]);
                $message = 'Período registrado com sucesso!';
            }
            echo json_encode(['success' => true, 'message' => $message]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception('ID de período não fornecido.');
            }
            $stmt = $pdo->prepare("DELETE FROM vacations WHERE id = ? AND company_id = ?");
            $stmt->execute([$id, $company_id]);
            echo json_encode(['success' => true, 'message' => 'Período excluído com sucesso!']);
            break;
        
        case 'update_status':
            $id = $_POST['id'] ?? null;
            $status = $_POST['status'] ?? null;
            if (!$id || !$status) {
                throw new Exception('Dados insuficientes para alterar o status.');
            }
            $stmt = $pdo->prepare("UPDATE vacations SET status = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$status, $id, $company_id]);
            echo json_encode(['success' => true, 'message' => 'Status alterado com sucesso!']);
            break;

        default:
            throw new Exception('Ação inválida.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
