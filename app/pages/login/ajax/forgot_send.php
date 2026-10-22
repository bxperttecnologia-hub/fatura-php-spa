<?php
require_once '../../../config/db.php';
require_once '../../../helpers/notify.php';
$pdo->exec("SET time_zone = '+00:00'");
date_default_timezone_set('UTC');

session_start();
header('Content-Type: application/json');

function out(bool $ok, string $msg, array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Requisição inválida.');
}

$method     = $_POST['method'] ?? '';
$identifier = trim($_POST['identifier'] ?? '');
$channel    = $_POST['channel'] ?? 'email';

// ---- Validação ----
$phone9 = null;
if ($method === 'email') {
    $channel = 'email';
    if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        out(false, 'Informe um e-mail válido.');
    }
} elseif ($method === 'phone') {
    if ($channel !== 'sms') {
        out(false, 'A recuperação por telefone está disponível por SMS.');
    }
    $phone9 = normalizePhone9($identifier);
    if (!$phone9) {
        out(false, 'Informe um telefone válido.');
    }
} else {
    out(false, 'Método inválido.');
}

// ---- Intervalo mínimo entre pedidos (por sessão) ----
$wait = 60 - (time() - (int) ($_SESSION['fp_last_send'] ?? 0));
if ($wait > 0) {
    out(false, "Aguarde $wait segundos antes de pedir um novo código.", ['wait' => $wait]);
}
$_SESSION['fp_last_send'] = time();

// ---- Máscara para mostrar ao utilizador (baseada no que ele digitou, não na BD) ----
if ($method === 'email') {
    [$local, $domain] = explode('@', $identifier, 2);
    $masked = substr($local, 0, 1) . '***@' . $domain;
} else {
    $masked = '*** *** ' . substr($phone9, -3);
}
$genericMsg = 'Se os dados estiverem corretos, enviámos um código.';

// ---- Procura o utilizador ----
if ($method === 'email') {
    $stmt = $pdo->prepare("SELECT id, email, phone FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $identifier]);
} else {
    $stmt = $pdo->prepare(
        "SELECT id, email, phone FROM users
         WHERE RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 9) = :phone LIMIT 1"
    );
    $stmt->execute([':phone' => $phone9]);
}
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Resposta idêntica quando a conta não existe (evita revelar quem está registado)
if (!$user) {
    $_SESSION['fp'] = ['id' => 0, 'verified' => false];
    out(true, $genericMsg, ['masked' => $masked, 'ttl' => RESET_CODE_TTL_SECONDS]);
}

// ---- Limite por utilizador: 5 pedidos por hora ----
$now   = gmdate('Y-m-d H:i:s');
$since = gmdate('Y-m-d H:i:s', time() - 3600);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = :u AND created_at > :since");
$stmt->execute([':u' => $user['id'], ':since' => $since]);
if ((int) $stmt->fetchColumn() >= 5) {
    out(false, 'Muitos pedidos. Tente novamente dentro de uma hora.');
}

// ---- Destino real (sempre o que está na conta) ----
if ($channel === 'email') {
    $destination = $user['email'];
} else {
    if (empty($user['phone'])) {
        out(true, $genericMsg, ['masked' => $masked, 'ttl' => RESET_CODE_TTL_SECONDS]);
    }
    $destination = toE164($user['phone']);
}

// ---- Gera o código e invalida os anteriores ----
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = :u AND used = 0")
    ->execute([':u' => $user['id']]);

$pdo->prepare(
    "INSERT INTO password_resets (user_id, token, expires_at, used, created_at)
     VALUES (:u, :token, :exp, 0, :now)"
)->execute([
    ':u' => $user['id'],
    ':token' => password_hash($code, PASSWORD_DEFAULT),
    ':exp' => gmdate('Y-m-d H:i:s', time() + RESET_CODE_TTL_SECONDS),
    ':now' => $now,
]);
$resetId = (int) $pdo->lastInsertId();

// ---- Envia ----
switch ($channel) {
    case 'sms':
        $smsError = null;
        $sent = sendSms($destination, $code, $smsError);
        break;
    default:
        $sent = sendResetEmail($destination, $code);
}

if (!$sent) {
    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :id")
        ->execute([':id' => $resetId]);
    $_SESSION['fp_last_send'] = 0;
    out(false, 'Não foi possível enviar o código. Tente novamente em instantes.'
        . (!empty($smsError) ? ' GoSMS: ' . $smsError : ''));
}

session_regenerate_id(true);
$_SESSION['fp'] = ['id' => $resetId, 'verified' => false, 'attempts' => 0];

out(true, $genericMsg, ['masked' => $masked, 'ttl' => RESET_CODE_TTL_SECONDS]);