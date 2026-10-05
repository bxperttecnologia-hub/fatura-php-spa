<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Importante: o token de sessão é criado em UTC no login (created_at/expires_at).
// Então as comparações e renovações aqui também precisam rodar em UTC,
// senão o fuso do servidor pode fazer a sessão expirar/renovar errado.
date_default_timezone_set('UTC');
if (isset($pdo)) {
    try { $pdo->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) { /* ignore */ }
}

/* ===== DEBUG SWITCH ===== */
$DEBUG_AUTH = false; // bota false quando terminar

function auth_debug_exit(string $title, array $data = [], int $code = 401): void {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');

    echo '<style>
        body{font-family:Arial,Helvetica,sans-serif;background:#0b1220;color:#e5e7eb;padding:24px}
        .box{max-width:980px;margin:0 auto;background:#0f172a;border:1px solid #1f2937;border-radius:12px;padding:18px}
        h2{margin:0 0 10px 0;font-size:18px}
        .tag{display:inline-block;background:#111827;border:1px solid #374151;color:#93c5fd;border-radius:999px;padding:4px 10px;font-size:12px;margin-bottom:12px}
        pre{white-space:pre-wrap;background:#020617;border:1px solid #1f2937;padding:14px;border-radius:10px;overflow:auto}
        a{color:#93c5fd}
    </style>';

    echo '<div class="box">';
    echo '<div class="tag">AUTH DEBUG</div>';
    echo '<h2>' . htmlspecialchars($title) . '</h2>';
    echo '<pre>' . htmlspecialchars(print_r($data, true)) . '</pre>';
    echo '<pre>' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>';
    echo '</div>';
    exit;
}

function go_login_or_debug(string $reason, array $extra = []): void {
    global $DEBUG_AUTH;

    if ($DEBUG_AUTH) {
        $payload = array_merge([
            'reason' => $reason,
            'php_timezone' => date_default_timezone_get(),
            'server_time_now' => date('Y-m-d H:i:s'),
            'server_time_ts' => time(),
            'session_id' => session_id(),
            'host' => $_SERVER['HTTP_HOST'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'cookie_present' => isset($_SERVER['HTTP_COOKIE']),
            'cookie_len' => isset($_SERVER['HTTP_COOKIE']) ? strlen($_SERVER['HTTP_COOKIE']) : 0,
            'has_user_id' => isset($_SESSION['user']['id']),
            'has_token' => isset($_SESSION['token']),
        ], $extra);

        auth_debug_exit('Bloqueado pelo middleware de sessão', $payload, 401);
    }

    header('Location: login.php?dx');
    exit;
}

/* ===== A PARTIR DAQUI É TUA LÓGICA ===== */

// Verifica se o usuário está logado
if (!isset($_SESSION['user']['id'])) {
    go_login_or_debug('user_id_ausente');
}

if (isset($_SESSION['token'])) {
    $now = date('Y-m-d H:i:s');

    // Verifica se a sessão é válida
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE session_token = :token LIMIT 1");
    $stmt->execute([':token' => $_SESSION['token']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    $nowTs = strtotime($now);

    if (!$session) {
        session_destroy();
        go_login_or_debug('sessao_nao_encontrada_no_banco');
    }

    $createdTs = strtotime($session['created_at'] ?? '');
    $expiresTs = strtotime($session['expires_at'] ?? '');

    if ($createdTs === false || $expiresTs === false || $nowTs === false) {
        go_login_or_debug('datas_invalidas_parse_strtotime', [
            'db_created_at' => $session['created_at'] ?? null,
            'db_expires_at' => $session['expires_at'] ?? null,
            'php_now' => $now,
            'created_ts' => $createdTs,
            'expires_ts' => $expiresTs,
            'now_ts' => $nowTs,
        ],);
    }

    if (strtotime($session['expires_at']) <= $nowTs) {
        session_destroy();
        go_login_or_debug('sessao_expirada', [
            'db_created_at' => $session['created_at'],
            'db_expires_at' => $session['expires_at'],
            'php_now' => $now,
            'created_ts' => $createdTs,
            'expires_ts' => $expiresTs,
            'now_ts' => $nowTs,
            'diff_seconds_expire_minus_now' => $expiresTs - $nowTs,
        ]);
    }

    // Calcula a duração original da sessão
    $session_duration = $expiresTs - $createdTs;

    if ($session_duration <= 0) {
        session_destroy();
        go_login_or_debug('duracao_sessao_invalida', [
            'db_created_at' => $session['created_at'],
            'db_expires_at' => $session['expires_at'],
            'duration' => $session_duration
        ]);
    }

    // Se ainda estiver dentro do período permitido, renova a sessão
    if ($nowTs < $expiresTs) {
        $new_expiry = date('Y-m-d H:i:s', time() + $session_duration);

        $update_stmt = $pdo->prepare("UPDATE sessions SET expires_at = :new_expiry WHERE session_token = :token");
        $update_stmt->execute([':new_expiry' => $new_expiry, ':token' => $_SESSION['token']]);
    } else {
        session_destroy();
        go_login_or_debug('now_maior_ou_igual_expires', [
            'db_expires_at' => $session['expires_at'],
            'php_now' => $now,
            'expires_ts' => $expiresTs,
            'now_ts' => $nowTs,
        ]);
    }
} else {
    session_destroy();
    go_login_or_debug('token_ausente');
}
