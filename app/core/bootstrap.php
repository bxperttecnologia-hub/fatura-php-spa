<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__, 2));

// Configuração única do ERP (constantes DB_*, APP_ENV, ...). A db.php legada carrega o mesmo ficheiro.
require_once ROOT . '/app/config/config.php';

function config(string $key) {
    return match ($key) {
        'debug'   => !defined('APP_ENV') || APP_ENV !== 'production',
        'db_host' => DB_HOST,
        'db_port' => defined('DB_PORT') ? DB_PORT : '3306',
        'db_name' => DB_NAME,
        'db_user' => DB_USER,
        'db_pass' => DB_PASS,
        default   => null,
    };
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
if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }

    // Helpers legados do ERP (t(), formatName(), currencySelects(), $paises...). Têm de existir uma só vez:
    // as páginas fazem require_once dos mesmos ficheiros, que o PHP ignora por já estarem carregados.
    require_once ROOT . '/app/helpers/translation.php';
    require_once ROOT . '/app/helpers/functions.php';
}
