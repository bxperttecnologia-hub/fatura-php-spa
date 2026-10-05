<?php
// register/ajax/set_password.php
// Define a senha de um utilizador recém-criado (não pede senha atual, pois ainda não existe uma).
require_once '../../../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

function responder(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => 'Método não permitido.']);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId === 0) {
    responder(401, ['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
}

$senha = $_POST['password'] ?? '';
$conf  = $_POST['password_confirm'] ?? '';

if ($senha !== $conf) {
    responder(422, ['success' => false, 'message' => 'As senhas não coincidem.']);
}
// Mesmas regras do register.js
if (strlen($senha) < 6 || !preg_match('/[A-Z]/', $senha) || !preg_match('/[a-z]/', $senha) || !preg_match('/[!@#$%^&*]/', $senha)) {
    responder(422, ['success' => false, 'message' => 'A senha deve ter 6+ caracteres, com maiúscula, minúscula e um caractere especial (!@#$%^&*).']);
}

// A condição must_change_password = 1 garante que este endpoint só serve para a primeira definição
$stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ? AND must_change_password = 1");
$stmt->execute([password_hash($senha, PASSWORD_DEFAULT), $userId]);

if ($stmt->rowCount() === 0) {
    responder(403, ['success' => false, 'message' => 'A senha já foi definida. Use a opção de alterar senha.']);
}

responder(200, ['success' => true]);
