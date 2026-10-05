<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__, 2));

function config(string $key) {
    static $c;
    $c ??= require ROOT . '/config/config.php';
    return $c[$key] ?? null;
}

spl_autoload_register(function (string $class) {
    if (!preg_match('/^\w+$/', $class)) return;
    foreach (['core', 'controllers', 'models'] as $dir) {
        $f = ROOT . "/app/$dir/$class.php";
        if (is_file($f)) { require $f; return; }
    }
});

require ROOT . '/app/core/helpers.php';

ini_set('display_errors', config('debug') ? '1' : '0');

// A sessão arranca aqui, antes de qualquer output.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
if (session_status() === PHP_SESSION_NONE) session_start();
