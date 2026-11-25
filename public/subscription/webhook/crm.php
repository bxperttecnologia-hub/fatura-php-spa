<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['accepted' => false, 'error' => 'Método não permitido.']);
  exit;
}

require_once dirname(__DIR__, 3) . '/app/config/db.php';
require_once dirname(__DIR__, 3) . '/app/helpers/crm_license_sync.php';
require_once dirname(__DIR__, 3) . '/app/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
  || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
if (APP_ENV === 'production' && !$isHttps) {
  http_response_code(400);
  echo json_encode(['accepted' => false, 'error' => 'A sincronização exige HTTPS.']);
  exit;
}
if (!defined('CRM_LICENSE_SYNC_SECRET') || CRM_LICENSE_SYNC_SECRET === '') {
  http_response_code(503);
  echo json_encode(['accepted' => false, 'error' => 'Sincronização de licenças não configurada.']);
  exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!is_array($headers)) $headers = [];
foreach ([
  'X-BXPERT-Timestamp' => 'HTTP_X_BXPERT_TIMESTAMP',
  'X-BXPERT-Event-Id' => 'HTTP_X_BXPERT_EVENT_ID',
  'X-BXPERT-Signature' => 'HTTP_X_BXPERT_SIGNATURE',
] as $name => $serverKey) {
  if (!isset($headers[$name]) && isset($_SERVER[$serverKey])) {
    $headers[$name] = $_SERVER[$serverKey];
  }
}
$rawBody = file_get_contents('php://input', false, null, 0, 32769);
if (!is_string($rawBody)) {
  http_response_code(400);
  echo json_encode(['accepted' => false, 'error' => 'Não foi possível ler o evento.']);
  exit;
}

$result = crm_license_sync_handle($pdo, $rawBody, $headers, CRM_LICENSE_SYNC_SECRET);
http_response_code($result['status']);
$responseBody = json_encode($result['body'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($result['status'] === 200 && is_string($responseBody)) {
  $responseTimestamp = (string)time();
  header('X-BXPERT-Response-Timestamp: ' . $responseTimestamp);
  header('X-BXPERT-Response-Signature: ' . hash_hmac(
    'sha256',
    $responseTimestamp . '.' . $responseBody,
    CRM_LICENSE_SYNC_SECRET
  ));
}
echo $responseBody;
