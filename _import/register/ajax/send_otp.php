<?php
// register/ajax/send_otp.php

// BUG FIX: o código original chamava ob_clean() sem nunca ter chamado
// ob_start(). Se o output_buffering não estiver ligado no php.ini do
// servidor, ob_get_length()/ob_clean() não têm nada para "limpar" e
// qualquer aviso/notice do PHP emitido pelos requires abaixo (db.php,
// otp.php) é enviado ANTES do JSON, corrompendo a resposta. É exatamente
// isto que faz o jQuery falhar o parse (dataType:'json') e cair no erro
// genérico "Ocorreu um erro ao processar a solicitação."
ob_start();

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/otp.php';

// Agora sim: há sempre um buffer ativo para limpar avisos/notices soltos.
if (ob_get_length()) ob_clean();

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

function responder(int $status, array $data): void
{
    if (ob_get_length()) ob_clean(); // garante que nada "sujo" escapa antes do JSON
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => 'Método não permitido.']);
}

$name  = trim($_POST['name'] ?? '');
$canal = (($_POST['channel'] ?? '') === 'whatsapp') ? 'whatsapp' : 'sms';
$phone = normalizarTelefone($_POST['phone'] ?? '');
$ip    = $_SERVER['REMOTE_ADDR'] ?? null;

if (mb_strlen($name) < 3) {
    responder(422, ['success' => false, 'message' => 'Indique o seu nome completo.']);
}

if ($phone === null) {
    responder(422, ['success' => false, 'message' => 'Telefone inválido ou prefixo do país não reconhecido.']);
}

// BUG FIX: todo o bloco de base de dados / envio Twilio estava fora de
// qualquer try/catch. Uma PDOException (ex: coluna obrigatória em falta
// no INSERT, ligação perdida) rebentava como erro fatal do PHP em vez de
// devolver JSON, com o mesmo sintoma do ecrã de erro genérico.
try {
    // Já existe conta?
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn()) {
        responder(409, ['success' => false, 'message' => 'Já existe uma conta com este telefone.']);
    }

    // =========================================================================
    // Limites de envio (Comentados temporariamente para facilitar seus testes)
    // Descomente quando for para produção!
    // =========================================================================
    $umaHora = date('Y-m-d H:i:s', time() - 3600);

    /*
    $stmt = $pdo->prepare("SELECT COUNT(*), MAX(created_at) FROM phone_verifications WHERE phone = ? AND created_at > ?");
    $stmt->execute([$phone, $umaHora]);
    [$enviosTel, $ultimo] = $stmt->fetch(PDO::FETCH_NUM);

    if ($enviosTel >= OTP_MAX_SENDS_PER_HOUR) {
        responder(429, ['success' => false, 'message' => 'Muitas tentativas. Tente novamente dentro de uma hora.']);
    }
    if ($ultimo && (time() - strtotime($ultimo)) < OTP_RESEND_SECONDS) {
        responder(429, ['success' => false, 'message' => 'Aguarde um minuto antes de pedir um novo código.']);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM phone_verifications WHERE ip = ? AND created_at > ?");
    $stmt->execute([$ip, $umaHora]);
    if ($stmt->fetchColumn() >= OTP_MAX_SENDS_PER_IP) {
        responder(429, ['success' => false, 'message' => 'Muitas tentativas. Tente novamente mais tarde.']);
    }
    */

    // Gera e guarda o código (só o hash)
    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $agora  = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', time() + OTP_TTL_MINUTES * 60);

    $stmt = $pdo->prepare("INSERT INTO phone_verifications (name, phone, channel, code_hash, ip, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $canal, password_hash($codigo, PASSWORD_DEFAULT), $ip, $expira, $agora]);
    $id = (int) $pdo->lastInsertId();

    if (!enviarOtp($phone, $canal, $codigo)) {
        $pdo->prepare("DELETE FROM phone_verifications WHERE id = ?")->execute([$id]);
        responder(502, ['success' => false, 'message' => 'Não foi possível enviar o código via Twilio. Verifique os dados no .env e os logs.']);
    }

    $_SESSION['reg_verification_id'] = $id;

    responder(200, [
        'success'      => true,
        'phone_masked' => substr($phone, 0, 4) . str_repeat('•', max(0, strlen($phone) - 7)) . substr($phone, -3),
        'message'      => 'Código enviado com sucesso!'
    ]);
} catch (Throwable $e) {
    // Nunca deixar um erro fatal virar HTML no meio do JSON: regista o
    // detalhe no log do servidor e devolve sempre uma resposta JSON limpa.
    error_log('send_otp: ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => 'Erro ao enviar o código. Tente novamente.']);
}