<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Regras:
 * - INSS = 3% do salário bruto.
 * - Base tributável para IRT = Bruto - INSS.
 * - IRT (Tabela Progressiva 2026 Grupo A) = Parcela Fixa + (Taxa * (base - limite_inferior)).
 * - Descontos de ausências (férias aprovadas) continuam em `discounts`.
 * - `net_salary` = bruto - (descontos_ausencias + inss + irt).
 */
function calcIrt(float $taxable): float {
    $x = max(0, $taxable);

    // [min, max, fixed, rate, excess_over]
    $brackets = [
        [0,        150000,   0,       0.00, 0],
        [150000,   200000,   12500,   0.16, 150000],
        [200000,   300000,   31250,   0.18, 200000],
        [300000,   500000,   49250,   0.19, 300000],
        [500000,  1000000,   87250,   0.20, 500000],
        [1000000, 1500000,   187250,  0.21, 1000000],
        [1500000, 2000000,   292250,  0.22, 1500000],
        [2000000, 2500000,   402250,  0.23, 2000000],
        [2500000, 5000000,   517250,  0.24, 2500000],
        [5000000,10000000,   1117250, 0.245, 5000000],
        [10000000, PHP_FLOAT_MAX, 2342250, 0.25, 10000000],
    ];

    foreach ($brackets as [$min, $max, $fixed, $rate, $over]) {
        if ($x > $min && $x <= $max) {
            return (float)$fixed + (float)$rate * max(0, ($x - $over));
        }
    }

    return 0.0;
}

function createMonthlyPayroll(): void {
    global $pdo;

    $referenceDate  = new DateTime('first day of last month');
    $referenceMonth = $referenceDate->format('Y-m');
    $year  = (int)$referenceDate->format('Y');
    $month = (int)$referenceDate->format('m');

    $stmtCompanies = $pdo->query("SELECT id FROM companies WHERE is_active = 1");
    $companies = $stmtCompanies->fetchAll(PDO::FETCH_ASSOC);

    foreach ($companies as $company) {
        $company_id = (int)$company['id'];

        // funcionários ativos
        $stmtEmployees = $pdo->prepare("SELECT id, salary FROM employees WHERE status = 'ativo' AND company_id = ?");
        $stmtEmployees->execute([$company_id]);
        $employees = $stmtEmployees->fetchAll(PDO::FETCH_ASSOC);

        foreach ($employees as $employee) {
            $employee_id = (int)$employee['id'];
            $base_salary = (float)$employee['salary'];

            // ausências (férias aprovadas) no mês de referência
            $stmtAbsences = $pdo->prepare(
                "SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1),0) as total_absences 
                 FROM vacations 
                 WHERE employee_id = ? 
                   AND status = 'Aprovado' 
                   AND YEAR(start_date) = ? 
                   AND MONTH(start_date) = ?"
            );
            $stmtAbsences->execute([$employee_id, $year, $month]);
            $total_absences = (float)$stmtAbsences->fetchColumn();

            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $daily_salary  = $days_in_month > 0 ? ($base_salary / $days_in_month) : 0;
            $discounts_abs = $total_absences * $daily_salary;

            // Mantém adicionais existentes (se já houver folha do mês) para não zerar manualmente
            $stmtExisting = $pdo->prepare("SELECT bonuses, food_allowance, transport_allowance, vacation_subsidy_pct, thirteenth_subsidy_pct, commissions, sales FROM payroll WHERE employee_id=? AND reference_month=? LIMIT 1");
            $stmtExisting->execute([$employee_id, $referenceMonth]);
            $ex = $stmtExisting->fetch(PDO::FETCH_ASSOC) ?: [];

            $bonuses = (float)($ex['bonuses'] ?? 0);
            $food = (float)($ex['food_allowance'] ?? 0);
            $transport = (float)($ex['transport_allowance'] ?? 0);
            $vacPct = (int)($ex['vacation_subsidy_pct'] ?? 0);
            $t13Pct = (int)($ex['thirteenth_subsidy_pct'] ?? 0);
            $comm = (float)($ex['commissions'] ?? 0);
            $sales = (float)($ex['sales'] ?? 0);

            $vacSub = $base_salary * ($vacPct / 100);
            $t13Sub = $base_salary * ($t13Pct / 100);

            $gross = $base_salary + $bonuses + $food + $transport + $comm + $sales + $vacSub + $t13Sub;

            $inss = round($gross * 0.03, 2);
            $taxable = max(0, $gross - $inss);
            $irt = round(calcIrt($taxable), 2);

            // total de descontos para exibir de forma transparente
            $discounts = round($discounts_abs + $inss + $irt, 2);
            $net_salary = round($gross - $discounts, 2);

            // upsert
            $stmtCheck = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND reference_month = ? LIMIT 1");
            $stmtCheck->execute([$employee_id, $referenceMonth]);
            $existingId = $stmtCheck->fetchColumn();

            if ($existingId) {
                $stmtUpdate = $pdo->prepare(
                    "UPDATE payroll SET 
                        base_salary = :base_salary,
                        bonuses = :bonuses,
                        food_allowance = :food,
                        transport_allowance = :transport,
                        vacation_subsidy_pct = :vacPct,
                        thirteenth_subsidy_pct = :t13Pct,
                        commissions = :comm,
                        sales = :sales,
                        discounts = :discounts,
                        inss_value = :inss,
                        irt_value = :irt,
                        net_salary = :net,
                        status = 'Pendente'
                     WHERE employee_id = :employee_id AND reference_month = :ref"
                );
                $stmtUpdate->execute([
                    ':base_salary' => $base_salary,
                    ':bonuses' => $bonuses,
                    ':food' => $food,
                    ':transport' => $transport,
                    ':vacPct' => $vacPct,
                    ':t13Pct' => $t13Pct,
                    ':comm' => $comm,
                    ':sales' => $sales,
                    ':discounts' => $discounts,
                    ':inss' => $inss,
                    ':irt' => $irt,
                    ':net' => $net_salary,
                    ':employee_id' => $employee_id,
                    ':ref' => $referenceMonth,
                ]);
            } else {
                $stmtInsert = $pdo->prepare(
                    "INSERT INTO payroll (
                        employee_id, company_id, reference_month,
                        base_salary, bonuses, food_allowance, transport_allowance,
                        vacation_subsidy_pct, thirteenth_subsidy_pct,
                        commissions, sales,
                        discounts, inss_value, irt_value, net_salary, status
                     ) VALUES (
                        :employee_id, :company_id, :ref,
                        :base_salary, :bonuses, :food, :transport,
                        :vacPct, :t13Pct,
                        :comm, :sales,
                        :discounts, :inss, :irt, :net, 'Pendente'
                     )"
                );
                $stmtInsert->execute([
                    ':employee_id' => $employee_id,
                    ':company_id' => $company_id,
                    ':ref' => $referenceMonth,
                    ':base_salary' => $base_salary,
                    ':bonuses' => $bonuses,
                    ':food' => $food,
                    ':transport' => $transport,
                    ':vacPct' => $vacPct,
                    ':t13Pct' => $t13Pct,
                    ':comm' => $comm,
                    ':sales' => $sales,
                    ':discounts' => $discounts,
                    ':inss' => $inss,
                    ':irt' => $irt,
                    ':net' => $net_salary,
                ]);
            }
        }
    }

    echo "Folha de pagamento de {$referenceMonth} gerada/atualizada com sucesso para todas as empresas ativas.";
}

createMonthlyPayroll();
