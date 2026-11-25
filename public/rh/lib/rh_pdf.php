<?php
/**
 * PDFs do módulo de RH (Dompdf): recibo de vencimento, folha do mês, documentos do funcionário.
 * O HTML do recibo é partilhado pelo recibo no RH, pelo envio por e-mail e pelo portal do colaborador.
 */
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers/company_logo.php';
require_once __DIR__ . '/payroll_engine.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function rh_e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rh_kz($value): string
{
    return number_format((float)$value, 2, ',', '.') . ' Kz';
}

function rh_date_pt(?string $date): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    return date('d/m/Y', strtotime($date));
}

function rh_company(PDO $pdo, int $companyId): array
{
    $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => 'Empresa'];
}

function rh_pdf_css(): string
{
    return <<<CSS
@page { margin: 26px 30px 40px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; }
h1 { font-size: 15px; margin: 0; color: #0f172a; letter-spacing: .3px; }
.muted { color: #64748b; }
.head { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
.head td { vertical-align: top; }
.logo { max-height: 54px; max-width: 170px; }
.company { font-size: 8.5px; line-height: 1.45; }
.company strong { font-size: 10.5px; color: #0f172a; }
.doc-title { text-align: right; }
.doc-title .ref { display: inline-block; margin-top: 5px; padding: 3px 9px; border-radius: 10px; background: #e0ecff; color: #1d4ed8; font-weight: bold; font-size: 9px; }
.box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 11px; margin-bottom: 12px; }
.grid { width: 100%; border-collapse: collapse; }
.grid td { padding: 2.5px 4px; vertical-align: top; }
.grid .k { color: #64748b; width: 16%; font-size: 8.5px; }
.grid .v { font-weight: bold; width: 34%; }
table.lines { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
table.lines th { background: #1e293b; color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; padding: 6px 7px; text-align: left; }
table.lines td { padding: 5px 7px; border-bottom: 1px solid #edf1f6; }
table.lines tr.alt td { background: #f8fafc; }
table.lines .num { text-align: right; white-space: nowrap; }
table.lines tr.total td { font-weight: bold; background: #eef2f7; border-top: 1.5px solid #cbd5e1; }
.net { width: 100%; border-collapse: collapse; margin: 4px 0 12px; }
.net td { padding: 9px 12px; background: #0f766e; color: #fff; font-size: 12px; font-weight: bold; }
.net td.num { text-align: right; font-size: 14px; }
.small { font-size: 8px; }
.sign { width: 100%; margin-top: 34px; border-collapse: collapse; }
.sign td { width: 50%; text-align: center; padding: 0 22px; font-size: 8.5px; color: #475569; }
.sign .line { border-top: 1px solid #334155; padding-top: 4px; }
.foot { position: fixed; bottom: -24px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #94a3b8; }
.badge { padding: 2px 7px; border-radius: 8px; font-size: 8px; font-weight: bold; }
.b-ok { background: #dcfce7; color: #166534; } .b-wait { background: #fef3c7; color: #92400e; } .b-info { background: #dbeafe; color: #1e40af; }
.doc p { font-size: 10.5px; line-height: 1.75; text-align: justify; margin: 0 0 10px; }
.doc h2 { font-size: 11px; margin: 14px 0 6px; color: #0f172a; }
CSS;
}

function rh_pdf_header(array $company, string $title, string $subtitle = ''): string
{
    $logo = '';
    try {
        $src = company_logo_pdf_src($company['logo_url'] ?? '');
        if ($src) {
            $logo = "<img class='logo' src='" . rh_e($src) . "'><br>";
        }
    } catch (Throwable $e) {
        $logo = '';
    }
    $address = implode(', ', array_filter([$company['address'] ?? '', $company['city'] ?? '', $company['country'] ?? '']));
    $contacts = implode(' · ', array_filter([
        !empty($company['phone']) ? 'Tel. ' . $company['phone'] : '',
        $company['email'] ?? '',
    ]));
    return "<table class='head'><tr>
        <td class='company'>{$logo}<strong>" . rh_e($company['name'] ?? '') . "</strong><br>"
        . 'NIF: ' . rh_e($company['registration_number'] ?? '—')
        . ($address ? '<br>' . rh_e($address) : '')
        . ($contacts ? '<br>' . rh_e($contacts) : '') . "</td>
        <td class='doc-title'><h1>" . rh_e($title) . "</h1>"
        . ($subtitle ? "<span class='ref'>" . rh_e($subtitle) . '</span>' : '') . "</td>
    </tr></table>";
}

function rh_pdf_document(string $body, string $footer = ''): string
{
    return "<!DOCTYPE html><html lang='pt'><head><meta charset='UTF-8'><style>" . rh_pdf_css() . "</style></head><body>"
        . ($footer ? "<div class='foot'>" . rh_e($footer) . '</div>' : '')
        . $body . '</body></html>';
}

/** Renderiza HTML em PDF. $output: 'inline' (envia ao browser), 'download' ou 'string' (devolve os bytes). */
function rh_pdf_render(string $html, string $filename, string $output = 'inline', string $orientation = 'portrait')
{
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', $orientation);
    $dompdf->render();
    if ($output === 'string') {
        return $dompdf->output();
    }
    $dompdf->stream($filename, ['Attachment' => $output === 'download']);
    exit;
}

/** Folha + funcionário + empresa. */
function rh_payslip_data(PDO $pdo, int $companyId, int $payrollId): ?array
{
    $stmt = $pdo->prepare("
        SELECT p.*, e.name AS employee_name, e.bi, e.nif AS employee_nif, e.inss_number, e.iban, e.bank_name,
               e.admission_date, e.contract_type, e.email AS employee_email,
               COALESCE(pos.name, e.position) AS position_name, d.name AS department_name
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id AND e.company_id = p.company_id
        LEFT JOIN positions pos ON pos.id = e.position_id
        LEFT JOIN departments d ON d.id = e.department_id
        WHERE p.id = ? AND p.company_id = ?
    ");
    $stmt->execute([$payrollId, $companyId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT ap.amount, a.type, a.description FROM rh_advance_payments ap JOIN rh_advances a ON a.id = ap.advance_id WHERE ap.payroll_id = ?");
    try {
        $stmt->execute([$payrollId]);
        $row['advance_lines'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $row['advance_lines'] = [];
    }
    return $row;
}

/** Linhas de proventos e descontos de uma folha (funciona com folhas antigas, calc_version 1). */
function rh_payslip_lines(array $p): array
{
    $base = (float)$p['base_salary'];
    $vac = (float)($p['vacation_subsidy'] ?? 0) ?: $base * (float)$p['vacation_subsidy_pct'] / 100;
    $nat = (float)($p['thirteenth_subsidy'] ?? 0) ?: $base * (float)$p['thirteenth_subsidy_pct'] / 100;

    $earnings = [
        ['001', 'Vencimento base', $base],
        ['002', 'Subsídio de alimentação', (float)$p['food_allowance']],
        ['003', 'Subsídio de transporte', (float)$p['transport_allowance']],
        ['004', 'Subsídio de férias' . ((float)$p['vacation_subsidy_pct'] ? ' (' . rtrim(rtrim(number_format((float)$p['vacation_subsidy_pct'], 2, ',', ''), '0'), ',') . '%)' : ''), $vac],
        ['005', 'Subsídio de Natal' . ((float)$p['thirteenth_subsidy_pct'] ? ' (' . rtrim(rtrim(number_format((float)$p['thirteenth_subsidy_pct'], 2, ',', ''), '0'), ',') . '%)' : ''), $nat],
        ['006', 'Horas extra' . ((float)($p['overtime_hours'] ?? 0) ? ' (' . number_format((float)$p['overtime_hours'], 2, ',', '') . ' h)' : ''), (float)($p['overtime_value'] ?? 0)],
        ['007', 'Prémios / bónus', (float)$p['bonuses']],
        ['008', 'Comissões', (float)$p['commissions']],
        ['009', 'Outros abonos', (float)($p['other_earnings'] ?? 0)],
    ];
    $earnings = array_values(array_filter($earnings, static fn($l) => round($l[2], 2) != 0));
    $grossFromLines = array_sum(array_column($earnings, 2));
    $gross = (float)($p['gross_salary'] ?? 0) ?: $grossFromLines;

    $deductions = [];
    if ((float)($p['absence_value'] ?? 0) > 0) {
        $deductions[] = ['101', 'Faltas injustificadas (' . rtrim(rtrim(number_format((float)$p['absence_days'], 1, ',', ''), '0'), ',') . ' dia(s))', (float)$p['absence_value']];
    }
    $deductions[] = ['102', 'Segurança Social (INSS)', (float)$p['inss_value']];
    $deductions[] = ['103', 'IRT — Imposto sobre o Rendimento do Trabalho', (float)$p['irt_value']];
    foreach ($p['advance_lines'] ?? [] as $a) {
        $deductions[] = ['104', ($a['type'] === 'emprestimo' ? 'Empréstimo' : 'Adiantamento') . ($a['description'] ? ' — ' . $a['description'] : ''), (float)$a['amount']];
    }
    if ((float)$p['discounts'] > 0) {
        $deductions[] = ['105', 'Outros descontos', (float)$p['discounts']];
    }
    $total = (float)($p['total_discounts'] ?? 0);
    $known = array_sum(array_column($deductions, 2));
    if ((int)($p['calc_version'] ?? 1) < 2 && $total - $known > 0.009) {
        // folhas antigas: faltas e descontos manuais estavam somados num só valor
        $deductions[] = ['106', 'Faltas e outros descontos', $total - $known];
        $known = $total;
    }
    $deductions = array_values(array_filter($deductions, static fn($l) => round($l[2], 2) != 0 || $l[0] === '102' || $l[0] === '103'));

    return [$earnings, $deductions, $gross, $total ?: $known];
}

function rh_payslip_html(PDO $pdo, int $companyId, int $payrollId): ?string
{
    $p = rh_payslip_data($pdo, $companyId, $payrollId);
    if (!$p) {
        return null;
    }
    $company = rh_company($pdo, $companyId);
    [$earnings, $deductions, $gross, $totalDed] = rh_payslip_lines($p);

    $statusBadge = ['Pago' => "<span class='badge b-ok'>PAGO" . ($p['payment_date'] ? ' em ' . rh_date_pt($p['payment_date']) : '') . '</span>',
                    'Aprovado' => "<span class='badge b-info'>APROVADO</span>",
                    'Pendente' => "<span class='badge b-wait'>PROVISÓRIO</span>"][$p['status']] ?? '';

    $rows = '';
    $n = max(count($earnings), count($deductions));
    $i = 0;
    foreach ($earnings as $l) {
        $rows .= "<tr class='" . ($i++ % 2 ? 'alt' : '') . "'><td>{$l[0]}</td><td>" . rh_e($l[1]) . "</td><td class='num'>" . rh_kz($l[2]) . "</td><td class='num'></td></tr>";
    }
    foreach ($deductions as $l) {
        $rows .= "<tr class='" . ($i++ % 2 ? 'alt' : '') . "'><td>{$l[0]}</td><td>" . rh_e($l[1]) . "</td><td class='num'></td><td class='num'>" . rh_kz($l[2]) . "</td></tr>";
    }
    $rows .= "<tr class='total'><td></td><td>Totais</td><td class='num'>" . rh_kz($gross) . "</td><td class='num'>" . rh_kz($totalDed) . '</td></tr>';

    $employee = "<div class='box'><table class='grid'>
        <tr><td class='k'>Nome</td><td class='v'>" . rh_e($p['employee_name']) . "</td><td class='k'>N.º funcionário</td><td class='v'>" . str_pad((string)$p['employee_id'], 5, '0', STR_PAD_LEFT) . "</td></tr>
        <tr><td class='k'>Função</td><td class='v'>" . rh_e($p['position_name'] ?: '—') . "</td><td class='k'>Departamento</td><td class='v'>" . rh_e($p['department_name'] ?: '—') . "</td></tr>
        <tr><td class='k'>BI</td><td class='v'>" . rh_e($p['bi'] ?: '—') . "</td><td class='k'>N.º INSS</td><td class='v'>" . rh_e($p['inss_number'] ?: '—') . "</td></tr>
        <tr><td class='k'>Admissão</td><td class='v'>" . rh_date_pt($p['admission_date']) . "</td><td class='k'>IBAN</td><td class='v'>" . rh_e($p['iban'] ?: '—') . "</td></tr>
    </table></div>";

    $bases = "<table class='grid small muted'><tr>
        <td>Base de incidência INSS: <strong>" . rh_kz($p['inss_base'] ?? 0) . "</strong></td>
        <td>Rendimento colectável IRT: <strong>" . rh_kz($p['irt_base'] ?? 0) . "</strong></td>
        <td>Abonos isentos: <strong>" . rh_kz($p['exempt_allowances'] ?? 0) . "</strong></td>
        <td>INSS entidade patronal: <strong>" . rh_kz($p['inss_employer_value'] ?? 0) . "</strong></td>
    </tr></table>";

    $body = rh_pdf_header($company, 'RECIBO DE VENCIMENTO', rh_month_label($p['reference_month']))
        . "<div style='text-align:right;margin:-6px 0 8px'>{$statusBadge}</div>"
        . $employee
        . "<table class='lines'><thead><tr><th style='width:9%'>Cód.</th><th>Descrição</th><th class='num' style='width:19%;text-align:right'>Abonos</th><th class='num' style='width:19%;text-align:right'>Descontos</th></tr></thead><tbody>{$rows}</tbody></table>"
        . "<table class='net'><tr><td>Líquido a receber</td><td class='num'>" . rh_kz($p['net_salary']) . '</td></tr></table>'
        . $bases
        . ($p['notes'] ? "<p class='small'><strong>Observações:</strong> " . nl2br(rh_e($p['notes'])) . '</p>' : '')
        . "<table class='sign'><tr><td><div class='line'>A entidade patronal</div></td><td><div class='line'>O trabalhador — declaro ter recebido a quantia acima</div></td></tr></table>";

    return rh_pdf_document($body, 'Processado por computador · ' . ($company['name'] ?? '') . ' · ' . date('d/m/Y H:i'));
}

function rh_payslip_filename(array $p): string
{
    $name = preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$p['employee_name']) ?: 'funcionario');
    return 'recibo-' . trim($name, '-') . '-' . $p['reference_month'] . '.pdf';
}
