<?php
require_once '../../app/config/db.php';
require_once '../../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_GET['code'])) {
    echo json_encode(['success' => false, 'message' => 'Código de autenticação não encontrado.']);
    exit;
}

// Configuração da API do Google
$client = new Google_Client();
$client->setClientId('340441912498-t0k66bgtblsld3jbml79olisfvsusavm.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/sistema_fatura/public/loginGoogle/processLoginGoogle.php');

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$client->setAccessToken($token);

$oauth2 = new Google_Service_Oauth2($client);
$userInfo = $oauth2->userinfo->get();

// Dados retornados pelo Google
$email = $userInfo->email;
$name = $userInfo->name;
$google_id = $userInfo->id;
$picture = $userInfo->picture;

// Verifica se o usuário já existe no banco
$query = "SELECT u.*, chu.company_id, chu.role, c.name name_company, c.registration_number, c.email email_company, cc.iso_code, cc.currency, cc.symbol, cc.position
          FROM users u 
            JOIN company_has_user chu ON chu.user_id = u.id
            JOIN companies c ON c.id = chu.company_id
            LEFT JOIN currencies cc ON cc.iso_code = u.currency 
          WHERE u.email = :email
          LIMIT 1";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':email', $email);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    unset($user['password']);
    $_SESSION['user'] = $user;
    date_default_timezone_set('America/Sao_Paulo');
    $expires_at = $remember_me ? date('Y-m-d H:i:s', strtotime('+24 hours')) : date('Y-m-d H:i:s', strtotime('+30 minutes'));
    $created_at =  date('Y-m-d H:i:s');

    $token = bin2hex(random_bytes(16));

    $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, expires_at,created_at) VALUES (:user_id, :token, :expires_at, :created_at)");
    $stmt->execute([
        ':user_id' => $user['id'],
        ':token' => $token,
        ':expires_at' => $expires_at,
        ':created_at'=> $created_at
    ]);

    setcookie('session_token', $token, strtotime($expires_at), "/", "", true, true);
    $_SESSION['token'] = $token;
    $_SESSION['expires_at'] = $expires_at; 

    if (empty($user['image']) || $user['image'] == 'default.jpg') {
        $newImagePath = "../../assets/img/profiles/{$user_id}.jpg";
        file_put_contents($newImagePath, file_get_contents($picture));

        // Atualiza no banco de dados
        $updateQuery = "UPDATE users SET image = :image WHERE id = :id";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':image', $user_id . '.jpg');
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();

        $_SESSION['user']['image'] = $user_id . '.jpg';
    }

    echo json_encode(['success' => true, 'message' => 'Login via Google realizado com sucesso.']);
    header('Location: ../index.php');
} else {
    echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
    header('Location: ../login.php?x22');
}
