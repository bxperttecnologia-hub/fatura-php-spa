<?php
require_once  '../../vendor/autoload.php';


$client = new Google_Client();
$client->setClientId('340441912498-t0k66bgtblsld3jbml79olisfvsusavm.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/sistema_fatura/public/loginGoogle/processLoginGoogle.php'); // Altere para a URL do seu sistema
$client->addScope("email");
$client->addScope("profile");

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit();
?>
