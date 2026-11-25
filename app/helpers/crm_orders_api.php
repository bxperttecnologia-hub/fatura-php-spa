<?php

declare(strict_types=1);

function crm_orders_api_header(array $headers, string $name): string
{
  foreach ($headers as $key => $value) {
    if (strcasecmp((string)$key, $name) === 0) return trim((string)$value);
  }
  return '';
}

function crm_orders_api_shape_order(array $row): array
{
  $row['id'] = (int)$row['id'];
  $row['erp_company_id'] = (int)$row['erp_company_id'];
  $row['period_months'] = (int)$row['period_months'];
  $row['crm_subscription_id'] = $row['crm_subscription_id'] === null
    ? null : (int)$row['crm_subscription_id'];
  $row['customer_email'] = filter_var($row['customer_email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null;
  return $row;
}

function crm_orders_api_plan_catalog(PDO $pdo): array
{
  $stmt = $pdo->query(
    'SELECT name, legacy_code, duration_months, price FROM plans ORDER BY legacy_code, duration_months, name'
  );
  $erpPlans = array_map(static function (array $row): array {
    $row['legacy_code'] = $row['legacy_code'] === null ? null : (string)$row['legacy_code'];
    $row['duration_months'] = (int)$row['duration_months'];
    $row['price'] = (string)$row['price'];
    return $row;
  }, $stmt->fetchAll(PDO::FETCH_ASSOC));
  return ['erp_plans' => $erpPlans];
}

function crm_orders_api_orders(PDO $pdo, array $request): array
{
  $select = "SELECT so.id, so.company_id AS erp_company_id, c.name AS company_name,
                    c.registration_number, c.currency, u.name AS customer_name, u.email AS customer_email,
                    so.plan_code, p.name AS plan_name, so.operation, so.period_months, so.amount,
                    so.status AS payment_status, so.subscription_status, so.created_at, so.paid_at,
                    so.license_issued_at,
                    (SELECT l.crm_subscription_id FROM subscription_license_links l
                     WHERE l.subscription_id = so.subscription_id AND l.company_id = so.company_id
                     ORDER BY l.updated_at DESC LIMIT 1) AS crm_subscription_id";
  $from = ' FROM subscription_orders so
            INNER JOIN companies c ON c.id = so.company_id
            LEFT JOIN users u ON u.id = so.created_by_user_id
            LEFT JOIN plans p ON p.id = so.plan_id';

  if (($request['action'] ?? null) === 'get') {
    $stmt = $pdo->prepare($select . $from . ' WHERE so.id = ? LIMIT 1');
    $stmt->execute([(int)$request['order_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['order' => $row ? crm_orders_api_shape_order($row) : null];
  }

  $clauses = [];
  $values = [];
  if (isset($request['company_id'])) {
    $clauses[] = 'so.company_id = ?';
    $values[] = (int)$request['company_id'];
  }
  if (isset($request['payment_status'])) {
    $clauses[] = 'so.status = ?';
    $values[] = $request['payment_status'];
  }
  if (isset($request['subscription_status'])) {
    $clauses[] = 'so.subscription_status = ?';
    $values[] = $request['subscription_status'];
  }
  if (isset($request['plan_code'])) {
    $clauses[] = 'so.plan_code = ?';
    $values[] = $request['plan_code'];
  }
  if (isset($request['period_months'])) {
    $clauses[] = 'so.period_months = ?';
    $values[] = (int)$request['period_months'];
  }
  if (isset($request['created_from'])) {
    $clauses[] = 'so.created_at >= ?';
    $values[] = $request['created_from'] . ' 00:00:00';
  }
  if (isset($request['created_to'])) {
    $clauses[] = 'so.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $values[] = $request['created_to'];
  }
  if (isset($request['company'])) {
    $clauses[] = '(c.name LIKE ? OR c.registration_number LIKE ? OR CAST(c.id AS CHAR) = ?)';
    $like = '%' . $request['company'] . '%';
    array_push($values, $like, $like, $request['company']);
  }
  if (isset($request['search'])) {
    $clauses[] = '(CAST(so.id AS CHAR) = ? OR so.plan_code LIKE ? OR u.email LIKE ?)';
    array_push($values, $request['search'], '%' . $request['search'] . '%', '%' . $request['search'] . '%');
  }

  $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
  $count = $pdo->prepare('SELECT COUNT(*)' . $from . $where);
  $count->execute($values);
  $total = (int)$count->fetchColumn();

  $page = (int)$request['page'];
  $limit = (int)$request['limit'];
  $stmt = $pdo->prepare(
    $select . $from . $where . ' ORDER BY so.created_at DESC, so.id DESC LIMIT ' . $limit
      . ' OFFSET ' . (($page - 1) * $limit)
  );
  $stmt->execute($values);
  $orders = array_map('crm_orders_api_shape_order', $stmt->fetchAll(PDO::FETCH_ASSOC));
  return ['orders' => $orders, 'total' => $total];
}

function crm_orders_api_handle(
  PDO $pdo,
  string $rawBody,
  array $headers,
  string $secret,
  ?int $now = null
): array {
  $now ??= time();
  $timestamp = crm_orders_api_header($headers, 'X-BXPERT-Timestamp');
  $signature = strtolower(crm_orders_api_header($headers, 'X-BXPERT-Signature'));
  $authenticated = strlen($secret) >= 32
    && preg_match('/^\d{10}$/', $timestamp)
    && abs($now - (int)$timestamp) <= 300
    && preg_match('/^[a-f0-9]{64}$/', $signature)
    && hash_equals(hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret), $signature);
  if (!$authenticated) {
    return ['status' => 401, 'authenticated' => false, 'body' => ['success' => false, 'code' => 'invalid_auth']];
  }
  if (strlen($rawBody) > 16384) {
    return ['status' => 413, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'request_too_large']];
  }
  try {
    $request = json_decode($rawBody, true, 12, JSON_THROW_ON_ERROR);
  } catch (JsonException) {
    return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_json']];
  }
  if (!is_array($request) || !in_array($request['action'] ?? null, ['list', 'get', 'plans'], true)) {
    return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_action']];
  }

  if ($request['action'] === 'plans') {
    try {
      return [
        'status' => 200, 'authenticated' => true,
        'body' => ['success' => true] + crm_orders_api_plan_catalog($pdo),
      ];
    } catch (Throwable $e) {
      error_log('[crm_orders_api] Could not fetch plan catalog: ' . $e->getMessage());
      return ['status' => 503, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'plan_catalog_query_failed']];
    }
  }

  if ($request['action'] === 'get') {
    $orderId = filter_var($request['order_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$orderId || $orderId < 1) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_order_id']];
    }
    try {
      return [
        'status' => 200, 'authenticated' => true,
        'body' => ['success' => true] + crm_orders_api_orders($pdo, ['action' => 'get', 'order_id' => $orderId]),
      ];
    } catch (Throwable $e) {
      error_log('[crm_orders_api] Could not fetch order ' . $orderId . ': ' . $e->getMessage());
      return ['status' => 503, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'order_query_failed']];
    }
  }

  $page = filter_var($request['page'] ?? 1, FILTER_VALIDATE_INT);
  $limit = filter_var($request['limit'] ?? 25, FILTER_VALIDATE_INT);
  if (!$page || $page < 1 || !$limit || $limit < 1 || $limit > 100) {
    return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_pagination']];
  }
  $filters = ['action' => 'list', 'page' => $page, 'limit' => $limit];
  foreach (['company_id' => 20, 'period_months' => 36] as $field => $maximum) {
    if (!array_key_exists($field, $request) || $request[$field] === null) continue;
    $value = filter_var($request[$field], FILTER_VALIDATE_INT);
    if (!$value || $value < 1 || $value > $maximum) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_' . $field]];
    }
    $filters[$field] = $value;
  }
  if (isset($request['payment_status'])) {
    if (!in_array($request['payment_status'], ['pending', 'paid', 'canceled'], true)) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_payment_status']];
    }
    $filters['payment_status'] = $request['payment_status'];
  }
  if (isset($request['subscription_status'])) {
    if (!is_string($request['subscription_status']) || !preg_match('/^[a-z_]{1,30}$/', $request['subscription_status'])) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_subscription_status']];
    }
    $filters['subscription_status'] = $request['subscription_status'];
  }
  if (isset($request['plan_code'])) {
    if (!is_string($request['plan_code']) || !preg_match('/^[A-Z0-9_]{1,40}$/', $request['plan_code'])) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_plan_code']];
    }
    $filters['plan_code'] = $request['plan_code'];
  }
  foreach (['search' => 100, 'company' => 100] as $field => $maxLength) {
    if (!array_key_exists($field, $request) || $request[$field] === '') continue;
    if (!is_string($request[$field]) || strlen($request[$field]) > $maxLength) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_' . $field]];
    }
    $filters[$field] = trim($request[$field]);
  }
  foreach (['created_from', 'created_to'] as $field) {
    if (!isset($request[$field])) continue;
    if (!is_string($request[$field]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $request[$field])
      || !checkdate((int)substr($request[$field], 5, 2), (int)substr($request[$field], 8, 2), (int)substr($request[$field], 0, 4))) {
      return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_date_filter']];
    }
    $filters[$field] = $request[$field];
  }
  if (isset($filters['created_from'], $filters['created_to']) && $filters['created_from'] > $filters['created_to']) {
    return ['status' => 400, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'invalid_date_range']];
  }

  try {
    return [
      'status' => 200, 'authenticated' => true,
      'body' => ['success' => true] + crm_orders_api_orders($pdo, $filters),
    ];
  } catch (Throwable $e) {
    error_log('[crm_orders_api] Could not list orders: ' . $e->getMessage());
    return ['status' => 503, 'authenticated' => true, 'body' => ['success' => false, 'code' => 'order_query_failed']];
  }
}
