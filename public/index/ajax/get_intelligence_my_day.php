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

$payload = ai_api_json('/intelligence/my-day', ['company_id' => $companyId]);

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
    'health_score' => 82,
    'resumo' => 'Há 3 alertas urgentes e 2 oportunidades de melhoria com impacto financeiro imediato.',
    'critico' => [
        [
            'title' => 'Faturas em atraso',
            'type' => 'invoice_overdue',
            'severity' => 'high',
            'message' => 'Existem clientes com pagamentos em atraso e risco de impacto no fluxo de caixa.',
            'action' => 'Rever a cobrança e priorizar clientes com dívida superior a 15 dias.'
        ],
        [
            'title' => 'Risco de caixa',
            'type' => 'cash_risk',
            'severity' => 'high',
            'message' => 'A projeção de caixa está abaixo da margem de segurança esperada para o próximo ciclo.',
            'action' => 'Mapear pagamentos pendentes e ajustar o calendário de pagamentos.'
        ]
    ],
    'atencao' => [
        [
            'title' => 'Prazo de vencimento do plano',
            'type' => 'subscription_expiring',
            'severity' => 'medium',
            'message' => 'A assinatura atual está próxima do limite de renovação.',
            'action' => 'Confirmar renovação para evitar interrupção de serviço.'
        ]
    ],
    'oportunidades' => [
        [
            'title' => 'Clientes com potencial de recompra',
            'type' => 'recovery_opportunity',
            'severity' => 'low',
            'message' => 'Há clientes com histórico de compra recente que ainda não foram recontactados.',
            'action' => 'Disparar uma campanha de follow-up em 48 horas.'
        ]
    ],
    'api_error' => $payload,
];

echo json_encode($generated);
