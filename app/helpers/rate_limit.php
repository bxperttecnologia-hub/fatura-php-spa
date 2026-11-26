<?php
/**
 * Limite de tentativas no servidor (tabela rate_limits, migração 20261126_login_rate_limit.sql).
 * Janela fixa: até $max falhas em $window segundos por "bucket" (ex.: login por IP, login por utilizador).
 * Se a tabela não existir, não bloqueia (regista no log) para nunca impedir o login por falta da migração.
 */

function bx_rate_limit_bucket(string $kind, string $value): string
{
    return hash('sha256', $kind . ':' . mb_strtolower(trim($value)));
}

function bx_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** true se o bucket já atingiu o limite (não conta uma tentativa). */
function bx_rate_limited(PDO $pdo, string $bucket, int $max, int $window): bool
{
    try {
        $st = $pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start > (NOW() - INTERVAL ? SECOND)');
        $st->execute([$bucket, $window]);
        return (int)$st->fetchColumn() >= $max;
    } catch (PDOException $e) {
        error_log('[rate_limit] ' . $e->getMessage());
        return false;
    }
}

/** Regista uma falha no bucket (reinicia a janela se já expirou). */
function bx_rate_limit_hit(PDO $pdo, string $bucket, int $window): void
{
    try {
        $pdo->prepare(
            'INSERT INTO rate_limits (bucket, hits, window_start) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                hits = IF(window_start > (NOW() - INTERVAL ? SECOND), hits + 1, 1),
                window_start = IF(window_start > (NOW() - INTERVAL ? SECOND), window_start, NOW())'
        )->execute([$bucket, $window, $window]);
        if (random_int(1, 200) === 1) {   // limpeza ocasional
            $pdo->exec('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 1 DAY)');
        }
    } catch (PDOException $e) {
        error_log('[rate_limit] ' . $e->getMessage());
    }
}

function bx_rate_limit_clear(PDO $pdo, string $bucket): void
{
    try {
        $pdo->prepare('DELETE FROM rate_limits WHERE bucket = ?')->execute([$bucket]);
    } catch (PDOException $e) {
        error_log('[rate_limit] ' . $e->getMessage());
    }
}

/**
 * Regras do login: 8 falhas por utilizador / 15 min e 30 falhas por IP / 15 min.
 * Devolve os buckets [utilizador, ip] ou null se estiver bloqueado.
 */
const BX_LOGIN_WINDOW = 900;

function bx_login_buckets(string $identifier): array
{
    return [bx_rate_limit_bucket('login-id', $identifier), bx_rate_limit_bucket('login-ip', bx_client_ip())];
}

function bx_login_blocked(PDO $pdo, string $identifier): bool
{
    [$byId, $byIp] = bx_login_buckets($identifier);
    return bx_rate_limited($pdo, $byId, 8, BX_LOGIN_WINDOW) || bx_rate_limited($pdo, $byIp, 30, BX_LOGIN_WINDOW);
}

function bx_login_failed(PDO $pdo, string $identifier): void
{
    foreach (bx_login_buckets($identifier) as $bucket) {
        bx_rate_limit_hit($pdo, $bucket, BX_LOGIN_WINDOW);
    }
}

function bx_login_succeeded(PDO $pdo, string $identifier): void
{
    bx_rate_limit_clear($pdo, bx_login_buckets($identifier)[0]);
}
