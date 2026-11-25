<?php
/**
 * Folha diária de ponto.
 *  GET  date=YYYY-MM-DD → funcionários activos com o registo do dia, férias/licença aprovada e feriado.
 *  POST date, rows[<employee_id>][type|check_in|check_out|justification] → grava em lote.
 *       Linhas com type vazio apagam o registo do dia.
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/attendance_lib.php';

$date = (string)($_REQUEST['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    rh_json(['success' => false, 'message' => 'Data inválida.'], 422);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
    $saved = 0;
    $removed = 0;
    $errors = [];
    foreach ($rows as $employeeId => $row) {
        $employeeId = (int)$employeeId;
        if (!$employeeId || !is_array($row)) {
            continue;
        }
        try {
            if (($row['type'] ?? '') === '') {
                if (rh_period_is_closed($pdo, $company_id, substr($date, 0, 7))) {
                    throw new RuntimeException('O mês está fechado.');
                }
                $stmt = $pdo->prepare('DELETE FROM attendance WHERE company_id = ? AND employee_id = ? AND date = ?');
                $stmt->execute([$company_id, $employeeId, $date]);
                $removed += $stmt->rowCount();
                continue;
            }
            rh_attendance_save($pdo, $company_id, $employeeId, $date, $row);
            $saved++;
        } catch (Throwable $e) {
            $errors[] = rh_error_message($e, 'attendance_day');
        }
    }
    $msg = "$saved registo(s) gravado(s)" . ($removed ? ", $removed removido(s)" : '') . '.';
    rh_json(['success' => $saved + $removed > 0 || !$errors, 'message' => $errors ? "$msg Alguns registos não foram gravados." : $msg, 'done' => $saved, 'errors' => $errors]);
}

$settings = rh_settings($pdo, $company_id);
$holidays = rh_get_holidays_in_range($pdo, $company_id, $date, $date);
$stmt = $pdo->prepare("SELECT e.id, e.name, COALESCE(p.name, e.position) AS position, e.admission_date
                       FROM employees e LEFT JOIN positions p ON p.id = e.position_id AND p.company_id = e.company_id
                       WHERE e.company_id = ? AND e.status = 'ativo' ORDER BY e.name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT id, employee_id, type, check_in, check_out, justification, worked_minutes, late_minutes, overtime_minutes FROM attendance WHERE company_id = ? AND date = ? ORDER BY id');
$stmt->execute([$company_id, $date]);
$records = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $records[(int)$r['employee_id']] ??= $r;
}
$stmt = $pdo->prepare("SELECT employee_id, type FROM vacations WHERE company_id = ? AND status = 'Aprovado' AND start_date <= ? AND end_date >= ?");
$stmt->execute([$company_id, $date, $date]);
$leave = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $leave[(int)$v['employee_id']] = $v['type'];
}
foreach ($employees as &$e) {
    $e['record'] = $records[(int)$e['id']] ?? null;
    $e['leave'] = $leave[(int)$e['id']] ?? null;
    $e['not_admitted'] = !empty($e['admission_date']) && $e['admission_date'] > $date;
}
unset($e);

rh_json([
    'success'  => true,
    'date'     => $date,
    'workday'  => (int)date('N', strtotime($date)) < 6 && !$holidays,
    'holiday'  => $holidays[$date] ?? null,
    'closed'   => rh_period_is_closed($pdo, $company_id, substr($date, 0, 7)),
    'settings' => [
        'work_start_time'        => substr((string)$settings['work_start_time'], 0, 5),
        'daily_hours'            => (float)$settings['daily_hours'],
        'break_minutes'          => (int)$settings['break_minutes'],
        'late_tolerance_minutes' => (int)$settings['late_tolerance_minutes'],
    ],
    'employees' => $employees,
]);
