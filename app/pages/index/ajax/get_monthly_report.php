<?php

/**
 * Proxy para o serviço de IA (Node/reportService) que gera o relatório mensal.
 * Mantém a URL interna do serviço fora do browser e evita problemas de CORS,
 * já que a chamada é feita aqui no servidor (cURL), não no JS do cliente.
 *
 * GET index/ajax/get_monthly_report.php?company_id=1&year=2026&month=5
 * (year e month são opcionais; por omissão usam o mês/ano atuais)
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Nunca deixar um erro PHP (fatal, warning, notice) imprimir HTML e
// corromper o JSON — qualquer que seja a causa.
ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Erro interno no servidor',
            'detail'  => $error['message'] . ' em ' . $error['file'] . ':' . $error['line'],
        ]);
    }
});

// --- Autenticação básica ---
if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// Verificação explícita em vez de deixar rebentar num Fatal error
// pouco claro — diz exatamente o que falta ativar no php.ini.
if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'A extensão cURL do PHP não está ativa.',
        'detail'  => 'Ativa "extension=curl" no php.ini e reinicia o Apache.',
    ]);
    exit;
}

require_once '../../../app/helpers/ai_api.php';

$companyId = (int)($_GET['company_id'] ?? $_SESSION['user']['company_id'] ?? 0);
$year      = (int)($_GET['year']  ?? date('Y'));
$month     = (int)($_GET['month'] ?? date('n'));

if ($companyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'company_id inválido']);
    exit;
}

// Segurança: só permite consultar dados da empresa ativa na sessão do utilizador
$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($sessionCompanyId > 0 && $companyId !== $sessionCompanyId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para esta empresa']);
    exit;
}

$payload = ai_api_json('/api/reports/monthly', [
    'company_id' => $companyId,
    'year'       => $year,
    'month'      => $month,
], [], 12);

if (!$payload['success']) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error'   => $payload['error'] ?? 'Serviço de IA indisponível',
        'detail'  => $payload['detail'] ?? null,
        'http_code' => $payload['http_code'] ?? 502,
    ]);
    exit;
}

$data = $payload;
$data['success']     = true;
$data['company_id']  = $companyId;
$data['year']        = $year;
$data['month']       = $month;
$data['ai_base_url'] = ai_api_base_url();

echo json_encode($data);