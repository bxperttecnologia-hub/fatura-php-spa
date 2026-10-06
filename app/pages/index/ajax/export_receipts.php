<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once __DIR__ . '/export_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$formato = $_GET['formato'] ?? 'excel';
$companyId = export_current_company_id();

// A tabela receipts não tem company_id direto — chega-se lá via invoices.
$sql = "
    SELECT
        r.id,
        r.serie,
        r.number,
        r.reference,
        r.pay_date,
        r.amount_paid,
        r.payment_method,
        r.pending_amount,
        r.notes,
        r.invoice_id,
        i.series      AS invoice_series,
        i.reference   AS invoice_reference,
        i.currency    AS invoice_currency,
        c.name        AS client_name
    FROM receipts r
    LEFT JOIN invoices i ON i.id = r.invoice_id
    LEFT JOIN contact  c ON c.id = i.contact_id
    WHERE 1 = 1
";

$params = [];

if ($companyId !== null) {
    $sql .= " AND i.company_id = :company_id";
    $params[':company_id'] = $companyId;
}

if (!empty($_GET['start'])) {
    $sql .= " AND r.pay_date >= :start_date";
    $params[':start_date'] = $_GET['start'];
}

if (!empty($_GET['end'])) {
    $sql .= " AND r.pay_date <= :end_date";
    $params[':end_date'] = $_GET['end'];
}

$sql .= " ORDER BY r.pay_date DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Recibos');

$sheet->fromArray([
    ['Recibo', 'Fatura', 'Cliente', 'Data Pagamento', 'Método', 'Moeda', 'Valor Pago', 'Pendente', 'Referência', 'Notas'],
], NULL, 'A1');

$row = 2;
foreach ($rows as $r) {
    $reciboLabel = $r['serie'] ? "{$r['serie']} {$r['number']}" : $r['number'];
    $faturaLabel = $r['invoice_series']
        ? "{$r['invoice_series']} ({$r['invoice_reference']})"
        : ($r['invoice_id'] ?? '-');

    $sheet->fromArray([
        $reciboLabel,
        $faturaLabel,
        $r['client_name'] ?? '-',
        $r['pay_date'],
        $r['payment_method'],
        $r['invoice_currency'] ?? '-',
        (float) $r['amount_paid'],
        (float) $r['pending_amount'],
        $r['reference'] ?? '-',
        $r['notes'] ?? '-',
    ], NULL, "A$row");
    $row++;
}

export_spreadsheet($spreadsheet, $formato, 'recibos');
