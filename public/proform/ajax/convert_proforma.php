<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/document_helper.php';

header('Content-Type: application/json');

session_start();

try {

    $companyId = (int)($_SESSION['user']['company_id'] ?? 0);
    $userId    = (int)($_SESSION['user']['id'] ?? 0);

    if (!$companyId) {
        throw new Exception('Sessão inválida.');
    }

    $proformaId = (int)($_POST['invoice_id'] ?? 0);

    if ($proformaId <= 0) {
        throw new Exception('Proforma inválida.');
    }

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | BUSCAR PROFORMA
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT *
        FROM proformas
        WHERE id = ?
        AND company_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $proformaId,
        $companyId
    ]);

    $proforma = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proforma) {
        throw new Exception('Proforma não encontrada.');
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFICAR SE JÁ FOI CONVERTIDA
    |--------------------------------------------------------------------------
    */

    if (!empty($proforma['converted_invoice_id'])) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                reference
            FROM invoices
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $proforma['converted_invoice_id']
        ]);

        $existingInvoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingInvoice) {

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'already_converted' => true,
                'new_invoice_id' => (int)$existingInvoice['id'],
                'reference' => $existingInvoice['reference']
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | FACTURA NÃO EXISTE MAIS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE proformas
            SET converted_invoice_id = NULL
            WHERE id = ?
        ");

        $stmt->execute([
            $proformaId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GERAR REFERÊNCIA
    |--------------------------------------------------------------------------
    */

    $reference = generate_document_number(
        $pdo,
        $companyId,
        'FT'
    );

    /*
    |--------------------------------------------------------------------------
    | CRIAR FACTURA RECIBO
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO invoices (
            contact_id,
            company_id,
            status,
            issue_date,
            due_date,
            reference,
            observation,
            series,
            currency,
            manual_exchange_rate,
            total_sum,
            total_discount,
            subtotal_without_tax,
            total_tax,
            final_total,
            converted_total,
            user_id,
            document_type,
            paid_total
        )
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $proforma['contact_id'],
        $companyId,
        4,
        date('Y-m-d'),
        $proforma['due_date'],
        $reference,
        $proforma['observation'],
        $proforma['series'],
        $proforma['currency'],
        $proforma['manual_exchange_rate'],
        $proforma['total_sum'],
        $proforma['total_discount'],
        $proforma['subtotal_without_tax'],
        $proforma['total_tax'],
        $proforma['final_total'],
        $proforma['final_total'],
        $userId,
        'FT',
        $proforma['final_total']
    ]);

    $invoiceId = (int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | CLONAR ITENS
    |--------------------------------------------------------------------------
    */

    $stmtItems = $pdo->prepare("
        SELECT *
        FROM proforma_items
        WHERE proforma_id = ?
    ");

    $stmtItems->execute([$proformaId]);

    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($items)) {

        $insertItem = $pdo->prepare("
            INSERT INTO invoice_items (
                invoice_id,
                item_id,
                quantity,
                unit_price,
                tax,
                discount
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {

            $insertItem->execute([
                $invoiceId,
                $item['item_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['tax'],
                $item['discount']
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR PROFORMA
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE proformas
        SET
            converted_invoice_id = ?,
            status = 2
        WHERE id = ?
    ");

    $stmt->execute([
        $invoiceId,
        $proformaId
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'already_converted' => false,
        'new_invoice_id' => $invoiceId,
        'reference' => $reference
    ]);
} catch (Throwable $e) {

    if (
        isset($pdo) &&
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
