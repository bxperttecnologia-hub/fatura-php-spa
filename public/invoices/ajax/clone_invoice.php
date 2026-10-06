<?php

session_start();

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/invoice_helper.php';

header('Content-Type: application/json');

try {

    $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
    $userId    = (int)($_SESSION['user']['id'] ?? 0);

    if (!$companyId || !$userId) {
        throw new Exception("Sessão inválida.");
    }

    $invoiceId = (int)($_POST['invoice_id'] ?? 0);

    if ($invoiceId <= 0) {
        throw new Exception("ID da factura inválido.");
    }

    $pdo->beginTransaction();

    // =====================================================
    // FATURA ORIGINAL
    // =====================================================

    $stmt = $pdo->prepare("
        SELECT *
        FROM invoices
        WHERE id = ?
        AND company_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $invoiceId,
        $companyId
    ]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Factura não encontrada.");
    }

    // =====================================================
    // BUSCAR ITENS
    // =====================================================

    $stmtItems = $pdo->prepare("
        SELECT *
        FROM invoice_items
        WHERE invoice_id = ?
    ");

    $stmtItems->execute([$invoiceId]);

    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception(
            "A factura não possui itens."
        );
    }

    // =====================================================
    // GERAR NOVA REFERÊNCIA
    // =====================================================

    $reference = generate_document_number(
        $pdo,
        $companyId,
        'FT'
    );

    // =====================================================
    // CLONAR FATURA
    // =====================================================

    $stmtInsert = $pdo->prepare("
        INSERT INTO invoices (
            contact_id,
            company_id,
            user_id,
            status,
            issue_date,
            due_date,
            reference,
            observation,
            series,
            retention,
            retention_value,
            currency,
            manual_exchange_rate,
            total_sum,
            total_discount,
            subtotal_without_tax,
            total_tax,
            final_total,
            converted_total,
            paid_total,
            document_type
        )
        VALUES (
            ?,?,?,?,?,?,?,?,?,?,
            ?,?,?,?,?,?,?,?,?,?,
            ?
        )
    ");

    $stmtInsert->execute([

        $invoice['contact_id'],
        $companyId,
        $userId,

        1, // rascunho

        date('Y-m-d'),

        $invoice['due_date'],

        $reference,

        $invoice['observation'],

        $invoice['series'],

        $invoice['retention'],

        $invoice['retention_value'],

        $invoice['currency'],

        $invoice['manual_exchange_rate'],

        $invoice['total_sum'],

        $invoice['total_discount'],

        $invoice['subtotal_without_tax'],

        $invoice['total_tax'],

        $invoice['final_total'],

        $invoice['converted_total'],

        0, // nova factura sem pagamentos

        'FT'
    ]);

    $newInvoiceId = (int)$pdo->lastInsertId();

    // =====================================================
    // CLONAR ITENS
    // =====================================================

    $stmtInsertItem = $pdo->prepare("
        INSERT INTO invoice_items (
            invoice_id,
            item_id,
            quantity,
            unit_price,
            tax,
            discount
        )
        VALUES (?,?,?,?,?,?)
    ");

    foreach ($items as $item) {

        $stmtInsertItem->execute([
            $newInvoiceId,
            $item['item_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['tax'],
            $item['discount']
        ]);
    }

    // =====================================================
    // COMMIT
    // =====================================================

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'new_invoice_id' => $newInvoiceId,
        'reference' => $reference,
        'message' => 'Factura clonada com sucesso.'
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
