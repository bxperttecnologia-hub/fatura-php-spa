<?php
// DIAGNÓSTICO TEMPORÁRIO da ligação à AGT. APAGAR ESTE FICHEIRO DEPOIS DE USAR.
// Uso: abrir no browser (com sessão iniciada) .../contacts/ajax/test_agt.php?nif=5000000000
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: text/plain; charset=utf-8');

if (!(defined('APP_ENV') && APP_ENV === 'development') || empty($_SESSION['user'])) {
    http_response_code(403);
    exit("Disponível apenas em desenvolvimento e com sessão iniciada.\n");
}

function t_env(array $names, $default = null)
{
    foreach ($names as $n) {
        if (defined($n) && constant($n) !== '') {
            return constant($n);
        }
        $v = getenv($n);
        if ($v !== false && $v !== '') {
            return $v;
        }
    }
    static $env = null;
    if ($env === null) {
        $env = [];
        foreach ([dirname(__DIR__, 3) . '/.env', dirname(__DIR__, 2) . '/.env'] as $f) {
            if (is_file($f)) {
                foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    list($k, $v) = array_map('trim', explode('=', $line, 2));
                    $env[$k] = trim($v, "\"'");
                }
                break;
            }
        }
    }
    foreach ($names as $n) {
        if (!empty($env[$n])) {
            return $env[$n];
        }
    }
    return $default;
}

$url  = t_env(['AGT_NIF_URL', 'AGT_URL'], 'https://sifphml.minfin.gov.ao/sigt/contribuinte/consultarNIF/v5/obter');
$user = t_env(['AGT_NIF_USERNAME', 'AGT_USERNAME', 'AGT_USER']);
$pass = t_env(['AGT_NIF_PASSWORD', 'AGT_PASSWORD', 'AGT_TOKEN']);
$nif  = preg_replace('/[^A-Za-z0-9]/', '', $_GET['nif'] ?? '5000000000');
$tipo = strtoupper(preg_replace('/[^A-Za-z]/', '', $_GET['tipo'] ?? 'NIF'));

echo "PHP " . PHP_VERSION . "\n";
echo "cURL: " . (function_exists('curl_init') ? 'sim' : 'NAO')
    . " | openssl: " . (extension_loaded('openssl') ? 'sim' : 'NAO')
    . " | allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'sim' : 'nao') . "\n";
echo "Username: " . ($user ? 'encontrado (' . strlen($user) . ' caracteres)' : 'NAO ENCONTRADO')
    . " | Password: " . ($pass ? 'encontrada (' . strlen($pass) . ' caracteres)' : 'NAO ENCONTRADA') . "\n";

$host = parse_url($url, PHP_URL_HOST);
$ip   = gethostbyname($host);
echo "DNS $host => " . ($ip === $host ? 'NAO RESOLVEU (o servidor nao chega a este endereco)' : $ip) . "\n\n";

$full = $url . '?' . http_build_query(['tipoDocumento' => $tipo, 'numeroDocumento' => $nif]);
$body = json_encode(['tipoDocumento' => $tipo, 'numeroDocumento' => $nif]);

$basic = 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
$variants = [
    'GET (Basic + Username/Password)' => ['GET', ['Accept: application/json', $basic, 'Username: ' . $user, 'Password: ' . $pass]],
    'GET (so Basic)'                  => ['GET', ['Accept: application/json', $basic]],
    'GET (so Username/Password)'      => ['GET', ['Accept: application/json', 'Username: ' . $user, 'Password: ' . $pass]],
];

foreach ($variants as $label => $variant) {
    list($method, $h) = $variant;
    echo "=== $label $full ===\n";

    if (function_exists('curl_init')) {
        $ch = curl_init($full);
        $o  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $h,
        ];
        if ($method === 'POST') {
            $o[CURLOPT_POST]       = true;
            $o[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $o);
        $resp  = curl_exec($ch);
        $info  = curl_getinfo($ch);
        $err   = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($resp === false) {
            echo "FALHA cURL ($errno): $err\n\n";
            continue;
        }
        $respHeaders = substr($resp, 0, $info['header_size']);
        $respBody    = substr($resp, $info['header_size']);
        echo "HTTP {$info['http_code']}\n$respHeaders";
        echo "--- corpo (primeiros 1500 caracteres) ---\n" . substr($respBody, 0, 1500) . "\n\n";
    } else {
        $http = [
            'method'        => $method,
            'header'        => implode("\r\n", $h),
            'timeout'       => 20,
            'ignore_errors' => true,
        ];
        if ($method === 'POST') {
            $http['content'] = $body;
        }
        $respBody = @file_get_contents($full, false, stream_context_create(['http' => $http]));
        echo implode("\n", $http_response_header ?? ['(sem cabecalhos)']) . "\n";
        if ($respBody === false) {
            $last = error_get_last();
            echo "FALHA: " . ($last['message'] ?? 'desconhecida') . "\n\n";
            continue;
        }
        echo "--- corpo (primeiros 1500 caracteres) ---\n" . substr($respBody, 0, 1500) . "\n\n";
    }
}