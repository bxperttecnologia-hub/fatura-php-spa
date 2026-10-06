<?php

/**
 * Envio do código de recuperação por e-mail, SMS e WhatsApp.
 *
 * SMS e WhatsApp usam a API da Twilio como exemplo. Para usar outro fornecedor
 * (gateway SMS local, Meta WhatsApp Cloud API, etc.) basta trocar o corpo de
 * sendSms() / sendWhatsapp(); o resto do fluxo não muda.
 *
 * Variáveis de ambiente esperadas:
 *   TWILIO_SID, TWILIO_TOKEN, TWILIO_SMS_FROM (ex.: +1555...), TWILIO_WA_FROM (ex.: +1415...)
 *   MAIL_FROM (ex.: no-reply@seudominio.com), DEFAULT_COUNTRY_CODE (ex.: 244)
 */

const RESET_CODE_TTL_SECONDS = 600; // 10 minutos

/** Devolve os últimos 9 dígitos do telefone (formato usado na comparação do login), ou null. */
function normalizePhone9(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    return strlen($digits) >= 9 ? substr($digits, -9) : null;
}

/** Converte um telefone guardado na BD para o formato internacional (+244...). */
function toE164(string $raw): string
{
    $cc = getenv('DEFAULT_COUNTRY_CODE') ?: '244';
    $d = preg_replace('/\D+/', '', $raw);
    if (strpos($d, '00') === 0) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 9) {
        $d = $cc . $d;
    }
    return '+' . $d;
}

function resetMessage(string $code): string
{
    $min = RESET_CODE_TTL_SECONDS / 60;
    return "BXpert: o seu código de recuperação é $code. Válido por $min minutos. Não o partilhe com ninguém.";
}

function sendResetEmail(string $to, string $code): bool
{
    $from = getenv('MAIL_FROM') ?: 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $subject = 'Código de recuperação de senha';
    $body = resetMessage($code);
    $headers = [
        'From: BXpert <' . $from . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    // Para produção, prefira SMTP autenticado (ex.: PHPMailer) em vez de mail().
    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

function sendSms(string $toE164, string $code): bool
{
    return twilioSend($toE164, (string) getenv('TWILIO_SMS_FROM'), resetMessage($code));
}

/**
 * Atenção: no WhatsApp Business, mensagens iniciadas pela empresa exigem um
 * template aprovado. Em produção, use um template de autenticação (OTP) em vez de texto livre.
 */
function sendWhatsapp(string $toE164, string $code): bool
{
    return twilioSend('whatsapp:' . $toE164, 'whatsapp:' . getenv('TWILIO_WA_FROM'), resetMessage($code));
}

function twilioSend(string $to, string $from, string $body): bool
{
    $sid = getenv('TWILIO_SID');
    $token = getenv('TWILIO_TOKEN');
    if (!$sid || !$token || $from === '' || $from === 'whatsapp:') {
        error_log('[notify] Twilio não configurado.');
        return false;
    }

    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => "$sid:$token",
        CURLOPT_POSTFIELDS     => http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        error_log("[notify] Twilio HTTP $http: $response");
        return false;
    }
    return true;
}
