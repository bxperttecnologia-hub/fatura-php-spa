<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription.php';
require_once __DIR__ . '/../../../app/config/appypay.php';

// Webhook AppyPay
// OBS: como o formato exato do payload pode variar, este endpoint tenta identificar a transação
// por merchantTransactionId e aplicar pagamento. Ajustamos quando você mandar um exemplo real.

header('Content-Type: application/json; charset=utf-8');

function out(array $p, int $code=200): void {
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

// Segurança simples opcional
if (defined('APPYPAY_WEBHOOK_TOKEN') && APPYPAY_WEBHOOK_TOKEN !== '') {
  $hdr = $_SERVER['HTTP_X_APPYPAY_WEBHOOK_TOKEN'] ?? '';
  if ($hdr !== APPYPAY_WEBHOOK_TOKEN) {
    out(['ok'=>false,'error'=>'unauthorized'], 401);
  }
}

$raw = file_get_contents('php://input');
$payload = $raw ? json_decode($raw, true) : null;
if (!is_array($payload)) {
  out(['ok'=>false,'error'=>'invalid_json'], 400);
}

// tenta pegar campos comuns
$merchant = $payload['merchantTransactionId']
  ?? ($payload['data']['merchantTransactionId'] ?? null)
  ?? ($payload['merchant_transaction_id'] ?? null);

$status = strtolower((string)($payload['status'] ?? ($payload['data']['status'] ?? '')));
$chargeId = $payload['id'] ?? ($payload['chargeId'] ?? ($payload['data']['id'] ?? null));

// Alguns webhooks podem vir com responseStatus
$rs = $payload['responseStatus'] ?? ($payload['data']['responseStatus'] ?? null);
if (is_array($rs)) {
  $status = strtolower((string)($rs['status'] ?? $status));
}

if (!$merchant) {
  out(['ok'=>false,'error'=>'merchantTransactionId_missing'], 400);
}

// encontra order
$stmt = $pdo->prepare("SELECT * FROM subscription_orders WHERE merchant_transaction_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([(string)$merchant]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
  out(['ok'=>false,'error'=>'order_not_found'], 404);
}

// Define condição de pago
$isPaid = in_array($status, ['paid','succeeded','success','completed','approved'], true);
if (!$isPaid && is_array($rs)) {
  $successful = (bool)($rs['successful'] ?? false);
  $st2 = strtolower((string)($rs['status'] ?? ''));
  if ($successful && in_array($st2, ['success','paid','completed','approved','succeeded'], true)) {
    $isPaid = true;
  }
}

// registra retorno bruto se houver colunas
try {
  $st = $pdo->prepare("UPDATE subscription_orders SET gateway='APPYPAY', gateway_status=?, gateway_charge_id=?, gateway_response_raw=? WHERE id=?");
  $st->execute([$status ?: null, $chargeId, $raw, (int)$order['id']]);
} catch (Throwable $e) {
  // sem colunas ainda
}

if (!$isPaid) {
  out(['ok'=>true,'received'=>true,'paid'=>false]);
}

$company_id = (int)$order['company_id'];
$months = (int)($order['period_months'] ?? 3);

$c = subscription_get_company($pdo, $company_id);
$currentPlan = (string)($c['plan_code'] ?? '');
$planCodeToApply = (string)($order['plan_code'] ?? '');
if ($planCodeToApply === '') {
  $planCodeToApply = $currentPlan !== '' ? $currentPlan : 'BXPERT_BAZA';
}

// Regra:
// - Se for troca de plano (upgrade/downgrade), reinicia a contagem a partir de hoje (zera dias restantes)
// - Se for renovação do mesmo plano, estende a partir do maior entre hoje e vencimento atual
$todayTs = strtotime(date('Y-m-d'));
$startTs = $todayTs;
if ($currentPlan !== '' && $currentPlan === $planCodeToApply) {
  $base = $c['plan_expires_at'] ?? null;
  $baseTs = $base ? strtotime($base) : 0;
  $startTs = max($todayTs, $baseTs);
}
$startDate = date('Y-m-d', $startTs);
$newExp = date('Y-m-d', strtotime("$startDate +$months months"));

$pdo->beginTransaction();
try {
  // marca order paid (idempotente)
  $st = $pdo->prepare("UPDATE subscription_orders SET status='paid', paid_at=NOW() WHERE id=? AND status<>'paid'");
  $st->execute([(int)$order['id']]);

  $st = $pdo->prepare("UPDATE companies SET plan_code=?, plan_started_at=?, plan_expires_at=?, plan_status='active', is_active=1 WHERE id=?");
  $st->execute([$planCodeToApply, date('Y-m-d'), $newExp, $company_id]);

  $pdo->commit();
} catch (Throwable $e) {
  $pdo->rollBack();
  out(['ok'=>false,'error'=>$e->getMessage()], 500);
}

out(['ok'=>true,'paid'=>true,'company_id'=>$company_id,'new_expires_at'=>$newExp]);
