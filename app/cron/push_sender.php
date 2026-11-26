<?php

declare(strict_types=1);

/**
 * Envia os pushes da fila (push_queue) para a Expo e lê os receipts.
 *
 *   php app/cron/push_sender.php
 *
 * Agendar de minuto a minuto. Um lock na BD impede duas execuções em simultâneo.
 *
 * Fluxo da Expo (https://docs.expo.dev/push-notifications/sending-notifications/):
 *   1) POST /push/send (até 100 mensagens) -> um "ticket" por mensagem (ok + id, ou erro imediato);
 *   2) ~15 min depois, POST /push/getReceipts com os ids -> resultado real junto da FCM/APNs.
 *   DeviceNotRegistered (app desinstalada / token inválido) -> o dispositivo é revogado.
 *
 * .env (opcional):
 *   EXPO_ACCESS_TOKEN       token da conta Expo, obrigatório se "Enhanced Push Security" estiver ligado (recomendado)
 *   PUSH_QUIET_HOURS=22-7   não envia entre as 22h e as 7h (hora de APP_TIMEZONE); os pushes ficam à espera
 *   PUSH_CA_BUNDLE          ficheiro de CAs para o cURL, se o PHP não tiver um configurado
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/env.php';

const PUSH_SEND_URL     = 'https://exp.host/--/api/v2/push/send';
const PUSH_RECEIPTS_URL = 'https://exp.host/--/api/v2/push/getReceipts';
const PUSH_BATCH        = 100;     // limite da Expo por pedido
const PUSH_MAX_ATTEMPTS = 5;
const PUSH_RECEIPT_WAIT_MIN = 15;  // a Expo recomenda esperar ~15 min pelos receipts
const PUSH_KEEP_DAYS    = 30;

$log = static function (string $msg): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$msg}\n");
};

if ((int)$pdo->query("SELECT GET_LOCK('bx_push_sender', 0)")->fetchColumn() !== 1) {
    $log('Outra execução em curso; a sair.');
    exit(0);
}

try {
    $sent = push_in_quiet_hours((string)bx_env('PUSH_QUIET_HOURS'))
        ? ['skipped' => 'quiet hours']
        : push_send_pending($pdo, $log);
    $receipts = push_check_receipts($pdo, $log);
    $pdo->exec('DELETE FROM push_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . PUSH_KEEP_DAYS . ' DAY)');
    $log('Envio: ' . json_encode($sent) . ' | Receipts: ' . json_encode($receipts));
} catch (Throwable $e) {
    $log('ERRO: ' . $e->getMessage());
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('bx_push_sender')");
}

/** "22-7" -> true entre as 22:00 e as 06:59. Vazio/ inválido -> nunca. */
function push_in_quiet_hours(string $spec, ?int $hour = null): bool
{
    if (!preg_match('/^\s*(\d{1,2})\s*-\s*(\d{1,2})\s*$/', $spec, $m)) {
        return false;
    }
    [$from, $to] = [(int)$m[1] % 24, (int)$m[2] % 24];
    $h = $hour ?? (int)date('G');
    return $from <= $to ? ($h >= $from && $h < $to) : ($h >= $from || $h < $to);
}

/** Envia os pendentes em lotes de 100. */
function push_send_pending(PDO $pdo, callable $log): array
{
    $rows = $pdo->query("
        SELECT q.id, q.payload, q.attempts, d.id AS device_id, d.expo_token, d.revoked_at
        FROM push_queue q
        JOIN push_devices d ON d.id = q.device_id
        WHERE q.status = 'pending' AND q.next_attempt_at <= NOW()
        ORDER BY q.id
        LIMIT 1000
    ")->fetchAll(PDO::FETCH_ASSOC);

    $stats = ['ok' => 0, 'error' => 0, 'retry' => 0, 'revoked' => 0];
    $active = [];
    foreach ($rows as $row) {
        if ($row['revoked_at'] !== null) {
            push_mark_error($pdo, (int)$row['id'], 'dispositivo revogado');
            $stats['revoked']++;
            continue;
        }
        $active[] = $row;
    }

    foreach (array_chunk($active, PUSH_BATCH) as $chunk) {
        $messages = [];
        foreach ($chunk as $row) {
            $message = json_decode((string)$row['payload'], true) ?: [];
            $message['to'] = $row['expo_token'];
            $messages[] = $message;
        }

        [$status, $body, $error] = push_http(PUSH_SEND_URL, $messages);
        $tickets = $body['data'] ?? null;

        if ($status !== 200 || !is_array($tickets) || count($tickets) !== count($chunk)) {
            // Falha do pedido inteiro (rede, 5xx, 429...): tenta de novo mais tarde.
            $reason = $error ?: ('HTTP ' . $status . ' ' . substr(json_encode($body['errors'] ?? $body), 0, 150));
            foreach ($chunk as $row) {
                push_retry_or_fail($pdo, $row, $reason) ? $stats['retry']++ : $stats['error']++;
            }
            $log('Lote falhou: ' . $reason);
            continue;
        }

        foreach ($chunk as $i => $row) {
            $ticket = $tickets[$i];
            if (($ticket['status'] ?? '') === 'ok' && !empty($ticket['id'])) {
                $pdo->prepare("UPDATE push_queue SET status = 'sent', ticket_id = ?, sent_at = NOW(), attempts = attempts + 1, error = NULL WHERE id = ?")
                    ->execute([substr((string)$ticket['id'], 0, 64), $row['id']]);
                $stats['ok']++;
                continue;
            }
            $code = (string)($ticket['details']['error'] ?? '');
            $reason = trim($code . ' ' . ($ticket['message'] ?? ''));
            if ($code === 'DeviceNotRegistered') {
                push_revoke_device($pdo, (int)$row['device_id']);
                push_mark_error($pdo, (int)$row['id'], $reason);
                $stats['revoked']++;
            } elseif ($code === 'MessageRateExceeded') {
                push_retry_or_fail($pdo, $row, $reason) ? $stats['retry']++ : $stats['error']++;
            } else {
                // MessageTooBig, InvalidCredentials, MismatchSenderId...: tentar de novo não resolve.
                push_mark_error($pdo, (int)$row['id'], $reason);
                $stats['error']++;
                if ($code === 'InvalidCredentials') {
                    $log('ATENÇÃO: credenciais FCM/APNs inválidas no projeto Expo (eas credentials).');
                }
            }
        }
    }
    return $stats;
}

/** Lê os receipts dos tickets enviados há mais de 15 minutos. */
function push_check_receipts(PDO $pdo, callable $log): array
{
    $rows = $pdo->query("
        SELECT q.id, q.ticket_id, q.device_id, q.sent_at
        FROM push_queue q
        WHERE q.status = 'sent' AND q.receipt_checked_at IS NULL
          AND q.sent_at <= DATE_SUB(NOW(), INTERVAL " . PUSH_RECEIPT_WAIT_MIN . " MINUTE)
        ORDER BY q.id
        LIMIT 1000
    ")->fetchAll(PDO::FETCH_ASSOC);

    $stats = ['delivered' => 0, 'error' => 0, 'pending' => 0];
    foreach (array_chunk($rows, 300) as $chunk) {
        $byTicket = array_column($chunk, null, 'ticket_id');
        [$status, $body, $error] = push_http(PUSH_RECEIPTS_URL, ['ids' => array_keys($byTicket)]);
        if ($status !== 200 || !is_array($body['data'] ?? null)) {
            $log('getReceipts falhou: ' . ($error ?: 'HTTP ' . $status));
            continue;
        }
        $receipts = $body['data'];

        foreach ($byTicket as $ticketId => $row) {
            $receipt = $receipts[$ticketId] ?? null;
            if ($receipt === null) {
                // A Expo ainda não tem o receipt; desiste ao fim de 24 h (os receipts expiram).
                if (strtotime((string)$row['sent_at']) < time() - 86400) {
                    $pdo->prepare('UPDATE push_queue SET receipt_checked_at = NOW() WHERE id = ?')->execute([$row['id']]);
                } else {
                    $stats['pending']++;
                }
                continue;
            }
            if (($receipt['status'] ?? '') === 'ok') {
                $pdo->prepare("UPDATE push_queue SET status = 'delivered', receipt_checked_at = NOW() WHERE id = ?")->execute([$row['id']]);
                $stats['delivered']++;
                continue;
            }
            $code = (string)($receipt['details']['error'] ?? '');
            if ($code === 'DeviceNotRegistered') {
                push_revoke_device($pdo, (int)$row['device_id']);
            } elseif ($code === 'InvalidCredentials') {
                $log('ATENÇÃO: credenciais FCM/APNs inválidas no projeto Expo (eas credentials).');
            }
            $pdo->prepare("UPDATE push_queue SET status = 'error', error = ?, receipt_checked_at = NOW() WHERE id = ?")
                ->execute([mb_substr(trim($code . ' ' . ($receipt['message'] ?? '')), 0, 255), $row['id']]);
            $stats['error']++;
        }
    }
    return $stats;
}

/** Volta a pôr na fila com espera exponencial (2, 4, 8, 16 min). Devolve false quando desiste. */
function push_retry_or_fail(PDO $pdo, array $row, string $reason): bool
{
    $attempts = (int)$row['attempts'] + 1;
    if ($attempts >= PUSH_MAX_ATTEMPTS) {
        push_mark_error($pdo, (int)$row['id'], $reason, $attempts);
        return false;
    }
    $pdo->prepare('UPDATE push_queue SET attempts = ?, error = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?')
        ->execute([$attempts, mb_substr($reason, 0, 255), 2 ** $attempts, $row['id']]);
    return true;
}

function push_mark_error(PDO $pdo, int $id, string $reason, ?int $attempts = null): void
{
    $pdo->prepare("UPDATE push_queue SET status = 'error', error = ?, attempts = COALESCE(?, attempts + 1) WHERE id = ?")
        ->execute([mb_substr($reason, 0, 255), $attempts, $id]);
}

function push_revoke_device(PDO $pdo, int $deviceId): void
{
    $pdo->prepare('UPDATE push_devices SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL')->execute([$deviceId]);
}

/** POST JSON. Devolve [status HTTP, corpo decodificado, erro de rede]. */
function push_http(string $url, array $payload): array
{
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    $accessToken = trim((string)bx_env('EXPO_ACCESS_TOKEN'));
    if ($accessToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',          // aceita gzip
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ];
    $ca = trim((string)bx_env('PUSH_CA_BUNDLE'), " \t\"'");
    if ($ca !== '' && is_readable($ca)) {
        $options[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = $raw === false ? curl_error($ch) : null;
    curl_close($ch);

    $body = is_string($raw) ? json_decode($raw, true) : null;
    return [$status, is_array($body) ? $body : [], $error];
}
