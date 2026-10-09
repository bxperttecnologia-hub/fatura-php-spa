<?php
// Este endpoint responde SEMPRE em JSON: erros/avisos PHP nunca são impressos como HTML.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start(); // apanha qualquer saída acidental (avisos do db.php, espaços, etc.)

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        error_log('[AGT consultarNIF] Erro fatal: ' . $e['message'] . ' em ' . $e['file'] . ':' . $e['line']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        $dev = defined('APP_ENV') && APP_ENV === 'development';
        echo json_encode([
            'status'  => 'error',
            'message' => 'Erro interno no servidor.' . ($dev ? ' [' . $e['message'] . ' em ' . basename($e['file']) . ':' . $e['line'] . ']' : ''),
        ], JSON_UNESCAPED_UNICODE);
    }
});

require_once '../../../config/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * Proxy para a API "consultarNIF" da AGT.
 *
 * Porquê no servidor e não directo no browser?
 *  - a API exige os cabeçalhos Username/Password (não podem ficar expostos no JS);
 *  - evita problemas de CORS.
 *
 * CONFIGURAÇÃO: constantes AGT_NIF_* no ficheiro de configuração (têm prioridade)
 * ou, em alternativa, um ficheiro .env na raiz do projecto, por exemplo:
 *   AGT_NIF_URL=https://sifphml.minfin.gov.ao/sigt/contribuinte/consultarNIF/v5/obter   (opcional)
 *   AGT_NIF_USERNAME=o_seu_username
 *   AGT_NIF_PASSWORD=o_seu_token
 */

// Carrega o .env (só define variáveis que ainda não existam no ambiente).
// Se o projecto já carrega o .env noutro sítio (ex.: vlucas/phpdotenv no db.php), isto não interfere.
function agt_load_env_file()
{
    $projectDir = __DIR__;
    for ($level = 0; $level < 6; $level++) {
        if (is_file($projectDir . '/app/config/db.php') && is_dir($projectDir . '/public')) {
            break;
        }
        $parentDir = dirname($projectDir);
        if ($parentDir === $projectDir) {
            $projectDir = null;
            break;
        }
        $projectDir = $parentDir;
    }

    $candidates = $projectDir
        ? [$projectDir . '/.env']
        : [];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/../.env';
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/.env';
    }

    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line, "\xEF\xBB\xBF \t\n\r\0\x0B");
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            if (stripos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }
            list($name, $value) = array_map('trim', explode('=', $line, 2));
            // remove aspas à volta do valor
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                $value = substr($value, 1, -1);
            } else {
                // comentário no fim da linha:  VALOR # comentário
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
            }
            if ($name !== '' && getenv($name) === false && !isset($_ENV[$name])) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
        return; // usa o primeiro .env encontrado
    }
}

function agt_env_value(array $names, $default = null)
{
    foreach ($names as $name) {
        // 1) constante definida no ficheiro de configuração (define('AGT_NIF_...', '...'))
        if (defined($name) && constant($name) !== '' && constant($name) !== null) {
            return constant($name);
        }
        // 2) variável de ambiente / .env
        $v = getenv($name);
        if ($v === false || $v === '') {
            $v = $_ENV[$name] ?? $_SERVER[$name] ?? false;
        }
        if ($v !== false && $v !== '') {
            return $v;
        }
    }
    return $default;
}

agt_load_env_file();

$agtUrl      = agt_env_value(['AGT_NIF_URL', 'AGT_URL'], 'https://sifp.minfin.gov.ao');
$agtUsername = agt_env_value(['AGT_NIF_USERNAME', 'AGT_USERNAME', 'AGT_USER']);
$agtPassword = agt_env_value(['AGT_NIF_PASSWORD', 'AGT_PASSWORD', 'AGT_TOKEN']);

function agt_respond(array $payload, int $httpCode = 200)
{
    // descarta saída acidental (avisos, HTML) para não corromper o JSON
    while (ob_get_level() > 0) {
        $stray = ob_get_clean();
        if (trim((string) $stray) !== '') {
            error_log('[AGT consultarNIF] Saída inesperada descartada: ' . substr(strip_tags($stray), 0, 300));
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// A consulta também é permitida durante o onboarding, após validação do telefone por OTP.
$registrationUserId = (int) ($_SESSION['user_id'] ?? 0);
$verifiedRegistration = false;
if ($registrationUserId > 0) {
    $verifiedStmt = $pdo->prepare(
        'SELECT 1 FROM users u
          WHERE u.id = ? AND u.phone_verified_at IS NOT NULL AND u.must_change_password = 1
            AND NOT EXISTS (SELECT 1 FROM company_has_user chu WHERE chu.user_id = u.id)
          LIMIT 1'
    );
    $verifiedStmt->execute([$registrationUserId]);
    $verifiedRegistration = (bool) $verifiedStmt->fetchColumn();
}
if (empty($_SESSION['user']) && !$verifiedRegistration) {
    agt_respond(['status' => 'error', 'message' => 'Sessão expirada.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    agt_respond(['status' => 'error', 'message' => 'Método inválido.'], 405);
}

$input = $_GET;

$tipoDocumento   = strtoupper(trim($input['tipoDocumento'] ?? 'NIF'));
$numeroDocumento = trim($input['numeroDocumento'] ?? '');

$tiposValidos = ['NIF', 'AID', 'REF', 'RES', 'BCER', 'PASS', 'FID', 'ONIF', 'OTHR'];
if (!in_array($tipoDocumento, $tiposValidos, true)) {
    agt_respond(['status' => 'error', 'message' => 'Tipo de documento inválido.'], 400);
}
if (!preg_match('/^[A-Za-z0-9]{5,25}$/', $numeroDocumento)) {
    agt_respond(['status' => 'error', 'message' => 'Número de documento inválido.'], 400);
}

if (!$agtUsername || !$agtPassword) {
    agt_respond(['status' => 'error', 'message' => 'Credenciais da AGT não encontradas. Verifique AGT_NIF_USERNAME e AGT_NIF_PASSWORD no .env.'], 500);
}

// ---- Cache no servidor (evita requests repetidos à AGT, partilhado entre utilizadores) ----
$cacheTtl  = 24 * 60 * 60; // 24h
$cacheDir  = sys_get_temp_dir() . '/agt_nif_cache';
$cacheFile = $cacheDir . '/' . sha1($tipoDocumento . '|' . strtoupper($numeroDocumento)) . '.json';
$forceRefresh = filter_var($input['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$forceRefresh && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && ($cached['status'] ?? '') === 'success') {
        $cached['cached'] = true;
        agt_respond($cached);
    }
}

// ---- Chamada à AGT ----
$agtEndpointPath = '/sigt/contribuinte/consultarNIF/v5/obter';
$configuredPath = rtrim((string) parse_url($agtUrl, PHP_URL_PATH), '/');
if (!str_ends_with($configuredPath, $agtEndpointPath)) {
    $agtUrl = rtrim($agtUrl, '/') . $agtEndpointPath;
}

$url = $agtUrl . '?' . http_build_query([
    'tipoDocumento'   => $tipoDocumento,
    'numeroDocumento' => $numeroDocumento,
]);

$dev = defined('APP_ENV') && APP_ENV === 'development';

/**
 * Pedido HTTP à AGT. Usa cURL se existir; senão, file_get_contents.
 * Devolve [corpo|false, código HTTP, erro].
 */
function agt_http_request($url, array $headers, $caBundle = null)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if (defined('CURLSSLOPT_NATIVE_CA') && defined('CURLOPT_SSL_OPTIONS')) {
            $opts[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
        }
        if ($caBundle) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($ch, $opts);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [$raw, $code, $err];
    }

    if (ini_get('allow_url_fopen')) {
        $http = [
            'method'        => 'GET',
            'header'        => implode("\r\n", $headers),
            'timeout'       => 15,
            'ignore_errors' => true, // devolve o corpo mesmo com HTTP 4xx/5xx
        ];
        $ssl = [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ];
        if ($caBundle) {
            $ssl['cafile'] = $caBundle;
        }
        $raw  = @file_get_contents($url, false, stream_context_create(['http' => $http, 'ssl' => $ssl]));
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        $err = '';
        if ($raw === false) {
            $last = error_get_last();
            $err  = $last['message'] ?? 'falha na ligação';
        }
        return [$raw, $code, $err];
    }

    return [false, 0, 'O PHP não tem cURL nem allow_url_fopen ativos'];
}

// A AGT (Oracle OWSM) responde 401 "WWW-Authenticate: Basic realm=owsm": exige HTTP Basic.
// Mantemos também os cabeçalhos Username/Password da documentação.
$headers = [
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($agtUsername . ':' . $agtPassword),
    'Username: ' . $agtUsername,
    'Password: ' . $agtPassword,
];

$caBundle = agt_env_value(['AGT_CA_BUNDLE']);
if (!$caBundle) {
    $caBundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
}
if ($caBundle && (!is_file($caBundle) || !is_readable($caBundle))) {
    error_log('[AGT consultarNIF] O ficheiro configurado em AGT_CA_BUNDLE não existe ou não pode ser lido.');
    agt_respond(['status' => 'error', 'message' => 'O ficheiro de certificados da AGT não está acessível no servidor.'], 500);
}

list($raw, $httpCode, $httpErr) = agt_http_request($url, $headers, $caBundle ?: null);

if ($raw === false) {
    error_log('[AGT consultarNIF] Ligação: ' . $httpErr);
    $message = 'Não foi possível contactar a AGT.';
    if (stripos($httpErr, 'certificate') !== false || stripos($httpErr, 'SSL') !== false) {
        $message .= ' Verifique a cadeia de certificados TLS; pode definir AGT_CA_BUNDLE no .env com o caminho para o certificado CA confiável.';
    }
    agt_respond(['status' => 'error', 'message' => $message . ($dev && $httpErr ? ' [' . $httpErr . ']' : '')], 502);
}

// Credenciais recusadas pela AGT
if ($httpCode === 401 || $httpCode === 403) {
    error_log("[AGT consultarNIF] HTTP $httpCode: credenciais recusadas");
    agt_respond([
        'status'  => 'error',
        'message' => 'A AGT recusou as credenciais (HTTP ' . $httpCode . '). Verifique o Username/Password e se são do mesmo ambiente do URL' . ($dev ? ' [' . $agtUrl . ']' : '') . '.',
    ], 502);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    error_log("[AGT consultarNIF] Resposta inválida (HTTP $httpCode): " . substr($raw, 0, 500));
    agt_respond(['status' => 'error', 'message' => 'Resposta inválida da AGT.' . ($dev ? " [GET → HTTP {$httpCode}: " . substr(trim(preg_replace('/\s+/', ' ', strip_tags($raw))), 0, 200) . ']' : '')], 502);
}

// A resposta vem dentro de "ObterContribuinte"
$root        = $data['ObterContribuinte'] ?? $data;
$mensagem    = $root['mensagem'] ?? '';
$contribuinte = $root['contribuinte'] ?? null;

if ($httpCode >= 400 || !is_array($contribuinte) || empty($contribuinte['numeroNIF'])) {
    if ($httpCode >= 500) {
        error_log("[AGT consultarNIF] HTTP $httpCode: " . substr($raw, 0, 500));
        agt_respond(['status' => 'error', 'message' => 'Serviço da AGT indisponível.' . ($dev ? " [GET → HTTP {$httpCode}]" : '')], 502);
    }
    agt_respond([
        'status'  => 'not_found',
        'message' => $mensagem ?: 'Contribuinte não encontrado.',
    ]);
}

$estados = [
    'A' => 'Ativo',
    'C' => 'Cessado',
    'D' => 'Falecido',
    'E' => 'Herança',
    'F' => 'Anulado',
    'G' => 'Suspenso',
];
$regimes = [
    'GNAD' => 'Regime Geral',
    'TRAG' => 'Regime Transitório',
    'SIMP' => 'Regime Simplificado',
    'NBND' => 'Regime de Não Sujeição',
    'EXCL' => 'Regime de Exclusão',
];

$estado = $contribuinte['estadoContribuinte'] ?? '';
$regime = $contribuinte['regimeIva'] ?? '';

$payload = [
    'status'  => 'success',
    'message' => $mensagem,
    'data'    => [
        'nif'           => $contribuinte['numeroNIF'],
        'nome'          => $contribuinte['nome'] ?? '',
        'tipo'          => $contribuinte['tipoContribuinte'] ?? '',
        'estado'        => $estado,
        'estado_label'  => $estados[$estado] ?? $estado,
        'regime_iva'    => $regime,
        'regime_label'  => $regimes[$regime] ?? $regime,
        'nao_residente' => filter_var($contribuinte['indicadorNaoResidente'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],
];

// Só guarda respostas com sucesso (nunca "não encontrado" nem erros)
if (is_dir($cacheDir) || @mkdir($cacheDir, 0700, true)) {
    @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

agt_respond($payload);