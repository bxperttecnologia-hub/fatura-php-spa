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
$riskLevel = (string)($_GET['risk_level'] ?? 'all');

if ($companyId <= 0 || ($sessionCompanyId > 0 && $companyId !== $sessionCompanyId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para esta empresa']);
    exit;
}

$payload = ai_api_json('/intelligence/customers', ['company_id' => $companyId, 'risk_level' => $riskLevel]);
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
    'items' => [
        [
            'contact_id' => 101,
            'customer_name' => 'ALFA Distribuição',
            'risk_level' => 'high',
            'days_since_last_purchase' => 31,
            'avg_ticket' => 18500,
            'reason' => 'Última compra há 31 dias e tendência de faturação em queda.',
            'recommendation' => 'Enviar proposta de retenção e rever condições de pagamento.'
        ],
        [
            'contact_id' => 102,
            'customer_name' => 'Jardim Verde',
            'risk_level' => 'medium',
            'days_since_last_purchase' => 18,
            'avg_ticket' => 9200,
            'reason' => 'Volume de compras decresceu, mas ainda mantém boa recorrência.',
            'recommendation' => 'Acordar contacto comercial para reforçar relacionamento.'
        ],
        [
            'contact_id' => 103,
            'customer_name' => 'Norte Construtora',
            'risk_level' => 'low',
            'days_since_last_purchase' => 9,
            'avg_ticket' => 22650,
            'reason' => 'Cliente estável com histórico consistente de compras.',
            'recommendation' => 'Manter acompanhamento normal e explorar upsell.'
        ]
    ],
    'api_error' => $payload,
];

echo json_encode($generated);
