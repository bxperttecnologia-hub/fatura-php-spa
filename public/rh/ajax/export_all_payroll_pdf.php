<?php
/** Folha de salários do mês (PDF, paisagem). GET mes=YYYY-MM. Também servido por /api/documents/payroll/export.pdf. */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/rh_pdf.php';

ini_set('display_errors', '0');
$month = (string)($_GET['mes'] ?? $_GET['reference_month'] ?? '');
if (!rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Mês de referência inválido.'], 422);
}
$company = rh_company($pdo, $company_id);
$stmt = $pdo->prepare("
    SELECT p.*, e.name AS employee_name, e.iban, COALESCE(pos.name, e.position) AS position_name
    FROM payroll p JOIN employees e ON e.id = p.employee_id
    LEFT JOIN positions pos ON pos.id = e.position_id
    WHERE p.company_id = ? AND p.reference_month = ? ORDER BY e.name
");
$stmt->execute([$company_id, $month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cols = ['base' => 0, 'subs' => 0, 'other' => 0, 'gross' => 0, 'abs' => 0, 'inss' => 0, 'irt' => 0, 'oth_ded' => 0, 'net' => 0, 'inss8' => 0];
$body = '';
$i = 0;
foreach ($rows as $p) {
    [$earnings, $deductions, $gross, $totalDed] = rh_payslip_lines($p + ['advance_lines' => []]);
    $subs = (float)$p['food_allowance'] + (float)$p['transport_allowance'] + (float)($p['vacation_subsidy'] ?: $p['base_salary'] * $p['vacation_subsidy_pct'] / 100) + (float)($p['thirteenth_subsidy'] ?: $p['base_salary'] * $p['thirteenth_subsidy_pct'] / 100);
    $other = $gross - (float)$p['base_salary'] - $subs;
    $abs = (float)($p['absence_value'] ?? 0);
    $othDed = $totalDed - $abs - (float)$p['inss_value'] - (float)$p['irt_value'];
    $vals = ['base' => (float)$p['base_salary'], 'subs' => $subs, 'other' => $other, 'gross' => $gross, 'abs' => $abs, 'inss' => (float)$p['inss_value'],
             'irt' => (float)$p['irt_value'], 'oth_ded' => $othDed, 'net' => (float)$p['net_salary'], 'inss8' => (float)($p['inss_employer_value'] ?? 0)];
    foreach ($vals as $k => $v) {
        $cols[$k] += $v;
    }
    $body .= "<tr class='" . ($i++ % 2 ? 'alt' : '') . "'><td>" . rh_e($p['employee_name']) . "<br><span class='muted small'>" . rh_e($p['position_name'] ?: '') . "</span></td>";
    foreach ($vals as $v) {
        $body .= "<td class='num'>" . number_format($v, 2, ',', '.') . '</td>';
    }
    $body .= '<td>' . rh_e($p['status']) . '</td></tr>';
}
$foot = "<tr class='total'><td>Total (" . count($rows) . ')</td>';
foreach ($cols as $v) {
    $foot .= "<td class='num'>" . number_format($v, 2, ',', '.') . '</td>';
}
$foot .= '<td></td></tr>';

$html = rh_pdf_document(
    rh_pdf_header($company, 'FOLHA DE SALÁRIOS', rh_month_label($month))
    . "<table class='lines'><thead><tr><th>Funcionário</th><th class='num'>Base</th><th class='num'>Subsídios</th><th class='num'>Outros abonos</th><th class='num'>Bruto</th><th class='num'>Faltas</th><th class='num'>INSS 3%</th><th class='num'>IRT</th><th class='num'>Outros desc.</th><th class='num'>Líquido</th><th class='num'>INSS 8%</th><th>Estado</th></tr></thead><tbody>"
    . ($body ?: "<tr><td colspan='12' class='muted'>Sem folhas neste mês.</td></tr>") . $foot . '</tbody></table>'
    . "<p class='small muted'>Custo total para a empresa (bruto − faltas + INSS patronal): <strong>" . rh_kz($cols['gross'] - $cols['abs'] + $cols['inss8']) . '</strong></p>',
    ($company['name'] ?? '') . ' · Folha de salários ' . rh_month_label($month) . ' · ' . date('d/m/Y H:i')
);
rh_pdf_render($html, "folha-salarios-$month.pdf", 'inline', 'landscape');
