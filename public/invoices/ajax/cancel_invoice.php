<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');

$invoiceId = (int)($_POST['invoice_id'] ?? 0);

try {

    $pdo->beginTransaction();

    // Bloquear se já existirem notas de entrega ligadas a esta factura
    $dn = $pdo->prepare("SELECT COUNT(*) FROM delivery_notes WHERE invoice_id = ?");
    $dn->execute([$invoiceId]);

    if ((int)$dn->fetchColumn() > 0) {
        throw new Exception('Esta factura tem notas de entrega emitidas e não pode ser reaberta.');
    }

    // Bloquear se já existirem notas de débito ligadas a esta factura
    $nd = $pdo->prepare("SELECT COUNT(*) FROM debit_notes WHERE invoice_id = ?");
    $nd->execute([$invoiceId]);

    if ((int)$nd->fetchColumn() > 0) {
        throw new Exception('Esta factura tem notas de débito emitidas e não pode ser reaberta.');
    }

    $items = $pdo->prepare("
        SELECT ii.*, i.code, i.track_stock
        FROM invoice_items ii
        JOIN items i ON i.id = ii.item_id
        WHERE ii.invoice_id = ?
    ");

    $items->execute([$invoiceId]);
    $rows = $items->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {

        if (strpos(strtoupper($r['code']), 'SERV') === 0) continue;

        $stmt = $pdo->prepare("CALL update_stock_quantity(?, ?, ?)");
        $stmt->execute([$r['stock_id'], $r['item_id'], $r['quantity']]);

        while ($stmt->nextRowset()) {
        }
    }

    $pdo->prepare("
        UPDATE invoices
        SET status = 0, numero_validacao = NULL, reference = NULL
        WHERE id = ?
    ")->execute([$invoiceId]);

    $pdo->commit();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
