<?php
/** Lista de férias/licenças. Filtros: mes (períodos que cruzam o mês), funcionario, status, type. */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/rh_helpers.php';

$sql = "SELECT v.id, v.employee_id, v.type, v.start_date, v.end_date, v.status, v.reason, v.working_days, v.requested_via, v.created_at, e.name AS employee_name
        FROM vacations v JOIN employees e ON e.id = v.employee_id WHERE v.company_id = ?";
$params = [$company_id];
if (!empty($_REQUEST['funcionario'])) {
    $sql .= ' AND v.employee_id = ?';
    $params[] = (int)$_REQUEST['funcionario'];
}
if (!empty($_REQUEST['mes']) && preg_match('/^\d{4}-\d{2}$/', $_REQUEST['mes'])) {
    $first = $_REQUEST['mes'] . '-01';
    $sql .= ' AND v.start_date <= ? AND v.end_date >= ?';
    $params[] = date('Y-m-t', strtotime($first));
    $params[] = $first;
}
if (in_array($_REQUEST['status'] ?? '', ['Aprovado', 'Pendente', 'Rejeitado'], true)) {
    $sql .= ' AND v.status = ?';
    $params[] = $_REQUEST['status'];
}
$stmt = $pdo->prepare($sql . " ORDER BY FIELD(v.status, 'Pendente', 'Aprovado', 'Rejeitado'), v.start_date DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as &$row) {
    if ($row['working_days'] === null) {
        $row['working_days'] = rh_working_days($pdo, $company_id, $row['start_date'], $row['end_date']);
    }
}
unset($row);
rh_json(['data' => $rows]);
