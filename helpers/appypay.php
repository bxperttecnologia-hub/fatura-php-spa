<?php
require_once __DIR__ . '/../config/appypay.php';

function appypay_is_configured(): bool {
  return APPYPAY_CLIENT_ID !== '' && APPYPAY_CLIENT_SECRET !== '' && APPYPAY_RESOURCE !== '' && APPYPAY_METHOD_REF !== '' && APPYPAY_METHOD_GPO !== '';
}

function appypay_token_cache_path(): string {
  return sys_get_temp_dir() . '/appypay_token_cache.json';
}

function appypay_get_token(): string {
  if (!appypay_is_configured()) {
    throw new Exception('AppyPay não configurado (client_id/secret/paymentMethods).');
  }

  $cacheFile = appypay_token_cache_path();
  if (is_file($cacheFile)) {
    $raw = @file_get_contents($cacheFile);
    $j = $raw ? json_decode($raw, true) : null;
    if (is_array($j) && !empty($j['access_token']) && !empty($j['expires_at'])) {
      if (time() < (int)$j['expires_at'] - 30) {
        return (string)$j['access_token'];
      }
    }
  }

  $fields = http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => APPYPAY_CLIENT_ID,
    'client_secret' => APPYPAY_CLIENT_SECRET,
    'resource' => APPYPAY_RESOURCE,
  ]);

  $ch = curl_init(APPYPAY_TOKEN_URL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);

  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false) {
    throw new Exception('Falha cURL (token): ' . $err);
  }

  $data = json_decode($resp, true);
  if (!is_array($data)) {
    throw new Exception('Resposta inválida do token AppyPay.');
  }

  if ($code >= 400 || empty($data['access_token'])) {
    throw new Exception('Falha ao obter token AppyPay: ' . ($data['error_description'] ?? $data['error'] ?? 'HTTP ' . $code));
  }

  $expiresIn = (int)($data['expires_in'] ?? 3600);
  @file_put_contents($cacheFile, json_encode([
    'access_token' => $data['access_token'],
    'expires_at' => time() + $expiresIn,
  ]));

  return (string)$data['access_token'];
}

function appypay_api_request(string $method, string $endpoint, ?array $json = null, ?array $query = null): array {
  $token = appypay_get_token();
  $url = rtrim(APPYPAY_API_BASE, '/') . $endpoint;
  if ($query && is_array($query) && count($query) > 0) {
    $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
  }

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Accept-Language: pt-BR',
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
  ]);

  if ($json !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
  }

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);

  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false) {
    throw new Exception('Falha cURL (appy): ' . $err);
  }

  $data = json_decode($resp, true);
  if (!is_array($data)) {
    $data = ['raw' => $resp];
  }

  return ['code' => $code, 'data' => $data, 'raw' => $resp];
}

function appypay_create_charge(array $payload): array {
  return appypay_api_request('POST', '/charges', $payload);
}

function appypay_get_charge(string $chargeId): array {
  $chargeId = trim($chargeId);
  if ($chargeId === '') throw new Exception('chargeId vazio');
  return appypay_api_request('GET', '/charges/' . rawurlencode($chargeId));
}

function appypay_method_id(string $kind): string {
  $kind = strtoupper(trim($kind));
  if ($kind === 'REF') return APPYPAY_METHOD_REF;
  if ($kind === 'GPO') return APPYPAY_METHOD_GPO;
  throw new Exception('Método de pagamento inválido.');
}
