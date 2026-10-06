<?php

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';

header('Content-Type: application/json');

session_start();

try {

    // =====================================================
    // VALIDAR SESSÃO
    // =====================================================

    if (!isset($_SESSION['user']['company_id'])) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'error' => 'Company ID não encontrado na sessão.'
        ]);

        exit;
    }

    $company_id = (int) $_SESSION['user']['company_id'];

    // company_id no pedido, se enviado, tem de bater certo com a sessão
    // (mesma regra usada nos outros endpoints do dashboard).
    $requestedCompanyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : $company_id;

    if ($requestedCompanyId !== $company_id) {

        http_response_code(403);

        echo json_encode([
            'success' => false,
            'error' => 'Não autorizado para esta empresa.'
        ]);

        exit;
    }

    $today = date('Y-m-d');

    // =====================================================
    // FATURADO HOJE + DOCUMENTOS HOJE
    // =====================================================

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(final_total), 0) AS total,
            COUNT(*) AS documentos
        FROM invoices
        WHERE company_id = :company_id
          AND DATE(issue_date) = :today
    ");

    $stmt->execute([
        'company_id' => $company_id,
        'today' => $today
    ]);

    $faturado = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'documentos' => 0];

    // =====================================================
    // RECEBIDO HOJE
    // Valor já pago nas faturas emitidas hoje (paid_total).
    // Não existe, neste esquema, uma tabela de pagamentos com
    // data própria, pelo que este valor reflete o que já foi
    // pago das faturas emitidas hoje - não recebimentos de
    // faturas de dias anteriores que tenham sido pagas hoje.
    // =====================================================

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(paid_total), 0) AS total
        FROM invoices
        WHERE company_id = :company_id
          AND DATE(issue_date) = :today
    ");

    $stmt->execute([
        'company_id' => $company_id,
        'today' => $today
    ]);

    $recebidoHoje = (float) $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'data_referencia' => date('d/m/Y', strtotime($today)),
            'faturado_hoje' => round((float) $faturado['total'], 2),
            'documentos_hoje' => (int) $faturado['documentos'],
            'recebido_hoje' => round($recebidoHoje, 2)
        ]
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
