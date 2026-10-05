<?php

/**
 * Fase 2, item 4 — Sugestão automática de subsídio de férias e 13º mês.
 *
 * Não decide sozinho: devolve uma SUGESTÃO que o formulário de folha
 * (payroll.php) pré-preenche nos selects existentes, continuando 100%
 * editável pelo utilizador antes de gravar.
 *
 * GET employee_id, reference_month (YYYY-MM)
 */

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../lib/rh_helpers.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
$employee_id = (int)($_GET['employee_id'] ?? 0);
$reference_month = $_GET['reference_month'] ?? null; // YYYY-MM

if (!$company_id || !$employee_id || !$reference_month || !preg_match('/^\d{4}-\d{2}$/', $reference_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$stmtEmp = $pdo->prepare("
    SELECT e.admission_date, p.vacation_subsidy_pct AS pos_vacation_pct, p.thirteenth_subsidy_pct AS pos_thirteenth_pct
    FROM employees e
    LEFT JOIN positions p ON p.id = e.position_id
    WHERE e.id = ? AND e.company_id = ?
");
$stmtEmp->execute([$employee_id, $company_id]);
$emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
    exit;
}

[$year, $month] = explode('-', $reference_month);
$firstDay = "{$year}-{$month}-01";
$lastDay = date('Y-m-t', strtotime($firstDay));

// --- Subsídio de férias: sugere o valor do cargo se houver férias APROVADAS
//     que cruzem este mês de referência ---
$stmtVac = $pdo->prepare("
    SELECT COUNT(*) FROM vacations
    WHERE employee_id = ? AND company_id = ? AND status = 'Aprovado'
    AND start_date <= ? AND end_date >= ?
");
$stmtVac->execute([$employee_id, $company_id, $lastDay, $firstDay]);
$hasApprovedVacationThisMonth = (bool)$stmtVac->fetchColumn();

$suggestedVacationPct = 0;
$vacationReason = 'Sem férias aprovadas neste mês de referência.';
if ($hasApprovedVacationThisMonth) {
    $suggestedVacationPct = (int)($emp['pos_vacation_pct'] ?? 100) ?: 100;
    $vacationReason = 'Há férias aprovadas que cruzam este mês de referência.';
}

// --- 13º mês: por defeito, só sugere lançamento em novembro/dezembro,
//     proporcional aos meses trabalhados no ano corrente ---
$suggestedThirteenthPct = 0;
$thirteenthReason = 'Fora do período habitual de lançamento (novembro/dezembro).';

if (in_array((int)$month, [11, 12], true)) {
    $admission = new DateTime($emp['admission_date']);
    $yearStart = new DateTime("{$year}-01-01");
    $refEnd = new DateTime($lastDay);

    $countFrom = $admission > $yearStart ? $admission : $yearStart;

    if ($countFrom > $refEnd) {
        $monthsWorked = 0;
    } else {
        $diff = $countFrom->diff($refEnd);
        $monthsWorked = min(12, $diff->y * 12 + $diff->m + 1); // +1 inclui o mês corrente
    }

    $proportion = $monthsWorked / 12;
    // Arredonda para a opção disponível mais próxima (0 / 50 / 100%)
    if ($proportion >= 0.75) {
        $suggestedThirteenthPct = 100;
    } elseif ($proportion >= 0.25) {
        $suggestedThirteenthPct = 50;
    } else {
        $suggestedThirteenthPct = 0;
    }

    $thirteenthReason = "Proporcional a {$monthsWorked} mês(es) trabalhado(s) em {$year}.";
}

echo json_encode([
    'success' => true,
    'suggested_vacation_subsidy_pct' => $suggestedVacationPct,
    'vacation_reason' => $vacationReason,
    'suggested_thirteenth_subsidy_pct' => $suggestedThirteenthPct,
    'thirteenth_reason' => $thirteenthReason,
]);
