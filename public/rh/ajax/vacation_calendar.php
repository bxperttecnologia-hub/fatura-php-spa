<?php
/** Calendário mensal da equipa: funcionários activos, períodos de férias/licenças e feriados. GET mes=YYYY-MM */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/vacation_lib.php';

$month = (string)($_GET['mes'] ?? date('Y-m'));
if (!rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Mês inválido.'], 422);
}
[$first, $last] = rh_month_bounds($month);
$stmt = $pdo->prepare("SELECT id, name FROM employees WHERE company_id = ? AND status = 'ativo' ORDER BY name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT v.id, v.employee_id, v.type, v.start_date, v.end_date, v.status FROM vacations v
                       WHERE v.company_id = ? AND v.status IN ('Aprovado','Pendente') AND v.start_date <= ? AND v.end_date >= ?");
$stmt->execute([$company_id, $last, $first]);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
rh_json([
    'success'   => true,
    'month'     => $month,
    'days'      => (int)date('t', strtotime($first)),
    'employees' => $employees,
    'periods'   => $periods,
    'holidays'  => rh_get_holidays_in_range($pdo, $company_id, $first, $last),
]);
