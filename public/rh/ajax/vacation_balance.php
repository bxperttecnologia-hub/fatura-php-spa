<?php
/** Saldos de férias por funcionário. GET year [, employee_id] */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/vacation_lib.php';

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    rh_json(['success' => false, 'message' => 'Ano inválido.'], 422);
}
$rows = array_values(rh_vacation_balances($pdo, $company_id, $year, (int)($_GET['employee_id'] ?? 0) ?: null));
rh_json(['success' => true, 'year' => $year, 'data' => $rows]);
