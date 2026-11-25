<?php
/**
 * Grava a folha de um funcionário (novo registo ou edição). O cálculo é sempre feito no
 * servidor pelo motor único (lib/payroll_engine.php) — os totais vindos do browser são ignorados.
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$id = (int)($_POST['id'] ?? 0) ?: null;
$employeeId = (int)($_POST['employee_id'] ?? 0);
$month = (string)($_POST['reference_month'] ?? '');

if (!$employeeId || !rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Indique o funcionário e o mês de referência.'], 422);
}

$overrides = array_intersect_key($_POST, array_flip([
    'base_salary', 'bonuses', 'food_allowance', 'transport_allowance', 'vacation_subsidy_pct',
    'thirteenth_subsidy_pct', 'commissions', 'sales', 'discounts', 'other_earnings', 'overtime_hours', 'absence_days',
]));

try {
    $calc = rh_payroll_compute($pdo, $company_id, $employeeId, $month, $overrides, $id);
    $savedId = rh_payroll_store($pdo, $company_id, $calc, [
        'id'           => $id,
        'status'       => $_POST['status'] ?? 'Pendente',
        'payment_date' => $_POST['payment_date'] ?? null,
        'notes'        => $_POST['notes'] ?? null,
        'user_id'      => $rh_user_id,
    ]);
    rh_json(['success' => true, 'id' => $savedId, 'message' => 'Folha gravada.', 'calc' => $calc]);
} catch (RuntimeException $e) {
    rh_json(['success' => false, 'message' => rh_error_message($e)], 422);
} catch (Throwable $e) {
    error_log('save_payroll: ' . $e->getMessage());
    rh_json(['success' => false, 'message' => 'Não foi possível gravar a folha.'], 500);
}
