<?php
class Database {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        ini_set('mysqlnd.net_read_timeout', '60');   // nunca esperar indefinidamente por uma ligação morta
        return self::$pdo ??= new PDO(
            'mysql:host=' . config('db_host') . ';port=' . config('db_port') . ';dbname=' . config('db_name') . ';charset=utf8mb4',
            config('db_user'), config('db_pass'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ] + (defined('DB_SQL_MODE') && DB_SQL_MODE !== ''
                ? [PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = '" . str_replace("'", '', DB_SQL_MODE) . "'"]
                : [])
        );
    }
}
