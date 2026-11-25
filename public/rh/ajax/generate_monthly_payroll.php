<?php
/**
 * "Gerar folha do mês": cria a folha (Pendente) de cada funcionário activo que ainda não a tem
 * nesse mês, com o cálculo completo do motor (faltas, horas extra, subsídios, INSS, IRT, adiantamentos).
 * Com recalculate=1 também recalcula as folhas Pendentes já existentes, mantendo os valores
 * introduzidos à mão (bónus, comissões, descontos, …).
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$month = (string)($_POST['reference_month'] ?? '');
if (!rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Mês de referência inválido.'], 400);
}
if (rh_period_is_closed($pdo, $company_id, $month)) {
    rh_json(['success' => false, 'message' => 'O mês ' . rh_month_label($month) . ' está fechado.'], 422);
}
[$first, $last] = rh_month_bounds($month);

// Activos admitidos até ao fim do mês (e não desligados antes do início)
$stmt = $pdo->prepare("
    SELECT e.id FROM employees e
    WHERE e.company_id = ? AND e.status = 'ativo'
      AND (e.admission_date IS NULL OR e.admission_date <= ?)
      AND e.id NOT IN (SELECT employee_id FROM payroll WHERE company_id = ? AND reference_month = ?)
    ORDER BY e.name
");
$stmt->execute([$company_id, $last, $company_id, $month]);
$newIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$created = 0;
$recalculated = 0;
$errors = [];
foreach ($newIds as $employeeId) {
    try {
        $calc = rh_payroll_compute($pdo, $company_id, (int)$employeeId, $month);
        rh_payroll_store($pdo, $company_id, $calc, ['user_id' => $rh_user_id]);
        $created++;
    } catch (RuntimeException $e) {
        $errors[] = rh_error_message($e);
    }
}

if (($_POST['recalculate'] ?? '') === '1') {
    $stmt = $pdo->prepare("SELECT * FROM payroll WHERE company_id = ? AND reference_month = ? AND status = 'Pendente'");
    $stmt->execute([$company_id, $month]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            $calc = rh_payroll_compute($pdo, $company_id, (int)$row['employee_id'], $month, rh_payroll_overrides_from_row($row), (int)$row['id']);
            rh_payroll_store($pdo, $company_id, $calc, ['id' => (int)$row['id'], 'status' => 'Pendente', 'user_id' => $rh_user_id]);
            $recalculated++;
        } catch (RuntimeException $e) {
            $errors[] = rh_error_message($e);
        }
    }
}

$parts = [];
$parts[] = $created ? "$created folha(s) criada(s)." : 'Todos os funcionários activos já tinham folha neste mês.';
if ($recalculated) {
    $parts[] = "$recalculated folha(s) pendente(s) recalculada(s).";
}
rh_json(['success' => true, 'created' => $created, 'recalculated' => $recalculated, 'errors' => $errors, 'message' => implode(' ', $parts)]);
