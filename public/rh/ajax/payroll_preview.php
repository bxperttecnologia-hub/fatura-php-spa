<?php
/** Simulação da folha (não grava): usada pelo formulário para mostrar o recibo em tempo real. */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$employeeId = (int)($_REQUEST['employee_id'] ?? 0);
$month = (string)($_REQUEST['reference_month'] ?? '');
$id = (int)($_REQUEST['id'] ?? 0) ?: null;
if (!$employeeId || !rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Indique o funcionário e o mês.'], 422);
}

// Só os campos que o utilizador já preencheu substituem os valores automáticos
$overrides = [];
foreach (['base_salary', 'bonuses', 'food_allowance', 'transport_allowance', 'vacation_subsidy_pct', 'thirteenth_subsidy_pct',
          'commissions', 'sales', 'discounts', 'other_earnings', 'overtime_hours', 'absence_days'] as $key) {
    if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
        $overrides[$key] = $_REQUEST[$key];
    }
}

try {
    $calc = rh_payroll_compute($pdo, $company_id, $employeeId, $month, $overrides, $id);
    $stmt = $pdo->prepare('SELECT id, status FROM payroll WHERE company_id = ? AND employee_id = ? AND reference_month = ? AND id <> ? LIMIT 1');
    $stmt->execute([$company_id, $employeeId, $month, (int)$id]);
    $calc['existing'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $calc['period_closed'] = rh_period_is_closed($pdo, $company_id, $month);
    rh_json(['success' => true, 'calc' => $calc]);
} catch (RuntimeException $e) {
    rh_json(['success' => false, 'message' => rh_error_message($e)], 422);
}
