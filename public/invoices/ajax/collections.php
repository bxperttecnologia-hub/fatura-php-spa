<?php
/**
 * Cobranças automáticas da empresa da sessão.
 *   GET  ?action=get      configuração + faturas que entram na próxima execução + histórico
 *   POST ?action=save     enabled, reminder_days, email_subject, email_message
 *   POST ?action=run      envia já os avisos em falta (mesmas regras do cron)
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/collections.php';
header('Content-Type: application/json; charset=utf-8');

$respond = static function (int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

$companyId = (int) ($_SESSION['user']['company_id'] ?? 0);
$userId = (int) ($_SESSION['user']['id'] ?? 0);
$action = (string) ($_GET['action'] ?? 'get');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($companyId <= 0) {
    $respond(401, ['success' => false, 'error' => 'Empresa não encontrada na sessão.']);
}
if (in_array($action, ['save', 'run'], true) && $method !== 'POST') {
    $respond(405, ['success' => false, 'error' => 'Método inválido.']);
}

try {
    switch ($action) {
        case 'get':
            $settings = bx_collection_settings($pdo, $companyId);
            $preview = bx_collection_run_company($pdo, $companyId, true, 'manual');
            $respond(200, [
                'success' => true,
                'settings' => $settings,
                'pending' => $preview['items'],
                'history' => bx_collection_history($pdo, $companyId, 30),
            ]);

        case 'save':
            $settings = bx_collection_save_settings($pdo, $companyId, $userId, $_POST);
            $respond(200, ['success' => true, 'settings' => $settings]);

        case 'run':
            @set_time_limit(300);
            $result = bx_collection_run_company($pdo, $companyId, false, 'manual');
            $respond(200, ['success' => true, 'result' => $result, 'history' => bx_collection_history($pdo, $companyId, 30)]);
    }
    $respond(404, ['success' => false, 'error' => 'Ação desconhecida.']);
} catch (InvalidArgumentException $e) {
    $respond(422, ['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('collections: ' . $e->getMessage());
    $respond(500, ['success' => false, 'error' => 'Erro ao processar a cobrança.']);
}
