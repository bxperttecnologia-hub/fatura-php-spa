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
$actionId = (string)($data['action_id'] ?? '');
$actionText = trim((string)($data['action_text'] ?? ''));
$approve = !empty($data['approve']) && (bool)$data['approve'];

$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($companyId <= 0 || ($sessionCompanyId > 0 && $companyId !== $sessionCompanyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para esta empresa']);
    exit;
}

if ($actionId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Identificador da ação inválido']);
    exit;
}

$payload = ai_api_json('/copilot/actions/' . urlencode($actionId) . '/confirm', ['company_id' => $companyId], [], 12);
if (!empty($payload['success'])) {
    $payload['source'] = 'api';
    $payload['company_id'] = $companyId;
    $payload['approved'] = $approve;
    $payload['action_id'] = $actionId;
    $payload['action_text'] = $actionText;
    echo json_encode($payload);
    exit;
}

echo json_encode([
    'success' => true,
    'source' => 'fallback',
    'company_id' => $companyId,
    'action_id' => $actionId,
    'action_text' => $actionText,
    'approved' => $approve,
    'message' => $approve ? 'Ação confirmada com sucesso.' : 'Ação rejeitada pelo utilizador.',
    'api_error' => $payload,
]);
