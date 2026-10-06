<?php
// Só para `php -S`. Em Apache/Nginx usa o .htaccess / regras de rewrite.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/rh/migrations')) { http_response_code(403); return true; }   // scripts CLI
if (str_starts_with($path, '/api/')) {
    $_GET['r'] = substr($path, 5);
    require __DIR__ . '/api.php';
    return true;
}
if ($path !== '/' && is_file(__DIR__ . $path)) return false;
require __DIR__ . '/index.php';
