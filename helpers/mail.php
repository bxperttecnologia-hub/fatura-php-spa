<?php

declare(strict_types=1);

namespace Cron;

class Mailer
{
    public function __construct(private array $mailConfig) {}

    /**
     * Envio simples via mail(). Para produção com Gmail/SES/SendGrid,
     * troca o corpo deste método por PHPMailer configurado com
     * $this->mailConfig['smtp_host'] / smtp_user / smtp_pass / smtp_port.
     */
    public function send(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $this->mailConfig['from_name'], $this->mailConfig['from_email']),
        ];

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
