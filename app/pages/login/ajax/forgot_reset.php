<?php
require_once dirname(__DIR__, 4) . '/app/config/db.php';
$pdo->exec("SET time_zone = '+00:00'");
date_default_timezone_set('UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

function out(bool $ok, string $msg): void
{
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

/** Descriptografa um valor enviado pelo JSEncrypt (mesma chave do login). */
function rsaDecrypt(string $encrypted): ?string
{
    $privateKeyPath = dirname(__DIR__, 4) . '/app/keys/private.key';
    $privateKey = file_get_contents($privateKeyPath);
    $ciphertext = base64_decode($encrypted, true);
    if ($privateKey === false || $ciphertext === false) {
        error_log('[forgot_reset] Private key missing or invalid encrypted input.');
        return null;
    }

    $resource = openssl_pkey_get_private($privateKey);
    $plain = null;
    if (!$resource || !openssl_private_decrypt($ciphertext, $plain, $resource)) {
        error_log('[forgot_reset] RSA password decryption failed.');
        return null;
    }
    return $plain ?: null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Requisição inválida.');
}

$fp = $_SESSION['fp'] ?? null;
if (
    !$fp
    || empty($fp['id'])
    || empty($fp['verified'])
    || empty($fp['verified_at'])
    || (int) $fp['verified_at'] < time() - 900
) {
    out(false, 'Sessão de recuperação inválida. Comece novamente.');
}

$password = rsaDecrypt(trim($_POST['password'] ?? ''));
$confirm  = rsaDecrypt(trim($_POST['password_confirm'] ?? ''));

if ($password === null || $confirm === null) {
    out(false, 'Erro na descriptografia da senha.');
}
if ($password !== $confirm) {
    out(false, 'As senhas não coincidem.');
}
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    out(false, 'A senha deve ter pelo menos 8 caracteres, com letras e números.');
}

$now = gmdate('Y-m-d H:i:s');

$stmt = $pdo->prepare(
    "SELECT id, user_id FROM password_resets
     WHERE id = :id AND used = 0 AND expires_at > :now
     LIMIT 1"
);
$stmt->execute([':id' => $fp['id'], ':now' => $now]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    out(false, 'O tempo para redefinir expirou. Comece novamente.');
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE users SET password = :p WHERE id = :u")
        ->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':u' => $row['user_id']]);

    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :id AND used = 0")
        ->execute([':id' => $row['id']]);

    // Termina as sessões ativas desta conta
    $pdo->prepare("DELETE FROM sessions WHERE user_id = :u")
        ->execute([':u' => $row['user_id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[forgot_reset] ' . $e->getMessage());
    out(false, 'Não foi possível alterar a senha. Tente novamente.');
}

unset($_SESSION['fp'], $_SESSION['fp_last_send']);
out(true, 'Senha alterada com sucesso.');