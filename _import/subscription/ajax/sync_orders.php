<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription.php';
require_once __DIR__ . '/../../../app/helpers/appypay.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $p, int $code=200): void {
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);
if (!$company_id) out(['success'=>false,'message'=>'Empresa inválida (sessão)'], 400);

$requester_id = (int)($_SESSION['user']['id'] ?? 0);
$stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1");
$stmt->execute([$requester_id, $company_id]);
$role = $stmt->fetchColumn();
if (!$role) out(['success'=>false,'message'=>'Acesso negado'], 403);

// pega até 10 pendentes
$st = $pdo->prepare("SELECT * FROM subscription_orders WHERE company_id=? AND status='pending' AND gateway='APPYPAY' AND gateway_charge_id IS NOT NULL ORDER BY id DESC LIMIT 10");
$st->execute([$company_id]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

$updated = 0;
$paid = 0;
$errors = [];

foreach ($orders as $o) {
  $oid = (int)$o['id'];
  $chargeId = (string)($o['gateway_charge_id'] ?? '');
  if ($chargeId === '') continue;

  try {
    $res = appypay_get_charge($chargeId);
    $code = (int)$res['code'];
    $data = $res['data'];

    $rs = $data['responseStatus'] ?? null;
    $successful = is_array($rs) ? (bool)($rs['successful'] ?? false) : false;
    $status = is_array($rs) ? (string)($rs['status'] ?? '') : '';

    // salva status bruto
    $u = $pdo->prepare("UPDATE subscription_orders SET gateway_status=?, gateway_response_raw=? WHERE id=?");
    $u->execute([$status !== '' ? $status : null, $res['raw'], $oid]);
    $updated++;

    // considera pago
    $isPaid = $successful && in_array(strtolower($status), ['success','paid','completed','approved','succeeded'], true);

    if ($isPaid) {
      $company_id2 = (int)$o['company_id'];
      $months = (int)($o['period_months'] ?? 3);
      $planCode = (string)($o['plan_code'] ?? '');

      $c = subscription_get_company($pdo, $company_id2);
      $currentPlan = (string)($c['plan_code'] ?? '');

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
      try {
        $u2 = $pdo->prepare("UPDATE subscription_orders SET status='paid', paid_at=NOW() WHERE id=? AND status<>'paid'");
        $u2->execute([$oid]);

        // aplica plano pago
        $u3 = $pdo->prepare("UPDATE companies SET plan_code=?, plan_started_at=?, plan_expires_at=?, plan_status='active', is_active=1 WHERE id=?");
        $u3->execute([$planCode, date('Y-m-d'), $newExp, $company_id2]);

        $pdo->commit();
        $paid++;
      } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
      }
    }

  } catch (Throwable $e) {
    $errors[] = ['order_id'=>$oid,'error'=>$e->getMessage()];
  }
}

// Reconciliação: se alguém marcou manualmente como paid no BD,
// garante que o plano atual da empresa acompanhe o último pagamento aprovado.
try {
  $st2 = $pdo->prepare("SELECT plan_code FROM subscription_orders WHERE company_id=? AND status='paid' ORDER BY paid_at DESC, id DESC LIMIT 1");
  $st2->execute([$company_id]);
  $lastPlan = (string)($st2->fetchColumn() ?: '');
  if ($lastPlan !== '') {
    $cNow = subscription_get_company($pdo, $company_id);
    $curPlan = (string)($cNow['plan_code'] ?? '');
    if ($curPlan !== $lastPlan) {
      $u = $pdo->prepare("UPDATE companies SET plan_code=?, plan_status='active', is_active=1 WHERE id=?");
      $u->execute([$lastPlan, $company_id]);
    }
  }
} catch (Throwable $e) {
  // ignora
}

out(['success'=>true,'checked'=>count($orders),'updated'=>$updated,'paid'=>$paid,'errors'=>$errors]);
