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
    if (defined('DB_SQL_MODE') && DB_SQL_MODE !== '') {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET SESSION sql_mode = '" . str_replace("'", '', DB_SQL_MODE) . "'";
    }

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
    if (defined('SUBSCRIPTION_CHARGE_API') && SUBSCRIPTION_CHARGE_API) {
        $technicalMessage = $e->getMessage();
        if (defined('DB_PASS') && DB_PASS !== '') {
            $technicalMessage = str_replace(DB_PASS, '[redacted]', $technicalMessage);
        }
        error_log('[subscription_payment] ' . json_encode([
            'order_id' => null,
            'charge_id' => null,
            'error_type' => 'DATABASE_CONNECTION_FAILED',
            'timestamp' => date(DATE_ATOM),
            'http_code' => 503,
            'technical_message' => $technicalMessage,
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível criar a cobrança. Tente novamente mais tarde.',
            'code' => 'DATABASE_UNAVAILABLE',
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
