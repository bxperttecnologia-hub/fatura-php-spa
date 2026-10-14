<?php
require_once '../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
header('Content-Type: application/json');

// Captura o ID da fatura
$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$invoiceId) {
    echo json_encode(['error' => 'Fatura não encontrada.']);
    exit;
}

try {
    // Busca dados da fatura
    $sql = "SELECT 
                i.id, 
                concat(YEAR(i.issue_date), '/', i.id) as codigo, 
                comp.name as company_name, 
                comp.address as company_address,
                comp.city as company_city,
                comp.country as company_country,
                comp.registration_number,
                comp.email as company_email,
                comp.phone as company_phone,
                comp.logo_url,
                comp.vat_regime,
                comp.goods_services,
                comp.bank_details,
                c.name as client_name, 
                c.address as client_address,  
                c.contributor as client_contributor,
                i.contact_id,
                c.country as client_country,
                c.city as client_city,
                'PF' as document_type,
                i.observation,
                i.issue_date,
                i.due_date, 
                i.series,
                i.total_sum,
                i.final_total,
                i.total_discount,
                i.retention,
                i.manual_exchange_rate,
                i.total_tax,
                i.reference,
                i.converted_total,
                ivs.name as status_invoice, 
                ivs.color,
                ivs.text_color, 
                cr.symbol, 
                cr.position, 
                cr2.symbol as company_symbol, 
                cr2.position as company_position,
                i.currency as currency,
                i.currency as currency_items,
                comp.currency as currency_company
            FROM proformas i
            JOIN companies comp ON comp.id = i.company_id
            JOIN contact c ON c.id = i.contact_id
            JOIN proforma_status ivs ON ivs.id = i.status
            JOIN currencies cr ON cr.iso_code = 'AOA'
            JOIN currencies cr2 on cr2.iso_code = comp.currency
            WHERE i.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$invoiceId]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        echo json_encode(['error' => 'Fatura não encontrada.']);
        exit;
    }

    // Padrão fixo para "Bens e serviços" quando não estiver preenchido na empresa
    if (array_key_exists('goods_services', $invoice)) {
        $gs = trim((string)($invoice['goods_services'] ?? ''));
        if ($gs === '') {
            $invoice['goods_services'] = "Os bens e serviços foram colocados à disposição do adquirente na data\ndo documento.";
        }
    }

    // Busca itens da fatura
    $sqlItems = "SELECT 
                    ii.item_id,
                    it.code,
                    it.name, 
                    it.description, 
                    ii.quantity, 
                    ii.unit_price, 
                    ii.tax, 
                    ii.discount 
                FROM proforma_items ii
                JOIN items it ON it.id = ii.item_id
                WHERE ii.proforma_id = :invoiceId";

    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->bindParam(':invoiceId', $invoiceId, PDO::PARAM_INT);
    $stmtItems->execute();

    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona os itens no resultado da fatura
    $invoice['items'] = $items;

    $taxSummary = bx_document_tax_summary(
        $items,
        (string)($invoice['vat_regime'] ?? '')
    );
    $retentionValue = max(
        0.0,
        ($taxSummary['subtotal'] + $taxSummary['total_tax'])
            * (float)($invoice['retention'] ?? 0) / 100
    );
    $taxSummary = bx_document_tax_summary(
        $items,
        (string)($invoice['vat_regime'] ?? ''),
        $retentionValue
    );
    $invoice['retention_value'] = $taxSummary['retention'];
    $invoice['total_sum'] = $taxSummary['total_sum'];
    $invoice['total_discount'] = $taxSummary['total_discount'];
    $invoice['subtotal_without_tax'] = $taxSummary['subtotal'];
    $invoice['total_tax'] = $taxSummary['total_tax'];
    $invoice['final_total'] = $taxSummary['final_total'];
    $invoice['tax_details'] = array_map(
        static fn(array $row): array => [
            'tax_rate' => $row['rate'],
            'tax_base' => $row['base'],
            'tax_value' => $row['iva'],
            'retention_rate' => (float)($invoice['retention'] ?? 0),
            'retention_value' => $row['retention'],
            'total_sum' => $taxSummary['total_sum'],
            'symbol' => $invoice['company_symbol'] ?? '',
            'position' => $invoice['company_position'] ?? 'right',
        ],
        $taxSummary['rows']
    );

    $invoice['paid_total'] = 0;
    $invoice['saldo'] = $invoice['final_total'];
    $invoice['pay_status'] = 'proforma';


    echo json_encode($invoice);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar dados da fatura: ' . $e->getMessage()]);
}
