<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function out(array $d): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user']['id'])) {
    out(["success" => false, "message" => "Sessão expirada. Faça login novamente."]);
}

if (!isset($_FILES['profileImage']) || $_FILES['profileImage']['error'] !== UPLOAD_ERR_OK) {
    out(["success" => false, "message" => "Erro no upload!"]);
}

$file = $_FILES['profileImage'];

if ($file['size'] > 5 * 1024 * 1024) {
    out(["success" => false, "message" => "Imagem muito grande (máx. 5 MB)."]);
}

// Tipo real do ficheiro (não confiar no nome enviado pelo cliente)
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extMap[$mime])) {
    out(["success" => false, "message" => "Formato inválido. Use JPG, PNG ou WEBP."]);
}

$dir = __DIR__ . '/../../assets/img/profiles/';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$newName = uniqid('profile_') . '.' . $extMap[$mime];

if (move_uploaded_file($file['tmp_name'], $dir . $newName)) {
    $_SESSION['user']['image'] = $newName;
    out(["success" => true, "imagePath" => $newName]);
}

out(["success" => false, "message" => "Erro ao mover arquivo! Verifique permissões da pasta assets/img/profiles."]);
