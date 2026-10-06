<?php

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/invoice_helper.php';

header('Content-Type: application/json');

$invoiceId = (int)($_POST['invoice_id'] ?? 0);

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT series, status FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) throw new Exception("Fatura não encontrada.");

    if (!isDraft((int)$inv['status'])) {
        throw new Exception("Já foi finalizada.");
    }

    $reference = generateInvoiceNumber($pdo, 1, $inv['series']);

    $check = $pdo->prepare("SELECT id FROM invoices WHERE numero_validacao = ?");
    $check->execute([$reference]);

    if ($check->fetch()) {
        throw new Exception("Número já existe.");
    }

    $statusId = getStatusId($pdo, 'Emitida');

    $pdo->prepare("
        UPDATE invoices
        SET status = ?, numero_validacao = ?, reference = ?
        WHERE id = ?
    ")->execute([$statusId, $reference, $reference, $invoiceId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'reference' => $reference
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
