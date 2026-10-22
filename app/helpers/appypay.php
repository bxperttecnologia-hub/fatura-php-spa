<?php
require_once __DIR__ . '/../config/appypay.php';

function appypay_is_configured(): bool
{
  return APPYPAY_ENV !== 'invalid'
    && APPYPAY_API_BASE !== ''
    && APPYPAY_TOKEN_URL !== ''
    && APPYPAY_CLIENT_ID !== ''
    && APPYPAY_CLIENT_SECRET !== ''
    && APPYPAY_RESOURCE !== ''
    && APPYPAY_METHOD_REF !== ''
    && APPYPAY_METHOD_GPO !== '';
}

function appypay_token_cache_path(): string
{
  $key = hash('sha256', APPYPAY_ENV . "\0" . APPYPAY_CLIENT_ID . "\0" . APPYPAY_RESOURCE);
  return sys_get_temp_dir() . '/appypay_token_' . $key . '.json';
}

function appypay_set_http_transport_for_testing(?callable $transport): void
{
  $GLOBALS['appypay_http_transport_for_testing'] = $transport;
}

function appypay_http_request(string $url, array $options): array
{
  $testTransport = $GLOBALS['appypay_http_transport_for_testing'] ?? null;
  if (is_callable($testTransport)) {
    return $testTransport($url, $options);
  }

  $ch = curl_init($url);
  if ($ch === false) {
    return ['body' => '', 'code' => 0, 'error' => 'cURL initialisation failed'];
  }
  if (APPYPAY_CA_BUNDLE !== '') {
    if (!is_file(APPYPAY_CA_BUNDLE) || !is_readable(APPYPAY_CA_BUNDLE)) {
      curl_close($ch);
      return ['body' => '', 'code' => 0, 'error' => 'Configured AppyPay CA bundle is not readable'];
    }
    $options[CURLOPT_CAINFO] = APPYPAY_CA_BUNDLE;
  }
  curl_setopt_array($ch, $options);
  $body = curl_exec($ch);
  $error = $body === false ? curl_error($ch) : '';
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  return ['body' => $body === false ? '' : $body, 'code' => $code, 'error' => $error];
}

function appypay_get_token(): string
{
  if (!appypay_is_configured()) {
    throw new RuntimeException('AppyPay não está configurado para o ambiente activo.');
  }

  $cacheFile = appypay_token_cache_path();
  $previousUmask = umask(0077);
  $cache = fopen($cacheFile, 'c+');
  umask($previousUmask);
  if ($cache === false) {
    throw new RuntimeException('Não foi possível abrir a cache segura do AppyPay.');
  }

  try {
    if (!flock($cache, LOCK_EX)) {
      throw new RuntimeException('Não foi possível bloquear a cache do AppyPay.');
    }
    @chmod($cacheFile, 0600);

    rewind($cache);
    $rawCache = stream_get_contents($cache);
    $cached = $rawCache !== false ? json_decode($rawCache, true) : null;
    if (
      is_array($cached)
      && !empty($cached['access_token'])
      && !empty($cached['expires_at'])
      && time() < (int)$cached['expires_at'] - 60
    ) {
      return (string)$cached['access_token'];
    }

    $fields = http_build_query([
      'grant_type' => 'client_credentials',
      'client_id' => APPYPAY_CLIENT_ID,
      'client_secret' => APPYPAY_CLIENT_SECRET,
      'resource' => APPYPAY_RESOURCE,
    ]);

    $result = appypay_http_request(APPYPAY_TOKEN_URL, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
      CURLOPT_POSTFIELDS => $fields,
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if (!empty($result['error'])) {
      throw new RuntimeException('Falha de comunicação ao obter token AppyPay.');
    }

    $data = json_decode((string)($result['body'] ?? ''), true);
    if (!is_array($data)) {
      throw new RuntimeException('Resposta inválida do serviço de autenticação AppyPay.');
    }

    if ((int)($result['code'] ?? 0) >= 400 || empty($data['access_token']) || empty($data['expires_in'])) {
      throw new RuntimeException('O serviço de autenticação AppyPay recusou as credenciais.');
    }

    $expiresIn = (int)$data['expires_in'];
    if ($expiresIn <= 60) {
      throw new RuntimeException('O serviço de autenticação AppyPay devolveu um token com validade inválida.');
    }

    $token = (string)$data['access_token'];
    rewind($cache);
    if (!ftruncate($cache, 0)) {
      throw new RuntimeException('Não foi possível actualizar a cache do AppyPay.');
    }
    $written = fwrite($cache, json_encode([
      'access_token' => $token,
      'expires_at' => time() + $expiresIn,
    ], JSON_THROW_ON_ERROR));
    if ($written === false || !fflush($cache)) {
      throw new RuntimeException('Não foi possível guardar a cache do AppyPay.');
    }
    @chmod($cacheFile, 0600);

    return $token;
  } finally {
    flock($cache, LOCK_UN);
    fclose($cache);
  }
}

function appypay_api_request(string $method, string $endpoint, ?array $json = null, ?array $query = null): array
{
  $token = appypay_get_token();
  $url = rtrim(APPYPAY_API_BASE, '/') . $endpoint;
  if ($query && is_array($query) && count($query) > 0) {
    $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
  }

  $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => strtoupper($method),
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Accept-Language: pt-BR',
      'Authorization: Bearer ' . $token,
      'Content-Type: application/json',
    ],
  ];

  if ($json !== null) {
    $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  $options[CURLOPT_CONNECTTIMEOUT] = 10;
  $options[CURLOPT_TIMEOUT] = 60;
  $options[CURLOPT_FOLLOWLOCATION] = false;
  $options[CURLOPT_SSL_VERIFYPEER] = true;
  $options[CURLOPT_SSL_VERIFYHOST] = 2;
  $result = appypay_http_request($url, $options);

  if (!empty($result['error'])) {
    throw new RuntimeException('Falha de comunicação com a AppyPay.');
  }

  $resp = (string)($result['body'] ?? '');
  $data = json_decode($resp, true);
  if (!is_array($data)) {
    throw new RuntimeException('A AppyPay devolveu uma resposta inválida.');
  }

  return ['code' => (int)($result['code'] ?? 0), 'data' => $data, 'raw' => $resp];
}

function appypay_create_charge(array $payload): array
{
  return appypay_api_request('POST', '/charges', $payload);
}

function appypay_get_charge(string $chargeId): array
{
  $chargeId = trim($chargeId);
  if ($chargeId === '' || !preg_match('/^[A-Za-z0-9-]{1,80}$/', $chargeId)) {
    throw new InvalidArgumentException('Identificador de cobrança inválido.');
  }
  return appypay_api_request('GET', '/charges/' . rawurlencode($chargeId));
}

function appypay_method_id(string $kind): string
{
  $kind = strtoupper(trim($kind));
  if ($kind === 'REF') return APPYPAY_METHOD_REF;
  if ($kind === 'GPO') return APPYPAY_METHOD_GPO;
  throw new Exception('Método de pagamento inválido.');
}
