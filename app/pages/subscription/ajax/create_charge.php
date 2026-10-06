<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription.php';
require_once __DIR__ . '/../../../app/helpers/appypay.php';

session_start();
header('Content-Type: application/json');

$company_id = (int)($_SESSION['user']['company_id'] ?? 0); // sempre pela empresa ativa na navbar (sessão)
$method = strtoupper(trim((string)($_POST['method'] ?? ''))); // REF|GPO
$phone = trim((string)($_POST['phone'] ?? ''));
$requestedPlan = strtoupper(trim((string)($_POST['plan_code'] ?? '')));

if (!$company_id) {
  echo json_encode(['success' => false, 'message' => 'Empresa inválida']);
  exit;
}
if (!in_array($method, ['REF','GPO'], true)) {
  echo json_encode(['success' => false, 'message' => 'Método inválido']);
  exit;
}
if ($method === 'GPO') {
  $digits = preg_replace('/\D+/', '', $phone);
  if (strlen($digits) < 8) {
    echo json_encode(['success' => false, 'message' => 'Informe um número de telefone válido para GPO.']);
    exit;
  }
  $phone = $digits;
}

// Só owner/admin pode
$requester_id = (int)($_SESSION['user']['id'] ?? 0);
$stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1");
$stmt->execute([$requester_id, $company_id]);
$role = $stmt->fetchColumn();
if (!$role) {
  echo json_encode(['success' => false, 'message' => 'Acesso negado']);
  exit;
}

if (!appypay_is_configured()) {
  echo json_encode(['success' => false, 'message' => 'Gateway AppyPay não configurado no servidor.']);
  exit;
}

$c = subscription_get_company($pdo, $company_id);
$plans = subscription_plans();

$planCode = $c['plan_code'] ?? 'BXPERT_BAZA';
if ($requestedPlan !== '' && isset($plans[$requestedPlan])) {
  $planCode = $requestedPlan;
}

$plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
$amount = (float)($plan['price_quarter'] ?? 0);

if ($amount <= 0) {
  echo json_encode(['success' => false, 'message' => 'Valor inválido para este plano.']);
  exit;
}

// Cria pedido pendente
$stmt = $pdo->prepare("INSERT INTO subscription_orders (company_id, plan_code, period_months, amount, status) VALUES (?, ?, 3, ?, 'pending')");
$stmt->execute([$company_id, $planCode, $amount]);
$orderId = (int)$pdo->lastInsertId();

// AppyPay exige MerchantTransactionId alfanumérico (sem símbolos) e com no máximo 15 caracteres
// Formato: S + (orderId em base36 com 4 chars) + (mdHis = 10 chars) => 15
$base36 = strtoupper(base_convert((string)$orderId, 10, 36));
$base36 = str_pad($base36, 4, '0', STR_PAD_LEFT);
$merchantTransactionId = 'S' . $base36 . date('mdHis');

$payload = [
  'amount' => $amount,
  'currency' => 'AOA',
  'description' => 'RENOVACAO_' . $planCode,
  'merchantTransactionId' => $merchantTransactionId,
  'paymentMethod' => appypay_method_id($method),
];

if ($method === 'GPO') {
  $payload['paymentInfo'] = [
    'phoneNumber' => $phone,
  ];
}

try {
  $res = appypay_create_charge($payload);
  $code = (int)$res['code'];
  $data = $res['data'];

  // extrai campos
  $chargeId = $data['id'] ?? $data['chargeId'] ?? ($data['data']['id'] ?? null);
  $status = $data['status'] ?? ($data['chargeStatus'] ?? null) ?? ($data['responseStatus']['status'] ?? null);

  $respStatus = $data['responseStatus'] ?? null;
  $successful = is_array($respStatus) ? ($respStatus['successful'] ?? null) : null;

  $ref = is_array($respStatus) ? ($respStatus['reference'] ?? null) : null;
  $refEntity = is_array($ref) ? ($ref['entity'] ?? null) : null;
  $refNumber = is_array($ref) ? ($ref['referenceNumber'] ?? null) : null;
  $refDue = is_array($ref) ? ($ref['dueDate'] ?? null) : null;

  // salva no pedido (colunas podem não existir ainda -> tenta e ignora se falhar)
  try {
    $payRef = null;
    if ($method === 'REF' && ($refEntity || $refNumber || $refDue)) {
      $payRef = json_encode(['entity'=>$refEntity,'referenceNumber'=>$refNumber,'dueDate'=>$refDue], JSON_UNESCAPED_UNICODE);
    }
    $st = $pdo->prepare("UPDATE subscription_orders SET gateway='APPYPAY', payment_method=?, merchant_transaction_id=?, gateway_charge_id=?, gateway_status=?, gateway_response_raw=?, payment_ref=? WHERE id=?");
    $st->execute([$method, $merchantTransactionId, $chargeId, $status, $res['raw'], $payRef, $orderId]);
  } catch (Throwable $e) {
    // sem migração ainda
  }

  if ($code >= 400) {
    echo json_encode(['success' => false, 'message' => 'Falha ao gerar cobrança no AppyPay.', 'details' => $data, 'order_id' => $orderId]);
    exit;
  }

  // Para GPO, o AppyPay pode responder HTTP 200 mas com successful=false (pagamento recusado)
  if ($method === 'GPO' && $successful === false) {
    $srcDetails = is_array($respStatus) ? ($respStatus['sourceDetails'] ?? null) : null;
    $sdCode = is_array($srcDetails) ? (string)($srcDetails['code'] ?? '') : '';

    $msg = is_array($respStatus) ? (string)($respStatus['message'] ?? '') : '';
    $sdMsg = is_array($srcDetails) ? (string)($srcDetails['message'] ?? '') : '';

    // Mensagem amigável para caso clássico: telefone não activo/cadastrado no Multicaixa Express
    if ($sdCode === 'EPMS_907') {
      $msg = 'Pagamento recusado: o número de telemóvel não está ativo/cadastrado no Multicaixa Express.';
    } elseif ($sdMsg !== '') {
      $msg = $msg !== '' ? ($msg . ' (' . $sdMsg . ')') : $sdMsg;
    } elseif ($msg === '') {
      $msg = 'Pagamento recusado.';
    }

    // marca pedido como cancelado (não vai ser pago)
    try {
      $pdo->prepare("UPDATE subscription_orders SET status='canceled' WHERE id=? LIMIT 1")->execute([$orderId]);
    } catch (Throwable $e) {}

    echo json_encode(['success' => false, 'message' => $msg, 'details' => $data, 'order_id' => $orderId, 'status' => $status]);
    exit;
  }

  echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'merchantTransactionId' => $merchantTransactionId,
    'method' => $method,
    'charge_id' => $chargeId,
    'status' => $status,
    'ref' => [
      'entity' => $refEntity,
      'referenceNumber' => $refNumber,
      'dueDate' => $refDue,
    ],
    'charge' => $data,
  ]);

} catch (Throwable $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage(), 'order_id' => $orderId]);
}
