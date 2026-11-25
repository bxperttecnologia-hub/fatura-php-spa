<?php
/**
 * Definições de RH da empresa e feriados.
 * GET  ?year=YYYY                → definições + feriados do ano
 * POST action=save               → grava definições
 * POST action=holiday_add        → date, name
 * POST action=holiday_delete     → id
 * POST action=holidays_import    → year (feriados nacionais de Angola; não duplica)
 * POST action=reset_irt          → repõe a tabela de IRT por omissão
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

function rh_easter(int $year): DateTime
{
    $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7; $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31); $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    return new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

/** Feriados nacionais de Angola (lista por omissão — confirmar alterações legais e acrescentar tolerâncias de ponto). */
function rh_angola_holidays(int $year): array
{
    $easter = rh_easter($year);
    return [
        "$year-01-01" => 'Dia do Ano Novo',
        (clone $easter)->modify('-47 days')->format('Y-m-d') => 'Carnaval',
        "$year-02-04" => 'Dia do Início da Luta Armada de Libertação Nacional',
        "$year-03-08" => 'Dia Internacional da Mulher',
        "$year-03-23" => 'Dia da Libertação da África Austral',
        "$year-04-04" => 'Dia da Paz e da Reconciliação Nacional',
        (clone $easter)->modify('-2 days')->format('Y-m-d') => 'Sexta-Feira Santa',
        "$year-05-01" => 'Dia Internacional do Trabalhador',
        "$year-09-17" => 'Dia do Fundador da Nação e do Herói Nacional',
        "$year-11-02" => 'Dia dos Finados',
        "$year-11-11" => 'Dia da Independência Nacional',
        "$year-12-25" => 'Dia de Natal e da Família',
    ];
}

$numericFields = [
    'salario_minimo_nacional' => [0, 100000000], 'aviso_previo_demissao_dias' => [0, 365],
    'inss_employee_rate' => [0, 100], 'inss_employer_rate' => [0, 100],
    'food_exempt_limit' => [0, 100000000], 'transport_exempt_limit' => [0, 100000000],
    'inss_on_exempt_allowances' => [0, 1], 'absence_divisor' => [1, 31], 'monthly_hours' => [1, 400],
    'overtime_rate_1' => [0, 300], 'overtime_rate_2' => [0, 300], 'overtime_threshold_hours' => [0, 400],
    'vacation_days_per_year' => [0, 60], 'vacation_days_first_year_per_month' => [0, 10],
    'thirteenth_month' => [1, 12], 'daily_hours' => [1, 24], 'late_tolerance_minutes' => [0, 240], 'break_minutes' => [0, 240],
];

$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $year = (int)($_GET['year'] ?? date('Y'));
    $stmt = $pdo->prepare('SELECT id, date, name FROM holidays WHERE company_id = ? AND YEAR(date) = ? ORDER BY date');
    $stmt->execute([$company_id, $year]);
    rh_json(['success' => true, 'settings' => rh_settings($pdo, $company_id), 'default_irt_table' => rh_default_irt_table(), 'defaults' => array_diff_key(rh_default_settings(), ['irt_table' => 1]), 'holidays' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'year' => $year]);
}

switch ($action) {
    case 'save':
        $values = [];
        foreach ($numericFields as $field => [$min, $max]) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                continue;
            }
            $v = rh_parse_number($_POST[$field]); // aceita "70 000,00", "70000" ou "70.000,5"
            if ($v < $min || $v > $max) {
                rh_json(['success' => false, 'message' => "Valor inválido em $field (entre $min e $max)."], 422);
            }
            $values[$field] = $v;
        }
        if (isset($_POST['work_start_time']) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $_POST['work_start_time'])) {
            $values['work_start_time'] = strlen($_POST['work_start_time']) === 5 ? $_POST['work_start_time'] . ':00' : $_POST['work_start_time'];
        }
        if (isset($_POST['payslip_email_message'])) {
            $values['payslip_email_message'] = mb_substr(trim((string)$_POST['payslip_email_message']), 0, 2000);
        }
        if (isset($_POST['irt_table'])) {
            $table = json_decode((string)$_POST['irt_table'], true);
            if (!rh_irt_table_valid($table)) {
                rh_json(['success' => false, 'message' => 'Tabela de IRT inválida.'], 422);
            }
            usort($table, static fn($a, $b) => ($a['up_to'] ?? PHP_INT_MAX) <=> ($b['up_to'] ?? PHP_INT_MAX));
            $values['irt_table'] = json_encode(array_map(static fn($r) => [
                'up_to' => $r['up_to'] === null || $r['up_to'] === '' ? null : rh_parse_number($r['up_to']),
                'fixed' => rh_parse_number($r['fixed']), 'rate' => rh_parse_number($r['rate']), 'over' => rh_parse_number($r['over']),
            ], $table));
        }
        if (!$values) {
            rh_json(['success' => false, 'message' => 'Nada para gravar.'], 422);
        }
        $cols = array_keys($values);
        $sql = 'INSERT INTO rh_settings (company_id, ' . implode(', ', array_map(fn($c) => "`$c`", $cols)) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ')
                ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", $cols));
        $pdo->prepare($sql)->execute([$company_id, ...array_values($values)]);
        rh_json(['success' => true, 'message' => 'Definições gravadas.']);

    case 'reset_irt':
        $pdo->prepare('INSERT INTO rh_settings (company_id, irt_table) VALUES (?, NULL) ON DUPLICATE KEY UPDATE irt_table = NULL')->execute([$company_id]);
        rh_json(['success' => true, 'message' => 'Tabela de IRT reposta.']);

    case 'holiday_add':
        $date = (string)($_POST['date'] ?? '');
        $name = trim((string)($_POST['name'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $name === '') {
            rh_json(['success' => false, 'message' => 'Indique a data e o nome do feriado.'], 422);
        }
        $stmt = $pdo->prepare('SELECT 1 FROM holidays WHERE company_id = ? AND date = ?');
        $stmt->execute([$company_id, $date]);
        if ($stmt->fetchColumn()) {
            rh_json(['success' => false, 'message' => 'Já existe um feriado nessa data.'], 422);
        }
        $pdo->prepare('INSERT INTO holidays (company_id, date, name) VALUES (?, ?, ?)')->execute([$company_id, $date, mb_substr($name, 0, 150)]);
        rh_json(['success' => true, 'message' => 'Feriado adicionado.']);

    case 'holiday_delete':
        $pdo->prepare('DELETE FROM holidays WHERE id = ? AND company_id = ?')->execute([(int)($_POST['id'] ?? 0), $company_id]);
        rh_json(['success' => true, 'message' => 'Feriado removido.']);

    case 'holidays_import':
        $year = (int)($_POST['year'] ?? date('Y'));
        if ($year < 2000 || $year > 2100) {
            rh_json(['success' => false, 'message' => 'Ano inválido.'], 422);
        }
        $exists = $pdo->prepare('SELECT 1 FROM holidays WHERE company_id = ? AND date = ?');
        $insert = $pdo->prepare('INSERT INTO holidays (company_id, date, name) VALUES (?, ?, ?)');
        $added = 0;
        foreach (rh_angola_holidays($year) as $date => $name) {
            $exists->execute([$company_id, $date]);
            if (!$exists->fetchColumn()) {
                $insert->execute([$company_id, $date, $name]);
                $added++;
            }
        }
        rh_json(['success' => true, 'message' => $added ? "$added feriado(s) de $year adicionado(s)." : "Os feriados nacionais de $year já estavam registados."]);
}
rh_json(['success' => false, 'message' => 'Acção inválida.'], 400);
