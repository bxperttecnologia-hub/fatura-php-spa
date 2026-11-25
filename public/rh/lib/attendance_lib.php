<?php
/**
 * Ponto: cálculo de minutos trabalhados, atraso e horas extra a partir da entrada/saída,
 * gravação (um registo por funcionário e dia) e resumo mensal.
 *
 * Regras (Definições de RH):
 *  - trabalhado = saída − entrada − pausa (a pausa só se desconta em jornadas acima de 6 h);
 *  - atraso = entrada − hora de início, quando passa da tolerância (conta desde a hora de início);
 *  - extra = trabalhado − horas diárias; em fim-de-semana ou feriado todo o tempo trabalhado é extra.
 */
require_once __DIR__ . '/payroll_engine.php';
require_once __DIR__ . '/rh_helpers.php';

const RH_ATTENDANCE_TYPES = ['presença', 'falta', 'atestado', 'folga'];

function rh_time_to_minutes(?string $time): ?int
{
    if ($time === null || $time === '' || !preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
        return null;
    }
    $h = (int)$m[1];
    $i = (int)$m[2];
    return ($h > 23 || $i > 59) ? null : $h * 60 + $i;
}

function rh_normalize_time(?string $time): ?string
{
    $min = rh_time_to_minutes($time);
    return $min === null ? null : sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);
}

function rh_is_workday(PDO $pdo, int $companyId, string $date): bool
{
    return (int)date('N', strtotime($date)) < 6 && !rh_is_holiday($pdo, $companyId, $date);
}

/** Devolve ['worked' => min, 'late' => min, 'overtime' => min]. */
function rh_attendance_minutes(?string $checkIn, ?string $checkOut, array $settings, bool $workday): array
{
    $in = rh_time_to_minutes($checkIn);
    $out = rh_time_to_minutes($checkOut);
    $res = ['worked' => 0, 'late' => 0, 'overtime' => 0];
    if ($in === null) {
        return $res;
    }
    $start = rh_time_to_minutes((string)$settings['work_start_time']) ?? 480;
    if ($workday && $in - $start > (int)$settings['late_tolerance_minutes'] && $in - $start <= 360) { // entrada > 6 h depois: outro turno, não é atraso
        $res['late'] = $in - $start;
    }
    if ($out === null) {
        return $res;
    }
    if ($out < $in) {          // turno que passa a meia-noite
        $out += 24 * 60;
    }
    $span = $out - $in;
    $break = (int)$settings['break_minutes'];
    $worked = $span > 360 ? max(0, $span - $break) : $span;
    $res['worked'] = $worked;
    $daily = (int)round((float)$settings['daily_hours'] * 60);
    $res['overtime'] = $workday ? max(0, $worked - $daily) : $worked;
    return $res;
}

/**
 * Grava (insere ou actualiza) o registo de um funcionário num dia. $data: type, check_in, check_out, justification.
 * Lança RuntimeException com mensagem para o utilizador quando os dados não são válidos.
 */
function rh_attendance_save(PDO $pdo, int $companyId, int $employeeId, string $date, array $data, ?int $id = null): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        throw new RuntimeException('Data inválida.');
    }
    $stmt = $pdo->prepare('SELECT id, name FROM employees WHERE id = ? AND company_id = ?');
    $stmt->execute([$employeeId, $companyId]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        throw new RuntimeException('Funcionário não encontrado.');
    }
    $type = (string)($data['type'] ?? 'presença');
    if (!in_array($type, RH_ATTENDANCE_TYPES, true)) {
        throw new RuntimeException('Tipo de registo inválido.');
    }
    $month = substr($date, 0, 7);
    if (rh_period_is_closed($pdo, $companyId, $month)) {
        throw new RuntimeException('O mês ' . rh_month_label($month) . ' está fechado. Reabra o período na Folha de Pagamento para alterar o ponto.');
    }
    if ($type === 'falta' && rh_is_holiday($pdo, $companyId, $date)) {
        throw new RuntimeException("{$emp['name']}: $date é feriado — não pode ser lançado como falta.");
    }
    $checkIn = $type === 'presença' ? rh_normalize_time($data['check_in'] ?? null) : null;
    $checkOut = $type === 'presença' ? rh_normalize_time($data['check_out'] ?? null) : null;
    if ($checkOut && !$checkIn) {
        throw new RuntimeException("{$emp['name']}: indique a hora de entrada.");
    }
    $settings = rh_settings($pdo, $companyId);
    $min = rh_attendance_minutes($checkIn, $checkOut, $settings, rh_is_workday($pdo, $companyId, $date));
    $justification = trim((string)($data['justification'] ?? '')) ?: null;

    if (!$id) {
        $stmt = $pdo->prepare('SELECT id FROM attendance WHERE company_id = ? AND employee_id = ? AND date = ? ORDER BY id LIMIT 1');
        $stmt->execute([$companyId, $employeeId, $date]);
        $id = (int)$stmt->fetchColumn() ?: null;
    } else {
        $stmt = $pdo->prepare('SELECT id FROM attendance WHERE company_id = ? AND employee_id = ? AND date = ? AND id <> ? LIMIT 1');
        $stmt->execute([$companyId, $employeeId, $date, $id]);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException("{$emp['name']} já tem um registo de ponto em $date.");
        }
    }
    $values = [$employeeId, $date, $type, $justification, $checkIn, $checkOut, $min['worked'], $min['late'], $min['overtime']];
    if ($id) {
        $stmt = $pdo->prepare('UPDATE attendance SET employee_id = ?, date = ?, type = ?, justification = ?, check_in = ?, check_out = ?,
                               worked_minutes = ?, late_minutes = ?, overtime_minutes = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([...$values, $id, $companyId]);
        if (!$stmt->rowCount()) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE id = ? AND company_id = ?');
            $chk->execute([$id, $companyId]);
            if (!$chk->fetchColumn()) {
                throw new RuntimeException('Registo não encontrado.');
            }
        }
        return $id;
    }
    $stmt = $pdo->prepare('INSERT INTO attendance (employee_id, date, type, justification, check_in, check_out, worked_minutes, late_minutes, overtime_minutes, company_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([...$values, $companyId]);
    return (int)$pdo->lastInsertId();
}

/** Resumo mensal por funcionário activo. */
function rh_attendance_summary(PDO $pdo, int $companyId, string $month): array
{
    [$first, $last] = rh_month_bounds($month);
    $holidays = rh_get_holidays_in_range($pdo, $companyId, $first, $last);
    $stmt = $pdo->prepare("SELECT id, name, admission_date FROM employees WHERE company_id = ? AND status = 'ativo' ORDER BY name");
    $stmt->execute([$companyId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $out[(int)$e['id']] = ['employee_id' => (int)$e['id'], 'name' => $e['name'], 'admission_date' => $e['admission_date'],
            'presencas' => 0, 'faltas' => 0, 'atestados' => 0, 'folgas' => 0, 'ferias' => 0,
            'worked_minutes' => 0, 'late_minutes' => 0, 'late_days' => 0, 'overtime_minutes' => 0, 'sem_registo' => 0, '_days' => []];
    }
    if (!$out) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT employee_id, date, type, worked_minutes, late_minutes, overtime_minutes FROM attendance WHERE company_id = ? AND date BETWEEN ? AND ?');
    $stmt->execute([$companyId, $first, $last]);
    $map = ['presença' => 'presencas', 'falta' => 'faltas', 'atestado' => 'atestados', 'folga' => 'folgas'];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $k = (int)$a['employee_id'];
        if (!isset($out[$k]) || isset($out[$k]['_days'][$a['date']])) {
            continue;
        }
        $out[$k]['_days'][$a['date']] = true;
        if ($a['type'] === 'falta' && isset($holidays[$a['date']])) {
            continue;
        }
        $out[$k][$map[$a['type']] ?? 'presencas']++;
        $out[$k]['worked_minutes'] += (int)$a['worked_minutes'];
        $out[$k]['late_minutes'] += (int)$a['late_minutes'];
        $out[$k]['late_days'] += (int)$a['late_minutes'] > 0 ? 1 : 0;
        $out[$k]['overtime_minutes'] += (int)$a['overtime_minutes'];
    }
    // dias de férias/licenças aprovadas
    $stmt = $pdo->prepare("SELECT employee_id, start_date, end_date FROM vacations WHERE company_id = ? AND status = 'Aprovado' AND start_date <= ? AND end_date >= ?");
    $stmt->execute([$companyId, $last, $first]);
    $vacDays = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $d = new DateTime(max($v['start_date'], $first));
        $end = new DateTime(min($v['end_date'], $last));
        for (; $d <= $end; $d->modify('+1 day')) {
            $vacDays[(int)$v['employee_id']][$d->format('Y-m-d')] = true;
        }
    }
    // dias úteis sem registo (até hoje, desde a admissão)
    $today = date('Y-m-d');
    foreach ($out as $k => &$row) {
        $d = new DateTime($first);
        $end = new DateTime(min($last, $today));
        for (; $d <= $end; $d->modify('+1 day')) {
            $iso = $d->format('Y-m-d');
            $isWork = (int)$d->format('N') < 6 && !isset($holidays[$iso]);
            if (isset($vacDays[$k][$iso])) {
                $row['ferias'] += $isWork ? 1 : 0;
                continue;
            }
            if ($isWork && !isset($row['_days'][$iso]) && (!$row['admission_date'] || $row['admission_date'] <= $iso)) {
                $row['sem_registo']++;
            }
        }
        unset($row['_days']);
    }
    return array_values($out);
}
