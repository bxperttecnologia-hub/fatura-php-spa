<?php
/**
 * Login com Google (OAuth). Credenciais no .env — nunca no código:
 *   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI (ex.: https://app.bxpert.co.ao/loginGoogle/processLoginGoogle.php)
 * Sem estas variáveis o login Google fica desligado.
 */
require_once __DIR__ . '/../config/config.php';

function bx_google_client(): ?Google_Client
{
    $id = bx_config('GOOGLE_CLIENT_ID');
    $secret = bx_config('GOOGLE_CLIENT_SECRET');
    $redirect = bx_config('GOOGLE_REDIRECT_URI');
    if ($id === '' || $secret === '' || $redirect === '') {
        return null;
    }
    $client = new Google_Client();
    $client->setClientId($id);
    $client->setClientSecret($secret);
    $client->setRedirectUri($redirect);
    $client->addScope('email');
    $client->addScope('profile');
    return $client;
}

function bx_google_fail(string $code): never
{
    header('Location: /login?google=' . rawurlencode($code));
    exit;
}
