<?php
/**
 * Acções sobre várias folhas: approve (Pendente→Aprovado), pay (→Pago, com data), revert (Aprovado→Pendente),
 * delete (não pagas), recalc (Pendentes: recalcula com o motor, mantendo os valores manuais).
 * POST action, ids[] , payment_date (pay)
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$action = (string)($_POST['action'] ?? '');
$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
if (!$ids || !in_array($action, ['approve', 'pay', 'revert', 'delete', 'recalc'], true)) {
    rh_json(['success' => false, 'message' => 'Selecione pelo menos uma folha.'], 422);
}
$paymentDate = (string)($_POST['payment_date'] ?? '');
if ($action === 'pay' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
    $paymentDate = date('Y-m-d');
}

$marks = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT p.*, e.name AS employee_name FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.company_id = ? AND p.id IN ($marks)");
$stmt->execute([$company_id, ...$ids]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$done = 0;
$errors = [];
foreach ($rows as $row) {
    $name = $row['employee_name'];
    try {
        if (rh_period_is_closed($pdo, $company_id, $row['reference_month'])) {
            throw new RuntimeException('o mês ' . rh_month_label($row['reference_month']) . ' está fechado');
        }
        switch ($action) {
            case 'approve':
                if ($row['status'] !== 'Pendente') {
                    throw new RuntimeException('só folhas pendentes podem ser aprovadas');
                }
                $pdo->prepare("UPDATE payroll SET status = 'Aprovado', approved_at = NOW(), approved_by = ? WHERE id = ? AND company_id = ?")
                    ->execute([$rh_user_id ?: null, $row['id'], $company_id]);
                break;
            case 'pay':
                if ($row['status'] === 'Pago') {
                    throw new RuntimeException('já está paga');
                }
                $pdo->prepare("UPDATE payroll SET status = 'Pago', payment_date = ?, approved_at = COALESCE(approved_at, NOW()), approved_by = COALESCE(approved_by, ?) WHERE id = ? AND company_id = ?")
                    ->execute([$paymentDate, $rh_user_id ?: null, $row['id'], $company_id]);
                break;
            case 'revert':
                if ($row['status'] !== 'Aprovado') {
                    throw new RuntimeException('só folhas aprovadas voltam a pendente');
                }
                $pdo->prepare("UPDATE payroll SET status = 'Pendente', approved_at = NULL, approved_by = NULL WHERE id = ? AND company_id = ?")
                    ->execute([$row['id'], $company_id]);
                break;
            case 'delete':
                rh_payroll_delete($pdo, $company_id, (int)$row['id']);
                break;
            case 'recalc':
                if ($row['status'] !== 'Pendente') {
                    throw new RuntimeException('só folhas pendentes podem ser recalculadas');
                }
                $calc = rh_payroll_compute($pdo, $company_id, (int)$row['employee_id'], $row['reference_month'], rh_payroll_overrides_from_row($row), (int)$row['id']);
                rh_payroll_store($pdo, $company_id, $calc, ['id' => (int)$row['id'], 'status' => 'Pendente', 'user_id' => $rh_user_id, 'payment_date' => $row['payment_date']]);
                break;
        }
        $done++;
    } catch (RuntimeException $e) {
        $errors[] = "$name: " . rh_error_message($e);
    }
}

$verb = ['approve' => 'aprovada(s)', 'pay' => 'marcada(s) como paga(s)', 'revert' => 'devolvida(s) a pendente', 'delete' => 'eliminada(s)', 'recalc' => 'recalculada(s)'][$action];
rh_json(['success' => $done > 0 || !$errors, 'done' => $done, 'errors' => $errors, 'message' => "$done folha(s) $verb."]);
