<?php
require_once '../../../app/config/db.php';
$pdo->exec("SET time_zone = '+00:00'");
date_default_timezone_set('UTC');

session_start();
header('Content-Type: application/json');

function out(bool $ok, string $msg): void
{
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(false, 'Requisição inválida.');
}

$code = preg_replace('/\D+/', '', $_POST['code'] ?? '');
if (strlen($code) !== 6) {
    out(false, 'Digite o código de 6 dígitos.');
}

$fp = $_SESSION['fp'] ?? null;
if (!$fp || empty($fp['id'])) {
    out(false, 'Código inválido ou expirado.');
}

$now = gmdate('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE id = :id AND used_at IS NULL LIMIT 1");
$stmt->execute([':id' => $fp['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || $row['expires_at'] < $now) {
    out(false, 'Código inválido ou expirado. Peça um novo código.');
}

if ((int) $row['attempts'] >= 5) {
    out(false, 'Muitas tentativas. Peça um novo código.');
}

// Conta a tentativa antes de comparar
$pdo->prepare("UPDATE password_resets SET attempts = attempts + 1 WHERE id = :id")
    ->execute([':id' => $row['id']]);

if (!password_verify($code, $row['code_hash'])) {
    $left = max(0, 4 - (int) $row['attempts']);
    out(false, "Código inválido. Tentativas restantes: $left.");
}

$pdo->prepare("UPDATE password_resets SET verified_at = :now WHERE id = :id")
    ->execute([':now' => $now, ':id' => $row['id']]);

session_regenerate_id(true);
$_SESSION['fp']['verified'] = true;

out(true, 'Código confirmado.');