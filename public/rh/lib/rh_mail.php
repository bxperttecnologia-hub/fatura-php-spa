<?php
/** Envio de e-mails do RH (recibos, link do portal) pelo SMTP do .env, com anexos. */
require_once __DIR__ . '/../../../app/helpers/env.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

function rh_smtp_configured(): bool
{
    return trim((string)bx_env('SMTP_HOST')) !== ''
        && filter_var(trim((string)bx_env('SMTP_USERNAME')), FILTER_VALIDATE_EMAIL)
        && (string)bx_env('SMTP_PASSWORD') !== '';
}

/**
 * $attachments: [[bytes, filename], …]. Devolve null se correu bem, ou a mensagem de erro.
 */
function rh_send_mail(string $to, string $subject, string $text, array $attachments = [], string $fromName = ''): ?string
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'e-mail inválido';
    }
    if (!rh_smtp_configured()) {
        return 'o envio de e-mail não está configurado (SMTP no .env)';
    }
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $port = (int)(bx_env('SMTP_PORT') ?: 465);
        $mail->isSMTP();
        $mail->Host = trim((string)bx_env('SMTP_HOST'));
        $mail->SMTPAuth = true;
        $mail->Username = trim((string)bx_env('SMTP_USERNAME'));
        $mail->Password = (string)bx_env('SMTP_PASSWORD');
        $mail->SMTPSecure = $port === 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($mail->Username, $fromName ?: (trim((string)bx_env('SMTP_FROM_NAME')) ?: 'BXpert'));
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;line-height:1.55">'
            . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</div>';
        $mail->AltBody = $text;
        foreach ($attachments as [$bytes, $filename]) {
            $mail->addStringAttachment($bytes, $filename, \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64, 'application/pdf');
        }
        $mail->send();
        return null;
    } catch (\Throwable $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}
