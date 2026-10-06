<?php
// register/ajax/verify_otp.php

// BUG FIX: este ficheiro nem sequer tinha ob_clean(). Qualquer aviso/notice
// do PHP vindo dos requires abaixo era enviado antes do JSON e partia a
// resposta (mesmo sintoma do "Ocorreu um erro ao processar a solicitação").
ob_start();

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/otp.php';

if (ob_get_length()) ob_clean();

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

function responder(int $status, array $data): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => 'Método não permitido.']);
}

$id     = (int) ($_SESSION['reg_verification_id'] ?? 0);
$codigo = preg_replace('/\D/', '', $_POST['code'] ?? '');

if ($id === 0 || strlen($codigo) !== 6) {
    responder(422, ['success' => false, 'message' => 'Código inválido.']);
}

// BUG FIX: o catch original só apanhava PDOException. Se gerarUsername(),
// iniciarSessao() ou qualquer outra chamada fora do bloco de BD lançasse
// um erro (TypeError, etc.), continuava a rebentar como erro fatal em vez
// de devolver JSON. Agora apanha-se Throwable de ponta a ponta.
try {
    $pdo->beginTransaction();

    // FOR UPDATE: impede que dois pedidos simultâneos contornem o limite de tentativas
    $stmt = $pdo->prepare("SELECT * FROM phone_verifications WHERE id = ? AND verified_at IS NULL FOR UPDATE");
    $stmt->execute([$id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$v) {
        $pdo->rollBack();
        responder(400, ['success' => false, 'message' => 'Pedido de verificação não encontrado. Peça um novo código.']);
    }
    if (strtotime($v['expires_at']) < time()) {
        $pdo->rollBack();
        responder(410, ['success' => false, 'message' => 'O código expirou. Peça um novo código.']);
    }
    if ($v['attempts'] >= OTP_MAX_ATTEMPTS) {
        $pdo->rollBack();
        responder(429, ['success' => false, 'message' => 'Demasiadas tentativas. Peça um novo código.']);
    }

    if (!password_verify($codigo, $v['code_hash'])) {
        $pdo->prepare("UPDATE phone_verifications SET attempts = attempts + 1 WHERE id = ?")->execute([$id]);
        $pdo->commit();
        responder(422, ['success' => false, 'message' => 'Código incorreto.']);
    }

    // Código correto: cria a conta. Ainda não há senha: guarda-se o hash de um valor aleatório que
    // ninguém conhece, e must_change_password = 1 leva o utilizador a definir a sua própria senha.
    $username = gerarUsername($pdo, $v['name']);
    $agora    = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO users (username, name, password, phone, created_at, phone_verified_at, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$username, $v['name'], password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $v['phone'], $agora, $agora]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->prepare("UPDATE phone_verifications SET verified_at = ? WHERE id = ?")->execute([$agora, $id]);
    $pdo->commit();

    unset($_SESSION['reg_verification_id']);

    // Login automático: o telefone acabou de ser verificado por OTP
    iniciarSessao($userId, $username, $v['name']);

    responder(200, ['success' => true, 'redirect' => 'index.php']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('verify_otp: ' . $e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        responder(409, ['success' => false, 'message' => 'Já existe uma conta com estes dados.']);
    }
    responder(500, ['success' => false, 'message' => 'Erro ao concluir o registo.']);
}