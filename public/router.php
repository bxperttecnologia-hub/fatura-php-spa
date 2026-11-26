<?php
// Só para `php -S`. Em Apache/Nginx usa o .htaccess / regras de rewrite.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/rh/migrations')) { http_response_code(403); return true; }   // scripts CLI
// Pastas de ficheiros enviados: nunca executar scripts (igual a assets/img|docs/.htaccess no Apache)
if (preg_match('#^/assets/(img|docs)/#i', $path) && preg_match('#\.(php\d?|phtml|phar|pht|phps)$#i', $path)) {
    http_response_code(403);
    return true;
}
// Documentos de funcionários: só via /rh/ajax/employee_file.php (igual a assets/docs/employees/.htaccess)
if (stripos($path, '/assets/docs/employees/') === 0) { http_response_code(403); return true; }
if (str_starts_with($path, '/api/documents/')) {   // PDFs de documentos (ver document_pdf.php)
    $_GET['path'] = substr($path, strlen('/api/documents/'));
    require __DIR__ . '/document_pdf.php';
    return true;
}
if (str_starts_with($path, '/api/')) {
    $_GET['r'] = substr($path, 5);
    require __DIR__ . '/api.php';
    return true;
}
if ($path !== '/' && is_file(__DIR__ . $path)) return false;
require __DIR__ . '/index.php';
