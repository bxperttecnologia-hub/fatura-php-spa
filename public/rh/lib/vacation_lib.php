<?php
/**
 * Férias: direito anual, dias gozados (dias úteis, sem fins-de-semana nem feriados) e saldo.
 *
 * Direito por omissão (configurável em Definições de RH):
 *   - ano de admissão: N dias úteis por mês completo de trabalho (2 por omissão), até ao máximo anual;
 *   - anos seguintes: 22 dias úteis.
 * Saldo = direito do ano + dias transitados (ficha do funcionário) − dias de férias aprovadas no ano.
 * ⚠️ Confirmar as regras com a Lei Geral do Trabalho em vigor e o contrato colectivo, se houver.
 */
require_once __DIR__ . '/payroll_engine.php';

function rh_vacation_entitlement(?string $admissionDate, int $year, array $settings): float
{
    $perYear = (float)$settings['vacation_days_per_year'];
    if (!$admissionDate || str_starts_with($admissionDate, '0000')) {
        return $perYear;
    }
    $admission = new DateTime($admissionDate);
    $admYear = (int)$admission->format('Y');
    if ($admYear > $year) {
        return 0.0;
    }
    if ($admYear < $year) {
        return $perYear;
    }
    // Ano de admissão: meses completos até ao fim do ano (ou até hoje, se for o ano corrente)
    $end = new DateTime("$year-12-31");
    $today = new DateTime('today');
    if ($today < $end) {
        $end = $today;
    }
    if ($end < $admission) {
        return 0.0;
    }
    $diff = $admission->diff((clone $end)->modify('+1 day'));
    $months = $diff->y * 12 + $diff->m;
    return min($perYear, $months * (float)$settings['vacation_days_first_year_per_month']);
}

/** Dias úteis de um período cortados a um ano. */
function rh_working_days_in_year(PDO $pdo, int $companyId, string $start, string $end, int $year): int
{
    $s = max($start, "$year-01-01");
    $e = min($end, "$year-12-31");
    return $s > $e ? 0 : rh_working_days($pdo, $companyId, $s, $e);
}

/**
 * Saldos de férias de todos os funcionários activos (ou de um) num ano.
 * Devolve [employee_id => [name, admission_date, entitlement, carry, used, pending, balance]].
 */
function rh_vacation_balances(PDO $pdo, int $companyId, int $year, ?int $employeeId = null): array
{
    $settings = rh_settings($pdo, $companyId);
    $sql = "SELECT id, name, admission_date, COALESCE(vacation_carry_days, 0) AS carry FROM employees WHERE company_id = ? AND status = 'ativo'";
    $params = [$companyId];
    if ($employeeId) {
        $sql .= ' AND id = ?';
        $params[] = $employeeId;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY name');
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $out[(int)$e['id']] = [
            'employee_id'    => (int)$e['id'],
            'name'           => $e['name'],
            'admission_date' => $e['admission_date'],
            'entitlement'    => rh_vacation_entitlement($e['admission_date'], $year, $settings),
            'carry'          => (float)$e['carry'],
            'used'           => 0.0,
            'pending'        => 0.0,
        ];
    }
    if (!$out) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($out), '?'));
    $stmt = $pdo->prepare("SELECT employee_id, start_date, end_date, status FROM vacations
                           WHERE company_id = ? AND type = 'Férias' AND status IN ('Aprovado','Pendente')
                             AND start_date <= ? AND end_date >= ? AND employee_id IN ($marks)");
    $stmt->execute([$companyId, "$year-12-31", "$year-01-01", ...array_keys($out)]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $days = rh_working_days_in_year($pdo, $companyId, $v['start_date'], $v['end_date'], $year);
        $out[(int)$v['employee_id']][$v['status'] === 'Aprovado' ? 'used' : 'pending'] += $days;
    }
    foreach ($out as &$row) {
        $row['balance'] = round($row['entitlement'] + $row['carry'] - $row['used'], 1);
    }
    return $out;
}

/** Períodos (Aprovados ou Pendentes) do funcionário que se sobrepõem a [start, end]. */
function rh_vacation_overlaps(PDO $pdo, int $companyId, int $employeeId, string $start, string $end, ?int $excludeId = null): array
{
    $stmt = $pdo->prepare("SELECT id, type, start_date, end_date, status FROM vacations
                           WHERE company_id = ? AND employee_id = ? AND status IN ('Aprovado','Pendente')
                             AND start_date <= ? AND end_date >= ? AND id <> ?");
    $stmt->execute([$companyId, $employeeId, $end, $start, (int)$excludeId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
