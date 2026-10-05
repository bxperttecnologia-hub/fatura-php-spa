<?php

declare(strict_types=1);

// ===== DEBUG: ative só quando precisar, sem editar o código =====
// Acesse a URL com ?debug=1 (ex.: update_profile.php?debug=1) para ver o dump.
// Nunca deixe isso acessível em produção sem proteção extra (ex.: checar $_SESSION['user']['adm']).
$DEBUG = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
// ===================================================================

require_once __DIR__ . '/../../../app/config/db.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método não permitido."]);
    exit;
}

if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Sessão expirada. Faça login novamente."]);
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// Whitelist de campos permitidos — só entra na query o que for enviado E permitido
$allowedKeys = [
    'username',
    'name',
    'phone',
    'email',
    'address',
    'country',
    'lang'
];

$updates = [];
$params = [];
$sessionUpdates = [];

foreach ($_POST as $key => $value) {
    if (!in_array($key, $allowedKeys, true)) {
        continue;
    }
    if (!is_scalar($value)) {
        continue;
    }

    $value = trim((string)$value);

    if ($value === '') {
        continue;
    }

    $updates[] = "$key = :$key";
    $params[":$key"] = $value;
    $sessionUpdates[$key] = $value;
}

// Imagem: só atualiza se houver uma nova imagem pendente na sessão
// (garanta que o nome salvo aqui NÃO tenha querystring, ex: "foto.jpg" e não "foto.jpg?t=123")
if (!empty($_SESSION['user']['image'])) {
    $updates[] = "image = :image";
    $params[":image"] = $_SESSION['user']['image'];
}

// if ($DEBUG) {
//     echo json_encode([
//         "status" => "debug",
//         "userId" => $userId,
//         "post_recebido" => $_POST,
//         "updates_montado" => $updates,
//         "params_montado" => $params,
//         "pdo_existe" => isset($pdo) ? get_class($pdo) : "PDO NAO DEFINIDO",
//     ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//     exit;
// }

if (empty($updates)) {
    echo json_encode(["status" => "error", "message" => "Nenhum dado válido para atualizar."]);
    exit;
}

$sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
$params[":id"] = $userId;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Atualiza a sessão com os novos valores
    foreach ($sessionUpdates as $key => $value) {
        $_SESSION['user'][$key] = $value;
    }

    echo json_encode([
        "status" => "success",
        "rowsAffected" => $stmt->rowCount(), // útil no front pra saber se algo de fato mudou no banco
    ]);
} catch (PDOException $e) {
    error_log('Erro ao atualizar perfil: ' . $e->getMessage());

    if ($DEBUG) {
        echo json_encode(["status" => "error", "message" => $e->getMessage(), "sql" => $sql, "params" => $params]);
        exit;
    }

    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Erro ao atualizar perfil."]);
}
