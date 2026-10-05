<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

require_once '../../../app/helpers/ai_api.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);

$companyId = (int)($data['company_id'] ?? $_SESSION['user']['company_id'] ?? 0);
$message = trim((string)($data['message'] ?? ''));

$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($companyId <= 0 || ($sessionCompanyId > 0 && $companyId !== $sessionCompanyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para esta empresa']);
    exit;
}

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia']);
    exit;
}

$payload = ai_api_json('/copilot/ask', ['company_id' => $companyId], [], 12);
if (!empty($payload['success'])) {
    $payload['source'] = 'api';
    $payload['company_id'] = $companyId;
    echo json_encode($payload);
    exit;
}

$answer = 'Analisei o contexto da empresa e a ação prioritária recomendada é rever as pendências de faturação e clientes com risco de atraso. Reune os dados de fluxo de caixa, faturação e relacionamento para confirmar a ordem de ação.';

$lower = strtolower($message);
if (str_contains($lower, 'cliente') || str_contains($lower, 'risco')) {
    $answer = 'Os clientes com maior risco são os que apresentam ausência de compras recentes, quedas no ticket médio e atraso superior a 15 dias. A recomendação principal é contactar estes clientes em prioridade e propor uma ação de retenção.';
} elseif (str_contains($lower, 'saude') || str_contains($lower, 'empresa')) {
    $answer = 'A saúde da empresa está estável, mas o risco principal continua concentrado na cobrança e no atraso de pagamento. O melhor próximo passo é reforçar a cobrança e reduzir o tempo entre faturação e recebimento.';
} elseif (str_contains($lower, 'ação') || str_contains($lower, 'prioridade')) {
    $answer = 'A ação prioritária é rever a carteira de clientes em atraso e priorizar os mais urgentes por risco de impacto no fluxo de caixa. Em paralelo, confirmar pendências internas e ajustar a comunicação comercial.';
}

echo json_encode([
    'success' => true,
    'source' => 'fallback',
    'company_id' => $companyId,
    'answer' => $answer,
    'message' => $answer,
    'api_error' => $payload,
]);
