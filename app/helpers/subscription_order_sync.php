<?php

declare(strict_types=1);

function subscription_order_sync_uuid(): string
{
  $bytes = random_bytes(16);
  $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
  $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
  $hex = bin2hex($bytes);
  return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
    . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function subscription_order_sync_is_confirmed_paid_order(array $order): bool
{
  return ($order['status'] ?? null) === 'paid' && !empty($order['paid_at']);
}

function subscription_order_sync_enqueue(PDO $pdo, int $orderId): void
{
  $stmt = $pdo->prepare(
    'SELECT so.id, so.company_id, so.plan_code, so.operation, so.period_months, so.amount,
            so.status, so.paid_at, so.created_at, u.email AS customer_email,
            (SELECT l.crm_subscription_id FROM subscription_license_links l
             WHERE l.subscription_id = so.subscription_id AND l.company_id = so.company_id
             ORDER BY l.updated_at DESC LIMIT 1) AS crm_subscription_id
     FROM subscription_orders so
     LEFT JOIN users u ON u.id = so.created_by_user_id
     WHERE so.id = ? LIMIT 1 FOR UPDATE'
  );
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$order || !subscription_order_sync_is_confirmed_paid_order($order)) {
    throw new RuntimeException('Só pedidos pagos e confirmados podem ser enviados ao CRM.');
  }

  $eventId = subscription_order_sync_uuid();
  $email = filter_var($order['customer_email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
  $payload = [
    'event_id' => $eventId,
    'event_type' => 'subscription_order.paid',
    'order_id' => (int)$order['id'],
    'order_version' => 1,
    'company_id' => (int)$order['company_id'],
    'plan_code' => (string)$order['plan_code'],
    'operation' => (string)$order['operation'],
    'crm_subscription_id' => !empty($order['crm_subscription_id']) ? (int)$order['crm_subscription_id'] : null,
    'period_months' => (int)$order['period_months'],
    'amount' => (float)$order['amount'],
    'customer_email' => $email,
    'paid_at' => (string)$order['paid_at'],
    'created_at' => (string)$order['created_at'],
  ];
  $pdo->prepare(
    "INSERT INTO subscription_order_sync_outbox
     (event_id, event_type, order_id, payload, next_attempt_at, created_at)
     VALUES (?, 'subscription_order.paid', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
  )->execute([$eventId, $orderId, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}

function subscription_order_sync_http_post(string $url, string $body, array $headers): array
{
  $curl = curl_init($url);
  if ($curl === false) throw new RuntimeException('Não foi possível iniciar a ligação ao CRM.');
  $responseHeaders = [];
  curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
      $length = strlen($line);
      $parts = explode(':', $line, 2);
      if (count($parts) === 2) $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
      return $length;
    },
  ]);
  $responseBody = curl_exec($curl);
  $error = curl_error($curl);
  $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  curl_close($curl);
  if ($responseBody === false) throw new RuntimeException('Falha de comunicação com o CRM: ' . $error);
  return ['status' => $status, 'body' => (string)$responseBody, 'headers' => $responseHeaders];
}

function subscription_order_sync_flush(PDO $pdo, ?int $orderId = null, int $batch = 25, ?callable $transport = null): array
{
  if ($orderId !== null) {
    $pdo->prepare(
      'UPDATE subscription_order_sync_outbox SET next_attempt_at = UTC_TIMESTAMP()
       WHERE order_id = ? AND delivered_at IS NULL'
    )->execute([$orderId]);
  }
  $sql = 'SELECT id, event_id, order_id, payload, attempts FROM subscription_order_sync_outbox
          WHERE delivered_at IS NULL AND next_attempt_at <= UTC_TIMESTAMP()';
  $params = [];
  if ($orderId !== null) {
    $sql .= ' AND order_id = ?';
    $params[] = $orderId;
  }
  $sql .= ' ORDER BY id LIMIT ' . max(1, min(100, $batch));
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $sent = 0;
  $failed = 0;

  foreach ($rows as $row) {
    try {
      if (!defined('CRM_ORDER_SYNC_URL') || CRM_ORDER_SYNC_URL === ''
        || !defined('CRM_ORDER_SYNC_SECRET') || strlen(CRM_ORDER_SYNC_SECRET) < 32) {
        throw new RuntimeException('A URL e o segredo de sincronização ERP → CRM não estão configurados.');
      }
      $url = parse_url(CRM_ORDER_SYNC_URL);
      $isLoopback = APP_ENV !== 'production' && is_array($url)
        && ($url['scheme'] ?? '') === 'http'
        && in_array($url['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
      if (!is_array($url) || (($url['scheme'] ?? '') !== 'https' && !$isLoopback)) {
        throw new RuntimeException('O endpoint CRM de pedidos tem de usar HTTPS.');
      }
      $payload = json_decode((string)$row['payload'], true, 16, JSON_THROW_ON_ERROR);
      $payload['event_id'] = (string)$row['event_id'];
      $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $timestamp = (string)time();
      $signature = hash_hmac('sha256', $timestamp . '.' . $row['event_id'] . '.' . $body, CRM_ORDER_SYNC_SECRET);
      $result = $transport
        ? $transport(CRM_ORDER_SYNC_URL, $body, [
          'timestamp' => $timestamp, 'event_id' => (string)$row['event_id'], 'signature' => $signature,
        ])
        : subscription_order_sync_http_post(CRM_ORDER_SYNC_URL, $body, [
          'Content-Type: application/json',
          'X-BXPERT-Timestamp: ' . $timestamp,
          'X-BXPERT-Event-Id: ' . $row['event_id'],
          'X-BXPERT-Signature: ' . $signature,
        ]);
      if (($result['status'] ?? 0) < 200 || ($result['status'] ?? 0) >= 300) {
        $response = json_decode((string)($result['body'] ?? ''), true);
        $reason = is_string($response['error'] ?? null) ? ': ' . $response['error'] : '';
        if (is_string($response['code'] ?? null)) $reason .= ' (' . $response['code'] . ')';
        throw new RuntimeException('CRM respondeu HTTP ' . (int)($result['status'] ?? 0) . $reason . '.');
      }
      $responseBody = (string)($result['body'] ?? '');
      $response = json_decode($responseBody, true, 8, JSON_THROW_ON_ERROR);
      $responseTimestamp = (string)($result['headers']['x-bxpert-response-timestamp'] ?? '');
      $responseSignature = (string)($result['headers']['x-bxpert-response-signature'] ?? '');
      $expectedResponseSignature = hash_hmac('sha256', $responseTimestamp . '.' . $responseBody, CRM_ORDER_SYNC_SECRET);
      if (!preg_match('/^\d{10}$/', $responseTimestamp)
        || abs(time() - (int)$responseTimestamp) > 300
        || !preg_match('/^[a-f0-9]{64}$/i', $responseSignature)
        || !hash_equals($expectedResponseSignature, strtolower($responseSignature))
        || ($response['accepted'] ?? false) !== true
        || !in_array($response['outcome'] ?? null, ['accepted', 'stale'], true)) {
        throw new RuntimeException('O CRM não confirmou a recepção autenticada do pedido.');
      }
      $pdo->prepare(
        'UPDATE subscription_order_sync_outbox
         SET delivered_at = UTC_TIMESTAMP(), last_attempt_at = UTC_TIMESTAMP(), last_error = NULL
         WHERE id = ? AND delivered_at IS NULL'
      )->execute([(int)$row['id']]);
      $sent++;
    } catch (Throwable $e) {
      $attempt = (int)$row['attempts'];
      $delay = min(3600, 5 * (2 ** min($attempt, 10)));
      $message = substr($e->getMessage(), 0, 1000);
      $pdo->prepare(
        'UPDATE subscription_order_sync_outbox
         SET attempts = attempts + 1, last_attempt_at = UTC_TIMESTAMP(),
             next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), last_error = ?
         WHERE id = ? AND delivered_at IS NULL'
      )->execute([$delay, $message, (int)$row['id']]);
      error_log('[subscription_order_sync] Order ' . (int)$row['order_id'] . ': ' . $message);
      $failed++;
    }
  }
  return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
}
