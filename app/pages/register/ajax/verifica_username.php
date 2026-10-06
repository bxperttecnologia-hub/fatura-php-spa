<?php
require_once '../../../app/config/db.php';

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);

    // Prepara a consulta no banco para verificar se o username já existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $existe = $stmt->fetchColumn();

    // Retorna a resposta em JSON
    echo json_encode(['existe' => $existe > 0]);
}
?>
