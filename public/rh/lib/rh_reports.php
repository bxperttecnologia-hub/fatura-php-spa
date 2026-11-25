<?php
/**
 * Mapas mensais do RH a partir das folhas gravadas (sem recalcular):
 *   folha — mapa salarial completo
 *   inss  — mapa de remunerações para a Segurança Social (3 % + 8 %)
 *   irt   — mapa de retenções de IRT (Grupo A)
 *   banco — ordem de transferência dos salários (IBAN / valor líquido)
 * Cada mapa sai em xlsx, pdf ou csv.
 */
require_once __DIR__ . '/rh_pdf.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function rh_report_types(): array
{
    return [
        'folha' => 'Mapa salarial',
        'inss'  => 'Mapa de remunerações INSS',
        'irt'   => 'Mapa de retenções de IRT',
        'banco' => 'Ordem de transferência de salários',
    ];
}

/**
 * Devolve ['title', 'columns' => [[label, type]], 'rows' => [[…]], 'totals' => [col => value], 'note'].
 * type: text | money | number. $statuses limita os estados (banco: só Aprovado/Pago).
 */
function rh_report_data(PDO $pdo, int $companyId, string $type, string $month): array
{
    $statusFilter = $type === 'banco' ? " AND p.status IN ('Aprovado','Pago')" : '';
    $stmt = $pdo->prepare("
        SELECT p.*, e.name AS employee_name, e.bi, e.nif AS employee_nif, e.inss_number, e.iban, e.bank_name,
               COALESCE(pos.name, e.position) AS position_name
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id
        LEFT JOIN positions pos ON pos.id = e.position_id
        WHERE p.company_id = ? AND p.reference_month = ? $statusFilter
        ORDER BY e.name
    ");
    $stmt->execute([$companyId, $month]);
    $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    switch ($type) {
        case 'inss':
            $columns = [['Funcionário', 'text'], ['N.º INSS', 'text'], ['BI', 'text'], ['Base de incidência', 'money'], ['Trabalhador (3%)', 'money'], ['Entidade patronal (8%)', 'money'], ['Total a entregar', 'money']];
            foreach ($payrolls as $p) {
                $base = (float)($p['inss_base'] ?? 0) ?: ((float)$p['inss_value'] > 0 ? (float)$p['inss_value'] / 0.03 : 0);
                $rows[] = [$p['employee_name'], $p['inss_number'] ?: '—', $p['bi'] ?: '—', $base, (float)$p['inss_value'], (float)($p['inss_employer_value'] ?? 0), (float)$p['inss_value'] + (float)($p['inss_employer_value'] ?? 0)];
            }
            $note = 'Valores retidos nas folhas do mês. Entregar à Segurança Social até ao prazo legal.';
            break;
        case 'irt':
            $columns = [['Funcionário', 'text'], ['NIF', 'text'], ['Rendimento bruto', 'money'], ['Abonos isentos', 'money'], ['INSS (3%)', 'money'], ['Matéria colectável', 'money'], ['IRT retido', 'money']];
            foreach ($payrolls as $p) {
                [, , $gross] = rh_payslip_lines($p + ['advance_lines' => []]);
                $rows[] = [$p['employee_name'], $p['employee_nif'] ?: ($p['bi'] ?: '—'), $gross - (float)($p['absence_value'] ?? 0), (float)($p['exempt_allowances'] ?? 0), (float)$p['inss_value'], (float)($p['irt_base'] ?? 0), (float)$p['irt_value']];
            }
            $note = 'IRT Grupo A retido na fonte. Quando o NIF do funcionário não está registado, é mostrado o BI.';
            break;
        case 'banco':
            $columns = [['Beneficiário', 'text'], ['Banco', 'text'], ['IBAN', 'text'], ['Valor líquido', 'money'], ['Descritivo', 'text']];
            foreach ($payrolls as $p) {
                $rows[] = [$p['employee_name'], $p['bank_name'] ?: '—', preg_replace('/\s+/', '', (string)$p['iban']) ?: 'SEM IBAN', (float)$p['net_salary'], 'Salário ' . rh_month_label($month)];
            }
            $note = 'Inclui apenas folhas aprovadas ou pagas. Verifique os IBAN antes de submeter ao banco.';
            break;
        default:
            $type = 'folha';
            $columns = [['Funcionário', 'text'], ['Função', 'text'], ['Base', 'money'], ['Subsídios', 'money'], ['Horas extra', 'money'], ['Outros abonos', 'money'], ['Bruto', 'money'], ['Faltas', 'money'], ['INSS 3%', 'money'], ['IRT', 'money'], ['Outros desc.', 'money'], ['Líquido', 'money'], ['INSS 8%', 'money'], ['IBAN', 'text'], ['Estado', 'text']];
            foreach ($payrolls as $p) {
                [, , $gross, $totalDed] = rh_payslip_lines($p + ['advance_lines' => []]);
                $vac = (float)($p['vacation_subsidy'] ?: $p['base_salary'] * $p['vacation_subsidy_pct'] / 100);
                $nat = (float)($p['thirteenth_subsidy'] ?: $p['base_salary'] * $p['thirteenth_subsidy_pct'] / 100);
                $subs = (float)$p['food_allowance'] + (float)$p['transport_allowance'] + $vac + $nat;
                $ot = (float)($p['overtime_value'] ?? 0);
                $abs = (float)($p['absence_value'] ?? 0);
                $rows[] = [$p['employee_name'], $p['position_name'] ?: '', (float)$p['base_salary'], $subs, $ot, $gross - $p['base_salary'] - $subs - $ot, $gross, $abs,
                           (float)$p['inss_value'], (float)$p['irt_value'], $totalDed - $abs - $p['inss_value'] - $p['irt_value'], (float)$p['net_salary'], (float)($p['inss_employer_value'] ?? 0), $p['iban'] ?: '', $p['status']];
            }
            $note = '';
    }

    $totals = [];
    foreach ($columns as $i => [, $kind]) {
        if ($kind === 'money') {
            $totals[$i] = rh_money(array_sum(array_column($rows, $i)));
        }
    }
    return ['type' => $type, 'title' => rh_report_types()[$type], 'month' => $month, 'columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'note' => $note];
}

function rh_report_output(PDO $pdo, int $companyId, array $report, string $format): void
{
    $company = rh_company($pdo, $companyId);
    $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $report['title']))) . '-' . $report['month'];

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $base . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($report['columns'], 0), ';');
        foreach ($report['rows'] as $row) {
            foreach ($report['columns'] as $i => [, $kind]) {
                if ($kind === 'money') {
                    $row[$i] = number_format((float)$row[$i], 2, ',', '');
                }
            }
            fputcsv($out, $row, ';');
        }
        fclose($out);
        exit;
    }

    if ($format === 'pdf') {
        $head = '';
        foreach ($report['columns'] as [$label, $kind]) {
            $head .= '<th' . ($kind === 'money' ? " class='num' style='text-align:right'" : '') . '>' . rh_e($label) . '</th>';
        }
        $body = '';
        foreach ($report['rows'] as $n => $row) {
            $body .= "<tr class='" . ($n % 2 ? 'alt' : '') . "'>";
            foreach ($report['columns'] as $i => [, $kind]) {
                $body .= $kind === 'money' ? "<td class='num'>" . number_format((float)$row[$i], 2, ',', '.') . '</td>' : '<td>' . rh_e($row[$i]) . '</td>';
            }
            $body .= '</tr>';
        }
        $foot = "<tr class='total'>";
        foreach ($report['columns'] as $i => [, $kind]) {
            $foot .= $i === 0 ? '<td>Total (' . count($report['rows']) . ')</td>' : ($kind === 'money' ? "<td class='num'>" . number_format($report['totals'][$i], 2, ',', '.') . '</td>' : '<td></td>');
        }
        $foot .= '</tr>';
        $wide = count($report['columns']) > 8;
        $html = rh_pdf_document(
            rh_pdf_header($company, mb_strtoupper($report['title']), rh_month_label($report['month']))
            . "<table class='lines'" . ($wide ? " style='font-size:8px'" : '') . "><thead><tr>$head</tr></thead><tbody>"
            . ($body ?: "<tr><td colspan='" . count($report['columns']) . "' class='muted'>Sem registos neste mês.</td></tr>") . $foot . '</tbody></table>'
            . ($report['note'] ? "<p class='small muted'>" . rh_e($report['note']) . '</p>' : ''),
            ($company['name'] ?? '') . ' · ' . $report['title'] . ' · ' . date('d/m/Y H:i')
        );
        rh_pdf_render($html, $base . '.pdf', 'inline', $wide ? 'landscape' : 'portrait');
    }

    // xlsx
    $sheet = (new Spreadsheet())->getActiveSheet();
    $sheet->setTitle(mb_substr($report['title'], 0, 30));
    $last = Coordinate::stringFromColumnIndex(count($report['columns']));
    $sheet->setCellValue('A1', mb_strtoupper($company['name'] ?? ''));
    $sheet->setCellValue('A2', $report['title'] . ' — ' . rh_month_label($report['month']));
    $sheet->setCellValue('A3', 'NIF: ' . ($company['registration_number'] ?? '—') . ' · Gerado em ' . date('d/m/Y H:i'));
    foreach ([1, 2, 3] as $r) {
        $sheet->mergeCells("A$r:$last$r");
    }
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

    $r = 5;
    foreach ($report['columns'] as $i => [$label]) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r, $label);
    }
    $sheet->getStyle("A$r:$last$r")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    ]);
    foreach ($report['rows'] as $row) {
        $r++;
        foreach ($report['columns'] as $i => [, $kind]) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . $r;
            if ($kind === 'text') {
                $sheet->setCellValueExplicit($cell, (string)$row[$i], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue($cell, (float)$row[$i]);
            }
        }
    }
    $r++;
    $sheet->setCellValue("A$r", 'TOTAL (' . count($report['rows']) . ')');
    foreach ($report['totals'] as $i => $value) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r, $value);
    }
    $sheet->getStyle("A$r:$last$r")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
    ]);
    $sheet->getStyle("A5:$last$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
    foreach ($report['columns'] as $i => [, $kind]) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        if ($kind === 'money') {
            $sheet->getStyle("{$col}6:$col$r")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    if ($report['note']) {
        $sheet->setCellValue('A' . ($r + 2), $report['note']);
    }
    $sheet->freezePane('A6');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
    (new Xlsx($sheet->getParent()))->save('php://output');
    exit;
}
