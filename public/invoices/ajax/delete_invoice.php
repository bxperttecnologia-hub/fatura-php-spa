<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');
session_start();

$invoiceId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
if (!isset($_SESSION['user']['company_id'])) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error' => 'Empresa não encontrada na sessão.'
    ]);

    exit;
}

$company_id = (int)$_SESSION['user']['company_id'];

if (!$invoiceId || !$company_id) {
    echo json_encode([
        'success' => false,
        'message' => 'invoice_id e company_id são obrigatórios.'
    ]);
    exit;
}

try {

    $pdo->beginTransaction();

    // Procurar a factura
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM invoices
        WHERE id = :id
          AND company_id = :company_id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $invoiceId,
        'company_id' => $company_id
    ]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception('Factura não encontrada.');
    }

    // Bloquear se já existirem notas de entrega ligadas a esta factura
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM delivery_notes
        WHERE invoice_id = :id
          AND company_id = :company_id
    ");
    $stmt->execute(['id' => $invoiceId, 'company_id' => $company_id]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new Exception('Esta factura tem notas de entrega emitidas e não pode ser eliminada ou cancelada.');
    }

    // Bloquear se já existirem notas de débito ligadas a esta factura
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM debit_notes
        WHERE invoice_id = :id
          AND company_id = :company_id
    ");
    $stmt->execute(['id' => $invoiceId, 'company_id' => $company_id]);

    if ((int)$stmt->fetchColumn() > 0) {
        throw new Exception('Esta factura tem notas de débito emitidas e não pode ser eliminada ou cancelada.');
    }

    if ((int)$invoice['status'] === 1) {

        // Eliminar os itens
        $stmt = $pdo->prepare("
            DELETE FROM invoice_items
            WHERE invoice_id = :id
        ");

        $stmt->execute([
            'id' => $invoiceId
        ]);

        // Eliminar a factura
        $stmt = $pdo->prepare("
            DELETE FROM invoices
            WHERE id = :id
              AND company_id = :company_id
        ");

        $stmt->execute([
            'id' => $invoiceId,
            'company_id' => $company_id
        ]);

        $message = 'Factura eliminada com sucesso.';
    } else {

        // Cancelar a factura
        $stmt = $pdo->prepare("
            UPDATE invoices
            SET status = 2,
                updated_at = NOW()
            WHERE id = :id
              AND company_id = :company_id
        ");

        $stmt->execute([
            'id' => $invoiceId,
            'company_id' => $company_id
        ]);

        $message = 'Factura cancelada com sucesso.';
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
