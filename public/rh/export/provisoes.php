<?php

/**
 * Fase 5, item 3 — Provisões contabilísticas.
 *
 * Gera, para um mês de referência, o valor de provisão de férias e de 13º
 * mês por funcionário e o total — para lançar na contabilidade (não
 * confunde com o pagamento em si, feito na folha mensal quando o subsídio
 * é efetivamente pago).
 *
 * Metodologia (mensal, regime de competência):
 *  - Provisão de férias do mês = salário base / 12  (o funcionário "ganha"
 *    1/12 do direito a subsídio de férias a cada mês trabalhado)
 *  - Provisão de 13º do mês   = salário base / 12  (mesma lógica)
 *  - Estes valores ACUMULAM ao longo do ano; quando o subsídio é
 *    efetivamente pago na folha (vacation_subsidy_pct / thirteenth_subsidy_pct
 *    > 0), a provisão acumulada correspondente é "consumida" — este script
 *    NÃO faz esse abate automaticamente (isso é uma decisão contabilística,
 *    não técnica) — confirma com o contabilista como quer reconciliar isto
 *    com o plano de contas.
 *
 * Uso: rh/export/provisoes.php?mes=2026-09&format=pdf   (ou format=xlsx)
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

$stmt = $pdo->prepare("SELECT id, name, salary_base FROM employees WHERE company_id = ? AND status = 'ativo' ORDER BY name ASC");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
$totalFerias = 0;
$totalDecimo = 0;

foreach ($employees as $emp) {
    $base = (float)$emp['salary_base'];
    $provFerias = round($base / 12, 2);
    $provDecimo = round($base / 12, 2);
    $totalFerias += $provFerias;
    $totalDecimo += $provDecimo;

    $rows[] = [
        'name' => $emp['name'],
        'base_salary' => $base,
        'prov_ferias' => $provFerias,
        'prov_decimo' => $provDecimo,
        'prov_total' => $provFerias + $provDecimo,
    ];
}

$totalGeral = $totalFerias + $totalDecimo;
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');

if ($format === 'xlsx') {

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Provisões');

    $primaryColor = '2563EB';
    $borderColor = 'D1D5DB';

    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', strtoupper($companyName));
    $sheet->mergeCells('A2:E2');
    $sheet->setCellValue('A2', 'PROVISÕES DE FÉRIAS E 13º MÊS — Referência: ' . $mes);

    $sheet->getStyle('A1:E2')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryColor]],
    ]);

    $headers = ['Funcionário', 'Salário Base (Kz)', 'Provisão Férias 1/12 (Kz)', 'Provisão 13º 1/12 (Kz)', 'Total Provisão (Kz)'];
    $sheet->fromArray($headers, null, 'A4');
    $sheet->getStyle('A4:E4')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $primaryColor]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]],
    ]);

    $r = 5;
    foreach ($rows as $row) {
        $sheet->fromArray([
            $row['name'],
            $row['base_salary'],
            $row['prov_ferias'],
            $row['prov_decimo'],
            $row['prov_total'],
        ], null, 'A' . $r);
        $r++;
    }

    $sheet->fromArray(['TOTAL', '', $totalFerias, $totalDecimo, $totalGeral], null, 'A' . $r);
    $sheet->getStyle('A' . $r . ':E' . $r)->getFont()->setBold(true);

    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = 'Provisoes_' . str_replace('-', '', $mes) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ---- PDF (padrão) ----

$linesHtml = '';
foreach ($rows as $row) {
    $linesHtml .= "<tr>
        <td>{$row['name']}</td>
        <td style='text-align:right'>Kz " . $fmt($row['base_salary']) . "</td>
        <td style='text-align:right'>Kz " . $fmt($row['prov_ferias']) . "</td>
        <td style='text-align:right'>Kz " . $fmt($row['prov_decimo']) . "</td>
        <td style='text-align:right'>Kz " . $fmt($row['prov_total']) . "</td>
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
    .nota { font-size: 9px; color: #6b7280; margin-top: 10px; }
</style>
</head>
<body>
    <h2>" . htmlspecialchars(strtoupper($companyName)) . "</h2>
    <h4>Provisões de Férias e 13º Mês — Referência: {$mes}</h4>
    <table>
        <thead>
            <tr>
                <th>Funcionário</th>
                <th>Salário Base</th>
                <th>Provisão Férias (1/12)</th>
                <th>Provisão 13º (1/12)</th>
                <th>Total Provisão</th>
            </tr>
        </thead>
        <tbody>
            {$linesHtml}
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL</td>
                <td></td>
                <td style='text-align:right'>Kz " . $fmt($totalFerias) . "</td>
                <td style='text-align:right'>Kz " . $fmt($totalDecimo) . "</td>
                <td style='text-align:right'>Kz " . $fmt($totalGeral) . "</td>
            </tr>
        </tfoot>
    </table>
    <p class='nota'>Provisão mensal = salário base ÷ 12, acumulada ao longo do ano. Não inclui o abate quando o
    subsídio correspondente é efetivamente pago na folha — confirmar com o contabilista o tratamento no plano de contas.</p>
</body>
</html>
";

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('Provisoes_' . str_replace('-', '', $mes) . '.pdf', ['Attachment' => false]);
