<?php
/** Lista de folhas (filtros: mes, status, employee_id) + totais do conjunto filtrado e estado do mês. */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$where = ['p.company_id = ?'];
$params = [$company_id];
$month = (string)($_REQUEST['mes'] ?? '');
if (rh_valid_month($month)) {
    $where[] = 'p.reference_month = ?';
    $params[] = $month;
}
if (in_array($_REQUEST['status'] ?? '', ['Pendente', 'Aprovado', 'Pago'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $_REQUEST['status'];
}
if (!empty($_REQUEST['employee_id'])) {
    $where[] = 'p.employee_id = ?';
    $params[] = (int)$_REQUEST['employee_id'];
}

$stmt = $pdo->prepare("
    SELECT p.*, e.name AS employee_name, e.iban, e.email, e.bank_name,
           COALESCE(pos.name, e.position) AS position_name,
           COALESCE(p.gross_salary, p.base_salary + p.bonuses + p.food_allowance + p.transport_allowance + p.commissions) AS gross_value,
           COALESCE(p.total_discounts, p.discounts) AS discounts_value
    FROM payroll p
    JOIN employees e ON e.id = p.employee_id
    LEFT JOIN positions pos ON pos.id = e.position_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.reference_month DESC, e.name ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totals = ['count' => count($rows), 'gross' => 0, 'absences' => 0, 'net' => 0, 'inss' => 0, 'inss_employer' => 0, 'irt' => 0, 'pending' => 0, 'approved' => 0, 'paid' => 0];
foreach ($rows as $r) {
    $totals['gross'] += (float)$r['gross_value'];
    $totals['absences'] += (float)($r['absence_value'] ?? 0);
    $totals['net'] += (float)$r['net_salary'];
    $totals['inss'] += (float)$r['inss_value'];
    $totals['inss_employer'] += (float)($r['inss_employer_value'] ?? 0);
    $totals['irt'] += (float)$r['irt_value'];
    $totals[['Pendente' => 'pending', 'Aprovado' => 'approved', 'Pago' => 'paid'][$r['status']] ?? 'pending']++;
}
foreach (['gross', 'absences', 'net', 'inss', 'inss_employer', 'irt'] as $k) {
    $totals[$k] = rh_money($totals[$k]);
}
$totals['employer_cost'] = rh_money($totals['gross'] - $totals['absences'] + $totals['inss_employer']);

$period = null;
if (rh_valid_month($month)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'ativo' AND (admission_date IS NULL OR admission_date <= ?)");
    $stmt->execute([$company_id, rh_month_bounds($month)[1]]);
    $period = [
        'month'            => $month,
        'label'            => rh_month_label($month),
        'closed'           => rh_period_is_closed($pdo, $company_id, $month),
        'active_employees' => (int)$stmt->fetchColumn(),
    ];
}

rh_json(['data' => $rows, 'totals' => $totals, 'period' => $period]);
