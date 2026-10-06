<?php

/**
 * Endpoint LEVE para o card "Sugestões do Agente IA" no dashboard.
 * Ao contrário de get_monthly_report.php (que faz proxy AO VIVO para o
 * serviço Node e pode chamar o Gemini), este ficheiro só lê a tabela
 * `ai_insight_cache`, escrita pelo monthlyReportJob do plugin Node.
 * Isto é DELIBERADO: o dashboard atualiza-se a cada 30s (refreshDashboard),
 * e se chamasse o Gemini a cada refresh esgotava a quota gratuita em minutos.
 *
 * GET index/ajax/get_ai_insights.php?company_id=1
 */

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

// Nunca deixar um erro PHP (fatal, warning, notice) imprimir HTML e
// corromper o JSON — qualquer que seja a causa, a resposta tem de
// continuar a ser JSON válido. Isto é o que estava a acontecer antes.
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

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// TODO: ajusta este require ao ficheiro real de bootstrap da tua ligação
// PDO (o mesmo que os outros scripts em index/ajax/ já usam), populando $pdo.
require_once '../../config/db.php';
require_once '../../../app/helpers/ai_api.php';

$companyId = (int)($_GET['company_id'] ?? $_SESSION['user']['company_id'] ?? 0);

$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($companyId <= 0 || ($sessionCompanyId > 0 && $companyId !== $sessionCompanyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para esta empresa']);
    exit;
}

$apiPayload = ai_api_json('/intelligence/my-day', ['company_id' => $companyId]);
if ($apiPayload['success']) {
    $apiPayload['source'] = 'api';
    $apiPayload['company_id'] = $companyId;
    $apiPayload['success'] = true;
    echo json_encode($apiPayload);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT year, month, summary, suggestions, generated_at
         FROM ai_insight_cache
         WHERE company_id = :company_id
         ORDER BY year DESC, month DESC
         LIMIT 1'
    );
    $stmt->execute(['company_id' => $companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao consultar o cache de insights',
        'detail' => $e->getMessage(),
        'api_error' => $apiPayload,
    ]);
    exit;
}

if (!$row) {
    echo json_encode([
        'success'     => true,
        'available'   => false,
        'source'      => 'cache',
        'company_id'  => $companyId,
        'message'     => 'Ainda sem sugestões geradas. O relatório mensal corre no dia 1 de cada mês.',
        'api_error'   => $apiPayload,
    ]);
    exit;
}

echo json_encode([
    'success'      => true,
    'available'    => true,
    'source'       => 'cache',
    'company_id'   => $companyId,
    'year'         => (int)$row['year'],
    'month'        => (int)$row['month'],
    'summary'      => json_decode($row['summary'] ?? '{}', true),
    'suggestions'  => $row['suggestions'],
    'generated_at' => $row['generated_at'],
    'api_error'    => $apiPayload,
]);
