<?php
/**
 * Cobranças automáticas por e-mail.
 *
 * Cada empresa ativa a cobrança e escolhe as etapas em dias em relação ao vencimento
 * (ex.: "-3,0,7,15,30": 3 dias antes, no dia, e 7/15/30 dias depois). Todos os dias o cron
 * (bin/cobrancas_automaticas.php) envia ao cliente o aviso da etapa mais recente que já
 * chegou e ainda não foi enviada — nunca envia a mesma etapa duas vezes (collection_reminders)
 * nem reenvia etapas antigas em atraso.
 *
 * Valor em dívida = total + notas de débito − notas de crédito − recibos.
 * SMTP vem do .env: SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_NAME.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/anonymous_contact.php';

const BX_COLLECTION_DEFAULT_DAYS = '-3,0,7,15,30';

/** "-3, 0,7" → [-3, 0, 7] (ordenado, sem repetidos, entre -60 e 365). */
function bx_collection_parse_days(string $value): array
{
    $days = [];
    foreach (preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) as $part) {
        if (!preg_match('/^[+-]?\d{1,3}$/', $part)) {
            throw new InvalidArgumentException('Dias inválidos: use números separados por vírgulas (ex.: -3,0,7,15,30).');
        }
        $day = (int) $part;
        if ($day < -60 || $day > 365) {
            throw new InvalidArgumentException('Cada etapa tem de estar entre 60 dias antes e 365 dias depois do vencimento.');
        }
        $days[$day] = $day;
    }
    if (!$days) {
        throw new InvalidArgumentException('Indique pelo menos uma etapa de cobrança.');
    }
    if (count($days) > 10) {
        throw new InvalidArgumentException('No máximo 10 etapas de cobrança.');
    }
    sort($days);
    return array_values($days);
}

function bx_collection_settings(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare('SELECT * FROM collection_settings WHERE company_id = ?');
    $stmt->execute([$companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'enabled' => (bool) ($row['enabled'] ?? false),
        'reminder_days' => (string) ($row['reminder_days'] ?? BX_COLLECTION_DEFAULT_DAYS),
        'email_subject' => (string) ($row['email_subject'] ?? ''),
        'email_message' => (string) ($row['email_message'] ?? ''),
        'updated_at' => $row['updated_at'] ?? null,
        'smtp_configured' => bx_smtp_configured(),
    ];
}

function bx_collection_save_settings(PDO $pdo, int $companyId, int $userId, array $input): array
{
    $days = implode(',', bx_collection_parse_days((string) ($input['reminder_days'] ?? BX_COLLECTION_DEFAULT_DAYS)));
    $subject = mb_substr(trim((string) ($input['email_subject'] ?? '')), 0, 200);
    $message = mb_substr(trim((string) ($input['email_message'] ?? '')), 0, 4000);
    $pdo->prepare("
        INSERT INTO collection_settings (company_id, enabled, reminder_days, email_subject, email_message, updated_by)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), reminder_days = VALUES(reminder_days),
            email_subject = VALUES(email_subject), email_message = VALUES(email_message), updated_by = VALUES(updated_by)
    ")->execute([$companyId, !empty($input['enabled']) ? 1 : 0, $days, $subject ?: null, $message ?: null, $userId ?: null]);
    return bx_collection_settings($pdo, $companyId);
}

/**
 * Faturas (FT) finalizadas com valor em dívida, com o vencimento, os dias em relação a ele
 * e o e-mail do cliente. $today em 'Y-m-d'.
 */
function bx_collection_open_invoices(PDO $pdo, int $companyId, string $today): array
{
    $stmt = $pdo->prepare("
        SELECT i.id, i.company_id, i.reference, i.document_type, i.issue_date, i.due_date, i.final_total, i.paid_total,
               DATE_ADD(i.issue_date, INTERVAL COALESCE(i.due_date, 0) DAY) AS due_at,
               DATEDIFF(:today, DATE_ADD(i.issue_date, INTERVAL COALESCE(i.due_date, 0) DAY)) AS days_from_due,
               COALESCE((SELECT SUM(dn.final_total) FROM debit_notes dn WHERE dn.invoice_id = i.id), 0) AS debited,
               COALESCE((SELECT SUM(cn.final_total) FROM credit_notes cn WHERE cn.invoice_id = i.id), 0) AS credited,
               c.name AS client_name, c.email AS client_email,
               cr.symbol AS currency_symbol
        FROM invoices i
        JOIN contact c ON c.id = i.contact_id
        LEFT JOIN currencies cr ON cr.iso_code = i.currency
        WHERE i.company_id = :company
          AND i.status IN (3, 5)
          AND COALESCE(i.document_type, 'FT') = 'FT'
          AND i.reference IS NOT NULL AND i.reference <> ''
          AND i.issue_date IS NOT NULL
        ORDER BY due_at, i.id
    ");
    $stmt->execute([':today' => $today, ':company' => $companyId]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $due = round((float) $row['final_total'] + (float) $row['debited'] - (float) $row['credited'] - (float) $row['paid_total'], 2);
        if ($due <= 0) {
            continue;
        }
        $row['amount_due'] = $due;
        $row['days_from_due'] = (int) $row['days_from_due'];
        $rows[] = $row;
    }
    return $rows;
}

/** Etapa a enviar hoje: a mais recente já alcançada (null se nenhuma). */
function bx_collection_current_stage(array $days, int $daysFromDue): ?int
{
    $stage = null;
    foreach ($days as $day) {
        if ($day <= $daysFromDue) {
            $stage = $day;
        }
    }
    return $stage;
}

function bx_collection_money(float $value, ?string $symbol): string
{
    return number_format($value, 2, ',', ' ') . ' ' . (trim((string) $symbol) ?: 'Kz');
}

/** Assunto e corpo (HTML) do aviso. Variáveis: {cliente} {empresa} {fatura} {valor} {vencimento} {dias}. */
function bx_collection_message(array $settings, array $invoice, string $companyName, int $stage): array
{
    $dueBr = date('d/m/Y', strtotime((string) $invoice['due_at']));
    $vars = [
        '{cliente}' => (string) $invoice['client_name'],
        '{empresa}' => $companyName,
        '{fatura}' => (string) $invoice['reference'],
        '{valor}' => bx_collection_money((float) $invoice['amount_due'], $invoice['currency_symbol'] ?? null),
        '{vencimento}' => $dueBr,
        '{dias}' => (string) abs((int) $invoice['days_from_due']),
    ];
    if ($stage < 0) {
        $defaultSubject = 'Fatura {fatura} vence a {vencimento}';
        $defaultText = "Caro(a) {cliente},\n\nLembramos que a fatura {fatura}, no valor em dívida de {valor}, vence a {vencimento}.\n\nSe já efetuou o pagamento, ignore esta mensagem.\n\nCom os melhores cumprimentos,\n{empresa}";
    } elseif ($stage === 0) {
        $defaultSubject = 'Fatura {fatura} vence hoje';
        $defaultText = "Caro(a) {cliente},\n\nA fatura {fatura}, no valor em dívida de {valor}, vence hoje ({vencimento}).\n\nSe já efetuou o pagamento, ignore esta mensagem.\n\nCom os melhores cumprimentos,\n{empresa}";
    } else {
        $defaultSubject = 'Fatura {fatura} em atraso';
        $defaultText = "Caro(a) {cliente},\n\nA fatura {fatura} venceu a {vencimento} ({dias} dias) e tem ainda {valor} por regularizar.\n\nAgradecemos que proceda ao pagamento com a maior brevidade. Se já o fez, ignore esta mensagem.\n\nCom os melhores cumprimentos,\n{empresa}";
    }
    $subject = strtr(trim($settings['email_subject']) !== '' ? $settings['email_subject'] : $defaultSubject, $vars);
    $text = strtr(trim($settings['email_message']) !== '' ? $settings['email_message'] : $defaultText, $vars);
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222;line-height:1.5">'
        . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</div>';
    return [$subject, $html, $text];
}

function bx_smtp_configured(): bool
{
    return trim((string) bx_env('SMTP_HOST')) !== ''
        && filter_var(trim((string) bx_env('SMTP_USERNAME')), FILTER_VALIDATE_EMAIL)
        && (string) bx_env('SMTP_PASSWORD') !== '';
}

/** Envia um e-mail pelo SMTP do .env. Devolve null se correu bem, ou a mensagem de erro. */
function bx_send_mail(string $to, string $subject, string $html, string $text, string $fromName = ''): ?string
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return 'E-mail do cliente inválido.';
    }
    if (!bx_smtp_configured()) {
        return 'O envio de e-mail não está configurado (SMTP_HOST, SMTP_USERNAME e SMTP_PASSWORD no .env).';
    }
    require_once __DIR__ . '/../../vendor/autoload.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $port = (int) (bx_env('SMTP_PORT') ?: 465);
        $mail->isSMTP();
        $mail->Host = trim((string) bx_env('SMTP_HOST'));
        $mail->SMTPAuth = true;
        $mail->Username = trim((string) bx_env('SMTP_USERNAME'));
        $mail->Password = (string) bx_env('SMTP_PASSWORD');
        $mail->SMTPSecure = $port === 465 ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $port;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($mail->Username, $fromName ?: (trim((string) bx_env('SMTP_FROM_NAME')) ?: 'BXpert'));
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        return null;
    } catch (\Throwable $e) {
        return $mail->ErrorInfo ?: $e->getMessage();
    }
}

/**
 * Corre a cobrança de uma empresa. $dryRun: só lista o que seria enviado.
 * $trigger: 'cron' ou 'manual'. Devolve ['sent' => n, 'failed' => n, 'skipped' => n, 'items' => [...]].
 */
function bx_collection_run_company(PDO $pdo, int $companyId, bool $dryRun = false, string $trigger = 'cron', ?string $today = null): array
{
    $today ??= date('Y-m-d');
    $settings = bx_collection_settings($pdo, $companyId);
    $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'items' => []];
    if (!$settings['enabled'] && $trigger === 'cron') {
        return $result;
    }
    $days = bx_collection_parse_days($settings['reminder_days']);
    $stmt = $pdo->prepare('SELECT name FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $companyName = (string) $stmt->fetchColumn();

    $sentStmt = $pdo->prepare("SELECT 1 FROM collection_reminders WHERE invoice_id = ? AND stage_day = ? AND channel = 'email' AND status = 'sent'");
    $logStmt = $pdo->prepare("
        INSERT INTO collection_reminders (company_id, invoice_id, stage_day, channel, recipient, amount_due, status, error, `trigger`)
        VALUES (?, ?, ?, 'email', ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE recipient = VALUES(recipient), amount_due = VALUES(amount_due), status = VALUES(status),
            error = VALUES(error), `trigger` = VALUES(`trigger`), created_at = CURRENT_TIMESTAMP
    ");

    foreach (bx_collection_open_invoices($pdo, $companyId, $today) as $invoice) {
        $stage = bx_collection_current_stage($days, $invoice['days_from_due']);
        if ($stage === null) {
            continue;   // ainda não chegou à primeira etapa
        }
        $sentStmt->execute([(int) $invoice['id'], $stage]);
        if ($sentStmt->fetchColumn()) {
            continue;   // esta etapa já foi enviada
        }
        $item = [
            'invoice_id' => (int) $invoice['id'],
            'reference' => $invoice['reference'],
            'client' => $invoice['client_name'],
            'email' => $invoice['client_email'],
            'amount_due' => $invoice['amount_due'],
            'days_from_due' => $invoice['days_from_due'],
            'stage_day' => $stage,
        ];
        $email = trim((string) $invoice['client_email']);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strcasecmp($email, ANON_CONTACT_EMAIL) === 0) {
            $item['status'] = 'skipped';
            $item['error'] = strcasecmp($email, ANON_CONTACT_EMAIL) === 0 ? 'Consumidor final (sem e-mail).' : 'Cliente sem e-mail válido.';
            $result['skipped']++;
            $result['items'][] = $item;
            continue;
        }
        if ($dryRun) {
            $item['status'] = 'pending';
            $result['items'][] = $item;
            continue;
        }
        [$subject, $html, $text] = bx_collection_message($settings, $invoice, $companyName, $stage);
        $error = bx_send_mail($email, $subject, $html, $text, $companyName);
        $item['status'] = $error === null ? 'sent' : 'failed';
        $item['error'] = $error;
        $logStmt->execute([$companyId, (int) $invoice['id'], $stage, $email, $invoice['amount_due'], $item['status'], $error, $trigger === 'manual' ? 'manual' : 'cron']);
        $result[$error === null ? 'sent' : 'failed']++;
        $result['items'][] = $item;
    }
    return $result;
}

/** Últimos avisos enviados (ou falhados) da empresa. */
function bx_collection_history(PDO $pdo, int $companyId, int $limit = 50): array
{
    $stmt = $pdo->prepare("
        SELECT r.id, r.invoice_id, r.stage_day, r.recipient, r.amount_due, r.status, r.error, r.`trigger`, r.created_at,
               i.reference, c.name AS client_name
        FROM collection_reminders r
        JOIN invoices i ON i.id = r.invoice_id
        JOIN contact c ON c.id = i.contact_id
        WHERE r.company_id = ?
        ORDER BY r.created_at DESC, r.id DESC
        LIMIT " . max(1, min(200, $limit))
    );
    $stmt->execute([$companyId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
