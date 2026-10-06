<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');

$documentId = (int)($_GET['id'] ?? 0);

if (!$documentId) {
    echo json_encode([
        'success' => false,
        'error'   => 'Documento não informado.'
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | DETECTAR TIPO DE DOCUMENTO
    |--------------------------------------------------------------------------
    */

    $isProforma = false;

    $check = $pdo->prepare("
        SELECT id
        FROM proformas
        WHERE id = ?
        LIMIT 1
    ");

    $check->execute([$documentId]);

    if ($check->fetch()) {
        $isProforma = true;
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIGURAÇÕES
    |--------------------------------------------------------------------------
    */

    $table         = $isProforma ? 'proformas' : 'invoices';
    $itemsTable    = $isProforma ? 'proforma_items' : 'invoice_items';
    $statusTable   = $isProforma ? 'proforma_status' : 'invoice_status';
    $statusAlias   = $isProforma ? 'ps' : 'ivs';

    /*
    |--------------------------------------------------------------------------
    | DOCUMENTO
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            d.id,
            CONCAT(YEAR(d.issue_date), '/', d.id) AS codigo,

            comp.name AS company_name,
            comp.address AS company_address,
            comp.city AS company_city,
            comp.country AS company_country,
            comp.registration_number,
            comp.email AS company_email,
            comp.phone AS company_phone,
            comp.logo_url,
            comp.vat_regime,
            comp.goods_services,
            comp.bank_details,

            c.name AS client_name,
            c.address AS client_address,
            c.contributor AS client_contributor,
            c.country AS client_country,
            c.city AS client_city,

            d.contact_id,
            d.document_type,
            d.observation,
            d.issue_date,
            d.due_date,
            d.series,
            d.total_sum,
            d.final_total,
            d.total_discount,
            d.retention,
            d.retention_value,
            d.total_tax,
            d.manual_exchange_rate,
            d.reference,
            d.converted_total,

            {$statusAlias}.name AS status_invoice,
            {$statusAlias}.color,
            {$statusAlias}.text_color,

            cr.symbol,
            cr.position,

            cr2.symbol AS company_symbol,
            cr2.position AS company_position,

            d.currency,
            comp.currency AS currency_company

        FROM {$table} d

        INNER JOIN companies comp
            ON comp.id = d.company_id

        INNER JOIN contact c
            ON c.id = d.contact_id

        INNER JOIN {$statusTable} {$statusAlias}
            ON {$statusAlias}.id = d.status

        LEFT JOIN currencies cr
            ON cr.iso_code = d.currency

        LEFT JOIN currencies cr2
            ON cr2.iso_code = comp.currency

        WHERE d.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documentId]);

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        throw new Exception('Documento não encontrado.');
    }

    /*
    |--------------------------------------------------------------------------
    | TEXTO PADRÃO AGT
    |--------------------------------------------------------------------------
    */

    if (empty(trim($document['goods_services'] ?? ''))) {
        $document['goods_services'] =
            "Os bens e serviços foram colocados à disposição do adquirente na data do documento.";
    }

    /*
    |--------------------------------------------------------------------------
    | ITENS
    |--------------------------------------------------------------------------
    */

    $stmtItems = $pdo->prepare("
        SELECT
            di.item_id,
            it.code,
            it.name,
            it.description,
            di.quantity,
            di.unit_price,
            di.tax,
            di.discount
        FROM {$itemsTable} di
        INNER JOIN items it
            ON it.id = di.item_id
        WHERE di." . ($isProforma ? "proforma_id" : "invoice_id") . " = ?
    ");

    $stmtItems->execute([$documentId]);

    $document['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | IMPOSTOS
    |--------------------------------------------------------------------------
    */

    $stmtTaxes = $pdo->prepare("
        SELECT
            di.tax AS tax_rate,

            ROUND(
                SUM(di.unit_price * di.quantity)
                *
                (1 - (di.discount / 100)),
                2
            ) AS tax_base,

            ROUND(
                SUM(
                    (di.unit_price * di.quantity)
                    *
                    (di.tax / 100)
                ),
                2
            ) AS tax_value,

            d.retention AS retention_rate,
            d.retention_value,
            d.total_sum,

            cr.symbol,
            cr.position

        FROM {$table} d

        INNER JOIN {$itemsTable} di
            ON di." . ($isProforma ? "proforma_id" : "invoice_id") . " = d.id

        INNER JOIN companies comp
            ON comp.id = d.company_id

        LEFT JOIN currencies cr
            ON cr.iso_code = comp.currency

        WHERE d.id = ?

        GROUP BY
            di.tax,
            d.retention,
            d.retention_value
    ");

    $stmtTaxes->execute([$documentId]);

    $document['tax_details'] = $stmtTaxes->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | PAGAMENTOS (SÓ FATURAS)
    |--------------------------------------------------------------------------
    */

    if (!$isProforma) {

        $stmtPaid = $pdo->prepare("
            SELECT
                COALESCE(SUM(amount_paid),0)
            FROM receipts
            WHERE invoice_id = ?
        ");

        $stmtPaid->execute([$documentId]);

        $paid = (float)$stmtPaid->fetchColumn();

        $document['paid_total'] = $paid;

        $document['saldo'] =
            (float)$document['final_total'] - $paid;

        $document['pay_status'] =
            $paid <= 0
            ? 'pendente'
            : (
                $document['saldo'] <= 0.009
                ? 'pago'
                : 'parcial'
            );
    } else {

        $document['paid_total'] = 0;
        $document['saldo']      = $document['final_total'];
        $document['pay_status'] = 'proforma';
    }

    /*
    |--------------------------------------------------------------------------
    | META
    |--------------------------------------------------------------------------
    */

    $document['is_proforma'] = $isProforma;

    echo json_encode([
        'success' => true,
        'data'    => $document
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
