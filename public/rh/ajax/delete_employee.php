<?php

require_once '../../../app/config/db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);
$employee_id = (int)($_POST['employee_id'] ?? 0);

try {

  if (!$employee_id || !$company_id) {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error' => 'Dados inválidos.'
    ]);
    exit;
  }

  // Bloqueia a exclusão se existirem registos associados (mesmo padrão já
  // usado em delete_position.php). Apagar um funcionário com histórico de
  // folha/férias/ponto destruiria esse histórico; o caminho correto é
  // desativar (status = 'inativo').
  $stmtCheck = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM payroll    WHERE employee_id = ? AND company_id = ?) AS payroll_count,
            (SELECT COUNT(*) FROM vacations  WHERE employee_id = ? AND company_id = ?) AS vacations_count,
            (SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND company_id = ?) AS attendance_count
    ");
  $stmtCheck->execute([
    $employee_id, $company_id,
    $employee_id, $company_id,
    $employee_id, $company_id
  ]);
  $counts = $stmtCheck->fetch(PDO::FETCH_ASSOC);

  if (($counts['payroll_count'] ?? 0) > 0 || ($counts['vacations_count'] ?? 0) > 0 || ($counts['attendance_count'] ?? 0) > 0) {
    http_response_code(409);
    echo json_encode([
      'success' => false,
      'error' => 'Não é possível eliminar: há registos de folha, férias ou ponto associados a este funcionário. Desative o funcionário em vez de o eliminar.',
      'suggestion' => 'deactivate'
    ]);
    exit;
  }

  $stmt = $pdo->prepare("
        DELETE FROM employees 
        WHERE id = ? AND company_id = ?
    ");

  $stmt->execute([$employee_id, $company_id]);

  $deleted = $stmt->rowCount();

  if ($deleted === 0) {
    http_response_code(404);
    echo json_encode([
      'success' => false,
      'error' => 'Funcionário não encontrado.'
    ]);
    exit;
  }

  echo json_encode([
    'success' => true
  ]);
} catch (Throwable $e) {

  http_response_code(500);

  echo json_encode([
    'success' => false,
    'error' => 'Erro interno no servidor.'
  ]);
}
