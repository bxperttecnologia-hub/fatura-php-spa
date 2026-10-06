<?php
// Configurações de conexão ao banco de dados
require_once __DIR__ . '/config.php';

try {
    $isLocal = strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ];

    if ($isLocal) {
        // Usa conexão persistente no localhost
        $options[PDO::ATTR_PERSISTENT] = true;
    }

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
