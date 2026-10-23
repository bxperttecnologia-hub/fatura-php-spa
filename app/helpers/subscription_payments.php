<?php

declare(strict_types=1);

require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/appypay.php';

function subscription_payment_out(array $payload, int $status = 200): never
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

function subscription_payment_check_csrf(): void
{
  $expected = (string)($_SESSION['csrf'] ?? '');
  $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
  if (!subscription_payment_csrf_is_valid($expected, $provided)) {
    subscription_payment_out(['success' => false, 'message' => 'Pedido inválido. Actualize a página e tente novamente.'], 403);
  }
}

function subscription_payment_csrf_is_valid(string $expected, string $provided): bool
{
  return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
}

function subscription_payment_role_can_manage(string|false $role): bool
{
  return in_array($role, ['owner', 'admin'], true);
}

function subscription_payment_company_role(PDO $pdo, int $userId, int $companyId): string|false
{
  if ($userId <= 0 || $companyId <= 0) {
    return false;
  }

  $stmt = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
  $stmt->execute([$userId, $companyId]);
  $role = $stmt->fetchColumn();
  return is_string($role) ? $role : false;
}

function subscription_payment_require_member(PDO $pdo, int $userId, int $companyId): void
{
  if (subscription_payment_company_role($pdo, $userId, $companyId) === false) {
    subscription_payment_out(['success' => false, 'message' => 'Acesso negado.'], 403);
  }
}

function subscription_payment_require_manager(PDO $pdo, int $userId, int $companyId): void
{
  if ($userId <= 0 || $companyId <= 0) {
    subscription_payment_out(['success' => false, 'message' => 'Sessão inválida. Inicie sessão novamente.'], 401);
  }

  if (!subscription_payment_role_can_manage(subscription_payment_company_role($pdo, $userId, $companyId))) {
    subscription_payment_out(['success' => false, 'message' => 'Apenas proprietários e administradores podem gerir a subscrição.'], 403);
  }
}

function subscription_payment_quote(array $plans, string $currentPlanCode, string $requestedPlanCode): array
{
  if (!isset($plans[$currentPlanCode])) {
    $currentPlanCode = 'BXPERT_BAZA';
  }
  $planCode = $requestedPlanCode !== '' ? $requestedPlanCode : $currentPlanCode;
  if (!isset($plans[$planCode])) {
    throw new InvalidArgumentException('Plano inválido.');
  }

  $amount = (float)($plans[$planCode]['price'] ?? 0);
  if ($amount <= 0) {
    throw new InvalidArgumentException('Preço inválido.');
  }

  $currentRank = array_search($currentPlanCode, array_keys($plans), true);
  $targetRank = array_search($planCode, array_keys($plans), true);
  if ($targetRank < $currentRank) {
    throw new InvalidArgumentException('Só é possível renovar o plano actual ou actualizar para um plano superior.');
  }

  return [
    'current_plan_code' => $currentPlanCode,
    'plan_code' => $planCode,
    'operation' => $planCode === $currentPlanCode ? 'renewal' : 'upgrade',
    'amount' => $amount,
    'period_months' => 3,
  ];
}

function subscription_payment_new_transaction_id(): string
{
  return 'S' . strtoupper(bin2hex(random_bytes(7)));
}

function subscription_payment_build_payment_info(string $method, string $phone = ''): array
{
  $method = strtoupper(trim($method));
  if ($method === 'GPO') {
    return ['phoneNumber' => $phone];
  }
  if ($method === 'REF') {
    return [
      'referenceNumber' => (string)random_int(100000000, 999999999),
      'dueDate' => (new DateTimeImmutable('+7 days'))->format('Y-m-d\TH:i:s'),
    ];
  }
  throw new InvalidArgumentException('Método de pagamento inválido.');
}

function subscription_payment_remote_charge(array $response): array
{
  $charge = $response['data'] ?? [];
  if (isset($charge['data']) && is_array($charge['data'])) {
    $charge = $charge['data'];
  }
  if (isset($charge['payment']) && is_array($charge['payment'])) {
    $charge = $charge['payment'];
  }
  if (!is_array($charge)) {
    return [];
  }

  $status = $charge['responseStatus'] ?? [];
  if (!is_array($status)) {
    $status = [];
  }
  $successful = $status['successful'] ?? $charge['successful'] ?? null;
  if (!is_bool($successful)) {
    $events = $charge['transactionEvents'] ?? [];
    if (is_array($events)) {
      foreach (array_reverse($events) as $event) {
        $eventStatus = is_array($event) ? ($event['responseStatus'] ?? []) : [];
        if (is_array($eventStatus) && is_bool($eventStatus['successful'] ?? null)) {
          $successful = $eventStatus['successful'];
          break;
        }
      }
    }
  }

  return [
    'charge' => $charge,
    'id' => (string)($charge['id'] ?? $charge['chargeId'] ?? ''),
    'merchant_transaction_id' => (string)($charge['merchantTransactionId'] ?? ''),
    'status' => (string)($status['status'] ?? $charge['status'] ?? $charge['chargeStatus'] ?? ''),
    'successful' => $successful === true,
    'amount' => $charge['amount'] ?? null,
    'currency' => $charge['currency'] ?? null,
    'payment_method' => (string)($charge['paymentMethod'] ?? ''),
  ];
}

function subscription_payment_gateway_charge_is_terminal(int $httpCode, string $gatewayStatus): bool
{
  if ($httpCode === 404) {
    return true;
  }

  return $httpCode >= 200
    && $httpCode < 300
    && in_array(strtolower(trim($gatewayStatus)), [
      'failed',
      'failure',
      'rejected',
      'declined',
      'cancelled',
      'canceled',
      'expired',
      'timeout',
      'not_found',
    ], true);
}

function subscription_payment_verify_remote_charge(array $response, array $order, bool $requireFinancialFields = false): array
{
  $code = (int)($response['code'] ?? 0);
  $remote = subscription_payment_remote_charge($response);
  $status = strtolower(trim($remote['status']));
  $expectedChargeId = (string)($order['gateway_charge_id'] ?? '');
  $expectedMerchantId = (string)($order['merchant_transaction_id'] ?? '');

  if ($code < 200 || $code >= 300) {
    return ['confirmed' => false, 'status' => $remote['status'] ?: 'unavailable', 'reason' => 'gateway_http'];
  }
  if (
    $expectedChargeId === ''
    || $remote['id'] === ''
    || !hash_equals($expectedChargeId, $remote['id'])
  ) {
    return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'charge_id_mismatch'];
  }
  if ($remote['merchant_transaction_id'] !== '' && !hash_equals($expectedMerchantId, $remote['merchant_transaction_id'])) {
    return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'transaction_mismatch'];
  }
  if ($requireFinancialFields && $remote['merchant_transaction_id'] === '') {
    return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'transaction_missing'];
  }
  if ($requireFinancialFields && ($remote['amount'] === null || $remote['currency'] === null)) {
    return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'financial_fields_missing'];
  }
  if ($remote['amount'] !== null && (!is_numeric($remote['amount']) || (float)$remote['amount'] !== (float)$order['amount'])) {
    return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'amount_mismatch'];
  }
  if ($remote['currency'] !== null && strtoupper((string)$remote['currency']) !== 'AOA') {
    return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'currency_mismatch'];
  }
  if ($remote['payment_method'] !== '') {
    $methodKind = strtoupper((string)($order['payment_method'] ?? ''));
    $remoteMethod = strtoupper(trim($remote['payment_method']));
    $expectedMethod = appypay_method_id($methodKind);
    if (
      $remoteMethod !== strtoupper($expectedMethod)
      && $remoteMethod !== $methodKind
      && !str_starts_with($remoteMethod, $methodKind . '_')
    ) {
      return ['confirmed' => false, 'status' => $remote['status'], 'reason' => 'method_mismatch'];
    }
  }
  if (!$remote['successful'] || $status !== 'success') {
    return ['confirmed' => false, 'status' => $remote['status'] ?: 'pending', 'reason' => 'not_paid'];
  }

  return ['confirmed' => true, 'status' => $remote['status'], 'reason' => null];
}

function subscription_payment_expiration(array $company, string $targetPlanCode, ?string $today = null): array
{
  $today = $today ?? date('Y-m-d');
  $todayTs = strtotime($today);
  $startTs = $todayTs;
  $plans = subscription_plans();
  $currentPlanCode = (string)($company['plan_code'] ?? '');
  if (!isset($plans[$currentPlanCode])) {
    $currentPlanCode = 'BXPERT_BAZA';
  }
  $currentRank = array_search($currentPlanCode, array_keys($plans), true);
  $targetRank = array_search($targetPlanCode, array_keys($plans), true);
  $operation = $currentPlanCode === $targetPlanCode
    ? 'renewal'
    : ($targetRank > $currentRank ? 'upgrade' : 'downgrade');

  if ($operation === 'renewal' && !empty($company['plan_expires_at'])) {
    $expiryTs = strtotime((string)$company['plan_expires_at']);
    if ($expiryTs !== false) {
      $startTs = max($todayTs, $expiryTs);
    }
  }

  $startDate = date('Y-m-d', $startTs);
  return [
    'operation' => $operation,
    'started_at' => $today,
    'expires_at' => date('Y-m-d', strtotime($startDate . ' +3 months')),
  ];
}

function subscription_payment_apply_order(PDO $pdo, int $orderId, string $gatewayStatus, string $gatewayRaw): array
{
  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare('SELECT * FROM subscription_orders WHERE id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || $order['gateway'] !== 'APPYPAY') {
      throw new RuntimeException('Pedido AppyPay não encontrado.');
    }

    if ($order['status'] === 'paid') {
      $pdo->commit();
      return ['applied' => false, 'already_paid' => true];
    }
    if ($order['status'] !== 'pending') {
      $pdo->commit();
      return ['applied' => false, 'already_paid' => false];
    }

    $plans = subscription_plans();
    $planCode = (string)$order['plan_code'];
    $plan = $plans[$planCode] ?? null;
    if (
      $plan === null
      || (int)$order['period_months'] !== 3
      || (float)$order['amount'] !== (float)$plan['price']
      || !in_array($order['payment_method'], ['REF', 'GPO'], true)
    ) {
      throw new RuntimeException('Os dados locais do pedido não correspondem à configuração do plano.');
    }

    $companyStmt = $pdo->prepare('SELECT plan_code, plan_expires_at FROM companies WHERE id = ? FOR UPDATE');
    $companyStmt->execute([(int)$order['company_id']]);
    $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
      throw new RuntimeException('Empresa do pedido não encontrada.');
    }

    $currentPlanCode = (string)($company['plan_code'] ?? '');
    if (!isset($plans[$currentPlanCode])) {
      $currentPlanCode = 'BXPERT_BAZA';
    }
    $currentRank = array_search($currentPlanCode, array_keys($plans), true);
    $targetRank = array_search($planCode, array_keys($plans), true);
    $operation = (string)($order['operation'] ?? '');
    if (
      ($operation === 'renewal' && $planCode !== $currentPlanCode)
      || ($operation === 'upgrade' && $targetRank <= $currentRank)
      || !in_array($operation, ['renewal', 'upgrade'], true)
    ) {
      throw new RuntimeException('O plano da empresa mudou desde a criação do pedido; o pagamento não pode alterar a subscrição.');
    }

    $dates = subscription_payment_expiration($company, $planCode);
    $today = $dates['started_at'];
    $newExpiry = $dates['expires_at'];
    $paidAt = gmdate('Y-m-d H:i:s');

    $updateOrder = $pdo->prepare(
      "UPDATE subscription_orders
       SET status = 'paid', gateway_status = ?, gateway_response_raw = ?, paid_at = ?
       WHERE id = ? AND status = 'pending'"
    );
    $updateOrder->execute([$gatewayStatus, $gatewayRaw, $paidAt, $orderId]);
    if ($updateOrder->rowCount() !== 1) {
      throw new RuntimeException('O pedido foi alterado durante a confirmação.');
    }

    $updateCompany = $pdo->prepare(
      "UPDATE companies
       SET plan_code = ?, plan_started_at = ?, plan_expires_at = ?, plan_status = 'active', is_active = 1
       WHERE id = ?"
    );
    $updateCompany->execute([$planCode, $today, $newExpiry, (int)$order['company_id']]);

    $pdo->commit();
    return ['applied' => true, 'already_paid' => false, 'new_expires_at' => $newExpiry];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function subscription_payment_reconcile_order(PDO $pdo, int $orderId): array
{
  $lockName = 'appy_sub_' . $orderId;
  $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
  $lockStmt->execute([$lockName]);
  if ((int)$lockStmt->fetchColumn() !== 1) {
    return ['checked' => false, 'applied' => false, 'status' => 'syncing'];
  }

  try {
    $stmt = $pdo->prepare(
      "SELECT * FROM subscription_orders
       WHERE id = ? AND gateway = 'APPYPAY'
       LIMIT 1"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || $order['status'] !== 'pending' || empty($order['gateway_charge_id'])) {
      return ['checked' => false, 'applied' => false, 'status' => $order['status'] ?? 'not_found'];
    }

    $response = appypay_get_charge((string)$order['gateway_charge_id']);
    $verified = subscription_payment_verify_remote_charge($response, $order);
    $gatewayStatus = $verified['status'] !== '' ? $verified['status'] : 'unknown';
    if ((int)($response['code'] ?? 0) === 404) {
      $gatewayStatus = 'not_found';
    }
    $raw = (string)($response['raw'] ?? '');

    $update = $pdo->prepare(
      "UPDATE subscription_orders
       SET gateway_status = ?, gateway_response_raw = ?
       WHERE id = ? AND status = 'pending'"
    );
    $update->execute([$gatewayStatus, $raw, $orderId]);

    if (!$verified['confirmed']) {
      $gatewayCode = (int)($response['code'] ?? 0);
      if (subscription_payment_gateway_charge_is_terminal($gatewayCode, $gatewayStatus)) {
        $pdo->prepare(
          "UPDATE subscription_orders SET status = 'canceled'
           WHERE id = ? AND status = 'pending'"
        )->execute([$orderId]);
      }
      if (!empty($verified['reason']) && $verified['reason'] !== 'not_paid') {
        error_log('[subscription_payment] Reconciliation rejected order ' . $orderId . ': ' . $verified['reason']);
      }
      return [
        'checked' => true,
        'applied' => false,
        'status' => $gatewayStatus,
        'reason' => $verified['reason'],
      ];
    }

    $result = subscription_payment_apply_order($pdo, $orderId, $gatewayStatus, $raw);
    return array_merge(['checked' => true, 'status' => $gatewayStatus], $result);
  } catch (Throwable $e) {
    error_log('[subscription_payment] Reconciliation failed for order ' . $orderId . ': ' . $e->getMessage());
    $update = $pdo->prepare(
      "UPDATE subscription_orders
       SET gateway_status = 'unknown'
       WHERE id = ? AND status = 'pending'"
    );
    $update->execute([$orderId]);
    return ['checked' => true, 'applied' => false, 'status' => 'unknown', 'reason' => 'gateway_unavailable'];
  } finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);
  }
}

function subscription_payment_reconcile_unknown_order(PDO $pdo, int $orderId, int $companyId, string $chargeId): array
{
  $lockName = 'appy_unknown_' . $orderId;
  $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 0)');
  $lockStmt->execute([$lockName]);
  if ((int)$lockStmt->fetchColumn() !== 1) {
    return ['reconciled' => false, 'status' => 'syncing'];
  }

  try {
    $stmt = $pdo->prepare(
      "SELECT * FROM subscription_orders
       WHERE id = ? AND company_id = ? AND gateway = 'APPYPAY'
       LIMIT 1"
    );
    $stmt->execute([$orderId, $companyId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (
      !$order
    ) {
      return ['reconciled' => false, 'status' => 'not_found', 'reason' => 'charge_mismatch'];
    }
    if ($order['status'] === 'paid') {
      return ['reconciled' => true, 'applied' => false, 'already_paid' => true, 'status' => 'paid'];
    }
    if ($order['status'] !== 'pending') {
      return ['reconciled' => false, 'status' => (string)$order['status'], 'reason' => 'charge_mismatch'];
    }
    if (!empty($order['gateway_charge_id'])) {
      if (hash_equals((string)$order['gateway_charge_id'], $chargeId)) {
        return ['reconciled' => true, 'applied' => false, 'status' => (string)$order['gateway_status']];
      }
      return ['reconciled' => false, 'status' => (string)$order['gateway_status'], 'reason' => 'charge_mismatch'];
    }
    if ($order['gateway_status'] !== 'unknown') {
      return ['reconciled' => false, 'status' => (string)$order['gateway_status'], 'reason' => 'charge_mismatch'];
    }

    $response = appypay_get_charge($chargeId);
    $expectedOrder = $order;
    $expectedOrder['gateway_charge_id'] = $chargeId;
    $verified = subscription_payment_verify_remote_charge($response, $expectedOrder, true);
    if (!$verified['confirmed'] && $verified['reason'] !== 'not_paid') {
      error_log('[subscription_payment] Unknown charge could not be matched for order ' . $orderId . ': ' . $verified['reason']);
      return ['reconciled' => false, 'status' => $verified['status'], 'reason' => 'charge_mismatch'];
    }

    $gatewayStatus = $verified['status'] !== '' ? $verified['status'] : 'unknown';
    $update = $pdo->prepare(
      "UPDATE subscription_orders
       SET gateway_charge_id = ?, gateway_status = ?, gateway_response_raw = ?
       WHERE id = ? AND company_id = ? AND status = 'pending'
         AND gateway_charge_id IS NULL AND gateway_status = 'unknown'"
    );
    $update->execute([
      $chargeId,
      $gatewayStatus,
      (string)($response['raw'] ?? ''),
      $orderId,
      $companyId,
    ]);
    if ($update->rowCount() !== 1) {
      return ['reconciled' => false, 'status' => 'already_reconciled'];
    }

    if (!$verified['confirmed']) {
      if (in_array(strtolower($gatewayStatus), ['failed', 'failure', 'rejected', 'declined', 'cancelled', 'canceled', 'expired', 'timeout'], true)) {
        $pdo->prepare("UPDATE subscription_orders SET status = 'canceled' WHERE id = ? AND status = 'pending'")
          ->execute([$orderId]);
      }
      return ['reconciled' => true, 'applied' => false, 'status' => $gatewayStatus];
    }

    $result = subscription_payment_apply_order(
      $pdo,
      $orderId,
      $gatewayStatus,
      (string)($response['raw'] ?? '')
    );
    return array_merge(['reconciled' => true, 'status' => $gatewayStatus], $result);
  } catch (Throwable $e) {
    error_log('[subscription_payment] Unknown order reconciliation failed for order ' . $orderId . ': ' . $e->getMessage());
    return ['reconciled' => false, 'status' => 'unknown', 'reason' => 'gateway_unavailable'];
  } finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);
  }
}

function subscription_payment_create_charge(PDO $pdo, array $post): never
{
  subscription_payment_check_csrf();

  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  subscription_payment_require_manager($pdo, $userId, $companyId);

  $method = strtoupper(trim((string)($post['method'] ?? '')));
  if (!in_array($method, ['REF', 'GPO'], true)) {
    subscription_payment_out(['success' => false, 'message' => 'Método de pagamento inválido.'], 422);
  }

  $plans = subscription_plans();
  $companyStmt = $pdo->prepare('SELECT id, plan_code, plan_expires_at FROM companies WHERE id = ? LIMIT 1');
  $companyStmt->execute([$companyId]);
  $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
  if (!$company) {
    subscription_payment_out(['success' => false, 'message' => 'Empresa activa não encontrada.'], 404);
  }

  try {
    $quote = subscription_payment_quote(
      $plans,
      (string)($company['plan_code'] ?? ''),
      strtoupper(trim((string)($post['plan_code'] ?? '')))
    );
  } catch (InvalidArgumentException $e) {
    subscription_payment_out(['success' => false, 'message' => 'Plano ou preço inválido.'], 422);
  }
  $currentPlanCode = $quote['current_plan_code'];
  $planCode = $quote['plan_code'];
  $amount = $quote['amount'];
  if ($amount <= 0 || !appypay_is_configured()) {
    subscription_payment_out(['success' => false, 'message' => 'O pagamento não está disponível neste momento.'], 503);
  }

  $phone = '';
  if ($method === 'GPO') {
    $phone = preg_replace('/\D+/', '', (string)($post['phone'] ?? '')) ?? '';
    if (strlen($phone) === 12 && str_starts_with($phone, '244')) {
      $phone = substr($phone, 3);
    } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
      $phone = substr($phone, 1);
    }
    if (!preg_match('/^9\d{8}$/', $phone)) {
      subscription_payment_out(['success' => false, 'message' => 'Informe um número angolano válido para o Multicaixa Express.'], 422);
    }
  }

  try {
    $paymentInfo = subscription_payment_build_payment_info($method, $phone);
  } catch (Throwable $e) {
    error_log('[subscription_payment] Could not prepare payment details: ' . $e->getMessage());
    subscription_payment_out(['success' => false, 'message' => 'Não foi possível preparar os dados de pagamento.'], 500);
  }

  try {
    appypay_get_token();
  } catch (Throwable $e) {
    error_log('[subscription_payment] AppyPay authentication failed: ' . $e->getMessage());
    subscription_payment_out(['success' => false, 'message' => 'Não foi possível iniciar o pagamento. Tente novamente mais tarde.'], 503);
  }

  $pendingOrders = $pdo->prepare(
    "SELECT id FROM subscription_orders
     WHERE company_id = ? AND gateway = 'APPYPAY' AND status = 'pending'
     ORDER BY id ASC LIMIT 10"
  );
  $pendingOrders->execute([$companyId]);
  foreach ($pendingOrders->fetchAll(PDO::FETCH_COLUMN) as $pendingOrderId) {
    $reconciled = subscription_payment_reconcile_order($pdo, (int)$pendingOrderId);
    if (!empty($reconciled['applied']) || !empty($reconciled['already_paid'])) {
      subscription_payment_out([
        'success' => false,
        'message' => 'Um pagamento anterior acabou de ser confirmado e a subscrição foi actualizada. Actualize a página antes de iniciar outro pagamento.',
        'order_id' => (int)$pendingOrderId,
      ], 409);
    }
  }

  $orderId = 0;
  $merchantId = '';
  $plan = $plans[$planCode];
  $operation = $quote['operation'];

  try {
    $pdo->beginTransaction();
    $companyLock = $pdo->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE');
    $companyLock->execute([$companyId]);
    if (!$companyLock->fetchColumn()) {
      throw new RuntimeException('Empresa não encontrada.');
    }

    $pending = $pdo->prepare(
      "SELECT id, payment_method, merchant_transaction_id, gateway_charge_id, gateway_status
       FROM subscription_orders
       WHERE company_id = ? AND gateway = 'APPYPAY' AND status = 'pending'
       ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $pending->execute([$companyId]);
    $existing = $pending->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
      $pdo->commit();
      $unknownWithoutChargeId = $existing['gateway_status'] === 'unknown'
        && empty($existing['gateway_charge_id']);
      subscription_payment_out([
        'success' => false,
        'message' => $unknownWithoutChargeId
          ? 'A tentativa anterior ainda não foi localizada na AppyPay. Não gere outra cobrança para evitar duplicação. Consulte o portal AppyPay pela transacção e associe o ID da cobrança no histórico.'
          : 'Já existe um pagamento pendente para esta empresa. Consulte o histórico antes de tentar novamente.',
        'order_id' => (int)$existing['id'],
        'merchant_transaction_id' => (string)$existing['merchant_transaction_id'],
      ], 409);
    }

    $merchantId = subscription_payment_new_transaction_id();
    $insert = $pdo->prepare(
      "INSERT INTO subscription_orders
       (company_id, created_by_user_id, plan_code, operation, period_months, amount, status,
        gateway, payment_method, merchant_transaction_id, gateway_status)
       VALUES (?, ?, ?, ?, 3, ?, 'pending', 'APPYPAY', ?, ?, 'creating')"
    );
    $insert->execute([$companyId, $userId, $planCode, $operation, $amount, $method, $merchantId]);
    $orderId = (int)$pdo->lastInsertId();
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log('[subscription_payment] Could not reserve order for company ' . $companyId . ': ' . $e->getMessage());
    subscription_payment_out(['success' => false, 'message' => 'Não foi possível criar o pedido de pagamento. Tente novamente.'], 500);
  }

  $descriptionPlan = str_replace('_', ' ', $planCode);
  $payload = [
    'amount' => $amount,
    'currency' => 'AOA',
    'description' => ($operation === 'renewal' ? 'Renovacao' : 'Alteracao') . ' subscricao BXPERT ' . $descriptionPlan,
    'merchantTransactionId' => $merchantId,
    'paymentMethod' => appypay_method_id($method),
    'paymentInfo' => $paymentInfo,
  ];

  try {
    $response = appypay_create_charge($payload);
    $data = $response['data'] ?? [];
    $charge = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
    $responseStatus = $charge['responseStatus'] ?? [];
    $chargeId = (string)($charge['id'] ?? $charge['chargeId'] ?? '');
    $gatewayStatus = (string)($responseStatus['status'] ?? $charge['status'] ?? 'unknown');
    $successful = is_array($responseStatus) ? ($responseStatus['successful'] ?? null) : null;
    $reference = is_array($responseStatus) ? ($responseStatus['reference'] ?? []) : [];
    $referenceData = is_array($reference) ? [
      'entity' => (string)($reference['entity'] ?? ''),
      'referenceNumber' => (string)($reference['referenceNumber'] ?? $paymentInfo['referenceNumber'] ?? ''),
      'dueDate' => (string)($reference['dueDate'] ?? $paymentInfo['dueDate'] ?? ''),
    ] : [];
    $paymentRef = $method === 'REF'
      ? json_encode($referenceData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
      : null;

    if ((int)($response['code'] ?? 0) < 200 || (int)($response['code'] ?? 0) >= 300 || $successful === false) {
      $update = $pdo->prepare(
        "UPDATE subscription_orders SET status = 'canceled', gateway_status = ?, gateway_response_raw = ?
         WHERE id = ? AND status = 'pending'"
      );
      $update->execute([$gatewayStatus ?: 'failed', (string)($response['raw'] ?? ''), $orderId]);
      subscription_payment_out(['success' => false, 'message' => 'A AppyPay não aceitou esta cobrança. Confirme os dados e tente novamente.'], 502);
    }

    if ($successful !== true || $chargeId === '') {
      $pdo->prepare(
        "UPDATE subscription_orders SET gateway_status = 'unknown', gateway_response_raw = ?
         WHERE id = ? AND status = 'pending'"
      )->execute([(string)($response['raw'] ?? ''), $orderId]);
      error_log('[subscription_payment] Charge creation outcome is unknown for order ' . $orderId);
      subscription_payment_out([
        'success' => false,
        'message' => 'A cobrança pode ter sido criada. Não crie outro pagamento; obtenha o ID no Portal AppyPay e use “Associar ID” no histórico, ou contacte o suporte.',
        'order_id' => $orderId,
      ], 202);
    }

    $update = $pdo->prepare(
      "UPDATE subscription_orders
       SET gateway_charge_id = ?, gateway_status = ?, gateway_response_raw = ?, payment_ref = ?
       WHERE id = ? AND status = 'pending'"
    );
    $update->execute([$chargeId, $gatewayStatus, (string)($response['raw'] ?? ''), $paymentRef, $orderId]);

    subscription_payment_out([
      'success' => true,
      'order_id' => $orderId,
      'merchantTransactionId' => $merchantId,
      'method' => $method,
      'status' => $gatewayStatus,
      'ref' => $referenceData,
    ]);
  } catch (Throwable $e) {
    error_log('[subscription_payment] Charge request outcome unknown for order ' . $orderId . ': ' . $e->getMessage());
    try {
      $pdo->prepare(
        "UPDATE subscription_orders SET gateway_status = 'unknown'
         WHERE id = ? AND status = 'pending'"
      )->execute([$orderId]);
    } catch (Throwable $dbError) {
      error_log('[subscription_payment] Could not mark unknown order ' . $orderId . ': ' . $dbError->getMessage());
    }
    subscription_payment_out([
      'success' => false,
      'message' => 'A cobrança pode ter sido criada. Não crie outro pagamento; obtenha o ID no Portal AppyPay e use “Associar ID” no histórico, ou contacte o suporte.',
      'order_id' => $orderId,
    ], 202);
  }
}

function subscription_payment_sync_orders(PDO $pdo): never
{
  subscription_payment_check_csrf();

  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  subscription_payment_require_manager($pdo, $userId, $companyId);

  $stmt = $pdo->prepare(
    "SELECT id FROM subscription_orders
     WHERE company_id = ? AND status = 'pending' AND gateway = 'APPYPAY'
       AND gateway_charge_id IS NOT NULL
     ORDER BY id ASC LIMIT 10"
  );
  $stmt->execute([$companyId]);
  $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $checked = 0;
  $paid = 0;
  foreach ($orders as $orderId) {
    $result = subscription_payment_reconcile_order($pdo, (int)$orderId);
    $checked += !empty($result['checked']) ? 1 : 0;
    $paid += !empty($result['applied']) ? 1 : 0;
  }

  subscription_payment_out(['success' => true, 'checked' => $checked, 'paid' => $paid]);
}

function subscription_payment_handle_unknown_reconciliation(PDO $pdo, array $post): never
{
  subscription_payment_check_csrf();
  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  subscription_payment_require_manager($pdo, $userId, $companyId);

  $orderId = filter_var($post['order_id'] ?? null, FILTER_VALIDATE_INT);
  $chargeId = trim((string)($post['charge_id'] ?? ''));
  if (!$orderId || !preg_match('/^[A-Za-z0-9-]{1,80}$/', $chargeId)) {
    subscription_payment_out(['success' => false, 'message' => 'Dados de reconciliação inválidos.'], 422);
  }

  $result = subscription_payment_reconcile_unknown_order($pdo, (int)$orderId, $companyId, $chargeId);
  if (!empty($result['reason'])) {
    $status = $result['reason'] === 'gateway_unavailable' ? 503 : 422;
    subscription_payment_out([
      'success' => false,
      'message' => $status === 503
        ? 'Não foi possível consultar a AppyPay. Tente novamente mais tarde.'
        : 'A cobrança não corresponde a este pedido ou ainda não pode ser reconciliada.',
    ], $status);
  }

  subscription_payment_out([
    'success' => !empty($result['reconciled']),
    'paid' => !empty($result['applied']) || !empty($result['already_paid']) || ($result['status'] ?? '') === 'paid',
    'status' => $result['status'] ?? 'unknown',
    'message' => !empty($result['applied'])
      ? 'Pagamento confirmado e subscrição actualizada.'
      : 'Cobrança associada. A subscrição só será actualizada após confirmação de pagamento.',
  ]);
}

function subscription_payment_list_orders(PDO $pdo, array $query): never
{
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  subscription_payment_require_member($pdo, $userId, $companyId);

  $page = max(1, min(100000, (int)($query['page'] ?? 1)));
  $limit = max(1, min(50, (int)($query['limit'] ?? 10)));
  $offset = ($page - 1) * $limit;

  $count = $pdo->prepare('SELECT COUNT(*) FROM subscription_orders WHERE company_id = ?');
  $count->execute([$companyId]);
  $total = (int)$count->fetchColumn();

  $stmt = $pdo->prepare(
    "SELECT id, plan_code, operation, period_months, amount, status, gateway, payment_method,
           merchant_transaction_id, gateway_status, payment_ref, created_at, paid_at,
           CASE WHEN status = 'pending' AND gateway_status = 'unknown' AND gateway_charge_id IS NULL
                THEN 1 ELSE 0 END AS can_reconcile
     FROM subscription_orders
     WHERE company_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
  );
  $stmt->bindValue(1, $companyId, PDO::PARAM_INT);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $stmt->execute();

  subscription_payment_out([
    'success' => true,
    'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'total_pages' => max(1, (int)ceil($total / $limit)),
  ]);
}

function subscription_payment_handle_webhook(PDO $pdo): never
{
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    subscription_payment_out(['ok' => false, 'error' => 'method_not_allowed'], 405);
  }
  if (APPYPAY_WEBHOOK_TOKEN !== '') {
    $provided = (string)($_SERVER['HTTP_X_APPYPAY_WEBHOOK_TOKEN'] ?? '');
    if (!hash_equals(APPYPAY_WEBHOOK_TOKEN, $provided)) {
      subscription_payment_out(['ok' => false, 'error' => 'unauthorized'], 401);
    }
  }

  $payload = json_decode((string)file_get_contents('php://input'), true);
  if (!is_array($payload)) {
    subscription_payment_out(['ok' => false, 'error' => 'invalid_json'], 400);
  }

  $data = $payload['data'] ?? $payload;
  if (!is_array($data)) {
    subscription_payment_out(['ok' => false, 'error' => 'invalid_payload'], 400);
  }
  $merchantId = (string)($data['merchantTransactionId'] ?? $data['merchant_transaction_id'] ?? '');
  $chargeId = (string)($data['id'] ?? $data['chargeId'] ?? '');
  if ($merchantId === '' || $chargeId === '') {
    subscription_payment_out(['ok' => false, 'error' => 'missing_charge_identity'], 400);
  }

  $stmt = $pdo->prepare(
    "SELECT id FROM subscription_orders
     WHERE gateway = 'APPYPAY' AND merchant_transaction_id = ? AND gateway_charge_id = ?
     LIMIT 1"
  );
  $stmt->execute([$merchantId, $chargeId]);
  $orderId = $stmt->fetchColumn();
  if ($orderId === false) {
    subscription_payment_out(['ok' => false, 'error' => 'order_not_found'], 404);
  }

  $result = subscription_payment_reconcile_order($pdo, (int)$orderId);
  if (($result['reason'] ?? '') === 'gateway_unavailable') {
    subscription_payment_out(['ok' => false, 'error' => 'verification_unavailable'], 503);
  }

  subscription_payment_out([
    'ok' => true,
    'received' => true,
    'paid' => !empty($result['applied']) || !empty($result['already_paid']) || ($result['status'] ?? '') === 'paid',
    'status' => $result['status'] ?? 'pending',
  ]);
}

function subscription_payment_disable_manual_confirmation(): never
{
  subscription_payment_check_csrf();
  subscription_payment_out([
    'success' => false,
    'message' => 'A confirmação manual foi desactivada. Os pagamentos são confirmados directamente com a AppyPay.',
  ], 403);
}
