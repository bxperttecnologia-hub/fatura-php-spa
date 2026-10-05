<?php

/**
 * Cálculo do IRT (Grupo A, tabela 2026) — mesma lógica usada em save_payroll.php
 */
function calcIrtGrupoA2026($base)
{
    $b = (float)$base;

    // [min, max, fixed, rate]
    $brackets = [
        [0,        150000,     0,        0.00],
        [150001,   200000,     12500,    0.16],
        [200001,   300000,     31250,    0.18],
        [300001,   500000,     49250,    0.19],
        [500001,   1000000,    87250,    0.20],
        [1000001,  1500000,    187250,   0.21],
        [1500001,  2000000,    292250,   0.22],
        [2000001,  2500000,    402250,   0.23],
        [2500001,  5000000,    517250,   0.24],
        [5000001,  10000000,   1117250,  0.245],
        [10000001, PHP_INT_MAX, 2342250, 0.25],
    ];

    if ($b <= 150000) return 0.0;

    foreach ($brackets as $br) {
        [$min, $max, $fixed, $rate] = $br;
        if ($b >= $min && $b <= $max) {
            $lower = ($min === 150001) ? 150000 : ($min - 1);
            $excess = max(0, $b - $lower);
            return (float)$fixed + ($rate * $excess);
        }
    }

    return 0.0;
}

/**
 * Calcula a folha de salário de UM colaborador para um mês de referência.
 * Não grava nada na base de dados — apenas devolve os valores calculados,
 * para poderem ser mostrados num resumo antes de o utilizador aprovar.
 *
 * $overrides permite substituir valores individuais vindos de um formulário
 * (bónus, comissões, vendas, descontos manuais, etc.) — usado tanto no
 * registo individual como na emissão em lote.
 *
 * Lança Exception se o colaborador não for encontrado ou não tiver cargo associado.
 */
function calculate_employee_payroll(PDO $pdo, int $company_id, int $employee_id, string $reference_month, array $overrides = []): array
{
    $stmt = $pdo->prepare("
        SELECT e.id, e.name, e.salary_base, e.iban, e.status,
               p.food_allowance, p.transport_allowance,
               p.vacation_subsidy_pct, p.thirteenth_subsidy_pct
        FROM employees e
        JOIN positions p ON p.name = e.position
        WHERE e.id = ? AND e.company_id = ?
    ");
    $stmt->execute([$employee_id, $company_id]);
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$emp) {
        throw new Exception("Colaborador #{$employee_id} não encontrado ou sem cargo associado.");
    }

    $base_salary            = (float)($overrides['base_salary'] ?? $emp['salary_base']);
    $bonuses                = (float)($overrides['bonuses'] ?? 0);
    $food_allowance         = (float)($overrides['food_allowance'] ?? $emp['food_allowance']);
    $transport_allowance    = (float)($overrides['transport_allowance'] ?? $emp['transport_allowance']);
    $vacation_subsidy_pct   = (int)($overrides['vacation_subsidy_pct'] ?? $emp['vacation_subsidy_pct']);
    $thirteenth_subsidy_pct = (int)($overrides['thirteenth_subsidy_pct'] ?? $emp['thirteenth_subsidy_pct']);
    $commissions            = (float)($overrides['commissions'] ?? 0);
    $sales                  = (float)($overrides['sales'] ?? 0);
    $manual_discounts       = (float)($overrides['discounts'] ?? 0);

    // Faltas no mês
    $yearMonth = explode('-', $reference_month);
    $firstDay  = $yearMonth[0] . '-' . $yearMonth[1] . '-01';
    $lastDay   = date('Y-m-t', strtotime($firstDay));

    $stmtF = $pdo->prepare("
        SELECT COUNT(*) FROM attendance
        WHERE employee_id = ? AND company_id = ?
        AND type = 'falta'
        AND date BETWEEN ? AND ?
    ");
    $stmtF->execute([$employee_id, $company_id, $firstDay, $lastDay]);
    $total_faltas = (int)$stmtF->fetchColumn();

    $valor_faltas = ($base_salary / 30) * $total_faltas;

    $vacation_subsidy   = $base_salary * ($vacation_subsidy_pct / 100);
    $thirteenth_subsidy = $base_salary * ($thirteenth_subsidy_pct / 100);
    $total_adicionais   = $bonuses + $food_allowance + $transport_allowance
        + $vacation_subsidy + $thirteenth_subsidy + $commissions;
    $gross_salary = $base_salary + $total_adicionais;

    $inss_value = $gross_salary * 0.03;

    $irt_base  = max(0, $gross_salary - $inss_value);
    $irt_value = calcIrtGrupoA2026($irt_base);

    $total_descontos = $manual_discounts + $valor_faltas + $inss_value + $irt_value;
    $net_salary       = $gross_salary - $total_descontos;

    return [
        'employee_id'             => $employee_id,
        'employee_name'           => $emp['name'],
        'iban'                    => $emp['iban'],
        'reference_month'         => $reference_month,
        'base_salary'             => round($base_salary, 2),
        'bonuses'                 => round($bonuses, 2),
        'food_allowance'          => round($food_allowance, 2),
        'transport_allowance'     => round($transport_allowance, 2),
        'vacation_subsidy_pct'    => $vacation_subsidy_pct,
        'thirteenth_subsidy_pct'  => $thirteenth_subsidy_pct,
        'commissions'             => round($commissions, 2),
        'sales'                   => round($sales, 2),
        'total_faltas'            => $total_faltas,
        'valor_faltas'            => round($valor_faltas, 2),
        'gross_salary'            => round($gross_salary, 2),
        'inss_value'              => round($inss_value, 2),
        'irt_value'               => round($irt_value, 2),
        'discounts'               => round($total_descontos, 2),
        'manual_discounts'        => round($manual_discounts, 2),
        'net_salary'              => round($net_salary, 2),
    ];
}
