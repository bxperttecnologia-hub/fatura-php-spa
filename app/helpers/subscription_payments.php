<?php

declare(strict_types=1);

require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/appypay.php';
require_once __DIR__ . '/subscription_order_sync.php';

const SUBSCRIPTION_CANCELED_BY_USER = 'canceled_by_user';

function subscription_payment_out(array $payload, int $status = 200): never
{
  if (defined('SUBSCRIPTION_CHARGE_API') && SUBSCRIPTION_CHARGE_API) {
    if (($payload['success'] ?? null) === false && !isset($payload['code'])) {
      $payload['code'] = 'CHARGE_CREATION_FAILED';
    }
  }
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  exit;
}

function subscription_payment_log_charge_error(
  ?int $orderId,
  ?string $chargeId,
  string $errorType,
  int $httpCode,
  Throwable|string $error
): void {
  $message = $error instanceof Throwable ? $error->getMessage() : $error;
  foreach (['APPYPAY_CLIENT_SECRET', 'DB_PASS'] as $constant) {
    if (defined($constant)) {
      $secret = (string)constant($constant);
      if ($secret !== '') {
        $message = str_replace($secret, '[redacted]', $message);
      }
    }
  }
  error_log('[subscription_payment] ' . json_encode([
    'order_id' => $orderId,
    'charge_id' => $chargeId,
    'error_type' => $errorType,
    'timestamp' => date(DATE_ATOM),
    'http_code' => $httpCode,
    'technical_message' => $message,
  ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
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

function subscription_payment_quote(
  array $plans,
  string $currentPlanCode,
  string $requestedPlanCode,
  int $periodMonths = 3
): array
{
  $currentPlanCode = subscription_internal_plan_code($currentPlanCode) ?? 'BXPERT_BAZA';
  if ($requestedPlanCode !== '') {
    $requestedPlanCode = subscription_internal_plan_code($requestedPlanCode) ?? $requestedPlanCode;
  }
  if (!isset($plans[$currentPlanCode])) {
    $currentPlanCode = 'BXPERT_BAZA';
  }
  $planCode = $requestedPlanCode !== '' ? $requestedPlanCode : $currentPlanCode;
  if (!isset($plans[$planCode])) {
    throw new InvalidArgumentException('Plano inválido.');
  }

  if (!in_array($periodMonths, [3, 6, 12], true)) {
    throw new InvalidArgumentException('Período inválido.');
  }

  $planPrices = $plans[$planCode]['prices'] ?? [3 => ($plans[$planCode]['price'] ?? 0)];
  $amount = (float)($planPrices[$periodMonths] ?? 0);
  if ($amount <= 0) {
    throw new InvalidArgumentException('Preço inválido.');
  }

  // Classifica a operação. O período e o plano só são aplicados após o CRM emitir a licença.
  $currentRank = array_search($currentPlanCode, array_keys($plans), true);
  $targetRank = array_search($planCode, array_keys($plans), true);

  return [
    'current_plan_code' => $currentPlanCode,
    'plan_code' => $planCode,
    'operation' => $planCode === $currentPlanCode ? 'renewal' : ($targetRank > $currentRank ? 'upgrade' : 'downgrade'),
    'amount' => $amount,
    'period_months' => $periodMonths,
  ];
}

function subscription_payment_plan_id_from_matches(array $matches): int
{
  if (count($matches) !== 1) {
    throw new DomainException('O plano ERP não tem uma correspondência única por código e duração.');
  }
  $planId = filter_var($matches[0]['id'] ?? null, FILTER_VALIDATE_INT);
  if (!$planId || $planId < 1) {
    throw new DomainException('A correspondência do plano ERP devolveu um identificador inválido.');
  }
  return $planId;
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
    // Número de referência (9 dígitos) enviado à AppyPay no pedido da cobrança; a entidade
    // vem na resposta. Não enviar "entity" vazio: é a AppyPay que a atribui.
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

function subscription_payment_extract_reference(array $response): array
{
  $charge = $response['data'] ?? [];
  if (isset($charge['data']) && is_array($charge['data'])) {
    $charge = $charge['data'];
  }
  if (isset($charge['payment']) && is_array($charge['payment'])) {
    $charge = $charge['payment'];
  }
  $status = is_array($charge) ? ($charge['responseStatus'] ?? []) : [];
  $reference = is_array($status) ? ($status['reference'] ?? []) : [];
  if (!is_array($reference) || empty($reference)) {
    $reference = is_array($charge) && is_array($charge['reference'] ?? null) ? $charge['reference'] : [];
  }

  return [
    'entity' => (string)($reference['entity'] ?? ''),
    'referenceNumber' => (string)($reference['referenceNumber'] ?? ''),
    'dueDate' => (string)($reference['dueDate'] ?? ''),
  ];
}

function subscription_payment_order_blocks_new_charge(array $order): bool
{
  return ($order['status'] ?? null) === 'paid'
    && in_array($order['subscription_status'] ?? null, [
      'pending', 'awaiting_license', 'awaiting_sync', 'license_issued', 'sync_failed', 'license_rejected',
    ], true)
    && (!empty($order['paid_at']) || !empty($order['gateway_charge_id']));
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

function subscription_payment_expiration(
  array $company,
  string $targetPlanCode,
  ?string $today = null,
  int $periodMonths = 3
): array
{
  if (!in_array($periodMonths, [3, 6, 12], true)) {
    throw new InvalidArgumentException('Período inválido.');
  }
  $today = $today ?? date('Y-m-d');
  $todayTs = strtotime($today);
  $startTs = $todayTs;
  $plans = subscription_plans();
  $currentPlanCode = subscription_internal_plan_code((string)($company['plan_code'] ?? '')) ?? '';
  $targetPlanCode = subscription_internal_plan_code($targetPlanCode) ?? $targetPlanCode;
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
    'expires_at' => subscription_payment_add_months($startDate, $periodMonths),
  ];
}

function subscription_payment_add_months(string $date, int $months): string
{
  $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
  if ($start === false || !in_array($months, [3, 6, 12], true)) {
    throw new InvalidArgumentException('Data ou período inválido.');
  }
  $targetMonth = $start->modify('first day of this month')->modify("+{$months} months");
  $day = min(
    (int)$start->format('d'),
    (int)$targetMonth->modify('last day of this month')->format('d')
  );
  return $targetMonth
    ->setDate((int)$targetMonth->format('Y'), (int)$targetMonth->format('m'), $day)
    ->format('Y-m-d');
}

function subscription_payment_apply_order(PDO $pdo, int $orderId, string $gatewayStatus, string $gatewayRaw): array
{
  $pdo->beginTransaction();
  $chargeId = '';
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
    $planCode = subscription_internal_plan_code((string)$order['plan_code']) ?? '';
    $plan = $plans[$planCode] ?? null;
    $periodMonths = (int)$order['period_months'];
    $planPrices = $plan['prices'] ?? ($plan === null ? [] : [3 => ($plan['price'] ?? 0)]);
    if (
      $plan === null
      || !in_array($periodMonths, [3, 6, 12], true)
      || !isset($planPrices[$periodMonths])
      || abs((float)$order['amount'] - (float)$planPrices[$periodMonths]) >= 0.01
      || !in_array($order['payment_method'], ['REF', 'GPO'], true)
    ) {
      throw new RuntimeException('Os dados locais do pedido não correspondem à configuração do plano.');
    }

    $companyStmt = $pdo->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE');
    $companyStmt->execute([(int)$order['company_id']]);
    $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
      throw new RuntimeException('Empresa do pedido não encontrada.');
    }

    $paidAt = gmdate('Y-m-d H:i:s');

    $updateOrder = $pdo->prepare(
      "UPDATE subscription_orders
       SET status = 'paid', subscription_status = 'awaiting_license',
           gateway_status = ?, gateway_response_raw = ?, paid_at = ?, activated_at = NULL
       WHERE id = ? AND status = 'pending'"
    );
    $updateOrder->execute([$gatewayStatus, $gatewayRaw, $paidAt, $orderId]);
    if ($updateOrder->rowCount() !== 1) {
      throw new RuntimeException('O pedido foi alterado durante a confirmação.');
    }

    subscription_order_sync_enqueue($pdo, $orderId);
    $pdo->commit();
    return ['applied' => true, 'already_paid' => false, 'awaiting_license' => true];
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
    $canceledByUser = $order
      && $order['status'] === 'canceled'
      && $order['gateway_status'] === SUBSCRIPTION_CANCELED_BY_USER;
    if (!$order || ($order['status'] !== 'pending' && !$canceledByUser) || empty($order['gateway_charge_id'])) {
      return ['checked' => false, 'applied' => false, 'status' => $order['status'] ?? 'not_found'];
    }

    $response = appypay_get_charge((string)$order['gateway_charge_id']);
    $verified = subscription_payment_verify_remote_charge($response, $order);

    if ($canceledByUser) {
      // Referência cancelada no BX, mas o cliente pagou-a na mesma: o dinheiro entrou,
      // por isso o pedido volta a pendente e a subscrição é aplicada normalmente.
      if (!$verified['confirmed']) {
        return ['checked' => true, 'applied' => false, 'status' => 'canceled'];
      }
      $pdo->prepare(
        "UPDATE subscription_orders SET status = 'pending'
         WHERE id = ? AND status = 'canceled' AND gateway_status = ?"
      )->execute([$orderId, SUBSCRIPTION_CANCELED_BY_USER]);
      error_log('[subscription_payment] Order ' . $orderId . ' was canceled by the user but has been paid; applying it.');
    }
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

/** Corrige texto UTF-8 lido duas vezes como Latin-1 ("TelemÃ³vel" → "Telemóvel"), como vem do processador. */
function subscription_payment_fix_mojibake(string $text): string
{
  if (!preg_match('/Ã[\x{0080}-\x{00BF}]/u', $text)) {
    return $text;
  }
  $fixed = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
  return mb_check_encoding($fixed, 'UTF-8') ? $fixed : $text;
}

/**
 * Mensagem para o utilizador quando a AppyPay recusa a cobrança: a mensagem geral e, se existir,
 * o motivo concreto do processador (ex.: "Número de Telemóvel não Activo no MCX Express").
 */
function subscription_payment_gateway_error_message(array $responseStatus): string
{
  $general = trim(subscription_payment_fix_mojibake((string)($responseStatus['message'] ?? '')));
  $details = $responseStatus['sourceDetails'] ?? null;
  $detail = is_array($details) ? trim(subscription_payment_fix_mojibake((string)($details['message'] ?? ''))) : '';
  $detail = trim((string)preg_replace('/^Recusado pelo Processador\.\s*/u', '', $detail));
  $detail = trim((string)preg_replace('/\s*Necess[áa]rio contactar a [áa]rea de suporte\.?$/u', '', $detail));

  $message = $general !== '' ? $general : 'A AppyPay não aceitou esta cobrança. Confirme os dados e tente novamente.';
  if ($detail !== '' && mb_stripos($message, $detail) === false) {
    $message .= ' Motivo: ' . rtrim($detail, '.') . '.';
  }
  return $message;
}

function subscription_payment_sync_schema_failure(?string $sqlState): ?array
{
  if (!in_array($sqlState, ['42S02', '42S22'], true)) {
    return null;
  }

  return [
    'status' => 503,
    'message' => 'A integração de licenças ainda não está pronta. Confirme as migrações ERP 20261118–20261123; para criar a tabela de tentativas, execute php bin/migrate_subscription_license_sync_attempts.php --dry-run e, após confirmar a base, repita com --apply.',
  ];
}

function subscription_payment_create_charge(PDO $pdo, array $post): never
{
  subscription_payment_check_csrf();

  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  // Qualquer utilizador da empresa pode pagar a subscrição (gerar referência / Multicaixa Express):
  // assim o pagamento não fica à espera do proprietário.
  subscription_payment_require_member($pdo, $userId, $companyId);

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
      strtoupper(trim((string)($post['plan_code'] ?? ''))),
      (int)filter_var($post['period_months'] ?? 3, FILTER_VALIDATE_INT)
    );
  } catch (InvalidArgumentException $e) {
    subscription_payment_out(['success' => false, 'message' => 'Plano ou preço inválido.'], 422);
  }
  $currentPlanCode = $quote['current_plan_code'];
  $planCode = $quote['plan_code'];
  $erpPlanCode = subscription_erp_plan_code($planCode);
  $amount = $quote['amount'];
  $periodMonths = $quote['period_months'];
  if ($amount <= 0 || $erpPlanCode === null) {
    subscription_payment_log_charge_error(null, null, 'INVALID_PLAN_CONFIGURATION', 503, 'No ERP plan code is configured for the selected plan.');
    subscription_payment_out([
      'success' => false, 'code' => 'ERP_PLAN_MAPPING_INVALID',
      'message' => 'O plano seleccionado não tem um código ERP configurado.',
    ], 503);
  }
  if (!appypay_is_configured()) {
    subscription_payment_log_charge_error(null, null, 'APPYPAY_NOT_CONFIGURED', 503, 'AppyPay configuration is incomplete for the active environment.');
    subscription_payment_out(['success' => false, 'message' => 'O pagamento não está disponível neste momento.'], 503);
  }

  try {
    $planLookup = $pdo->prepare(
      'SELECT id FROM plans WHERE legacy_code = ? AND duration_months = ? LIMIT 2'
    );
    $planLookup->execute([$erpPlanCode, $periodMonths]);
    $erpPlanId = subscription_payment_plan_id_from_matches($planLookup->fetchAll(PDO::FETCH_ASSOC));
  } catch (DomainException $e) {
    subscription_payment_log_charge_error(null, null, 'ERP_PLAN_MAPPING_MISSING', 409, $e);
    subscription_payment_out([
      'success' => false, 'code' => 'ERP_PLAN_MAPPING_INVALID',
      'message' => 'O plano e a duração não têm uma correspondência única no ERP.',
    ], 409);
  } catch (PDOException $e) {
    subscription_payment_log_charge_error(null, null, 'ERP_PLAN_LOOKUP_FAILED', 503, $e);
    subscription_payment_out([
      'success' => false, 'code' => 'ERP_PLAN_LOOKUP_FAILED',
      'message' => 'Não foi possível validar a correspondência do plano no ERP.',
    ], 503);
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
    subscription_payment_log_charge_error(null, null, 'APPYPAY_AUTHENTICATION_FAILED', 503, $e);
    subscription_payment_out([
      'success' => false, 'code' => 'APPYPAY_UNAVAILABLE',
      'message' => 'Não foi possível iniciar o pagamento. Tente novamente mais tarde.',
    ], 503);
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
        'message' => 'Um pagamento anterior foi confirmado e aguarda a emissão da licença pelo CRM. Actualize a página antes de iniciar outro pagamento.',
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
    $companyLock = $pdo->prepare('SELECT id, plan_code, plan_expires_at FROM companies WHERE id = ? FOR UPDATE');
    $companyLock->execute([$companyId]);
    $lockedCompany = $companyLock->fetch(PDO::FETCH_ASSOC);
    if (!$lockedCompany) {
      throw new RuntimeException('Empresa não encontrada.');
    }

    $currentPlanCode = (string)($lockedCompany['plan_code'] ?? '');
    $currentErpPlanCode = subscription_erp_plan_code($currentPlanCode) ?? '';
    $subscriptionLookup = $pdo->prepare(
      "SELECT s.id
       FROM subscriptions s
       INNER JOIN subscription_license_links l ON l.subscription_id = s.id
       WHERE s.company_id = ? AND s.status = 'active'
         AND l.plan_code = ? AND DATE(s.end_date) = ?
         AND EXISTS (
           SELECT 1 FROM subscription_license_links valid_license
           WHERE valid_license.subscription_id = s.id AND valid_license.company_id = s.company_id
             AND valid_license.license_status = 'active'
             AND (valid_license.start_date IS NULL OR valid_license.start_date <= UTC_TIMESTAMP())
             AND (valid_license.expires_at IS NULL OR valid_license.expires_at > UTC_TIMESTAMP())
         )
       ORDER BY s.id DESC LIMIT 2 FOR UPDATE"
    );
    $subscriptionLookup->execute([
      $companyId,
      $currentErpPlanCode,
      (string)($lockedCompany['plan_expires_at'] ?? ''),
    ]);
    $currentSubscriptions = $subscriptionLookup->fetchAll(PDO::FETCH_COLUMN);
    if (count($currentSubscriptions) > 1) {
      throw new RuntimeException('Existe mais de uma subscrição CRM válida para a empresa; é necessária reconciliação manual.');
    }
    $subscriptionId = $currentSubscriptions ? (int)$currentSubscriptions[0] : null;

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

    $awaitingLicense = $pdo->prepare(
      "SELECT id, paid_at, gateway_charge_id, subscription_status FROM subscription_orders
       WHERE company_id = ? AND status = 'paid'
         AND (paid_at IS NOT NULL OR (gateway_charge_id IS NOT NULL AND gateway_charge_id <> ''))
         AND subscription_status IN ('pending','awaiting_license','awaiting_sync','license_issued','sync_failed','license_rejected')
       ORDER BY id DESC LIMIT 1 FOR UPDATE"
    );
    $awaitingLicense->execute([$companyId]);
    $paidAwaitingLicense = $awaitingLicense->fetch(PDO::FETCH_ASSOC);
    if ($paidAwaitingLicense && subscription_payment_order_blocks_new_charge($paidAwaitingLicense)) {
      $pdo->commit();
      $confirmed = !empty($paidAwaitingLicense['paid_at']);
      subscription_payment_out([
        'success' => false,
        'code' => $confirmed ? 'PAID_ORDER_AWAITING_LICENSE' : 'ORDER_RECONCILIATION_REQUIRED',
        'message' => $confirmed
          ? 'Já existe um pagamento confirmado à espera da licença. Actualize a página antes de iniciar outro pagamento.'
          : 'Existe uma cobrança anterior por reconciliar. Não vamos criar outra para evitar duplicação; contacte o suporte.',
        'order_id' => (int)$paidAwaitingLicense['id'],
      ], 409);
    }

    $merchantId = subscription_payment_new_transaction_id();
    if ($subscriptionId === null) {
      $operation = 'new';
    }
    $insert = $pdo->prepare(
      "INSERT INTO subscription_orders
       (company_id, created_by_user_id, plan_code, plan_id, operation, subscription_id, period_months, amount, status,
        gateway, payment_method, merchant_transaction_id, gateway_status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'APPYPAY', ?, ?, 'creating')"
    );
    $insert->execute([
      $companyId, $userId, $erpPlanCode, $erpPlanId, $operation, $subscriptionId, $periodMonths, $amount, $method, $merchantId,
    ]);
    $orderId = (int)$pdo->lastInsertId();
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    subscription_payment_log_charge_error(
      $orderId > 0 ? $orderId : null,
      null,
      'ORDER_RESERVATION_FAILED',
      $e instanceof PDOException ? 503 : 500,
      $e
    );
    $schemaFailure = $e instanceof PDOException
      ? subscription_payment_sync_schema_failure((string)$e->getCode())
      : null;
    subscription_payment_out([
      'success' => false,
      'message' => $schemaFailure['message'] ?? 'Não foi possível criar o pedido de pagamento. Tente novamente.',
    ], $schemaFailure['status'] ?? 500);
  }

  $descriptionPlan = str_replace('_', ' ', $planCode);
  $payload = [
    'amount' => $amount,
    'currency' => 'AOA',
    'description' => ($operation === 'new' ? 'Nova contratacao' : ($operation === 'renewal' ? 'Renovacao' : 'Alteracao'))
      . ' subscricao BXPERT ' . $descriptionPlan,
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
    $referenceData = $method === 'REF' ? subscription_payment_extract_reference($response) : [];

    if ((int)($response['code'] ?? 0) < 200 || (int)($response['code'] ?? 0) >= 300 || $successful === false) {
      subscription_payment_log_charge_error(
        $orderId, $chargeId !== '' ? $chargeId : null, 'APPYPAY_CHARGE_REJECTED',
        (int)($response['code'] ?? 0),
        subscription_payment_gateway_error_message(is_array($responseStatus) ? $responseStatus : [])
      );
      $update = $pdo->prepare(
        "UPDATE subscription_orders SET status = 'canceled', gateway_status = ?, gateway_response_raw = ?
         WHERE id = ? AND status = 'pending'"
      );
      $update->execute([$gatewayStatus ?: 'failed', (string)($response['raw'] ?? ''), $orderId]);
      subscription_payment_out([
        'success' => false,
        'message' => subscription_payment_gateway_error_message(is_array($responseStatus) ? $responseStatus : []),
      ], 502);
    }

    if ($successful !== true || $chargeId === '') {
      $pdo->prepare(
        "UPDATE subscription_orders SET gateway_status = 'unknown', gateway_response_raw = ?
         WHERE id = ? AND status = 'pending'"
      )->execute([(string)($response['raw'] ?? ''), $orderId]);
      subscription_payment_log_charge_error(
        $orderId, $chargeId !== '' ? $chargeId : null, 'APPYPAY_CHARGE_OUTCOME_UNKNOWN',
        (int)($response['code'] ?? 0), 'AppyPay response did not confirm a charge ID and successful status.'
      );
      subscription_payment_out([
        'success' => false,
        'message' => 'A cobrança pode ter sido criada. Não crie outro pagamento; obtenha o ID no Portal AppyPay e use “Associar ID” no histórico, ou contacte o suporte.',
        'order_id' => $orderId,
      ], 202);
    }

    if ($method === 'REF' && ($referenceData['entity'] === '' || $referenceData['referenceNumber'] === '')) {
      // Cobrança assíncrona: a referência pode só estar disponível ao consultar a cobrança.
      try {
        $lookup = appypay_get_charge($chargeId);
        $found = subscription_payment_extract_reference($lookup);
        foreach ($found as $key => $value) {
          if ($value !== '' && ($referenceData[$key] ?? '') === '') {
            $referenceData[$key] = $value;
          }
        }
      } catch (Throwable $e) {
        subscription_payment_log_charge_error($orderId, $chargeId, 'APPYPAY_REFERENCE_LOOKUP_FAILED', 0, $e);
      }
    }
    // O que a AppyPay não devolver fica com os dados enviados no pedido (número e validade).
    foreach (['referenceNumber', 'dueDate'] as $key) {
      if ($referenceData && ($referenceData[$key] ?? '') === '' && !empty($paymentInfo[$key])) {
        $referenceData[$key] = (string)$paymentInfo[$key];
      }
    }
    $paymentRef = $method === 'REF'
      ? json_encode($referenceData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
      : null;

    $update = $pdo->prepare(
      "UPDATE subscription_orders
       SET gateway_charge_id = ?, gateway_status = ?, gateway_response_raw = ?, payment_ref = ?
       WHERE id = ? AND status = 'pending'"
    );
    $update->execute([$chargeId, $gatewayStatus, (string)($response['raw'] ?? ''), $paymentRef, $orderId]);

    subscription_payment_out([
      'success' => true,
      'message' => 'Cobrança criada com sucesso',
      'order_id' => $orderId,
      'merchantTransactionId' => $merchantId,
      'method' => $method,
      'status' => $gatewayStatus,
      'ref' => $referenceData,
    ]);
  } catch (Throwable $e) {
    subscription_payment_log_charge_error(
      $orderId, $chargeId !== '' ? $chargeId : null, 'APPYPAY_CHARGE_OUTCOME_UNKNOWN', 202, $e
    );
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
  // Qualquer utilizador da empresa pode pagar a subscrição (gerar referência / Multicaixa Express):
  // assim o pagamento não fica à espera do proprietário.
  subscription_payment_require_member($pdo, $userId, $companyId);

  $stmt = $pdo->prepare(
    "SELECT id FROM subscription_orders
     WHERE company_id = ? AND gateway = 'APPYPAY' AND gateway_charge_id IS NOT NULL
       AND (status = 'pending'
            OR (status = 'canceled' AND gateway_status = ? AND created_at >= NOW() - INTERVAL 10 DAY))
     ORDER BY id ASC LIMIT 10"
  );
  $stmt->execute([$companyId, SUBSCRIPTION_CANCELED_BY_USER]);
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

function subscription_payment_retry_crm_order_sync(PDO $pdo, array $post): never
{
  subscription_payment_check_csrf();
  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  subscription_payment_require_manager($pdo, $userId, $companyId);
  $orderId = filter_var($post['order_id'] ?? null, FILTER_VALIDATE_INT);
  if (!$orderId) subscription_payment_out(['success' => false, 'message' => 'Pedido inválido.'], 422);

  $stmt = $pdo->prepare(
    "SELECT id, status, paid_at FROM subscription_orders
     WHERE id = ? AND company_id = ? LIMIT 1"
  );
  $stmt->execute([(int)$orderId, $companyId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$order) subscription_payment_out(['success' => false, 'message' => 'Pedido não encontrado.'], 404);
  if (($order['status'] ?? null) !== 'paid') {
    subscription_payment_out(['success' => false, 'message' => 'Só pedidos pagos podem ser sincronizados com o CRM.'], 409);
  }
  if (!subscription_order_sync_is_confirmed_paid_order($order)) {
    subscription_payment_out([
      'success' => false,
      'message' => 'O pedido está marcado como pago, mas não tem confirmação financeira registada. Confirme o pagamento antes de sincronizar.',
    ], 409);
  }

  $outbox = $pdo->prepare(
    'SELECT id FROM subscription_order_sync_outbox WHERE order_id = ? AND delivered_at IS NULL LIMIT 1'
  );
  $outbox->execute([(int)$orderId]);
  if (!$outbox->fetchColumn()) {
    $delivered = $pdo->prepare(
      'SELECT id FROM subscription_order_sync_outbox WHERE order_id = ? AND delivered_at IS NOT NULL LIMIT 1'
    );
    $delivered->execute([(int)$orderId]);
    if ($delivered->fetchColumn()) {
      subscription_payment_out(['success' => true, 'status' => 'confirmed', 'message' => 'O pedido já foi recebido pelo CRM.']);
    }
    try {
      $pdo->beginTransaction();
      subscription_order_sync_enqueue($pdo, (int)$orderId);
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      error_log('[subscription_order_sync] Could not queue paid order ' . (int)$orderId . ': ' . $e->getMessage());
      subscription_payment_out(['success' => false, 'message' => 'Não foi possível preparar a sincronização com o CRM.'], 500);
    }
  }

  $result = subscription_order_sync_flush($pdo, (int)$orderId, 1);
  subscription_payment_out([
    'success' => $result['failed'] === 0,
    'status' => $result['failed'] === 0 ? 'confirmed' : 'failed',
    'message' => $result['failed'] === 0
      ? 'O pedido foi confirmado pelo CRM.'
      : 'A sincronização com o CRM falhou. O evento ficou pendente para nova tentativa.',
  ], $result['failed'] === 0 ? 200 : 503);
}

function subscription_payment_handle_unknown_reconciliation(PDO $pdo, array $post): never
{
  subscription_payment_check_csrf();
  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  // Qualquer utilizador da empresa pode pagar a subscrição (gerar referência / Multicaixa Express):
  // assim o pagamento não fica à espera do proprietário.
  subscription_payment_require_member($pdo, $userId, $companyId);

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
      ? 'Pagamento confirmado; a subscrição aguarda a emissão e validação da licença pelo CRM.'
      : 'Cobrança associada. A subscrição só será actualizada após confirmação de pagamento.',
  ]);
}

/**
 * Cancela uma referência / pedido pendente (ex.: gerado por engano), para se poder gerar outro.
 * Antes confirma na AppyPay que ainda não foi pago. Se a referência for paga depois do
 * cancelamento, subscription_payment_reconcile_order aplica o pagamento na mesma.
 */
function subscription_payment_cancel_order(PDO $pdo, array $post): never
{
  subscription_payment_check_csrf();
  $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
  $userId = (int)($_SESSION['user']['id'] ?? 0);
  subscription_payment_require_member($pdo, $userId, $companyId);

  $orderId = filter_var($post['order_id'] ?? null, FILTER_VALIDATE_INT);
  if (!$orderId) {
    subscription_payment_out(['success' => false, 'message' => 'Pedido inválido.'], 422);
  }

  $stmt = $pdo->prepare('SELECT id, status, gateway_charge_id FROM subscription_orders WHERE id = ? AND company_id = ? LIMIT 1');
  $stmt->execute([(int)$orderId, $companyId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$order) {
    subscription_payment_out(['success' => false, 'message' => 'Pedido não encontrado.'], 404);
  }
  if ($order['status'] !== 'pending') {
    subscription_payment_out(['success' => false, 'message' => 'Só é possível cancelar pagamentos pendentes.'], 409);
  }

  // Confirma primeiro na AppyPay: se já foi pago, aplica a subscrição em vez de cancelar.
  if (!empty($order['gateway_charge_id'])) {
    $check = subscription_payment_reconcile_order($pdo, (int)$orderId);
    if (!empty($check['applied']) || !empty($check['already_paid'])) {
      subscription_payment_out([
        'success' => false,
        'paid' => true,
        'message' => 'Este pagamento já foi feito e a subscrição foi actualizada. Não pode ser cancelado.',
      ], 409);
    }
    if (($check['status'] ?? '') === 'syncing') {
      subscription_payment_out(['success' => false, 'message' => 'O pagamento está a ser verificado. Tente daqui a pouco.'], 409);
    }
  }

  $cancel = $pdo->prepare(
    "UPDATE subscription_orders SET status = 'canceled', gateway_status = ?
     WHERE id = ? AND company_id = ? AND status = 'pending'"
  );
  $cancel->execute([SUBSCRIPTION_CANCELED_BY_USER, (int)$orderId, $companyId]);
  if ($cancel->rowCount() !== 1) {
    subscription_payment_out(['success' => false, 'message' => 'O pedido já não está pendente. Actualize a página.'], 409);
  }

  subscription_payment_out(['success' => true, 'message' => 'Pagamento cancelado. Já pode gerar uma nova referência.']);
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
           merchant_transaction_id, gateway_status, payment_ref, created_at, paid_at, subscription_status,
           CASE WHEN status = 'paid' AND EXISTS (
             SELECT 1 FROM subscription_order_sync_outbox delivered
             WHERE delivered.order_id = subscription_orders.id AND delivered.delivered_at IS NOT NULL
           ) THEN 'confirmed'
           WHEN status = 'paid' THEN 'pending' ELSE NULL END AS crm_order_sync_status,
           (SELECT last_error FROM subscription_order_sync_outbox sync
            WHERE sync.order_id = subscription_orders.id AND sync.delivered_at IS NULL
            ORDER BY sync.id DESC LIMIT 1) AS crm_order_sync_error,
           (SELECT last_attempt_at FROM subscription_order_sync_outbox sync
            WHERE sync.order_id = subscription_orders.id
            ORDER BY sync.id DESC LIMIT 1) AS crm_order_sync_attempted_at,
           CASE WHEN status = 'paid' AND subscription_status = 'awaiting_license'
                THEN 1 ELSE 0 END AS awaiting_license,
           CASE WHEN status = 'paid' AND subscription_status = 'awaiting_sync'
                THEN 1 ELSE 0 END AS awaiting_sync,
           CASE WHEN status = 'pending' AND gateway_status = 'unknown' AND gateway_charge_id IS NULL
                THEN 1 ELSE 0 END AS can_reconcile,
           CASE WHEN status = 'pending' THEN 1 ELSE 0 END AS can_cancel
     FROM subscription_orders
     WHERE company_id = ?
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
  );
  $stmt->bindValue(1, $companyId, PDO::PARAM_INT);
  $stmt->bindValue(2, $limit, PDO::PARAM_INT);
  $stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $stmt->execute();

  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $canRetrySync = subscription_payment_role_can_manage(subscription_payment_company_role($pdo, $userId, $companyId));
  foreach ($orders as &$order) {
    $order['can_retry_crm_sync'] = $canRetrySync && subscription_order_sync_is_confirmed_paid_order($order) ? 1 : 0;
  }
  unset($order);
  subscription_payment_out([
    'success' => true,
    'orders' => $orders,
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
