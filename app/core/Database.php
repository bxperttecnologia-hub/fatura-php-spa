<?php
class Database {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        return self::$pdo ??= new PDO(
            'mysql:host=' . config('db_host') . ';port=' . config('db_port') . ';dbname=' . config('db_name') . ';charset=utf8mb4',
            config('db_user'), config('db_pass'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
}
