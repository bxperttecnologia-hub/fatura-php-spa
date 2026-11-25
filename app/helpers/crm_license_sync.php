<?php

declare(strict_types=1);

require_once __DIR__ . '/subscription.php';

function crm_license_sync_header(array $headers, string $name): string
{
  foreach ($headers as $key => $value) {
    if (strcasecmp((string)$key, $name) === 0) return trim((string)$value);
  }
  return '';
}

function crm_license_sync_datetime(mixed $value): ?string
{
  if (!is_string($value) || $value === '') return null;
  try {
    $utc = new DateTimeZone('UTC');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $value)
      ? new DateTimeImmutable($value, $utc)
      : new DateTimeImmutable($value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) return null;
    return $date->setTimezone($utc)->format('Y-m-d H:i:s');
  } catch (Throwable) {
    return null;
  }
}

function crm_license_sync_date(mixed $value): ?string
{
  if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year) ? $value : null;
  }
  $datetime = crm_license_sync_datetime($value);
  if ($datetime === null) return null;
  return (new DateTimeImmutable($datetime, new DateTimeZone('UTC')))
    ->setTimezone(new DateTimeZone('Africa/Luanda'))->format('Y-m-d');
}

function crm_license_sync_validate_with_crm(array $event, string $secret, ?int $now = null): array
{
  if (!defined('CRM_LICENSE_VALIDATION_URL') || CRM_LICENSE_VALIDATION_URL === '') {
    throw new RuntimeException('crm_validation_not_configured');
  }
  $validationUrl = parse_url(CRM_LICENSE_VALIDATION_URL);
  if (!is_array($validationUrl) || empty($validationUrl['host'])
    || (defined('APP_ENV') && APP_ENV === 'production' && ($validationUrl['scheme'] ?? '') !== 'https')) {
    throw new RuntimeException('crm_validation_url_invalid');
  }
  if (!function_exists('curl_init')) throw new RuntimeException('crm_validation_transport_unavailable');

  $body = json_encode([
    'event_id' => $event['event_id'],
    'license_id' => $event['license_id'],
    'license_key' => $event['license_key'],
  ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  $timestamp = (string)($now ?? time());
  $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
  $responseHeaders = [];
  $curl = curl_init(CRM_LICENSE_VALIDATION_URL);
  if ($curl === false) throw new RuntimeException('crm_validation_transport_unavailable');
  curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'X-BXPERT-Timestamp: ' . $timestamp,
      'X-BXPERT-Signature: ' . $signature,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
      $length = strlen($line);
      $parts = explode(':', $line, 2);
      if (count($parts) === 2) $responseHeaders[trim($parts[0])] = trim($parts[1]);
      return $length;
    },
  ]);
  $responseBody = curl_exec($curl);
  $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
  $transportError = curl_error($curl);
  curl_close($curl);
  if (!is_string($responseBody) || $httpStatus !== 200) {
    throw new RuntimeException('crm_validation_unavailable:' . ($httpStatus ?: $transportError));
  }

  $responseTimestamp = crm_license_sync_header($responseHeaders, 'X-BXPERT-Validation-Timestamp');
  $responseSignature = crm_license_sync_header($responseHeaders, 'X-BXPERT-Validation-Signature');
  $now ??= time();
  if (!ctype_digit($responseTimestamp) || abs($now - (int)$responseTimestamp) > 300
    || !preg_match('/^[a-f0-9]{64}$/i', $responseSignature)
    || !hash_equals(hash_hmac('sha256', $responseTimestamp . '.' . $responseBody, $secret), strtolower($responseSignature))) {
    throw new RuntimeException('crm_validation_signature_invalid');
  }
  try {
    $result = json_decode($responseBody, true, 16, JSON_THROW_ON_ERROR);
  } catch (JsonException) {
    throw new RuntimeException('crm_validation_response_invalid');
  }
  if (!is_array($result) || ($result['event_id'] ?? null) !== $event['event_id'] || ($result['valid'] ?? false) !== true
    || !is_array($result['license'] ?? null)) {
    throw new DomainException('crm_license_not_found');
  }
  return $result['license'];
}

function crm_license_sync_record_failure(
  PDO $pdo,
  string $eventId,
  string $payloadHash,
  string $outcome,
  string $reason,
  ?int $licenseId,
  ?int $orderId,
  mixed $syncVersion
): void {
  try {
    $pdo->beginTransaction();
    $pdo->prepare(
      'INSERT INTO subscription_license_sync_events
       (event_id, payload_hash, license_id, source_order_id, sync_version, outcome, reason, received_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
       ON DUPLICATE KEY UPDATE outcome = VALUES(outcome), reason = VALUES(reason),
         received_at = VALUES(received_at), attempts = attempts + 1'
    )->execute([
      $eventId, $payloadHash, $licenseId, $orderId,
      filter_var($syncVersion, FILTER_VALIDATE_INT) ?: null, $outcome, substr($reason, 0, 255),
    ]);
    $pdo->prepare(
      'INSERT INTO subscription_license_sync_attempts (event_id, payload_hash, outcome, reason, attempted_at)
       VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$eventId, $payloadHash, $outcome, substr($reason, 0, 255)]);
    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[crm_license_sync] Could not persist result for ' . $eventId . ': ' . $e->getMessage());
  }
}

function crm_license_sync_validate_pair(array $event, array $verified): ?string
{
  $remote = [
    'license_id' => filter_var($verified['license_id'] ?? null, FILTER_VALIDATE_INT),
    'license_key' => $verified['license_key'] ?? null,
    'subscription_id' => filter_var($verified['subscription_id'] ?? null, FILTER_VALIDATE_INT),
    'subscription_order_id' => isset($verified['subscription_order_id'])
      ? filter_var($verified['subscription_order_id'], FILTER_VALIDATE_INT) : null,
    'company_id' => filter_var($verified['erp_company_id'] ?? null, FILTER_VALIDATE_INT),
    'crm_company_id' => filter_var($verified['crm_company_id'] ?? null, FILTER_VALIDATE_INT),
    'plan_code' => $verified['plan_code'] ?? null,
    'period_months' => filter_var($verified['period_months'] ?? null, FILTER_VALIDATE_INT),
    'operation_type' => $verified['operation_type'] ?? null,
    'start_date' => crm_license_sync_datetime($verified['start_date'] ?? null),
    'expires_at' => crm_license_sync_datetime($verified['expires_at'] ?? null),
    'license_status' => $verified['license_status'] ?? null,
  ];
  foreach (['license_id', 'subscription_id', 'company_id', 'crm_company_id', 'period_months'] as $field) {
    if (!$remote[$field]) return 'crm_validation_incomplete';
  }
  if (!is_string($remote['license_key']) || !is_string($remote['plan_code'])
    || $remote['plan_code'] === '' || !is_string($remote['license_status'])
    || !in_array($remote['operation_type'], ['NOVA_CONTRATACAO', 'RENOVACAO'], true)) {
    return 'crm_validation_incomplete';
  }

  $expectedOrderId = $event['subscription_order_id'];
  $expectedStart = crm_license_sync_datetime($event['start_date']);
  $expectedExpiry = crm_license_sync_datetime($event['expires_at']);
  if ((int)$remote['license_id'] !== $event['license_id']
    || !hash_equals($remote['license_key'], $event['license_key'])
    || (int)$remote['subscription_id'] !== $event['subscription_id']
    || $remote['subscription_order_id'] !== $expectedOrderId
    || (int)$remote['company_id'] !== $event['company_id']
    || (int)$remote['crm_company_id'] !== $event['crm_company_id']
    || !hash_equals($remote['plan_code'], $event['plan_code'])
    || $remote['period_months'] !== $event['period_months']
    || $remote['operation_type'] !== $event['operation_type']
    || $remote['start_date'] !== $expectedStart
    || $remote['expires_at'] !== $expectedExpiry) {
    return 'crm_license_mismatch';
  }
  $allowedRemoteStatuses = match ($event['event_type'] ?? null) {
    'license.activate' => ['pending'],
    'license.reactivate' => ['suspended'],
    default => [$event['license_status']],
  };
  if (!in_array($remote['license_status'], $allowedRemoteStatuses, true)) return 'crm_license_mismatch';
  return null;
}

function crm_license_sync_handle(
  PDO $pdo,
  string $rawBody,
  array $headers,
  string $secret,
  ?int $now = null,
  ?callable $validator = null
): array {
  $timestamp = crm_license_sync_header($headers, 'X-BXPERT-Timestamp');
  $eventHeader = strtolower(crm_license_sync_header($headers, 'X-BXPERT-Event-Id'));
  $signature = strtolower(crm_license_sync_header($headers, 'X-BXPERT-Signature'));
  $now ??= time();
  if ($secret === '' || !ctype_digit($timestamp) || abs($now - (int)$timestamp) > 300
    || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/', $eventHeader)
    || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
    return ['status' => 401, 'body' => ['accepted' => false, 'error' => 'Autenticação inválida.']];
  }
  if (strlen($rawBody) > 32768) {
    return ['status' => 413, 'body' => ['accepted' => false, 'error' => 'Evento demasiado grande.']];
  }
  if (!hash_equals(hash_hmac('sha256', $timestamp . '.' . $eventHeader . '.' . $rawBody, $secret), $signature)) {
    return ['status' => 401, 'body' => ['accepted' => false, 'error' => 'Autenticação inválida.']];
  }
  try {
    $event = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
  } catch (JsonException) {
    return ['status' => 400, 'body' => ['accepted' => false, 'error' => 'JSON inválido.']];
  }
  if (!is_array($event)) {
    return ['status' => 400, 'body' => ['accepted' => false, 'error' => 'Evento inválido.']];
  }

  $eventId = strtolower((string)($event['event_id'] ?? ''));
  if (!hash_equals($eventHeader, $eventId)) {
    return ['status' => 401, 'body' => ['accepted' => false, 'error' => 'Identificador do evento inválido.']];
  }
  $orderValue = $event['subscription_order_id'] ?? $event['source_order_id'] ?? null;
  $licenseId = filter_var($event['license_id'] ?? null, FILTER_VALIDATE_INT);
  $subscriptionId = filter_var($event['subscription_id'] ?? null, FILTER_VALIDATE_INT);
  $companyId = filter_var($event['company_id'] ?? null, FILTER_VALIDATE_INT);
  $crmCompanyId = filter_var($event['crm_company_id'] ?? null, FILTER_VALIDATE_INT);
  $syncVersion = filter_var($event['sync_version'] ?? null, FILTER_VALIDATE_INT);
  $orderId = $orderValue === null ? null : filter_var($orderValue, FILTER_VALIDATE_INT);
  $planCode = $event['plan_code'] ?? null;
  $periodMonths = filter_var($event['period_months'] ?? null, FILTER_VALIDATE_INT);
  $licenseKey = $event['license_key'] ?? null;
  $eventType = $event['event_type'] ?? null;
  $operationType = $event['operation_type'] ?? null;
  $licenseStatus = $event['license_status'] ?? $event['status'] ?? null;
  $startDate = $event['start_date'] ?? $event['valid_from'] ?? null;
  $expiresAt = $event['expires_at'] ?? null;
  $validFrom = crm_license_sync_datetime($startDate);
  $expiry = crm_license_sync_datetime($expiresAt);
  $payloadHash = hash('sha256', $rawBody);
  $allowedStatuses = [
    'license.issued' => ['pending'], 'license.updated' => ['pending', 'active', 'suspended', 'expired', 'cancelled'],
    'license.activate' => ['active'], 'license.suspend' => ['suspended'], 'license.reactivate' => ['active'],
    'license.cancel' => ['cancelled'], 'license.expired' => ['expired'], 'license.renewed' => ['active', 'suspended'],
  ];
  if (!$licenseId || !$subscriptionId || !$companyId || !$crmCompanyId || !$syncVersion
    || !$periodMonths || $periodMonths > 36
    || ($orderValue !== null && !$orderId)
    || !is_string($planCode) || $planCode === '' || strlen($planCode) > 40 || trim($planCode) !== $planCode
    || !is_string($licenseKey) || $licenseKey === '' || strlen($licenseKey) > 100
    || !is_string($eventType) || !in_array($licenseStatus, $allowedStatuses[$eventType] ?? [], true)
    || !in_array($operationType, ['NOVA_CONTRATACAO', 'RENOVACAO'], true)
    || subscription_internal_plan_code($planCode) === null
    || $validFrom === null || $expiry === null || $expiry <= $validFrom) {
    if (preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/', $eventId)) {
      crm_license_sync_record_failure(
        $pdo, $eventId, $payloadHash, 'rejected', 'invalid_event_fields',
        $licenseId ?: null, $orderId ?: null, $event['sync_version'] ?? null
      );
    }
    return ['status' => 422, 'body' => ['accepted' => false, 'outcome' => 'rejected', 'reason' => 'invalid_event_fields']];
  }

  $lockName = 'crm_license_sync_' . $eventId;
  $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
  $lock->execute([$lockName]);
  if ((int)$lock->fetchColumn() !== 1) {
    return ['status' => 503, 'body' => ['accepted' => false, 'error' => 'Evento em processamento.']];
  }

  try {
    $existing = $pdo->prepare(
      'SELECT payload_hash, outcome, reason FROM subscription_license_sync_events WHERE event_id = ? LIMIT 1'
    );
    $existing->execute([$eventId]);
    $prior = $existing->fetch(PDO::FETCH_ASSOC);
    if ($prior) {
      if (!hash_equals((string)$prior['payload_hash'], $payloadHash)) {
        return ['status' => 409, 'body' => ['accepted' => false, 'error' => 'O identificador do evento já foi utilizado.']];
      }
      if (in_array($prior['outcome'], ['accepted', 'stale'], true)) {
        return ['status' => 200, 'body' => [
          'accepted' => true, 'duplicate' => true, 'event_id' => $eventId,
          'outcome' => $prior['outcome'], 'reason' => $prior['reason'],
        ]];
      }
    }

    try {
      $validationError = null;
      $verified = $validator
        ? $validator($event)
        : crm_license_sync_validate_with_crm([
          'event_id' => $eventId, 'license_id' => $licenseId, 'license_key' => $licenseKey,
        ], $secret, $now);
    } catch (DomainException $e) {
      $verified = null;
      $validationError = 'crm_license_not_found';
    } catch (Throwable $e) {
      crm_license_sync_record_failure(
        $pdo, $eventId, $payloadHash, 'failed', 'crm_validation_unavailable',
        $licenseId, $orderId, $event['sync_version'] ?? null
      );
      if ($orderId) {
        try {
          $pdo->prepare(
            "UPDATE subscription_orders SET subscription_status = 'sync_failed'
             WHERE id = ? AND company_id = ? AND status = 'paid'"
          )->execute([$orderId, $companyId]);
        } catch (Throwable $statusError) {
          error_log('[crm_license_sync] Could not mark order ' . $orderId . ' as failed: ' . $statusError->getMessage());
        }
      }
      error_log('[crm_license_sync] CRM validation failed for event ' . $eventId . ': ' . $e->getMessage());
      return ['status' => 503, 'body' => ['accepted' => false, 'outcome' => 'failed', 'reason' => 'crm_validation_unavailable']];
    }

    $reason = $verified === null ? ($validationError ?? 'crm_license_not_found')
      : crm_license_sync_validate_pair([
        'license_id' => $licenseId, 'license_key' => $licenseKey, 'subscription_id' => $subscriptionId,
        'subscription_order_id' => $orderId, 'company_id' => $companyId, 'crm_company_id' => $crmCompanyId,
        'plan_code' => $planCode, 'period_months' => $periodMonths,
        'operation_type' => $operationType, 'event_type' => $eventType,
        'license_status' => $licenseStatus, 'start_date' => $startDate, 'expires_at' => $expiresAt,
      ], $verified);
    if ($reason === null && $licenseStatus === 'active'
      && ($validFrom > gmdate('Y-m-d H:i:s', $now) || $expiry <= gmdate('Y-m-d H:i:s', $now))) {
      $reason = 'license_outside_validity';
    }
    $pdo->beginTransaction();
    $outcome = $reason === null ? 'accepted' : 'rejected';
    $subscription = null;
    $order = null;
    $localSubscriptionId = null;
    $localPlanId = null;
    $stale = false;

    if ($reason === null) {
      $stmt = $pdo->prepare(
        'SELECT id FROM plans WHERE legacy_code = ? AND duration_months = ? LIMIT 2 FOR UPDATE'
      );
      $stmt->execute([$planCode, $periodMonths]);
      $matchingPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
      if (count($matchingPlans) !== 1) {
        $reason = 'plan_mapping_missing';
      } else {
        $localPlanId = (int)$matchingPlans[0]['id'];
      }
    }

    if ($reason === null && $orderId !== null) {
      $stmt = $pdo->prepare(
        'SELECT id, company_id, subscription_id, plan_id, plan_code, period_months, operation, status
         FROM subscription_orders WHERE id = ? LIMIT 1 FOR UPDATE'
      );
      $stmt->execute([$orderId]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      if (!$order || $order['status'] !== 'paid' || (int)$order['company_id'] !== $companyId
        || !hash_equals((string)$order['plan_code'], $planCode)
        || (int)$order['period_months'] !== $periodMonths) {
        $reason = 'source_order_mismatch';
      } elseif ($order['plan_id'] === null) {
        $pdo->prepare('UPDATE subscription_orders SET plan_id = ? WHERE id = ? AND plan_id IS NULL')
          ->execute([$localPlanId, $orderId]);
        $order['plan_id'] = $localPlanId;
      } elseif ((int)$order['plan_id'] !== $localPlanId) {
        $reason = 'source_order_plan_mismatch';
      }
    }

    if ($reason === null) {
      $stmt = $pdo->prepare(
        'SELECT license_id, subscription_id, crm_subscription_id, source_order_id, company_id
         FROM subscription_license_links
         WHERE license_id = ? OR (? IS NOT NULL AND source_order_id = ?)
         LIMIT 1 FOR UPDATE'
      );
      $stmt->execute([$licenseId, $orderId, $orderId]);
      $link = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      if ($link) {
        if ((int)$link['license_id'] !== $licenseId || (int)$link['company_id'] !== $companyId
          || (int)$link['crm_subscription_id'] !== $subscriptionId
          || ($orderId !== null && (int)$link['source_order_id'] !== $orderId)
          || ($order && !empty($order['subscription_id'])
            && (int)$link['subscription_id'] !== (int)$order['subscription_id'])) {
          $reason = 'license_already_linked';
        } else {
          $localSubscriptionId = (int)$link['subscription_id'];
        }
      } elseif ($order && !empty($order['subscription_id'])) {
        $localSubscriptionId = (int)$order['subscription_id'];
      } elseif ($order && (string)$order['operation'] === 'new') {
        $startLocal = crm_license_sync_date($startDate);
        $expiryLocal = crm_license_sync_date($expiresAt);
        $pdo->prepare(
          "INSERT INTO subscriptions (company_id, plan_id, start_date, end_date, status)
           VALUES (?, ?, ?, ?, 'awaiting_sync')"
        )->execute([$companyId, $localPlanId, $startLocal . ' 00:00:00', $expiryLocal . ' 00:00:00']);
        $localSubscriptionId = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE subscription_orders SET subscription_id = ? WHERE id = ? AND subscription_id IS NULL')
          ->execute([$localSubscriptionId, $orderId]);
      } elseif (($event['operation_type'] ?? null) === 'NOVA_CONTRATACAO') {
        $startLocal = crm_license_sync_date($startDate);
        $expiryLocal = crm_license_sync_date($expiresAt);
        $pdo->prepare(
          "INSERT INTO subscriptions (company_id, plan_id, start_date, end_date, status)
           VALUES (?, ?, ?, ?, 'awaiting_sync')"
        )->execute([$companyId, $localPlanId, $startLocal . ' 00:00:00', $expiryLocal . ' 00:00:00']);
        $localSubscriptionId = (int)$pdo->lastInsertId();
      } elseif (($event['operation_type'] ?? null) === 'RENOVACAO') {
        $stmt = $pdo->prepare(
          'SELECT subscription_id FROM subscription_license_links
           WHERE crm_subscription_id = ? AND company_id = ? ORDER BY updated_at DESC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$subscriptionId, $companyId]);
        $localSubscriptionId = (int)($stmt->fetchColumn() ?: 0) ?: null;
        if ($localSubscriptionId === null) $reason = 'subscription_relationship_mismatch';
      } else {
        $reason = 'subscription_relationship_mismatch';
      }
    }

    if ($reason === null) {
      $stmt = $pdo->prepare('SELECT id, company_id, status, license_sync_version FROM subscriptions WHERE id = ? LIMIT 1 FOR UPDATE');
      $stmt->execute([$localSubscriptionId]);
      $subscription = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      if (!$subscription || (int)$subscription['company_id'] !== $companyId) {
        $reason = 'subscription_relationship_mismatch';
      } elseif ($eventType === 'license.activate' && $subscription['status'] === 'cancelled') {
        $reason = 'subscription_not_activatable';
      } elseif ($syncVersion < (int)$subscription['license_sync_version']) {
        $stale = true;
      } elseif ($syncVersion === (int)$subscription['license_sync_version']) {
        $reason = 'duplicate_sync_version';
      }
    }

    if ($reason === null && !$stale) {
      $stmt = $pdo->prepare(
        'SELECT id, plan_code, plan_started_at, plan_expires_at, plan_status
         FROM companies WHERE id = ? LIMIT 1 FOR UPDATE'
      );
      $stmt->execute([$companyId]);
      $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      if (!$company) $reason = 'company_not_found';
    }

    if ($reason === null && !$stale) {
      $stmt = $pdo->prepare(
        'SELECT license_id FROM subscription_license_links WHERE license_id = ? LIMIT 1 FOR UPDATE'
      );
      $stmt->execute([$licenseId]);
      $linkExists = $stmt->fetchColumn() !== false;
      if ($linkExists) {
        $pdo->prepare(
          'UPDATE subscription_license_links
           SET subscription_id = ?, crm_subscription_id = ?, source_order_id = ?, company_id = ?, plan_code = ?, license_status = ?,
               start_date = ?, expires_at = ?, license_key_hash = ?, updated_at = UTC_TIMESTAMP()
           WHERE license_id = ?'
        )->execute([
          $localSubscriptionId, $subscriptionId, $orderId, $companyId, $planCode, $licenseStatus, $validFrom, $expiry,
          hash('sha256', $licenseKey), $licenseId,
        ]);
      } else {
        $pdo->prepare(
          'INSERT INTO subscription_license_links
           (license_id, subscription_id, crm_subscription_id, source_order_id, company_id, plan_code, license_status,
            start_date, expires_at, license_key_hash, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([
          $licenseId, $localSubscriptionId, $subscriptionId, $orderId, $companyId, $planCode, $licenseStatus,
          $validFrom, $expiry, hash('sha256', $licenseKey),
        ]);
      }

      $targetStatus = match ($licenseStatus) {
        'pending' => 'awaiting_sync',
        'active' => 'active',
        'suspended' => 'suspended',
        'expired' => 'expired',
        'cancelled' => 'cancelled',
        default => 'awaiting_sync',
      };
      if ($licenseStatus === 'active') {
        $startLocal = crm_license_sync_date($startDate);
        $expiryLocal = crm_license_sync_date($expiresAt);
        $pdo->prepare(
          'UPDATE subscriptions SET plan_id = ?, start_date = ?, end_date = ?, status = ?, license_sync_version = ? WHERE id = ? AND company_id = ?'
        )->execute([$localPlanId, $startLocal . ' 00:00:00', $expiryLocal . ' 00:00:00', $targetStatus, $syncVersion, $localSubscriptionId, $companyId]);
        $companyExpiry = crm_license_sync_date($company['plan_expires_at'] ?? null);
        $companyActive = ($company['plan_status'] ?? null) === 'active';
        $advances = !$companyActive || $companyExpiry === null || $expiryLocal >= $companyExpiry;
        if ($advances) {
          $companyStart = $companyActive
            && subscription_erp_plan_code((string)($company['plan_code'] ?? '')) === $planCode
            && !empty($company['plan_started_at'])
            ? crm_license_sync_date($company['plan_started_at'])
            : $startLocal;
          $pdo->prepare(
            "UPDATE companies SET plan_code = ?, plan_started_at = ?, plan_expires_at = ?, plan_status = 'active'
             WHERE id = ?"
          )->execute([
            subscription_internal_plan_code($planCode),
            $companyStart,
            $expiryLocal,
            $companyId,
          ]);
        }
      } else {
        if ($licenseStatus === 'pending' && $subscription['status'] === 'active') {
          $pdo->prepare('UPDATE subscriptions SET license_sync_version = ? WHERE id = ? AND company_id = ?')
            ->execute([$syncVersion, $localSubscriptionId, $companyId]);
        } else {
          $pdo->prepare('UPDATE subscriptions SET status = ?, license_sync_version = ? WHERE id = ? AND company_id = ?')
            ->execute([$targetStatus, $syncVersion, $localSubscriptionId, $companyId]);
        }
        $stmt = $pdo->prepare(
          "SELECT 1 FROM subscription_license_links
           WHERE company_id = ? AND license_status = 'active' AND expires_at > UTC_TIMESTAMP() LIMIT 1"
        );
        $stmt->execute([$companyId]);
        if (!$stmt->fetchColumn()) {
          $pdo->prepare('UPDATE companies SET plan_status = ? WHERE id = ?')->execute([$targetStatus, $companyId]);
        }
      }
      if ($orderId !== null) {
        $orderStatus = $licenseStatus === 'pending' ? 'license_issued' : $targetStatus;
        $pdo->prepare('UPDATE subscription_orders SET subscription_status = ? WHERE id = ?')
          ->execute([$orderStatus, $orderId]);
      }
    }

    $outcome = $stale ? 'stale' : ($reason === null ? 'accepted' : 'rejected');
    $pdo->prepare(
      'INSERT INTO subscription_license_sync_events
       (event_id, payload_hash, license_id, source_order_id, sync_version, outcome, reason, received_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
       ON DUPLICATE KEY UPDATE outcome = VALUES(outcome), reason = VALUES(reason), received_at = VALUES(received_at),
         attempts = attempts + 1'
    )->execute([
      $eventId, $payloadHash, $licenseId, $orderId,
      $syncVersion, $outcome, $reason,
    ]);
    $pdo->prepare(
      'INSERT INTO subscription_license_sync_attempts (event_id, payload_hash, outcome, reason, attempted_at)
       VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$eventId, $payloadHash, $outcome === 'stale' ? 'accepted' : $outcome, $reason]);
    if ($reason !== null && $orderId !== null) {
      $pdo->prepare(
        "UPDATE subscription_orders SET subscription_status = 'license_rejected'
         WHERE id = ? AND company_id = ? AND status = 'paid'"
      )->execute([$orderId, $companyId]);
    }
    $pdo->commit();

    if ($reason !== null) {
      error_log('[crm_license_sync] Event ' . $eventId . ' rejected: ' . $reason);
      return ['status' => 422, 'body' => ['accepted' => false, 'outcome' => 'rejected', 'reason' => $reason]];
    }
    if ($stale) return ['status' => 200, 'body' => ['accepted' => true, 'event_id' => $eventId, 'outcome' => 'stale']];
    return ['status' => 200, 'body' => ['accepted' => true, 'event_id' => $eventId, 'outcome' => 'accepted']];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    crm_license_sync_record_failure(
      $pdo, $eventId, $payloadHash, 'failed', 'erp_processing_failed',
      $licenseId ?: null, $orderId ?: null, $event['sync_version'] ?? null
    );
    if ($orderId !== null) {
      try {
        $pdo->prepare(
          "UPDATE subscription_orders SET subscription_status = 'sync_failed'
           WHERE id = ? AND company_id = ? AND status = 'paid'"
        )->execute([$orderId, $companyId]);
      } catch (Throwable $statusError) {
        error_log('[crm_license_sync] Could not update failed order ' . $orderId . ': ' . $statusError->getMessage());
      }
    }
    error_log('[crm_license_sync] Event processing failed ' . $eventId . ': ' . $e->getMessage());
    return ['status' => 503, 'body' => ['accepted' => false, 'outcome' => 'failed', 'error' => 'Evento não processado.']];
  } finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);
  }
}
