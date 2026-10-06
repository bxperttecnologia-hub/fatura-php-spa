<?php

declare(strict_types=1);

require_once '../../../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');

session_start();

try {

    // ======================================================
    // VALIDAR INVOICE ID
    // ======================================================

    $invoiceId = filter_input(
        INPUT_GET,
        'invoice_id',
        FILTER_VALIDATE_INT
    );

    if (!$invoiceId || $invoiceId <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'ID da fatura inválido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ======================================================
    // BUSCAR ÚLTIMA NOTA DE CRÉDITO
    // ======================================================

    $sql = "
        SELECT *
        FROM credit_notes
        WHERE invoice_id = :invoice_id
        ORDER BY id DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(
        ':invoice_id',
        $invoiceId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    // ======================================================
    // FETCH
    // ======================================================

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    // ======================================================
    // RESPOSTA
    // ======================================================

    echo json_encode([
        'success' => true,
        'data' => $note ?: null
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Erro interno no servidor.',
        // Remova esta linha em produção
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
