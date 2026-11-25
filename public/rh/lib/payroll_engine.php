<?php
/**
 * Motor ÚNICO de cálculo da folha salarial (Angola).
 *
 * Usado pelo registo individual, pela simulação, pelo lote e pelo "Gerar folha do mês" —
 * antes havia três cálculos diferentes (um deles com IRT = 0 e sem faltas).
 *
 * Regras (todas configuráveis em Definições de RH, tabela rh_settings):
 *  - Faltas: descontadas ao salário base (base / divisor × dias) ANTES de INSS e IRT.
 *  - Subsídios de alimentação e transporte: isentos até ao limite mensal de cada um;
 *    só o excedente entra na base de INSS/IRT (por omissão, 30 000 Kz cada).
 *  - INSS: trabalhador 3 % e entidade patronal 8 % sobre a base de incidência.
 *  - IRT (Grupo A): tabela de escalões sobre (rendimento tributável − INSS do trabalhador).
 *  - Subsídio de férias: sugerido no mês em que começam férias aprovadas (uma vez por ano).
 *  - Subsídio de Natal (13.º): sugerido no mês configurado, proporcional aos meses trabalhados.
 *  - Horas extra: valor hora = base / horas mensais; acréscimo de 50 % até ao limite mensal
 *    e 75 % acima (valores por omissão).
 *  - Adiantamentos / empréstimos: prestação do mês descontada automaticamente.
 *
 * ⚠️ Taxas e limites legais são valores por omissão — devem ser confirmados pelo
 * contabilista da empresa e ajustados em Definições de RH se a lei mudar.
 */

require_once __DIR__ . '/rh_helpers.php';

const RH_CALC_VERSION = 2;

function rh_default_irt_table(): array
{
    // Escalões do IRT Grupo A — mesmos valores que o sistema já usava ("tabela 2026").
    return [
        ['up_to' => 150000,   'fixed' => 0,       'rate' => 0,    'over' => 0],
        ['up_to' => 200000,   'fixed' => 12500,   'rate' => 16,   'over' => 150000],
        ['up_to' => 300000,   'fixed' => 31250,   'rate' => 18,   'over' => 200000],
        ['up_to' => 500000,   'fixed' => 49250,   'rate' => 19,   'over' => 300000],
        ['up_to' => 1000000,  'fixed' => 87250,   'rate' => 20,   'over' => 500000],
        ['up_to' => 1500000,  'fixed' => 187250,  'rate' => 21,   'over' => 1000000],
        ['up_to' => 2000000,  'fixed' => 292250,  'rate' => 22,   'over' => 1500000],
        ['up_to' => 2500000,  'fixed' => 402250,  'rate' => 23,   'over' => 2000000],
        ['up_to' => 5000000,  'fixed' => 517250,  'rate' => 24,   'over' => 2500000],
        ['up_to' => 10000000, 'fixed' => 1117250, 'rate' => 24.5, 'over' => 5000000],
        ['up_to' => null,     'fixed' => 2342250, 'rate' => 25,   'over' => 10000000],
    ];
}

function rh_default_settings(): array
{
    return [
        'salario_minimo_nacional'   => 70000.0,
        'aviso_previo_demissao_dias'=> 30,
        'inss_employee_rate'        => 3.0,
        'inss_employer_rate'        => 8.0,
        'food_exempt_limit'         => 30000.0,
        'transport_exempt_limit'    => 30000.0,
        'inss_on_exempt_allowances' => 0,
        'absence_divisor'           => 30,
        'monthly_hours'             => 176.0,
        'overtime_rate_1'           => 50.0,
        'overtime_rate_2'           => 75.0,
        'overtime_threshold_hours'  => 30.0,
        'vacation_days_per_year'    => 22,
        'vacation_days_first_year_per_month' => 2.0,
        'thirteenth_month'          => 12,
        'work_start_time'           => '08:00:00',
        'daily_hours'               => 8.0,
        'late_tolerance_minutes'    => 10,
        'break_minutes'             => 60,
        'irt_table'                 => rh_default_irt_table(),
        'payslip_email_message'     => "Olá {nome},\n\nSegue em anexo o seu recibo de salário referente a {mes}.\n\nCumprimentos,\n{empresa}",
    ];
}

/** Definições de RH da empresa (com valores por omissão para o que não estiver gravado). */
function rh_settings(PDO $pdo, int $companyId): array
{
    static $cache = [];
    if (isset($cache[$companyId])) {
        return $cache[$companyId];
    }
    $settings = rh_default_settings();
    try {
        $stmt = $pdo->prepare('SELECT * FROM rh_settings WHERE company_id = ?');
        $stmt->execute([$companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $row = [];
    }
    foreach ($row as $key => $value) {
        if ($value === null || !array_key_exists($key, $settings)) {
            continue;
        }
        if ($key === 'irt_table') {
            $table = json_decode((string)$value, true);
            if (rh_irt_table_valid($table)) {
                $settings['irt_table'] = $table;
            }
            continue;
        }
        $settings[$key] = is_numeric($settings[$key]) && is_numeric($value) ? $value + 0 : $value;
    }
    return $cache[$companyId] = $settings;
}

function rh_irt_table_valid($table): bool
{
    if (!is_array($table) || !$table) {
        return false;
    }
    foreach ($table as $row) {
        if (!is_array($row) || !array_key_exists('up_to', $row) || !is_numeric($row['fixed'] ?? null)
            || !is_numeric($row['rate'] ?? null) || !is_numeric($row['over'] ?? null)) {
            return false;
        }
    }
    return true;
}

/** "80 000,00", "80.000,00", "80000.5" → float. */
function rh_parse_number($value): float
{
    if (is_int($value) || is_float($value)) {
        return (float)$value;
    }
    $s = preg_replace('/[^\d,.\-]/u', '', str_replace(["\u{00A0}", "\u{202F}", ' '], '', (string)$value));
    $comma = strrpos($s, ',');
    $dot = strrpos($s, '.');
    if ($comma !== false && $dot !== false) {
        $decimal = $comma > $dot ? ',' : '.';
        $s = str_replace($decimal === ',' ? '.' : ',', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($comma !== false) {
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float)$s : 0.0;
}

function rh_money(float $value): float
{
    return round($value + 0.0, 2, PHP_ROUND_HALF_UP);
}

/** IRT Grupo A: escalão onde a base cai → parcela fixa + taxa × (base − limite inferior). */
function rh_irt(float $base, array $table): float
{
    if ($base <= 0) {
        return 0.0;
    }
    foreach ($table as $row) {
        if ($row['up_to'] === null || $base <= (float)$row['up_to']) {
            if ((float)$row['rate'] <= 0 && (float)$row['fixed'] <= 0) {
                return 0.0;
            }
            return rh_money((float)$row['fixed'] + ((float)$row['rate'] / 100) * max(0, $base - (float)$row['over']));
        }
    }
    return 0.0;
}

function rh_month_bounds(string $month): array
{
    $first = $month . '-01';
    return [$first, date('Y-m-t', strtotime($first))];
}

function rh_period_is_closed(PDO $pdo, int $companyId, string $month): bool
{
    try {
        $stmt = $pdo->prepare("SELECT status FROM rh_payroll_periods WHERE company_id = ? AND reference_month = ?");
        $stmt->execute([$companyId, $month]);
        return $stmt->fetchColumn() === 'fechado';
    } catch (PDOException $e) {
        return false;
    }
}

/** Funcionário + dados do cargo (pelo position_id; o nome do cargo fica como recurso para registos antigos). */
function rh_employee_for_payroll(PDO $pdo, int $companyId, int $employeeId): array
{
    $stmt = $pdo->prepare("
        SELECT e.id, e.name, e.salary_base, e.iban, e.status, e.admission_date, e.end_date, e.email, e.bi,
               COALESCE(p.food_allowance, p2.food_allowance, 0)           AS food_allowance,
               COALESCE(p.transport_allowance, p2.transport_allowance, 0) AS transport_allowance,
               COALESCE(p.vacation_subsidy_pct, p2.vacation_subsidy_pct)  AS vacation_subsidy_pct,
               COALESCE(p.thirteenth_subsidy_pct, p2.thirteenth_subsidy_pct) AS thirteenth_subsidy_pct,
               COALESCE(p.name, p2.name, e.position) AS position_name
        FROM employees e
        LEFT JOIN positions p  ON p.id = e.position_id AND p.company_id = e.company_id
        LEFT JOIN positions p2 ON e.position_id IS NULL AND p2.company_id = e.company_id AND TRIM(p2.name) = TRIM(e.position)
        WHERE e.id = ? AND e.company_id = ?
        LIMIT 1
    ");
    $stmt->execute([$employeeId, $companyId]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$emp) {
        throw new RuntimeException("Funcionário #{$employeeId} não encontrado.");
    }
    return $emp;
}

/** Faltas injustificadas do mês (dias de feriado não contam). */
function rh_absence_days(PDO $pdo, int $companyId, int $employeeId, string $month): float
{
    [$first, $last] = rh_month_bounds($month);
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance
        WHERE employee_id = ? AND company_id = ? AND type = 'falta' AND date BETWEEN ? AND ?
          AND date NOT IN (SELECT date FROM holidays WHERE company_id = ?)
    ");
    $stmt->execute([$employeeId, $companyId, $first, $last, $companyId]);
    return (float)$stmt->fetchColumn();
}

/** Horas extra registadas no ponto durante o mês. */
function rh_overtime_hours(PDO $pdo, int $companyId, int $employeeId, string $month): float
{
    [$first, $last] = rh_month_bounds($month);
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(overtime_minutes), 0) FROM attendance WHERE employee_id = ? AND company_id = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$employeeId, $companyId, $first, $last]);
        return round(((int)$stmt->fetchColumn()) / 60, 2);
    } catch (PDOException $e) {
        return 0.0;
    }
}

/** Já foi processado um subsídio (férias ou Natal) noutra folha do mesmo ano? */
function rh_subsidy_already_paid(PDO $pdo, int $companyId, int $employeeId, string $month, string $column, ?int $excludePayrollId): bool
{
    $column = $column === 'thirteenth_subsidy_pct' ? 'thirteenth_subsidy_pct' : 'vacation_subsidy_pct';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll WHERE company_id = ? AND employee_id = ? AND LEFT(reference_month, 4) = ?
                           AND reference_month <> ? AND $column > 0 AND id <> ?");
    $stmt->execute([$companyId, $employeeId, substr($month, 0, 4), $month, (int)$excludePayrollId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Sugestão de subsídio de férias e de Natal para o mês.
 * Devolve ['vacation_pct', 'vacation_reason', 'thirteenth_pct', 'thirteenth_reason'].
 */
function rh_suggest_subsidies(PDO $pdo, int $companyId, array $emp, string $month, array $settings, ?int $excludePayrollId = null): array
{
    [$first, $last] = rh_month_bounds($month);
    $employeeId = (int)$emp['id'];
    $year = (int)substr($month, 0, 4);

    // Férias: no mês em que começam férias aprovadas, uma vez por ano
    $vacationPct = 0.0;
    $vacationReason = 'Sem início de férias aprovadas neste mês.';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vacations WHERE employee_id = ? AND company_id = ? AND status = 'Aprovado'
                           AND type = 'Férias' AND start_date BETWEEN ? AND ?");
    $stmt->execute([$employeeId, $companyId, $first, $last]);
    if ($stmt->fetchColumn()) {
        if (rh_subsidy_already_paid($pdo, $companyId, $employeeId, $month, 'vacation_subsidy_pct', $excludePayrollId)) {
            $vacationReason = "O subsídio de férias de {$year} já foi processado noutro mês.";
        } else {
            $vacationPct = (float)($emp['vacation_subsidy_pct'] ?: 50);
            $vacationReason = 'Férias aprovadas começam neste mês.';
        }
    }

    // Natal (13.º): no mês configurado, proporcional aos meses trabalhados no ano
    $thirteenthPct = 0.0;
    $thirteenthMonth = (int)$settings['thirteenth_month'];
    $thirteenthReason = 'O subsídio de Natal é processado em ' . rh_month_name($thirteenthMonth) . '.';
    if ((int)substr($month, 5, 2) === $thirteenthMonth) {
        if (rh_subsidy_already_paid($pdo, $companyId, $employeeId, $month, 'thirteenth_subsidy_pct', $excludePayrollId)) {
            $thirteenthReason = "O subsídio de Natal de {$year} já foi processado noutro mês.";
        } else {
            $months = rh_months_worked_in_year($emp['admission_date'] ?? null, $year, $last);
            $fullPct = (float)($emp['thirteenth_subsidy_pct'] ?: 50);
            $thirteenthPct = round($fullPct * $months / 12, 2);
            $thirteenthReason = "Proporcional a {$months} mês(es) trabalhado(s) em {$year} ({$fullPct}% do salário base por ano completo).";
        }
    }

    return [
        'vacation_pct'      => $vacationPct,
        'vacation_reason'   => $vacationReason,
        'thirteenth_pct'    => $thirteenthPct,
        'thirteenth_reason' => $thirteenthReason,
    ];
}

function rh_months_worked_in_year(?string $admissionDate, int $year, string $untilDate): int
{
    $start = new DateTime("{$year}-01-01");
    if ($admissionDate) {
        $admission = new DateTime($admissionDate);
        if ($admission > $start) {
            $start = $admission;
        }
    }
    $end = new DateTime($untilDate);
    if ($start > $end) {
        return 0;
    }
    $diff = $start->diff($end);
    return min(12, $diff->y * 12 + $diff->m + 1);
}

function rh_month_name(int $month): string
{
    return ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][max(1, min(12, $month)) - 1];
}

function rh_month_label(string $month): string
{
    return ucfirst(rh_month_name((int)substr($month, 5, 2))) . ' de ' . substr($month, 0, 4);
}

/**
 * Prestações de adiantamentos/empréstimos a descontar neste mês.
 * Devolve [['advance_id', 'description', 'amount'], …].
 */
function rh_due_advances(PDO $pdo, int $companyId, int $employeeId, string $month, ?int $payrollId = null): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT a.id, a.type, a.description, a.total_amount, a.installment_amount,
                   COALESCE((SELECT SUM(ap.amount) FROM rh_advance_payments ap WHERE ap.advance_id = a.id AND ap.payroll_id <> ?), 0) AS paid_elsewhere,
                   (SELECT COUNT(*) FROM rh_advance_payments ap2 WHERE ap2.advance_id = a.id AND ap2.reference_month = ? AND ap2.payroll_id <> ?) AS paid_this_month
            FROM rh_advances a
            WHERE a.company_id = ? AND a.employee_id = ? AND a.status = 'ativo' AND a.start_month <= ?
            ORDER BY a.start_month, a.id
        ");
        $stmt->execute([(int)$payrollId, $month, (int)$payrollId, $companyId, $employeeId, $month]);
    } catch (PDOException $e) {
        return [];
    }
    $due = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $remaining = rh_money((float)$row['total_amount'] - (float)$row['paid_elsewhere']);
        if ($remaining <= 0 || (int)$row['paid_this_month'] > 0) {
            continue;
        }
        $label = $row['type'] === 'emprestimo' ? 'Empréstimo' : 'Adiantamento';
        $due[] = [
            'advance_id'  => (int)$row['id'],
            'description' => trim($label . ($row['description'] ? ' — ' . $row['description'] : '')),
            'amount'      => rh_money(min((float)$row['installment_amount'], $remaining)),
        ];
    }
    return $due;
}

/**
 * Calcula a folha de UM funcionário. Não grava nada.
 *
 * $overrides (todos opcionais): base_salary, bonuses, food_allowance, transport_allowance,
 *   vacation_subsidy_pct, thirteenth_subsidy_pct, commissions, sales, discounts (manual),
 *   other_earnings, overtime_hours, absence_days, skip_advances (bool).
 * Quando vacation_subsidy_pct / thirteenth_subsidy_pct não vêm, usa a sugestão automática.
 */
function rh_payroll_compute(PDO $pdo, int $companyId, int $employeeId, string $month, array $overrides = [], ?int $payrollId = null): array
{
    $settings = rh_settings($pdo, $companyId);
    $emp = rh_employee_for_payroll($pdo, $companyId, $employeeId);

    $num = static function (string $key, $default) use ($overrides): float {
        if (!array_key_exists($key, $overrides) || $overrides[$key] === '' || $overrides[$key] === null) {
            return (float)$default;
        }
        return max(0.0, rh_parse_number($overrides[$key]));
    };

    $suggestion = rh_suggest_subsidies($pdo, $companyId, $emp, $month, $settings, $payrollId);

    $base        = $num('base_salary', $emp['salary_base']);
    $bonuses     = $num('bonuses', 0);
    $commissions = $num('commissions', 0);
    $sales       = $num('sales', 0);
    $food        = $num('food_allowance', $emp['food_allowance']);
    $transport   = $num('transport_allowance', $emp['transport_allowance']);
    $otherEarn   = $num('other_earnings', 0);
    $manualDisc  = $num('discounts', 0);
    $vacPct      = min(100, $num('vacation_subsidy_pct', $suggestion['vacation_pct']));
    $natPct      = min(100, $num('thirteenth_subsidy_pct', $suggestion['thirteenth_pct']));

    $absenceDays   = $num('absence_days', rh_absence_days($pdo, $companyId, $employeeId, $month));
    $overtimeHours = $num('overtime_hours', rh_overtime_hours($pdo, $companyId, $employeeId, $month));

    $divisor      = max(1, (int)$settings['absence_divisor']);
    $absenceValue = rh_money(min($base, $base / $divisor * $absenceDays));

    $vacationSub   = rh_money($base * $vacPct / 100);
    $thirteenthSub = rh_money($base * $natPct / 100);

    $hourly    = (float)$settings['monthly_hours'] > 0 ? $base / (float)$settings['monthly_hours'] : 0;
    $threshold = (float)$settings['overtime_threshold_hours'];
    $h1 = min($overtimeHours, $threshold);
    $h2 = max(0, $overtimeHours - $threshold);
    $overtimeValue = rh_money($hourly * ($h1 * (1 + $settings['overtime_rate_1'] / 100) + $h2 * (1 + $settings['overtime_rate_2'] / 100)));

    $gross = rh_money($base + $bonuses + $commissions + $food + $transport + $vacationSub + $thirteenthSub + $overtimeValue + $otherEarn);

    $exemptFood      = min($food, (float)$settings['food_exempt_limit']);
    $exemptTransport = min($transport, (float)$settings['transport_exempt_limit']);
    $exempt          = rh_money($exemptFood + $exemptTransport);

    $taxable  = max(0, $gross - $absenceValue - $exempt);
    $inssBase = rh_money((int)$settings['inss_on_exempt_allowances'] ? max(0, $gross - $absenceValue) : $taxable);
    $inss         = rh_money($inssBase * $settings['inss_employee_rate'] / 100);
    $inssEmployer = rh_money($inssBase * $settings['inss_employer_rate'] / 100);
    $irtBase = rh_money(max(0, $taxable - $inss));
    $irt     = rh_irt($irtBase, $settings['irt_table']);

    $advances = !empty($overrides['skip_advances']) ? [] : rh_due_advances($pdo, $companyId, $employeeId, $month, $payrollId);
    $advanceTotal = rh_money(array_sum(array_column($advances, 'amount')));

    $totalDiscounts = rh_money($absenceValue + $inss + $irt + $manualDisc + $advanceTotal);
    $net = rh_money($gross - $totalDiscounts);

    $warnings = [];
    if ($net < 0) {
        $warnings[] = 'Os descontos são maiores do que os proventos — o líquido ficou negativo.';
    }
    if ((float)$settings['salario_minimo_nacional'] > 0 && $base > 0 && $base < (float)$settings['salario_minimo_nacional']) {
        $warnings[] = 'Salário base abaixo do salário mínimo nacional configurado.';
    }
    if (empty($emp['iban'])) {
        $warnings[] = 'Funcionário sem IBAN registado.';
    }

    return [
        'employee_id'            => $employeeId,
        'employee_name'          => $emp['name'],
        'position'               => $emp['position_name'],
        'iban'                   => $emp['iban'],
        'email'                  => $emp['email'],
        'reference_month'        => $month,
        'base_salary'            => rh_money($base),
        'bonuses'                => rh_money($bonuses),
        'commissions'            => rh_money($commissions),
        'sales'                  => rh_money($sales),
        'food_allowance'         => rh_money($food),
        'transport_allowance'    => rh_money($transport),
        'other_earnings'         => rh_money($otherEarn),
        'vacation_subsidy_pct'   => round($vacPct, 2),
        'thirteenth_subsidy_pct' => round($natPct, 2),
        'vacation_subsidy'       => $vacationSub,
        'thirteenth_subsidy'     => $thirteenthSub,
        'overtime_hours'         => round($overtimeHours, 2),
        'overtime_value'         => $overtimeValue,
        'absence_days'           => round($absenceDays, 1),
        'absence_value'          => $absenceValue,
        'gross_salary'           => $gross,
        'exempt_allowances'      => $exempt,
        'inss_base'              => $inssBase,
        'inss_value'             => $inss,
        'inss_employer_value'    => $inssEmployer,
        'irt_base'               => $irtBase,
        'irt_value'              => $irt,
        'manual_discounts'       => rh_money($manualDisc),
        'advances'               => $advances,
        'advance_deduction'      => $advanceTotal,
        'total_discounts'        => $totalDiscounts,
        'net_salary'             => $net,
        'employer_cost'          => rh_money($gross - $absenceValue + $inssEmployer),
        'suggestion'             => $suggestion,
        'warnings'               => $warnings,
        // compatibilidade com o formato antigo (calculate_payroll_batch)
        'total_faltas'           => round($absenceDays, 1),
        'valor_faltas'           => $absenceValue,
        'discounts'              => $totalDiscounts,
    ];
}

/**
 * Grava (insere ou actualiza) a folha calculada e as prestações de adiantamentos.
 * Lança RuntimeException se o mês estiver fechado, a folha já estiver paga, ou houver duplicado.
 */
function rh_payroll_store(PDO $pdo, int $companyId, array $calc, array $options = []): int
{
    $id          = isset($options['id']) && $options['id'] ? (int)$options['id'] : null;
    $status      = in_array($options['status'] ?? null, ['Pendente', 'Aprovado', 'Pago'], true) ? $options['status'] : 'Pendente';
    $paymentDate = !empty($options['payment_date']) ? $options['payment_date'] : null;
    $notes       = isset($options['notes']) ? trim((string)$options['notes']) : null;
    $userId      = (int)($options['user_id'] ?? 0);
    $month       = $calc['reference_month'];

    if (rh_period_is_closed($pdo, $companyId, $month)) {
        throw new RuntimeException('O mês ' . rh_month_label($month) . ' está fechado. Reabra o mês para alterar folhas.');
    }

    if ($id) {
        $stmt = $pdo->prepare('SELECT status, reference_month, employee_id FROM payroll WHERE id = ? AND company_id = ?');
        $stmt->execute([$id, $companyId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            throw new RuntimeException('Folha não encontrada.');
        }
        if ($current['status'] === 'Pago') {
            throw new RuntimeException('Esta folha já está paga e não pode ser alterada.');
        }
        if ($current['reference_month'] !== $month && rh_period_is_closed($pdo, $companyId, $current['reference_month'])) {
            throw new RuntimeException('O mês original desta folha está fechado.');
        }
    }

    $stmt = $pdo->prepare('SELECT id FROM payroll WHERE company_id = ? AND employee_id = ? AND reference_month = ? AND id <> ? LIMIT 1');
    $stmt->execute([$companyId, $calc['employee_id'], $month, (int)$id]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException($calc['employee_name'] . ' já tem folha em ' . rh_month_label($month) . '.');
    }

    if ($status === 'Pago' && !$paymentDate) {
        $paymentDate = date('Y-m-d');
    }

    $fields = [
        'employee_id'            => $calc['employee_id'],
        'company_id'             => $companyId,
        'reference_month'        => $month,
        'base_salary'            => $calc['base_salary'],
        'bonuses'                => $calc['bonuses'],
        'food_allowance'         => $calc['food_allowance'],
        'transport_allowance'    => $calc['transport_allowance'],
        'vacation_subsidy_pct'   => $calc['vacation_subsidy_pct'],
        'thirteenth_subsidy_pct' => $calc['thirteenth_subsidy_pct'],
        'commissions'            => $calc['commissions'],
        'sales'                  => $calc['sales'],
        'discounts'              => $calc['manual_discounts'],
        'total_discounts'        => $calc['total_discounts'],
        'inss_value'             => $calc['inss_value'],
        'inss_employer_value'    => $calc['inss_employer_value'],
        'irt_value'              => $calc['irt_value'],
        'net_salary'             => $calc['net_salary'],
        'gross_salary'           => $calc['gross_salary'],
        'absence_days'           => $calc['absence_days'],
        'absence_value'          => $calc['absence_value'],
        'vacation_subsidy'       => $calc['vacation_subsidy'],
        'thirteenth_subsidy'     => $calc['thirteenth_subsidy'],
        'overtime_hours'         => $calc['overtime_hours'],
        'overtime_value'         => $calc['overtime_value'],
        'other_earnings'         => $calc['other_earnings'],
        'advance_deduction'      => $calc['advance_deduction'],
        'inss_base'              => $calc['inss_base'],
        'irt_base'               => $calc['irt_base'],
        'exempt_allowances'      => $calc['exempt_allowances'],
        'calc_version'           => RH_CALC_VERSION,
        'payment_date'           => $paymentDate,
        'status'                 => $status,
    ];
    if ($notes !== null) {
        $fields['notes'] = $notes;
    }
    if ($status !== 'Pendente') {
        $fields['approved_at'] = date('Y-m-d H:i:s');
        $fields['approved_by'] = $userId ?: null;
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        if ($id) {
            $set = implode(', ', array_map(static fn($k) => "`$k` = ?", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE payroll SET $set WHERE id = ? AND company_id = ?");
            $stmt->execute([...array_values($fields), $id, $companyId]);
        } else {
            $cols = implode(', ', array_map(static fn($k) => "`$k`", array_keys($fields)));
            $marks = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $pdo->prepare("INSERT INTO payroll ($cols) VALUES ($marks)");
            $stmt->execute(array_values($fields));
            $id = (int)$pdo->lastInsertId();
        }

        // Prestações descontadas nesta folha
        $pdo->prepare('DELETE FROM rh_advance_payments WHERE payroll_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO rh_advance_payments (advance_id, payroll_id, reference_month, amount) VALUES (?, ?, ?, ?)');
        foreach ($calc['advances'] as $advance) {
            $ins->execute([$advance['advance_id'], $id, $month, $advance['amount']]);
        }
        rh_refresh_advance_status($pdo, $companyId, (int)$calc['employee_id']);

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $id;
}

/** Marca como liquidados os adiantamentos já totalmente descontados (e reactiva os que deixaram de estar). */
function rh_refresh_advance_status(PDO $pdo, int $companyId, int $employeeId): void
{
    $pdo->prepare("
        UPDATE rh_advances a
        SET a.status = CASE
            WHEN COALESCE((SELECT SUM(ap.amount) FROM rh_advance_payments ap WHERE ap.advance_id = a.id), 0) >= a.total_amount - 0.005 THEN 'liquidado'
            ELSE 'ativo' END
        WHERE a.company_id = ? AND a.employee_id = ? AND a.status IN ('ativo', 'liquidado')
    ")->execute([$companyId, $employeeId]);
}

/** Apaga uma folha (não paga, mês aberto) e as prestações associadas. */
function rh_payroll_delete(PDO $pdo, int $companyId, int $payrollId): void
{
    $stmt = $pdo->prepare('SELECT status, reference_month, employee_id FROM payroll WHERE id = ? AND company_id = ?');
    $stmt->execute([$payrollId, $companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Folha não encontrada.');
    }
    if ($row['status'] === 'Pago') {
        throw new RuntimeException('Uma folha paga não pode ser eliminada.');
    }
    if (rh_period_is_closed($pdo, $companyId, $row['reference_month'])) {
        throw new RuntimeException('O mês desta folha está fechado.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM rh_advance_payments WHERE payroll_id = ?')->execute([$payrollId]);
        $pdo->prepare('DELETE FROM payroll WHERE id = ? AND company_id = ?')->execute([$payrollId, $companyId]);
        rh_refresh_advance_status($pdo, $companyId, (int)$row['employee_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Valores de entrada guardados numa folha (para recalcular mantendo o que o RH escreveu). */
function rh_payroll_overrides_from_row(array $row): array
{
    return [
        'base_salary'            => $row['base_salary'],
        'bonuses'                => $row['bonuses'],
        'commissions'            => $row['commissions'],
        'sales'                  => $row['sales'],
        'food_allowance'         => $row['food_allowance'],
        'transport_allowance'    => $row['transport_allowance'],
        'other_earnings'         => $row['other_earnings'] ?? 0,
        'discounts'              => $row['discounts'],
        'vacation_subsidy_pct'   => $row['vacation_subsidy_pct'],
        'thirteenth_subsidy_pct' => $row['thirteenth_subsidy_pct'],
    ];
}
