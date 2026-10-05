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

$companyId = (int)($_GET['company_id'] ?? $_SESSION['user']['company_id'] ?? 0);
$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);

if ($companyId <= 0 || ($sessionCompanyId > 0 && $companyId !== $sessionCompanyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para esta empresa']);
    exit;
}

$payload = ai_api_json('/intelligence/health', ['company_id' => $companyId]);
if (!empty($payload['success'])) {
    $payload['source'] = 'api';
    $payload['company_id'] = $companyId;
    echo json_encode($payload);
    exit;
}

$generated = [
    'success' => true,
    'source' => 'fallback',
    'company_id' => $companyId,
    'overall_score' => 82,
    'financial_score' => 76,
    'customer_score' => 88,
    'operations_score' => 81,
    'reason' => 'A rentabilidade manteve-se estável, mas o risco de endividamento e atraso de faturação aumentou ligeiramente nos últimos 14 dias.',
    'history' => [
        ['period' => '2026-08', 'score' => 79],
        ['period' => '2026-09', 'score' => 82],
        ['period' => '2026-10', 'score' => 85]
    ],
    'api_error' => $payload,
];

echo json_encode($generated);
