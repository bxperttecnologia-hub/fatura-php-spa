<?php
// Devolve uma página legada (app/pages/*.php) como fragmento JSON para a SPA:
//   GET /view.php?__route=/employees[&outros=params]  ->  {"title": "...", "html": "..."}
//                                                    ou {"redirect": "/rota"}
// O CWD é public/, por isso os `require_once '../app/...'` das páginas resolvem para <raiz>/app/.
require __DIR__ . '/../app/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
define('SPA_FRAGMENT', true);

function spa_json(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$__pages = require ROOT . '/config/pages.php';
$__route = '/' . trim((string)($_GET['__route'] ?? '/'), '/');
unset($_GET['__route'], $_REQUEST['__route']);   // as páginas não precisam de o ver

if (!Auth::user())              spa_json(401, ['message' => 'Não autenticado']);
if (!isset($__pages[$__route])) spa_json(404, ['message' => 'Página não encontrada']);

[$__file, $__title] = $__pages[$__route];
$__path = ROOT . '/app/pages/' . $__file;
if (!is_file($__path)) spa_json(404, ['message' => "Ficheiro em falta: app/pages/$__file"]);

// Dependências legadas que não vêm no zip (db.php, helpers/*): dá erro claro em vez de fatal 500.
preg_match_all('~require(?:_once)?\s*[\'"]\.\./app/([^\'"]+)[\'"]~', (string)file_get_contents($__path), $__m);
$__missing = array_values(array_filter(array_unique($__m[1]), fn($f) => !is_file(ROOT . '/app/' . $f)));
if ($__missing) {
    spa_json(500, ['message' => 'Faltam ficheiros do ERP: app/' . implode(', app/', $__missing)]);
}

// Mapa ficheiro -> rota (para traduzir `header('Location: x.php')` das páginas)
$__fileRoutes = [];
foreach ($__pages as $r => [$f]) $__fileRoutes[$f] ??= $r;
$__fileRoutes['login.php'] = '/login';

// Corre também quando a página faz exit (ex.: redirect para a subscrição).
register_shutdown_function(function () use ($__title, $__fileRoutes) {
    $html = '';
    while (ob_get_level() > 0) $html = ob_get_clean() . $html;

    $loc = null;
    foreach (headers_list() as $h) {
        if (stripos($h, 'Location:') === 0) $loc = trim(substr($h, 9));
    }
    header_remove('Location');
    http_response_code(200);

    if ($loc !== null) {
        if (preg_match('~^(?:\.{1,2}/)*([\w\-]+\.php)(\?.*)?$~', $loc, $m) && isset($__fileRoutes[$m[1]])) {
            echo json_encode(['redirect' => $__fileRoutes[$m[1]] . ($m[2] ?? '')]);
        } else {
            echo json_encode(['redirect' => $loc, 'external' => true]);
        }
        return;
    }
    echo json_encode(['title' => $__title, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

ob_start();
try {
    include $__path;           // ao nível global: $pdo & co. ficam visíveis para os helpers legados
} catch (Throwable $e) {
    error_log((string)$e);
    while (ob_get_level() > 0) ob_end_clean();
    spa_json(500, ['message' => config('debug') ? $e->getMessage() : 'Erro interno']);
}
