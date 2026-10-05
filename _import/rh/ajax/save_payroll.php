<?php
require_once '../../../app/config/db.php';
session_start();

$company_id = $_SESSION['user']['company_id'];

$employee_id = $_POST['employee_id'] ?? null;
$reference_month = $_POST['reference_month'] ?? '';
$base_salary = floatval($_POST['base_salary'] ?? 0);
$bonuses = floatval($_POST['bonuses'] ?? 0);
$food_allowance = floatval($_POST['food_allowance'] ?? 0);
$transport_allowance = floatval($_POST['transport_allowance'] ?? 0);
$vacation_subsidy_pct = intval($_POST['vacation_subsidy_pct'] ?? 0);
$thirteenth_subsidy_pct = intval($_POST['thirteenth_subsidy_pct'] ?? 0);
$commissions = floatval($_POST['commissions'] ?? 0);
$sales = floatval($_POST['sales'] ?? 0);
$manual_discounts = floatval($_POST['discounts'] ?? 0);
$payment_date = $_POST['payment_date'] ?? null;
$status = $_POST['status'] ?? 'Pendente';
$id = $_POST['id'] ?? null;

// Cálculo de faltas
$yearMonth = explode('-', $reference_month);
$firstDay = $yearMonth[0] . '-' . $yearMonth[1] . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance 
                       WHERE employee_id = ? AND company_id = ? 
                       AND type = 'falta' 
                       AND date BETWEEN ? AND ?
                       AND date NOT IN (SELECT date FROM holidays WHERE company_id = ?)");
$stmt->execute([$employee_id, $company_id, $firstDay, $lastDay, $company_id]);
$total_faltas = $stmt->fetchColumn();

// Salário diário (30 dias padrão)
$valor_faltas = ($base_salary / 30) * $total_faltas;

// Base bruta (salário bruto) = base + adicionais
$vacation_subsidy = $base_salary * ($vacation_subsidy_pct / 100);
$thirteenth_subsidy = $base_salary * ($thirteenth_subsidy_pct / 100);
$total_adicionais = $bonuses + $food_allowance + $transport_allowance + $vacation_subsidy + $thirteenth_subsidy + $commissions;
$gross_salary = $base_salary + $total_adicionais;

// INSS (Segurança Social) = 3% sobre o bruto — encargo do trabalhador
$inss_value = $gross_salary * 0.03;

// INSS patronal = 8% sobre o bruto — encargo da empresa (Fase 2, item 1).
// Não é descontado do funcionário; é custo adicional da empresa, mostrado
// à parte nos relatórios (mapa INSS, dashboard de custo de folha).
$inss_employer_value = $gross_salary * 0.08;

// IRT (Tabela 2026 - Grupo A) calculado sobre (bruto - INSS)
$irt_base = max(0, $gross_salary - $inss_value);

function calcIrtGrupoA2026($base) {
    $b = (float)$base;
    // [min, max, fixed, rate]
    $brackets = [
        [0,        150000,    0,       0.00],
        [150001,   200000,    12500,   0.16],
        [200001,   300000,    31250,   0.18],
        [300001,   500000,    49250,   0.19],
        [500001,   1000000,   87250,   0.20],
        [1000001,  1500000,   187250,  0.21],
        [1500001,  2000000,   292250,  0.22],
        [2000001,  2500000,   402250,  0.23],
        [2500001,  5000000,   517250,  0.24],
        [5000001,  10000000,  1117250, 0.245],
        [10000001, PHP_INT_MAX,2342250, 0.25],
    ];

    // isento
    if ($b <= 150000) return 0.0;

    foreach ($brackets as $br) {
        [$min, $max, $fixed, $rate] = $br;
        if ($b >= $min && $b <= $max) {
            $lower = ($min === 150001) ? 150000 : ($min - 1);
            // regra: parcela fixa + taxa * (base - limite inferior do escalão)
            $excess = max(0, $b - $lower);
            return (float)$fixed + ($rate * $excess);
        }
    }
    return 0.0;
}

$irt_value = calcIrtGrupoA2026($irt_base);

// Total de descontos (manual + faltas + INSS + IRT)
$total_descontos = $manual_discounts + $valor_faltas + $inss_value + $irt_value;

// Salário líquido = bruto - descontos
$net_salary = $gross_salary - $total_descontos;

// IMPORTANTE: `discounts` guarda APENAS o valor manual digitado pelo utilizador
// (o que ele preenche no formulário). `total_discounts` guarda o total calculado
// (manual + faltas + INSS + IRT), usado nos relatórios/recibo/exportações.
// Antes, `discounts` guardava o total, e ao reabrir a folha para editar o
// formulário recarregava esse total como se fosse o valor manual, inflacionando
// os descontos a cada edição.
if ($id) {
    $stmt = $pdo->prepare("UPDATE payroll 
        SET reference_month = ?, base_salary = ?, bonuses = ?, food_allowance = ?, transport_allowance = ?, vacation_subsidy_pct = ?, thirteenth_subsidy_pct = ?, commissions = ?, sales = ?, discounts = ?, total_discounts = ?, inss_value = ?, inss_employer_value = ?, irt_value = ?, net_salary = ?, payment_date = ?, status = ? 
        WHERE id = ? AND company_id = ?");
    $stmt->execute([
        $reference_month,
        $base_salary,
        $bonuses,
        $food_allowance,
        $transport_allowance,
        $vacation_subsidy_pct,
        $thirteenth_subsidy_pct,
        $commissions,
        $sales,
        $manual_discounts,
        $total_descontos,
        $inss_value,
        $inss_employer_value,
        $irt_value,
        $net_salary,
        $payment_date,
        $status,
        $id,
        $company_id
    ]);
} else {
    $stmt = $pdo->prepare("INSERT INTO payroll 
        (employee_id, company_id, reference_month, base_salary, bonuses, food_allowance, transport_allowance, vacation_subsidy_pct, thirteenth_subsidy_pct, commissions, sales, discounts, total_discounts, inss_value, inss_employer_value, irt_value, net_salary, payment_date, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $employee_id,
        $company_id,
        $reference_month,
        $base_salary,
        $bonuses,
        $food_allowance,
        $transport_allowance,
        $vacation_subsidy_pct,
        $thirteenth_subsidy_pct,
        $commissions,
        $sales,
        $manual_discounts,
        $total_descontos,
        $inss_value,
        $inss_employer_value,
        $irt_value,
        $net_salary,
        $payment_date,
        $status
    ]);
}

echo 'ok';
