<?php

/**
 * Fase 5, item 1 — "Gerar folha do mês".
 *
 * Cria uma linha `payroll` (status 'Pendente') para cada funcionário ativo
 * SEM folha ainda nesse reference_month, pré-preenchendo salário e
 * subsídios a partir do cargo (positions) e da sugestão automática
 * (Fase 2, item 4: subsídio de férias/13º). O RH depois só revê e marca
 * como 'Pago' (fluxo já existente em payroll.php).
 *
 * POST: reference_month (YYYY-MM)
 */

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../lib/rh_helpers.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$reference_month = $_POST['reference_month'] ?? '';
if (!preg_match('/^\d{4}-\d{2}$/', $reference_month)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mês de referência inválido (usa YYYY-MM).']);
    exit;
}

[$year, $month] = explode('-', $reference_month);
$firstDay = "$year-$month-01";
$lastDay = date('Y-m-t', strtotime($firstDay));

try {
    $pdo->beginTransaction();

    // Funcionários ativos que ainda NÃO têm folha neste mês de referência
    $stmt = $pdo->prepare("
        SELECT e.id, e.salary_base, e.position_id, e.admission_date,
               p.food_allowance, p.transport_allowance, p.vacation_subsidy_pct, p.thirteenth_subsidy_pct
        FROM employees e
        LEFT JOIN positions p ON p.id = e.position_id
        WHERE e.company_id = ? AND e.status = 'ativo'
        AND e.id NOT IN (
            SELECT employee_id FROM payroll WHERE company_id = ? AND reference_month = ?
        )
    ");
    $stmt->execute([$company_id, $company_id, $reference_month]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtVac = $pdo->prepare("
        SELECT COUNT(*) FROM vacations
        WHERE employee_id = ? AND company_id = ? AND status = 'Aprovado'
        AND start_date <= ? AND end_date >= ?
    ");

    $stmtInsert = $pdo->prepare("
        INSERT INTO payroll
            (employee_id, company_id, reference_month, base_salary, bonuses, food_allowance, transport_allowance,
             vacation_subsidy_pct, thirteenth_subsidy_pct, commissions, sales, discounts, total_discounts,
             inss_value, inss_employer_value, irt_value, net_salary, status)
        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 0, 0, 0, 0, ?, ?, ?, ?, 'Pendente')
    ");

    $created = 0;

    foreach ($employees as $emp) {
        $baseSalary = (float)$emp['salary_base'];
        $foodAllowance = (float)($emp['food_allowance'] ?? 0);
        $transportAllowance = (float)($emp['transport_allowance'] ?? 0);

        // Sugestão automática de subsídios (mesma lógica de get_suggested_subsidies.php)
        $stmtVac->execute([$emp['id'], $company_id, $lastDay, $firstDay]);
        $hasApprovedVacation = (bool)$stmtVac->fetchColumn();
        $vacationPct = $hasApprovedVacation ? (int)($emp['vacation_subsidy_pct'] ?? 100) ?: 100 : 0;

        $thirteenthPct = 0;
        if (in_array((int)$month, [11, 12], true) && $emp['admission_date']) {
            $admission = new DateTime($emp['admission_date']);
            $yearStart = new DateTime("$year-01-01");
            $refEnd = new DateTime($lastDay);
            $countFrom = $admission > $yearStart ? $admission : $yearStart;
            $monthsWorked = $countFrom > $refEnd ? 0 : min(12, $countFrom->diff($refEnd)->y * 12 + $countFrom->diff($refEnd)->m + 1);
            $proportion = $monthsWorked / 12;
            $thirteenthPct = $proportion >= 0.75 ? 100 : ($proportion >= 0.25 ? 50 : 0);
        }

        $grossSalary = $baseSalary + $foodAllowance + $transportAllowance
            + ($baseSalary * $vacationPct / 100) + ($baseSalary * $thirteenthPct / 100);

        $inssValue = round($grossSalary * 0.03, 2);
        $inssEmployerValue = round($grossSalary * 0.08, 2);
        // IRT não é recalculado aqui (a tabela/regras de IRT já usadas em
        // save_payroll.php ficam a cargo da revisão manual do RH antes de
        // marcar como 'Pago' — evita duplicar a lógica de IRT nos dois sítios).
        $irtValue = 0;
        $netSalary = $grossSalary - $inssValue - $irtValue;

        $stmtInsert->execute([
            $emp['id'],
            $company_id,
            $reference_month,
            $baseSalary,
            $foodAllowance,
            $transportAllowance,
            $vacationPct,
            $thirteenthPct,
            $inssValue,
            $inssEmployerValue,
            $irtValue,
            $netSalary,
        ]);

        $created++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'created' => $created,
        'message' => "{$created} folha(s) pendente(s) criada(s) para {$reference_month}. Revê o IRT e marca como Pago em cada uma."
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
