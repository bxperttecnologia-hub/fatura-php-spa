<?php
require __DIR__ . '/../app/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');   // avisos PHP no corpo estragariam o JSON (ficam no log)

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = trim($_GET['r'] ?? '', '/');
    $raw    = file_get_contents('php://input');
    $input  = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($input)) throw new HttpException(400, 'JSON inválido');

    if ($method !== 'GET') Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($path === 'auth/me') Auth::noRenew();   // verificação periódica: não pode prolongar a sessão

    $r = new Router();
    $r->add('POST',   'auth/login',     [AuthController::class, 'login'], true);
    $r->add('POST',   'auth/logout',    [AuthController::class, 'logout'], true);
    $r->add('GET',    'auth/me',        [AuthController::class, 'me']);
    $r->add('GET',    'documents/config', [DocumentApiController::class, 'config']);
    $r->add('GET',    'clientes',       [ClienteController::class, 'index']);
    $r->add('POST',   'clientes',       [ClienteController::class, 'store']);
    $r->add('GET',    'clientes/{id}',  [ClienteController::class, 'show']);
    $r->add('PUT',    'clientes/{id}',  [ClienteController::class, 'update']);
    $r->add('DELETE', 'clientes/{id}',  [ClienteController::class, 'destroy']);
    $r->add('GET', 'files/companies/{company}/{name}', [FileController::class, 'show'], true);

    echo json_encode($r->dispatch($method, $path, $input));
} catch (HttpException $e) {
    http_response_code($e->status);
    echo json_encode(['message' => $e->getMessage(), 'errors' => $e->errors]);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo json_encode(['message' => config('debug') ? $e->getMessage() : 'Erro interno']);
}
