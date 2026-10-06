<?php
require_once '../../../app/config/db.php';
require_once '../helpers/payroll_calc.php';
session_start();

$company_id       = (int)($_SESSION['user']['company_id'] ?? 0);
$reference_month  = $_POST['reference_month'] ?? '';
$employee_ids     = $_POST['employee_ids'] ?? [];

if (!$company_id || !$reference_month || !is_array($employee_ids) || empty($employee_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione o mês e pelo menos um colaborador.']);
    exit;
}

$items = [];
$errors = [];
$total_net = 0;

foreach ($employee_ids as $employeeId) {
    $employeeId = (int)$employeeId;

    try {
        $calc = calculate_employee_payroll($pdo, $company_id, $employeeId, $reference_month);
        $items[] = $calc;
        $total_net += $calc['net_salary'];
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

echo json_encode([
    'success'    => true,
    'items'      => $items,
    'errors'     => $errors,
    'total_net'  => round($total_net, 2),
    'count'      => count($items),
]);
