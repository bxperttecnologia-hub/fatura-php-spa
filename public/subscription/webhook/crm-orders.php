<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => false, 'code' => 'method_not_allowed']);
  exit;
}

require_once dirname(__DIR__, 3) . '/app/config/db.php';
require_once dirname(__DIR__, 3) . '/app/config/config.php';
require_once dirname(__DIR__, 3) . '/app/helpers/crm_orders_api.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
  || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
if (APP_ENV === 'production' && !$isHttps) {
  http_response_code(400);
  echo json_encode(['success' => false, 'code' => 'https_required']);
  exit;
}
if (!defined('CRM_ORDER_SYNC_SECRET') || strlen(CRM_ORDER_SYNC_SECRET) < 32) {
  http_response_code(503);
  echo json_encode(['success' => false, 'code' => 'order_sync_not_configured']);
  exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!is_array($headers)) $headers = [];
foreach ([
  'X-BXPERT-Timestamp' => 'HTTP_X_BXPERT_TIMESTAMP',
  'X-BXPERT-Signature' => 'HTTP_X_BXPERT_SIGNATURE',
] as $name => $serverKey) {
  if (!isset($headers[$name]) && isset($_SERVER[$serverKey])) $headers[$name] = $_SERVER[$serverKey];
}
$rawBody = file_get_contents('php://input', false, null, 0, 16385);
if (!is_string($rawBody)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'code' => 'request_unreadable']);
  exit;
}

$result = crm_orders_api_handle($pdo, $rawBody, $headers, CRM_ORDER_SYNC_SECRET);
$responseBody = json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
http_response_code($result['status']);
if ($result['authenticated'] && is_string($responseBody)) {
  $responseTimestamp = (string)time();
  header('X-BXPERT-Response-Timestamp: ' . $responseTimestamp);
  header('X-BXPERT-Response-Signature: ' . hash_hmac(
    'sha256',
    $responseTimestamp . '.' . $responseBody,
    CRM_ORDER_SYNC_SECRET
  ));
}
echo $responseBody;
