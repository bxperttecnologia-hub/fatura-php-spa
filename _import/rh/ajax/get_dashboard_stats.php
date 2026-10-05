<?php

/**
 * Fase 5, item 4 — Dashboard de RH: dados agregados.
 *
 * GET mes (YYYY-MM, opcional — default mês corrente): usado para custo de
 * folha do mês, absentismo do mês e turnover do mês.
 */

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
[$year, $month] = explode('-', $mes);
$firstDay = "$year-$month-01";
$lastDay = date('Y-m-t', strtotime($firstDay));

// --- Headcount total e por departamento ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'ativo'");
$stmt->execute([$company_id]);
$headcountTotal = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COALESCE(d.name, 'Sem departamento') AS department_name, COUNT(*) AS total
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.company_id = ? AND e.status = 'ativo'
    GROUP BY department_name
    ORDER BY total DESC
");
$stmt->execute([$company_id]);
$headcountPorDepartamento = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Custo total de folha do mês (incluindo INSS patronal) ---
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(net_salary), 0) AS total_liquido,
        COALESCE(SUM(inss_employer_value), 0) AS total_inss_patronal,
        COALESCE(SUM(total_discounts), 0) AS total_descontos,
        COALESCE(SUM(base_salary + bonuses + food_allowance + transport_allowance
            + (base_salary * vacation_subsidy_pct / 100) + (base_salary * thirteenth_subsidy_pct / 100)
            + commissions), 0) AS total_bruto,
        COUNT(*) AS total_folhas
    FROM payroll
    WHERE company_id = ? AND reference_month = ?
");
$stmt->execute([$company_id, $mes]);
$custoFolha = $stmt->fetch(PDO::FETCH_ASSOC);
// Custo total para a empresa = bruto pago + INSS patronal (encargo da empresa, não descontado do funcionário)
$custoFolha['custo_total_empresa'] = (float)$custoFolha['total_bruto'] + (float)$custoFolha['total_inss_patronal'];

// --- Taxa de absentismo (faltas / dias úteis do mês, para todos os ativos) ---
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendance
    WHERE company_id = ? AND type = 'falta' AND date BETWEEN ? AND ?
    AND date NOT IN (SELECT date FROM holidays WHERE company_id = ?)
");
$stmt->execute([$company_id, $firstDay, $lastDay, $company_id]);
$totalFaltas = (int)$stmt->fetchColumn();

// dias úteis do mês (excluindo feriados) × funcionários ativos = "dias-homem" esperados
$stmtHolidays = $pdo->prepare('SELECT COUNT(*) FROM holidays WHERE company_id = ? AND date BETWEEN ? AND ?');
$stmtHolidays->execute([$company_id, $firstDay, $lastDay]);
$feriadosNoMes = (int)$stmtHolidays->fetchColumn();

$diasUteisMes = 0;
$cursor = new DateTime($firstDay);
$fimMes = new DateTime($lastDay);
while ($cursor <= $fimMes) {
    if ((int)$cursor->format('N') < 6) $diasUteisMes++;
    $cursor->modify('+1 day');
}
$diasUteisMes -= $feriadosNoMes;
$diasUteisMes = max(1, $diasUteisMes);

$diasHomemEsperados = $diasUteisMes * max(1, $headcountTotal);
$taxaAbsentismo = $diasHomemEsperados > 0 ? round(($totalFaltas / $diasHomemEsperados) * 100, 2) : 0;

// --- Turnover (admissões vs. desligamentos no mês) ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND admission_date BETWEEN ? AND ?");
$stmt->execute([$company_id, $firstDay, $lastDay]);
$admissoesNoMes = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'inativo' AND end_date BETWEEN ? AND ?");
$stmt->execute([$company_id, $firstDay, $lastDay]);
$desligamentosNoMes = (int)$stmt->fetchColumn();

$turnoverPct = $headcountTotal > 0 ? round(($desligamentosNoMes / $headcountTotal) * 100, 2) : 0;

// --- Distribuição de idade e tempo de casa ---
$stmt = $pdo->prepare("SELECT birth_date, admission_date FROM employees WHERE company_id = ? AND status = 'ativo'");
$stmt->execute([$company_id]);
$hoje = new DateTime();

$faixasIdade = ['<25' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55+' => 0, 'Não informado' => 0];
$faixasTempoCasa = ['<1 ano' => 0, '1-3 anos' => 0, '3-5 anos' => 0, '5-10 anos' => 0, '10+ anos' => 0];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['birth_date']) {
        $idade = (new DateTime($row['birth_date']))->diff($hoje)->y;
        if ($idade < 25) $faixasIdade['<25']++;
        elseif ($idade < 35) $faixasIdade['25-34']++;
        elseif ($idade < 45) $faixasIdade['35-44']++;
        elseif ($idade < 55) $faixasIdade['45-54']++;
        else $faixasIdade['55+']++;
    } else {
        $faixasIdade['Não informado']++;
    }

    if ($row['admission_date']) {
        $anosCasa = (new DateTime($row['admission_date']))->diff($hoje)->y;
        if ($anosCasa < 1) $faixasTempoCasa['<1 ano']++;
        elseif ($anosCasa < 3) $faixasTempoCasa['1-3 anos']++;
        elseif ($anosCasa < 5) $faixasTempoCasa['3-5 anos']++;
        elseif ($anosCasa < 10) $faixasTempoCasa['5-10 anos']++;
        else $faixasTempoCasa['10+ anos']++;
    }
}

echo json_encode([
    'success' => true,
    'reference_month' => $mes,
    'headcount_total' => $headcountTotal,
    'headcount_por_departamento' => $headcountPorDepartamento,
    'custo_folha' => $custoFolha,
    'absentismo' => [
        'total_faltas' => $totalFaltas,
        'dias_uteis_mes' => $diasUteisMes,
        'taxa_pct' => $taxaAbsentismo,
    ],
    'turnover' => [
        'admissoes' => $admissoesNoMes,
        'desligamentos' => $desligamentosNoMes,
        'taxa_pct' => $turnoverPct,
    ],
    'distribuicao_idade' => $faixasIdade,
    'distribuicao_tempo_casa' => $faixasTempoCasa,
]);
