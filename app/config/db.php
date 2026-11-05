<?php
// Configurações de conexão ao banco de dados
require_once __DIR__ . '/config.php';
$permissionSessionOpened = session_status() !== PHP_SESSION_ACTIVE;
require_once __DIR__ . '/../core/bootstrap.php';

// Datas de emissão e systemEntryDate enviados à AGT têm de estar na hora de Angola,
// independentemente do fuso do servidor (o PHP assume UTC por omissão).
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Luanda');

try {
    // Sem conexões persistentes: uma ligação persistente que morre (MySQL reiniciado, host
    // Windows em suspensão) ficava pendurada sem resposta e bloqueava o pedido — e, com ele,
    // a sessão do utilizador — durante horas. Limites de tempo para nunca esperar indefinidamente.
    ini_set('mysqlnd.net_read_timeout', '60');
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ];

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );
    if (PHP_SAPI !== 'cli') {
        Permission::guardCurrentRequest($pdo);
        if ($permissionSessionOpened && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
