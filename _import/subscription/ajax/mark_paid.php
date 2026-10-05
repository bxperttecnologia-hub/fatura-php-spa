<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
session_start();
header('Content-Type: application/json');

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);
if (!$company_id) {
  echo json_encode(['success' => false, 'message' => 'Empresa inválida (sessão)']);
  exit;
}

$requester_id = (int)($_SESSION['user']['id'] ?? 0);
$stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1");
$stmt->execute([$requester_id, $company_id]);
$role = $stmt->fetchColumn();
if (!$role) {
  echo json_encode(['success' => false, 'message' => 'Acesso negado']);
  exit;
}

// pega último pedido pendente
$stmt = $pdo->prepare("SELECT * FROM subscription_orders WHERE company_id = ? AND status='pending' ORDER BY id DESC LIMIT 1");
$stmt->execute([$company_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
  echo json_encode(['success' => false, 'message' => 'Nenhum pedido pendente encontrado.']);
  exit;
}

$months = (int)($order['period_months'] ?? 3);

$c = subscription_get_company($pdo, $company_id);
$currentPlan = (string)($c['plan_code'] ?? '');
$planCode = (string)($order['plan_code'] ?? '');
if ($planCode === '') {
  $planCode = $currentPlan !== '' ? $currentPlan : 'BXPERT_BAZA';
}

// Regra:
// - Se for troca de plano (upgrade/downgrade), reinicia a contagem a partir de hoje (zera dias restantes)
// - Se for renovação do mesmo plano, estende a partir do maior entre hoje e vencimento atual
$todayTs = strtotime(date('Y-m-d'));
$startTs = $todayTs;
if ($currentPlan !== '' && $currentPlan === $planCode) {
  $base = $c['plan_expires_at'] ?? null;
  $baseTs = $base ? strtotime($base) : 0;
  $startTs = max($todayTs, $baseTs);
}
$startDate = date('Y-m-d', $startTs);
$newExp = date('Y-m-d', strtotime("$startDate +$months months"));

$pdo->beginTransaction();

$stmt = $pdo->prepare("UPDATE subscription_orders SET status='paid', paid_at=NOW() WHERE id = ?");
$stmt->execute([(int)$order['id']]);

$stmt = $pdo->prepare("UPDATE companies SET plan_code=?, plan_started_at=?, plan_expires_at=?, plan_status='active', is_active=1 WHERE id=?");
$stmt->execute([$planCode, date('Y-m-d'), $newExp, $company_id]);

$pdo->commit();

echo json_encode(['success' => true, 'new_expires_at' => $newExp]);
