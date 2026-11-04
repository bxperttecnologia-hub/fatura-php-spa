<?php

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../../../helpers/document_tax.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function bx_json_fail(int $status, string $message): void
{
    http_response_code($status);
    // 'message' é o campo que o front lê; 'error' mantém compatibilidade com código antigo.
    echo json_encode(['success' => false, 'message' => $message, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user']['company_id'])) {
    bx_json_fail(401, 'Sessão expirada. Inicie sessão novamente.');
}
$companyId = (int)$_SESSION['user']['company_id'];

$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    bx_json_fail(400, 'Documento não informado.');
}

// O TIPO É EXPLÍCITO. Nunca se deduz pelo id: invoices.id e proformas.id são
// sequências independentes e colidem (a fatura nº 5 e a proforma nº 5 coexistem).
$kind = strtolower((string)($_GET['kind'] ?? 'invoice'));
if (!in_array($kind, ['invoice', 'proforma'], true)) {
    bx_json_fail(400, 'Tipo de documento inválido.');
}

try {

    /*
    |--------------------------------------------------------------------------
    | TIPO DE DOCUMENTO (vem do pedido, nunca do id)
    |--------------------------------------------------------------------------
    */

    $isProforma = ($kind === 'proforma');

    /*
    |--------------------------------------------------------------------------
    | CONFIGURAÇÕES
    |--------------------------------------------------------------------------
    */

    $table         = $isProforma ? 'proformas' : 'invoices';
    $itemsTable    = $isProforma ? 'proforma_items' : 'invoice_items';
    $statusTable   = $isProforma ? 'proforma_status' : 'invoice_status';
    $statusAlias   = $isProforma ? 'ps' : 'ivs';
    $documentType  = $isProforma ? "'PF'" : 'd.document_type';
    $retentionValue = $isProforma ? '0' : 'd.retention_value';

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
            c.email AS client_email,
            c.address AS client_address,
            c.contributor AS client_contributor,
            c.country AS client_country,
            c.city AS client_city,

            d.contact_id,
            {$documentType} AS document_type,
            d.observation,
            d.issue_date,
            d.due_date,
            d.series,
            d.total_sum,
            d.final_total,
            d.total_discount,
            d.retention,
            {$retentionValue} AS retention_value,
            d.total_tax,
            d.tax_withholding_type,
            d.tax_withholding_rate,
            d.tax_withholding_base,
            d.tax_withholding_amount,
            d.tax_withholding_description,
            d.tax_withholding_confirmed,
            d.gross_total,
            d.net_payable,
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
          AND d.company_id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$documentId, $companyId]);

    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        bx_json_fail(404, $isProforma ? 'Proforma não encontrada.' : 'Factura não encontrada.');
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
            di.discount,
            di.retention_rate
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

    $taxSummary = bx_document_tax_summary(
        $document['items'],
        (string)($document['vat_regime'] ?? '')
    );
    $retentionValue = (float)($document['retention_value'] ?? 0);
    if ($isProforma) {
        $retentionValue = max(
            0.0,
            ($taxSummary['subtotal'] + $taxSummary['total_tax'])
                * (float)($document['retention'] ?? 0) / 100
        );
    }
    $taxSummary = bx_document_tax_summary(
        $document['items'],
        (string)($document['vat_regime'] ?? ''),
        $retentionValue
    );
    $document['total_sum'] = $taxSummary['total_sum'];
    $document['total_discount'] = $taxSummary['total_discount'];
    $document['subtotal_without_tax'] = $taxSummary['subtotal'];
    $document['total_tax'] = $taxSummary['total_tax'];
    $document['retention_value'] = $taxSummary['retention'];
    $document['final_total'] = $taxSummary['final_total'];
    $document['tax_details'] = array_map(
        static fn(array $row): array => [
            'tax_rate' => $row['rate'],
            'tax_base' => $row['base'],
            'tax_value' => $row['iva'],
            'retention_rate' => (float)($document['retention'] ?? 0),
            'retention_value' => $row['retention'],
            'total_sum' => $taxSummary['total_sum'],
            'symbol' => $document['company_symbol'] ?? '',
            'position' => $document['company_position'] ?? 'right',
        ],
        $taxSummary['rows']
    );

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
    $document['kind']        = $kind;

    echo json_encode([
        'success' => true,
        'data'    => $document
    ]);
} catch (Throwable $e) {

    error_log('[get_invoice] ' . $e->getMessage());
    bx_json_fail(500, 'Não foi possível carregar o documento.');
}