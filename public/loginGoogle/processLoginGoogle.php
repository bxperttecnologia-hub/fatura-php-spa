<?php
/**
 * Regresso do Google (redirect URI). Valida o state, troca o código por um token, exige e-mail verificado
 * e inicia sessão no utilizador com esse e-mail (só se já existir — não cria contas).
 */
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/google_login.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client = bx_google_client();
if (!$client) {
    bx_google_fail('desativado');
}

$expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);
if ($expectedState === '' || !hash_equals($expectedState, (string)($_GET['state'] ?? '')) || empty($_GET['code'])) {
    bx_google_fail('pedido_invalido');
}

try {
    $token = $client->fetchAccessTokenWithAuthCode((string)$_GET['code']);
    if (!empty($token['error'])) {
        bx_google_fail('codigo_invalido');
    }
    $client->setAccessToken($token);
    $info = (new Google_Service_Oauth2($client))->userinfo->get();
} catch (Throwable $e) {
    error_log('[google_login] ' . $e->getMessage());
    bx_google_fail('erro');
}

$email = (string)($info->email ?? '');
if ($email === '' || !$info->verifiedEmail) {
    bx_google_fail('email_nao_verificado');
}

// Só entra em contas que já existem com este e-mail (não cria contas). Mesma sessão/cookie do login normal.
if (!Auth::loginByEmail($email)) {
    bx_google_fail('sem_conta');
}

header('Location: /');
exit;
