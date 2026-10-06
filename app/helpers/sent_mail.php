<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function gerarSenhaTemporaria(int $tamanho = 16): string
{
    return bin2hex(random_bytes($tamanho / 2));
}

function gerarTokenReset(): string
{
    return bin2hex(random_bytes(32)); // 64 chars
}

/**
 * Salva o token de reset com expiração (ex: 24h) associado ao user_id.
 * Ajuste para sua estrutura real de banco.
 */
function salvarTokenReset(PDO $pdo, int $userId, string $token): void
{
    $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at, created_at)
        VALUES (:user_id, :token, :expires_at, NOW())
    ");
    $stmt->execute([
        ':user_id'    => $userId,
        ':token'      => hash('sha256', $token), // guarda hash do token, não o token em si
        ':expires_at' => $expira,
    ]);
}

function enviarEmailBoasVindas(string $emailDestino, string $nome, string $linkDefinirSenha): bool
{
    $phpmailer = new PHPMailer(true);

    try {
        $phpmailer->isSMTP();
        $phpmailer->Host = 'sandbox.smtp.mailtrap.io';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 2525;
        $phpmailer->Username = 'e87da20a4b1b5f';
        $phpmailer->Password = '59c70de83cc2cf';

        $phpmailer->setFrom('naoresponda@seudominio.com', 'Sua Empresa');
        $phpmailer->addAddress($emailDestino, $nome);

        $phpmailer->isHTML(true);
        $phpmailer->CharSet = 'UTF-8';
        $phpmailer->Subject = 'Bem-vindo(a)! Defina sua senha de acesso';
        $phpmailer->Body = "
            <p>Olá, <b>{$nome}</b>!</p>
            <p>Sua conta foi criada. Para acessar o sistema, defina sua senha clicando no link abaixo:</p>
            <p><a href=\"{$linkDefinirSenha}\" style=\"background:#2563eb;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;\">Definir minha senha</a></p>
            <p>Este link expira em 24 horas. Se você não solicitou isso, ignore este email.</p>
        ";
        $phpmailer->AltBody = "Defina sua senha acessando: {$linkDefinirSenha}";

        $phpmailer->send();
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar email de boas-vindas: {$phpmailer->ErrorInfo}");
        return false;
    }
}

// ===== USO dentro do fluxo de criação do usuário =====
// (depois de inserir o usuário no banco e pegar o $userId)

$senhaTemporaria = gerarSenhaTemporaria();
$hashSenha = password_hash($senhaTemporaria, PASSWORD_DEFAULT);
// ... salvar $hashSenha na coluna de senha do usuário ...

$token = gerarTokenReset();
salvarTokenReset($pdo, $userId, $token);

$link = "https://seudominio.com/reset_password.php?token={$token}";
enviarEmailBoasVindas($email, $nome, $link);

// resposta pro AJAX — sem expor senha:
echo json_encode(['success' => true, 'message' => 'Colaborador criado e vinculado.']);
