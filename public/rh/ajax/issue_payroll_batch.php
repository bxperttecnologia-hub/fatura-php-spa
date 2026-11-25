<?php
/**
 * Emissão em lote: grava a folha do mês para os funcionários escolhidos (salta quem já tem folha).
 * POST reference_month, employee_ids[], approved=1, status (Pendente|Aprovado|Pago), payment_date, notes,
 *      bonuses / other_earnings / discounts (opcionais, iguais para todos).
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$month = (string)($_POST['reference_month'] ?? '');
$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['employee_ids'] ?? [])))));
if (!rh_valid_month($month) || !$ids) {
    rh_json(['success' => false, 'message' => 'Selecione o mês e pelo menos um funcionário.'], 400);
}
if (($_POST['approved'] ?? '') !== '1') {
    rh_json(['success' => false, 'message' => 'É necessário confirmar o resumo antes de emitir a folha.'], 400);
}
if (rh_period_is_closed($pdo, $company_id, $month)) {
    rh_json(['success' => false, 'message' => 'O mês ' . rh_month_label($month) . ' está fechado. Reabra-o para lançar folhas.'], 422);
}
$status = in_array($_POST['status'] ?? '', ['Pendente', 'Aprovado', 'Pago'], true) ? $_POST['status'] : 'Pendente';
$paymentDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['payment_date'] ?? '')) ? $_POST['payment_date'] : null;
if ($status === 'Pago' && !$paymentDate) {
    $paymentDate = date('Y-m-d');
}
$notes = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 1000) ?: null;
$overrides = [];
foreach (['bonuses', 'other_earnings', 'discounts'] as $k) {
    if (isset($_POST[$k]) && $_POST[$k] !== '') {
        $overrides[$k] = $_POST[$k];
    }
}

$check = $pdo->prepare('SELECT COUNT(*) FROM payroll WHERE company_id = ? AND employee_id = ? AND reference_month = ?');
$issued = 0;
$skipped = 0;
$errors = [];
foreach ($ids as $employeeId) {
    try {
        $check->execute([$company_id, $employeeId, $month]);
        if ($check->fetchColumn()) {
            $skipped++;
            continue;
        }
        $calc = rh_payroll_compute($pdo, $company_id, $employeeId, $month, $overrides);
        rh_payroll_store($pdo, $company_id, $calc, ['status' => $status, 'payment_date' => $paymentDate, 'notes' => $notes, 'user_id' => $rh_user_id]);
        $issued++;
    } catch (Throwable $e) {
        $errors[] = rh_error_message($e, 'issue_payroll_batch');
    }
}
$msg = "$issued folha(s) gravada(s) como $status" . ($skipped ? "; $skipped já tinham folha neste mês" : '') . '.';
rh_json(['success' => $issued > 0 || !$errors, 'done' => $issued, 'issued_count' => $issued, 'skipped' => $skipped, 'errors' => $errors, 'message' => $msg]);
