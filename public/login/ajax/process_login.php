<?php
require_once '../../../app/config/db.php';
$pdo->exec("SET time_zone = '+00:00'");
date_default_timezone_set('UTC');

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Identificador: username, e-mail ou telefone
    $identifier = trim($_POST['user_email'] ?? '');
    $encrypted_password = trim($_POST['password'] ?? '');
    $remember_me = isset($_POST['remember_me']) ? 1 : 0;

    if (empty($identifier) || empty($encrypted_password)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
        exit;
    }

    // Carrega a chave privada
    $privateKey = file_get_contents('../../../app/keys/private.key');
    $privateKeyResource = openssl_pkey_get_private($privateKey);

    // Descriptografa a senha
    $password = null;
    openssl_private_decrypt(base64_decode($encrypted_password), $password, $privateKeyResource);

    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Erro na descriptografia da senha.']);
        exit;
    }

    // Normaliza telefone: só dígitos, últimos 9 (ex.: +244 923 456 789 -> 923456789)
    $phone = null;
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $digits = preg_replace('/\D+/', '', $identifier);
        if (strlen($digits) >= 9) {
            $phone = substr($digits, -9);
        }
    }

    // Autenticação por username, e-mail ou telefone
    $query = "SELECT u.*,
                chu.company_id, chu.role, c.name name_company, c.registration_number, c.email email_company,
                cc.iso_code, cc.currency, cc.symbol, cc.position
              FROM users u
                JOIN company_has_user chu ON chu.user_id = u.id
                JOIN companies c ON c.id = chu.company_id
                LEFT JOIN currencies cc ON cc.iso_code = u.currency
              WHERE u.username = :username
                 OR u.email = :email
                 OR RIGHT(REGEXP_REPLACE(u.phone, '[^0-9]', ''), 9) = :phone
              LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':username' => $identifier,
        ':email'    => $identifier,
        ':phone'    => $phone, // null nunca faz match
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;

        $created_at = gmdate('Y-m-d H:i:s');
        $expires_at = $remember_me
            ? gmdate('Y-m-d H:i:s', strtotime('+24 hours'))
            : gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));

        $token = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, expires_at, created_at) VALUES (:user_id, :token, :expires_at, :created_at)");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':token' => $token,
            ':expires_at' => $expires_at,
            ':created_at' => $created_at
        ]);

        setcookie('session_token', $token, strtotime($expires_at), "/", "", true, true);
        $_SESSION['token'] = $token;
        $_SESSION['expires_at'] = $expires_at;

        echo json_encode(['success' => true, 'data' => $_SESSION['user']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuário ou senha inválidos.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
}
