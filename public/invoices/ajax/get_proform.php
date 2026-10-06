<?php
require_once '../../../app/config/db.php';
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
                i.retention_value,
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
    $stmt->bindParam(':invoiceId', $invoiceId, PDO::PARAM_INT);
    $stmt->execute();

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    // Padrão fixo para "Bens e serviços" quando não estiver preenchido na empresa
    if (array_key_exists('goods_services', $invoice)) {
        $gs = trim((string)($invoice['goods_services'] ?? ''));
        if ($gs === '') {
            $invoice['goods_services'] = "Os bens e serviços foram colocados à disposição do adquirente na data\ndo documento.";
        }
    }

    if (!$invoice) {
        echo json_encode(['error' => 'Fatura não encontrada.']);
        exit;
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

    // Busca detalhes das taxas
    $sqlTaxes = "SELECT 
                    ii.tax AS tax_rate,
                   ROUND(SUM(ii.unit_price * ii.quantity) * (1 - (discount / 100)), 2) AS tax_base,
                    SUM(ii.unit_price * ii.quantity * (ii.tax / 100)) AS tax_value,
                    i.retention AS retention_rate,
                    i.retention_value AS retention_value,
                    i.total_sum,
                    cr.symbol, 
                    cr.position 
                FROM proformas i
                JOIN companies comp ON comp.id = i.company_id 
                JOIN invoice_items ii ON i.id = ii.invoice_id
                JOIN currencies cr ON cr.iso_code = comp.currency
                WHERE i.id = :invoiceId
                GROUP BY ii.tax, i.retention, i.retention_value";

    $stmtTaxes = $pdo->prepare($sqlTaxes);
    $stmtTaxes->bindParam(':invoiceId', $invoiceId, PDO::PARAM_INT);
    $stmtTaxes->execute();

    $taxDetails = $stmtTaxes->fetchAll(PDO::FETCH_ASSOC);

    // Adiciona os detalhes das taxas no resultado da fatura
    $invoice['tax_details'] = $taxDetails;

    /* ---------- total já pago ---------- */
    $sqlPaid = "SELECT COALESCE(SUM(amount_paid),0) AS paid_total
            FROM receipts
            WHERE invoice_id = :invoiceId";
    $stmPaid = $pdo->prepare($sqlPaid);
    $stmPaid->execute(['invoiceId' => $invoiceId]);
    $paid    = $stmPaid->fetch(PDO::FETCH_ASSOC)['paid_total'] ?? 0;

    /* anexa ao array da fatura */
    $invoice['paid_total'] = $paid;

    /* opcional: calcula saldo e um rótulo prático */
    $invoice['saldo']      = $invoice['final_total'] - $paid;
    $invoice['pay_status'] = ($paid == 0)
        ? 'pendente'
        : (($invoice['saldo'] <= 0.009) ? 'pago' : 'parcial');


    echo json_encode($invoice);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erro ao buscar dados da fatura: ' . $e->getMessage()]);
}
