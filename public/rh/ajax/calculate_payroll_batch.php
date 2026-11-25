<?php
/** Simulação em lote (não grava). POST reference_month, employee_ids[], bonuses/other_earnings/discounts (opcionais). */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$month = (string)($_POST['reference_month'] ?? '');
$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['employee_ids'] ?? [])))));
if (!rh_valid_month($month) || !$ids) {
    rh_json(['success' => false, 'message' => 'Selecione o mês e pelo menos um funcionário.'], 400);
}
$overrides = [];
foreach (['bonuses', 'other_earnings', 'discounts'] as $k) {
    if (isset($_POST[$k]) && $_POST[$k] !== '') {
        $overrides[$k] = $_POST[$k];
    }
}
$items = [];
$errors = [];
foreach ($ids as $employeeId) {
    try {
        $c = rh_payroll_compute($pdo, $company_id, $employeeId, $month, $overrides);
        $items[] = array_intersect_key($c, array_flip(['employee_id', 'employee_name', 'gross_salary', 'total_discounts', 'inss_value', 'irt_value', 'net_salary', 'employer_cost', 'warnings']));
    } catch (Throwable $e) {
        $errors[] = rh_error_message($e, 'calculate_payroll_batch');
    }
}
$sum = fn($k) => rh_money(array_sum(array_column($items, $k)));
rh_json([
    'success' => true, 'items' => $items, 'errors' => $errors, 'count' => count($items),
    'total_gross' => $sum('gross_salary'), 'total_discounts' => $sum('total_discounts'), 'total_net' => $sum('net_salary'), 'total_employer_cost' => $sum('employer_cost'),
]);
