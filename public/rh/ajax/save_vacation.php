<?php
/**
 * Regista/edita um período de férias ou licença. Calcula os dias úteis, impede sobreposições
 * e avisa quando as férias excedem o saldo do ano (não bloqueia: o RH decide).
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/vacation_lib.php';

$id = (int)($_POST['id'] ?? 0) ?: null;
$employeeId = (int)($_POST['employee_id'] ?? 0);
$type = in_array($_POST['type'] ?? '', ['Férias', 'Licença Médica', 'Licença Maternidade', 'Outros'], true) ? $_POST['type'] : 'Férias';
$start = (string)($_POST['start_date'] ?? '');
$end = (string)($_POST['end_date'] ?? '');
$reason = mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 2000);

if (!$employeeId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    rh_json(['success' => false, 'message' => 'Indique o funcionário e as datas de início e fim.'], 422);
}
if ($end < $start) {
    rh_json(['success' => false, 'message' => 'A data de fim não pode ser anterior à data de início.'], 422);
}
$stmt = $pdo->prepare('SELECT name FROM employees WHERE id = ? AND company_id = ?');
$stmt->execute([$employeeId, $company_id]);
if (!$stmt->fetchColumn()) {
    rh_json(['success' => false, 'message' => 'Funcionário não encontrado.'], 404);
}
if ($id) {
    $stmt = $pdo->prepare('SELECT status FROM vacations WHERE id = ? AND company_id = ?');
    $stmt->execute([$id, $company_id]);
    if ($stmt->fetchColumn() === false) {
        rh_json(['success' => false, 'message' => 'Período não encontrado.'], 404);
    }
}
$overlaps = rh_vacation_overlaps($pdo, $company_id, $employeeId, $start, $end, $id);
if ($overlaps) {
    $o = $overlaps[0];
    rh_json(['success' => false, 'message' => "Sobrepõe-se a outro período ({$o['type']}, " . date('d/m/Y', strtotime($o['start_date'])) . ' a ' . date('d/m/Y', strtotime($o['end_date'])) . ", {$o['status']})."], 422);
}

$days = rh_working_days($pdo, $company_id, $start, $end);
$warning = null;
if ($type === 'Férias') {
    $year = (int)substr($start, 0, 4);
    $bal = rh_vacation_balances($pdo, $company_id, $year, $employeeId)[$employeeId] ?? null;
    if ($bal) {
        $available = $bal['balance'] - $bal['pending'];
        if ($id) {
            // ao editar, o próprio período já está contado como pendente/aprovado
            $stmt = $pdo->prepare('SELECT start_date, end_date FROM vacations WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            $available += rh_working_days_in_year($pdo, $company_id, $old['start_date'], $old['end_date'], $year);
        }
        if ($days > $available) {
            $warning = "Atenção: o período tem {$days} dia(s) útil(eis) e o saldo disponível em {$year} é de " . rtrim(rtrim(number_format($available, 1, ',', ''), '0'), ',') . ' dia(s).';
        }
    }
}

if ($id) {
    $pdo->prepare('UPDATE vacations SET employee_id = ?, type = ?, start_date = ?, end_date = ?, reason = ?, working_days = ? WHERE id = ? AND company_id = ?')
        ->execute([$employeeId, $type, $start, $end, $reason, $days, $id, $company_id]);
} else {
    $pdo->prepare("INSERT INTO vacations (employee_id, company_id, type, start_date, end_date, reason, status, working_days, requested_via) VALUES (?, ?, ?, ?, ?, ?, 'Pendente', ?, 'rh')")
        ->execute([$employeeId, $company_id, $type, $start, $end, $reason, $days]);
}
rh_json(['success' => true, 'message' => ($id ? 'Período actualizado' : 'Período registado') . " ({$days} dia(s) útil(eis)).", 'warning' => $warning, 'working_days' => $days]);
