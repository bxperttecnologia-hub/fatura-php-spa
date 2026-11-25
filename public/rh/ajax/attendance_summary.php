<?php
/** Resumo mensal do ponto por funcionário. GET mes=YYYY-MM */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/attendance_lib.php';

$month = (string)($_GET['mes'] ?? date('Y-m'));
if (!rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Mês inválido.'], 422);
}
try {
    $rows = rh_attendance_summary($pdo, $company_id, $month);
    [$first, $last] = rh_month_bounds($month);
    rh_json(['success' => true, 'month' => $month, 'working_days' => rh_working_days($pdo, $company_id, $first, $last), 'data' => $rows]);
} catch (Throwable $e) {
    rh_json(['success' => false, 'message' => rh_error_message($e, 'attendance_summary')], 500);
}
