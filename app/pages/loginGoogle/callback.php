<?php
require_once '../../vendor/autoload.php';

$client = new Google_Client();
$client->setClientId('340441912498-t0k66bgtblsld3jbml79olisfvsusavm.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/sistema_fatura/public/callback.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);

    $oauth2 = new Google_Service_Oauth2($client);
    $userInfo = $oauth2->userinfo->get();
print_r($userInfo);
    echo "Nome: " . $userInfo->name . "<br>";
    echo "Email: " . $userInfo->email . "<br>";
    echo "Foto: <img src='" . $userInfo->picture . "'><br>";

    // Aqui você pode armazenar os dados do usuário no banco de dados
} else {
    echo "Erro ao autenticar!";
}
?>
