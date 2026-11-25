<?php
/**
 * Fecho do mês de processamento. action=close: só fecha se todas as folhas do mês estiverem pagas
 * (com force=1 fecha mesmo assim). action=reopen: reabre. Com o mês fechado, nenhuma folha desse
 * mês pode ser criada, alterada ou eliminada.
 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

$month = (string)($_POST['reference_month'] ?? '');
$action = (string)($_POST['action'] ?? '');
if (!rh_valid_month($month) || !in_array($action, ['close', 'reopen'], true)) {
    rh_json(['success' => false, 'message' => 'Pedido inválido.'], 422);
}

if ($action === 'close') {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status <> 'Pago') AS open FROM payroll WHERE company_id = ? AND reference_month = ?");
    $stmt->execute([$company_id, $month]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!(int)$c['total']) {
        rh_json(['success' => false, 'message' => 'Não há folhas neste mês.'], 422);
    }
    if ((int)$c['open'] > 0 && ($_POST['force'] ?? '') !== '1') {
        rh_json(['success' => false, 'needs_force' => true, 'message' => (int)$c['open'] . ' folha(s) ainda não estão pagas.'], 409);
    }
    $pdo->prepare("INSERT INTO rh_payroll_periods (company_id, reference_month, status, closed_at, closed_by) VALUES (?, ?, 'fechado', NOW(), ?)
                   ON DUPLICATE KEY UPDATE status = 'fechado', closed_at = NOW(), closed_by = VALUES(closed_by)")
        ->execute([$company_id, $month, $rh_user_id ?: null]);
    rh_json(['success' => true, 'message' => rh_month_label($month) . ' fechado.']);
}

$pdo->prepare("UPDATE rh_payroll_periods SET status = 'aberto' WHERE company_id = ? AND reference_month = ?")->execute([$company_id, $month]);
rh_json(['success' => true, 'message' => rh_month_label($month) . ' reaberto.']);
