<?php
declare(strict_types=1);

require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);

if (!$invoiceId || $invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da fatura inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Notas de entrega já emitidas para esta fatura (mais recente primeiro)
    $stmt = $pdo->prepare("
        SELECT id, serie, number, issue_date
        FROM delivery_notes
        WHERE invoice_id = :invoice_id
        ORDER BY id DESC
    ");
    $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
    $stmt->execute();
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ainda há itens por entregar?
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ii.quantity - COALESCE(d.qty, 0)), 0)
        FROM invoice_items ii
        LEFT JOIN (
            SELECT dni.invoice_item_id, SUM(dni.quantity) AS qty
            FROM delivery_note_items dni
            JOIN delivery_notes dn ON dn.id = dni.delivery_note_id
            WHERE dn.invoice_id = :inv
            GROUP BY dni.invoice_item_id
        ) d ON d.invoice_item_id = ii.id
        WHERE ii.invoice_id = :inv2 AND ii.quantity > COALESCE(d.qty, 0)
    ");
    $stmt->execute([':inv' => $invoiceId, ':inv2' => $invoiceId]);
    $pendingQty = (float)$stmt->fetchColumn();

    echo json_encode([
        'success'     => true,
        'data'        => $notes,
        'has_pending' => $pendingQty > 0,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
