<?php

/**
 * Fase 2, item 2 — Mapa de Remunerações INSS.
 * Por mês de referência, lista nome, nº de contribuinte/BI, salário base,
 * INSS trabalhador (3%), INSS patronal (8%) e total, para submissão à
 * Segurança Social.
 *
 * Uso: rh/export/mapa_inss.php?mes=2026-09&format=pdf   (ou format=xlsx)
 */

session_start();

require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Dompdf\Dompdf;
use Dompdf\Options;

$company_id = $_SESSION['user']['company_id'] ?? null;
$mes = $_GET['mes'] ?? date('Y-m');
$format = $_GET['format'] ?? 'pdf';

if (!$company_id) {
    http_response_code(401);
    die('Sessão expirada ou inválida. Por favor, faça login novamente.');
}

$stmtCompany = $pdo->prepare('SELECT name FROM companies WHERE id = ?');
$stmtCompany->execute([$company_id]);
$companyName = $stmtCompany->fetchColumn() ?: 'EMPRESA';

$stmt = $pdo->prepare("
    SELECT e.name AS employee_name, e.bi, p.base_salary, p.inss_value, p.inss_employer_value
    FROM payroll p
    JOIN employees e ON e.id = p.employee_id
    WHERE p.company_id = ? AND p.reference_month = ?
    ORDER BY e.name ASC
");
$stmt->execute([$company_id, $mes]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalBase = 0;
$totalTrabalhador = 0;
$totalPatronal = 0;
foreach ($rows as $r) {
    $totalBase += (float)$r['base_salary'];
    $totalTrabalhador += (float)$r['inss_value'];
    $totalPatronal += (float)$r['inss_employer_value'];
}
$totalGeral = $totalTrabalhador + $totalPatronal;

$fmt = fn($v) => number_format((float)$v, 2, ',', '.');

if ($format === 'xlsx') {

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Mapa INSS');

    $primaryColor = '2563EB';
    $borderColor = 'D1D5DB';

    $sheet->mergeCells('A1:F1');
    $sheet->setCellValue('A1', strtoupper($companyName));
    $sheet->mergeCells('A2:F2');
    $sheet->setCellValue('A2', 'MAPA DE REMUNERAÇÕES — INSS — Referência: ' . $mes);

    $sheet->getStyle('A1:F2')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryColor]],
    ]);

    $headers = ['Funcionário', 'Nº Contribuinte / BI', 'Salário Base (Kz)', 'INSS Trabalhador 3% (Kz)', 'INSS Patronal 8% (Kz)', 'Total INSS (Kz)'];
    $sheet->fromArray($headers, null, 'A4');
    $sheet->getStyle('A4:F4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryColor]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
    ]);

    $row = 5;
    foreach ($rows as $r) {
        $total = (float)$r['inss_value'] + (float)$r['inss_employer_value'];
        $sheet->fromArray([
            $r['employee_name'],
            $r['bi'] ?: '—',
            (float)$r['base_salary'],
            (float)$r['inss_value'],
            (float)$r['inss_employer_value'],
            $total,
        ], null, 'A' . $row);
        $row++;
    }

    $sheet->fromArray(['TOTAL', '', $totalBase, $totalTrabalhador, $totalPatronal, $totalGeral], null, 'A' . $row);
    $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);

    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'Mapa_INSS_' . str_replace('-', '', $mes) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ---- PDF (padrão) ----

$linesHtml = '';
foreach ($rows as $r) {
    $total = (float)$r['inss_value'] + (float)$r['inss_employer_value'];
    $linesHtml .= "<tr>
        <td>{$r['employee_name']}</td>
        <td>" . htmlspecialchars($r['bi'] ?: '—') . "</td>
        <td style='text-align:right'>Kz " . $fmt($r['base_salary']) . "</td>
        <td style='text-align:right'>Kz " . $fmt($r['inss_value']) . "</td>
        <td style='text-align:right'>Kz " . $fmt($r['inss_employer_value']) . "</td>
        <td style='text-align:right'>Kz " . $fmt($total) . "</td>
    </tr>";
}

$html = "
<html>
<head>
<meta charset='UTF-8'>
<style>
    body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
    h2, h4 { margin: 2px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
    th { background: #2563EB; color: #fff; text-align: left; }
    tfoot td { font-weight: bold; background: #f1f5f9; }
</style>
</head>
<body>
    <h2>" . htmlspecialchars(strtoupper($companyName)) . "</h2>
    <h4>Mapa de Remunerações — INSS — Referência: {$mes}</h4>
    <table>
        <thead>
            <tr>
                <th>Funcionário</th>
                <th>Nº Contribuinte / BI</th>
                <th>Salário Base</th>
                <th>INSS Trabalhador (3%)</th>
                <th>INSS Patronal (8%)</th>
                <th>Total INSS</th>
            </tr>
        </thead>
        <tbody>
            {$linesHtml}
        </tbody>
        <tfoot>
            <tr>
                <td colspan='2'>TOTAL</td>
                <td style='text-align:right'>Kz " . $fmt($totalBase) . "</td>
                <td style='text-align:right'>Kz " . $fmt($totalTrabalhador) . "</td>
                <td style='text-align:right'>Kz " . $fmt($totalPatronal) . "</td>
                <td style='text-align:right'>Kz " . $fmt($totalGeral) . "</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
";

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Mapa_INSS_' . str_replace('-', '', $mes) . '.pdf', ['Attachment' => false]);
