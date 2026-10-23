<?php
require_once __DIR__ . '/env.php';

require_once __DIR__ . '/gosms.php';

const RESET_CODE_TTL_SECONDS = 600;

function normalizePhone9(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    return strlen($digits) >= 9 ? substr($digits, -9) : null;
}

function toE164(string $raw): string
{
    $countryCode = bx_env('DEFAULT_COUNTRY_CODE') ?: '244';
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
    $from = bx_env('MAIL_FROM') ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $subject = 'Código de recuperação de senha';
    $headers = [
        'From: BXpert <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', resetMessage($code), implode("\r\n", $headers));
}

function sendSms(string $toE164, string $code, ?string &$erro = null): bool
{
    return enviarMensagemGoSms($toE164, resetMessage($code), $erro);
}
