<?php
/** Sugestão de subsídio de férias e de Natal (mesma regra do motor de cálculo). */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$employeeId = (int)($_GET['employee_id'] ?? 0);
$month = (string)($_GET['reference_month'] ?? '');
if (!$employeeId || !rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}
try {
    $emp = rh_employee_for_payroll($pdo, $company_id, $employeeId);
    $s = rh_suggest_subsidies($pdo, $company_id, $emp, $month, rh_settings($pdo, $company_id), (int)($_GET['id'] ?? 0) ?: null);
    rh_json([
        'success' => true,
        'suggested_vacation_subsidy_pct'   => $s['vacation_pct'],
        'vacation_reason'                  => $s['vacation_reason'],
        'suggested_thirteenth_subsidy_pct' => $s['thirteenth_pct'],
        'thirteenth_reason'                => $s['thirteenth_reason'],
    ]);
} catch (RuntimeException $e) {
    rh_json(['success' => false, 'message' => $e->getMessage()], 404);
}
