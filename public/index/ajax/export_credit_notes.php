<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once __DIR__ . '/export_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$formato = $_GET['formato'] ?? 'excel';
$companyId = export_current_company_id();

$sql = "
    SELECT
        cn.id,
        cn.issue_date,
        cn.reason,
        cn.currency,
        cn.total_sum,
        cn.total_discount,
        cn.subtotal_without_tax,
        cn.total_tax,
        cn.retention_value,
        cn.final_total,
        cn.invoice_id,
        i.series      AS invoice_series,
        i.reference   AS invoice_reference,
        c.name        AS client_name
    FROM credit_notes cn
    LEFT JOIN invoices i ON i.id = cn.invoice_id
    LEFT JOIN contact  c ON c.id = cn.contact_id
    WHERE 1 = 1
";

$params = [];

if ($companyId !== null) {
    $sql .= " AND cn.company_id = :company_id";
    $params[':company_id'] = $companyId;
}

if (!empty($_GET['start'])) {
    $sql .= " AND cn.issue_date >= :start_date";
    $params[':start_date'] = $_GET['start'];
}

if (!empty($_GET['end'])) {
    $sql .= " AND cn.issue_date <= :end_date";
    $params[':end_date'] = $_GET['end'];
}

$sql .= " ORDER BY cn.issue_date DESC, cn.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notas de Crédito');

$sheet->fromArray([
    ['Nota Nº', 'Fatura', 'Cliente', 'Data Emissão', 'Moeda', 'Total Bruto', 'Desconto', 'Imposto', 'Retenção', 'Total Final', 'Motivo'],
], NULL, 'A1');

$row = 2;
foreach ($rows as $r) {
    $faturaLabel = $r['invoice_series']
        ? "{$r['invoice_series']} ({$r['invoice_reference']})"
        : ($r['invoice_id'] ?? '-');

    $sheet->fromArray([
        $r['id'],
        $faturaLabel,
        $r['client_name'] ?? '-',
        $r['issue_date'],
        $r['currency'],
        (float) $r['total_sum'],
        (float) $r['total_discount'],
        (float) $r['total_tax'],
        (float) $r['retention_value'],
        (float) $r['final_total'],
        $r['reason'] ?? '-',
    ], NULL, "A$row");
    $row++;
}

export_spreadsheet($spreadsheet, $formato, 'notas_credito');
