<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once __DIR__ . '/export_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;

$formato = $_GET['formato'] ?? 'excel';
$companyId = export_current_company_id();

// Não existe tabela "debit_notes" na BD — notas de débito são
// registos da própria tabela invoices com document_type = 'ND'.
$sql = "
    SELECT
        i.id,
        i.series,
        i.reference,
        i.issue_date,
        i.currency,
        i.total_sum,
        i.total_discount,
        i.subtotal_without_tax,
        i.total_tax,
        i.retention_value,
        i.final_total,
        i.observation,
        c.name AS client_name
    FROM invoices i
    LEFT JOIN contact c ON c.id = i.contact_id
    WHERE i.document_type = 'ND'
";

$params = [];

if ($companyId !== null) {
    $sql .= " AND i.company_id = :company_id";
    $params[':company_id'] = $companyId;
}

if (!empty($_GET['start'])) {
    $sql .= " AND i.issue_date >= :start_date";
    $params[':start_date'] = $_GET['start'];
}

if (!empty($_GET['end'])) {
    $sql .= " AND i.issue_date <= :end_date";
    $params[':end_date'] = $_GET['end'];
}

$sql .= " ORDER BY i.issue_date DESC, i.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Notas de Débito');

$sheet->fromArray([
    ['Nº', 'Cliente', 'Data Emissão', 'Moeda', 'Total Bruto', 'Desconto', 'Imposto', 'Retenção', 'Total Final', 'Observação'],
], NULL, 'A1');

$row = 2;
foreach ($rows as $r) {
    $numero = $r['series'] ? "{$r['series']} ({$r['reference']})" : "#{$r['id']}";

    $sheet->fromArray([
        $numero,
        $r['client_name'] ?? '-',
        $r['issue_date'],
        $r['currency'],
        (float) $r['total_sum'],
        (float) $r['total_discount'],
        (float) $r['total_tax'],
        (float) $r['retention_value'],
        (float) $r['final_total'],
        $r['observation'] ?? '-',
    ], NULL, "A$row");
    $row++;
}

export_spreadsheet($spreadsheet, $formato, 'notas_debito');
