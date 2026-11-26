<?php
/**
 * Fotografia de perfil (o perfil.js envia um JPEG recortado no browser).
 * Segurança: exige sessão, aceita só imagens reais (assinatura + getimagesize), a extensão vem do tipo
 * detetado (nunca do nome enviado) e o nome do ficheiro é aleatório. A pasta não executa PHP (.htaccess).
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';
header('Content-Type: application/json; charset=utf-8');

function profile_upload_fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_upload_fail(405, 'Método inválido.');
}

$file = $_FILES['profileImage'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    profile_upload_fail(400, 'Erro no upload!');
}
if ((int)$file['size'] <= 0 || (int)$file['size'] > 5 * 1024 * 1024) {
    profile_upload_fail(413, 'A imagem deve ter no máximo 5 MB.');
}

$extensions = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
$info = @getimagesize($file['tmp_name']);
if (!$info || !isset($extensions[$info[2]]) || $info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000) {
    profile_upload_fail(415, 'Formato de imagem inválido. Use JPG, PNG ou WebP.');
}

$newName = 'profile_' . bin2hex(random_bytes(16)) . '.' . $extensions[$info[2]];
$dir = dirname(__DIR__, 2) . '/assets/img/profiles';
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $newName)) {
    profile_upload_fail(500, 'Erro ao mover arquivo!');
}

$_SESSION['user']['image'] = $newName;
echo json_encode(['success' => true, 'imagePath' => $newName]);
