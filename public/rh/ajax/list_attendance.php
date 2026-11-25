<?php
/** Lista de registos de ponto. GET mes=YYYY-MM, funcionario, type */
require_once __DIR__ . '/../lib/rh_guard.php';

$sql = 'SELECT a.id, a.employee_id, a.date, a.type, a.justification, a.check_in, a.check_out,
               a.worked_minutes, a.late_minutes, a.overtime_minutes, e.name AS employee_name,
               (SELECT h.name FROM holidays h WHERE h.company_id = a.company_id AND h.date = a.date LIMIT 1) AS holiday
        FROM attendance a JOIN employees e ON e.id = a.employee_id AND e.company_id = a.company_id
        WHERE a.company_id = ?';
$params = [$company_id];
$month = (string)($_REQUEST['mes'] ?? '');
if ($month !== '') {
    if (!rh_valid_month($month)) {
        rh_json(['data' => [], 'message' => 'Mês inválido.'], 422);
    }
    $sql .= ' AND a.date BETWEEN ? AND LAST_DAY(?)';
    $params[] = "$month-01";
    $params[] = "$month-01";
}
if (!empty($_REQUEST['funcionario'])) {
    $sql .= ' AND a.employee_id = ?';
    $params[] = (int)$_REQUEST['funcionario'];
}
if (!empty($_REQUEST['type'])) {
    $sql .= ' AND a.type = ?';
    $params[] = (string)$_REQUEST['type'];
}
try {
    $stmt = $pdo->prepare($sql . ' ORDER BY a.date DESC, e.name');
    $stmt->execute($params);
    rh_json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Throwable $e) {
    rh_json(['data' => [], 'message' => rh_error_message($e, 'list_attendance')], 500);
}
