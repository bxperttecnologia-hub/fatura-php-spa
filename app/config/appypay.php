<?php

declare(strict_types=1);
require_once __DIR__ . '/../helpers/env.php';

function appypay_env(string $name, string $default = ''): string
{
    $value = bx_env($name);
    return $value === false ? $default : trim((string)$value);
}

function appypay_load_local_env(): void
{
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || bx_env($key) !== false) {
            continue;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");
        bx_putenv($key . '=' . $value);
    }
}

appypay_load_local_env();

$appypayEnvironment = strtolower(appypay_env('APPYPAY_ENV', 'sandbox'));
if (in_array($appypayEnvironment, ['test', 'tst'], true)) {
    $appypayEnvironment = 'sandbox';
}
if (!in_array($appypayEnvironment, ['sandbox', 'production'], true)) {
    $appypayEnvironment = 'invalid';
}

$appypaySuffix = strtoupper($appypayEnvironment);
$appypayGenericFallback = $appypayEnvironment === 'sandbox';

define('APPYPAY_ENV', $appypayEnvironment);
define('APPYPAY_CLIENT_ID', appypay_env(
    'APPYPAY_CLIENT_ID_' . $appypaySuffix,
    $appypayGenericFallback ? appypay_env('APPYPAY_CLIENT_ID') : ''
));
define('APPYPAY_CLIENT_SECRET', appypay_env(
    'APPYPAY_CLIENT_SECRET_' . $appypaySuffix,
    $appypayGenericFallback ? appypay_env('APPYPAY_CLIENT_SECRET') : ''
));
define('APPYPAY_RESOURCE', appypay_env(
    'APPYPAY_RESOURCE_' . $appypaySuffix,
    $appypayEnvironment === 'sandbox'
        ? appypay_env('APPYPAY_RESOURCE_TST', appypay_env('APPYPAY_RESOURCE'))
        : ''
));
define('APPYPAY_METHOD_REF', appypay_env(
    'APPYPAY_PAYMENT_METHOD_REF_' . $appypaySuffix,
    $appypayGenericFallback ? appypay_env('APPYPAY_PAYMENT_METHOD_REF') : ''
));
define('APPYPAY_METHOD_GPO', appypay_env(
    'APPYPAY_PAYMENT_METHOD_GPO_' . $appypaySuffix,
    $appypayGenericFallback ? appypay_env('APPYPAY_PAYMENT_METHOD_GPO') : ''
));
define(
    'APPYPAY_TOKEN_URL',
    appypay_env(
        'APPYPAY_TOKEN_URL_' . $appypaySuffix,
        $appypayEnvironment === 'sandbox'
            ? 'https://login.microsoftonline.com/appypaydev.onmicrosoft.com/oauth2/token'
            : ($appypayEnvironment === 'production'
                ? 'https://login.microsoftonline.com/auth.apppay.co.ao/oauth2/token'
                : '')
    )
);
define(
    'APPYPAY_API_BASE',
    appypay_env(
        'APPYPAY_API_BASE_' . $appypaySuffix,
        $appypayEnvironment === 'sandbox' ? 'https://gwy-api-tst.appypay.co.ao/v2.0' : ''
    )
);
$appypayCaBundle = appypay_env(
    'APPYPAY_CA_BUNDLE',
    appypay_env('PHP_CURL_CAINFO', (string)ini_get('curl.cainfo'))
);
if ($appypayCaBundle === '') {
    $xamppCaBundle = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
        . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'cacert.pem';
    if (is_file($xamppCaBundle) && is_readable($xamppCaBundle)) {
        $appypayCaBundle = $xamppCaBundle;
    }
}
define('APPYPAY_CA_BUNDLE', $appypayCaBundle);
define('APPYPAY_WEBHOOK_TOKEN', appypay_env('APPYPAY_WEBHOOK_TOKEN'));
