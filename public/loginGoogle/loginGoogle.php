<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';          // arranca a sessão
require_once __DIR__ . '/../../app/helpers/google_login.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client = bx_google_client();
if (!$client) {
    bx_google_fail('desativado');
}

// state: liga o regresso do Google a esta sessão (impede login CSRF)
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
$client->setState($_SESSION['google_oauth_state']);

header('Location: ' . $client->createAuthUrl());
exit;
