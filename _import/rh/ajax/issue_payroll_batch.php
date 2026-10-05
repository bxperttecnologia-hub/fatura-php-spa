<?php
require_once '../../../app/config/db.php';
require_once '../helpers/payroll_calc.php';
session_start();

$company_id       = (int)($_SESSION['user']['company_id'] ?? 0);
$reference_month  = $_POST['reference_month'] ?? '';
$employee_ids     = $_POST['employee_ids'] ?? [];
$approved         = $_POST['approved'] ?? '';

if (!$company_id || !$reference_month || !is_array($employee_ids) || empty($employee_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione o mês e pelo menos um colaborador.']);
    exit;
}

// A folha só pode ser emitida depois de o utilizador ter confirmado o resumo.
if ($approved !== '1') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'É necessário aprovar o resumo antes de emitir a folha.']);
    exit;
}

// Quem já tem folha lançada neste mês não é duplicado
$stmtExisting = $pdo->prepare("
    SELECT employee_id FROM payroll
    WHERE company_id = ? AND reference_month = ?
");
$stmtExisting->execute([$company_id, $reference_month]);
$existingIds = array_map('intval', $stmtExisting->fetchAll(PDO::FETCH_COLUMN));

$issued = [];
$skipped = [];
$errors = [];

try {
    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare("INSERT INTO payroll
        (employee_id, company_id, reference_month, base_salary, bonuses, food_allowance, transport_allowance, vacation_subsidy_pct, thirteenth_subsidy_pct, commissions, sales, discounts, inss_value, irt_value, net_salary, payment_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'Pendente')");

    foreach ($employee_ids as $employeeId) {
        $employeeId = (int)$employeeId;

        if (in_array($employeeId, $existingIds, true)) {
            $skipped[] = $employeeId;
            continue;
        }

        try {
            // Recalcula no servidor - nunca confiar em valores vindos do browser
            $calc = calculate_employee_payroll($pdo, $company_id, $employeeId, $reference_month);

            $insertStmt->execute([
                $employeeId,
                $company_id,
                $calc['reference_month'],
                $calc['base_salary'],
                $calc['bonuses'],
                $calc['food_allowance'],
                $calc['transport_allowance'],
                $calc['vacation_subsidy_pct'],
                $calc['thirteenth_subsidy_pct'],
                $calc['commissions'],
                $calc['sales'],
                $calc['discounts'],
                $calc['inss_value'],
                $calc['irt_value'],
                $calc['net_salary'],
            ]);

            $issued[] = $employeeId;
        } catch (Exception $e) {
            $errors[] = "Colaborador #{$employeeId}: " . $e->getMessage();
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'issued_count'  => count($issued),
        'skipped_count' => count($skipped),
        'errors'        => $errors,
        'message'       => count($issued) . ' folha(s) emitida(s) com sucesso.'
            . (count($skipped) ? ' ' . count($skipped) . ' colaborador(es) já tinham folha neste mês e foram ignorados.' : ''),
    ]);
} catch (Exception $e) {
    $pdo->rollBack();

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao emitir a folha: ' . $e->getMessage()]);
}
