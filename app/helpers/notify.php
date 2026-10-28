<?php

require_once __DIR__ . '/gosms.php';

const RESET_CODE_TTL_SECONDS = 600;

function normalizePhone9(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    return strlen($digits) >= 9 ? substr($digits, -9) : null;
}

function toE164(string $raw): string
{
    $countryCode = getenv('DEFAULT_COUNTRY_CODE') ?: '244';
    $digits = preg_replace('/\D+/', '', $raw);
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 9) {
        $digits = $countryCode . $digits;
    }
    return '+' . $digits;
}

function resetMessage(string $code): string
{
    $minutes = RESET_CODE_TTL_SECONDS / 60;
    return "BXpert: o seu código de recuperação é $code. Válido por $minutes minutos. Não o partilhe com ninguém.";
}

function sendResetEmail(string $to, string $code): bool
{
    if (!function_exists('curl_init')) {
        error_log('[forgot_send] PHP cURL extension is unavailable for email delivery.');
        return false;
    }

    $sharedSecret = trim((string) getenv('DOCUMENT_API_TOKEN_SECRET'));
    if ($sharedSecret === '' && defined('DB_PASS')) {
        $sharedSecret = (string) DB_PASS;
    }
    if ($sharedSecret === '') {
        $sharedSecret = trim((string) getenv('DB_PASS'));
    }
    if ($sharedSecret === '') {
        $sharedSecret = trim((string) getenv('DB_PASSWORD'));
    }
    if ($sharedSecret === '') {
        error_log('[forgot_send] Password reset email API secret is not configured.');
        return false;
    }

    $apiBaseUrl = trim((string) getenv('DOCUMENT_API_BASE_URL'));
    if ($apiBaseUrl === '') {
        $apiBaseUrl = defined('APP_ENV') && APP_ENV === 'production'
            ? 'https://api-sandibox.bxpert.co.ao'
            : 'http://localhost:3004';
    }
    if (
        !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)
        || !in_array(parse_url($apiBaseUrl, PHP_URL_SCHEME), ['http', 'https'], true)
    ) {
        error_log('[forgot_send] Password reset email API URL is invalid.');
        return false;
    }

    $body = json_encode(
        ['to' => $to, 'code' => $code],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($body)) {
        error_log('[forgot_send] Could not serialize password reset email request.');
        return false;
    }

    $timestamp = (string) time();
    $nonce = bin2hex(random_bytes(16));
    $key = hash_hmac('sha256', 'bxpert:password-reset-email:v1', $sharedSecret, true);
    $message = $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
    $signature = hash_hmac('sha256', $message, $key);

    $curl = curl_init(rtrim($apiBaseUrl, '/') . '/api/auth/password-reset/email');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Password-Reset-Timestamp: ' . $timestamp,
            'X-Password-Reset-Nonce: ' . $nonce,
            'X-Password-Reset-Signature: ' . $signature,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($responseBody === false) {
        error_log('[forgot_send] Password reset email API connection failed: ' . $curlError);
        return false;
    }

    $response = json_decode((string) $responseBody, true);
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($response) || ($response['status'] ?? '') !== 'success') {
        error_log(sprintf('[forgot_send] Password reset email API returned HTTP %d.', $statusCode));
        return false;
    }

    return true;
}

function sendSms(string $toE164, string $code, ?string &$erro = null): bool
{
    return enviarMensagemGoSms($toE164, resetMessage($code), $erro);
}
