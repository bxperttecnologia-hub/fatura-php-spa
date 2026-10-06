<?php
require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php';
session_start();

use Dompdf\Dompdf;
use Dompdf\Options;

$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$company_id) {
    die('Empresa não identificada.');
}

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$employee_id = $_GET['employee_id'] ?? null;

if (!$start || !$end) {
    $mes = $_GET['mes'] ?? date('Y-m');
    $start = $mes . '-01';
    $end = date('Y-m-t', strtotime($start));
}

$startDt = date('Y-m-d', strtotime($start));
$endDt   = date('Y-m-d', strtotime($end));

/*
|--------------------------------------------------------------------------
| EMPRESA
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT 
        name,
        logo_url,
        registration_number,
        address,
        city,
        country,
        phone,
        email
    FROM companies
    WHERE id = ?
");

$stmt->execute([$company_id]);

$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| REGISTROS
|--------------------------------------------------------------------------
*/
$params = [$company_id, $endDt, $startDt];

$sql = "
    SELECT 
        v.*,
        e.name AS employee_name,
        e.position
    FROM vacations v
    JOIN employees e 
        ON e.id = v.employee_id
    WHERE v.company_id = ?
    AND (v.start_date <= ? AND v.end_date >= ?)
";

if (!empty($employee_id)) {
    $sql .= " AND v.employee_id = ?";
    $params[] = (int)$employee_id;
}

$sql .= " ORDER BY e.name, v.start_date";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function formatDateAngola($date)
{
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/
$logoHtml = '';

if (!empty($empresa['logo_url'])) {

    $logoPath = trim($empresa['logo_url']);

    $publicRoot = realpath(__DIR__ . '/../../');

    $candidate = ltrim($logoPath, '/');

    if (
        strpos($candidate, '/') === false &&
        strpos($candidate, '\\') === false
    ) {
        $candidate = 'assets/img/companies/' . $candidate;
    }

    $full = realpath($publicRoot . '/' . $candidate);

    if (
        $full &&
        str_starts_with($full, $publicRoot) &&
        is_file($full)
    ) {

        $data = base64_encode(file_get_contents($full));

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));

        $mime =
            $ext === 'jpg' || $ext === 'jpeg'
            ? 'image/jpeg'
            : ($ext === 'webp'
                ? 'image/webp'
                : 'image/png');

        $logoHtml = "
            <img 
                src='data:$mime;base64,$data'
                style='height:70px; object-fit:contain;'
            >
        ";
    }
}

$title = "RELATÓRIO DE FÉRIAS E LICENÇAS";

$periodLabel =
    formatDateAngola($startDt)
    . ' até '
    . formatDateAngola($endDt);

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/
$html = "
<!DOCTYPE html>
<html lang='pt'>
<head>
<meta charset='UTF-8'>

<style>

@page{
    margin: 24px;
}

body{
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    color:#1f2937;
}

/* ======================================================
HEADER
====================================================== */

.header{
    width:100%;
    border-bottom:2px solid #3b82f6;
    padding-bottom:12px;
    margin-bottom:18px;
}

.header-table{
    width:100%;
    border-collapse:collapse;
}

.header-table td{
    border:none;
    vertical-align:top;
}

.company-name{
    font-size:24px;
    font-weight:700;
    color:#1e3a8a;
    margin-bottom:4px;
}

.report-title{
    font-size:15px;
    font-weight:700;
    color:#111827;
    margin-top:8px;
}

.company-info{
    font-size:10px;
    color:#6b7280;
    line-height:1.6;
}

/* ======================================================
INFO CARDS
====================================================== */

.info-wrapper{
    width:100%;
    margin-bottom:18px;
}

.info-box{
    width:100%;
    background:#f8fafc;
    border:1px solid #dbeafe;
    border-radius:8px;
}

.info-box td{
    border:none;
    padding:8px 10px;
    font-size:11px;
}

.label{
    color:#6b7280;
    font-weight:700;
}

.value{
    color:#111827;
}

/* ======================================================
TABLE
====================================================== */

.main-table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.main-table th{
    background:#2563eb;
    color:#ffffff;
    padding:10px 8px;
    font-size:11px;
    text-align:left;
    border:1px solid #dbeafe;
}

.main-table td{
    border:1px solid #e5e7eb;
    padding:9px 8px;
    font-size:10.5px;
}

.main-table tbody tr:nth-child(even){
    background:#f9fafb;
}

/* ======================================================
BADGES
====================================================== */

.badge{
    padding:4px 8px;
    border-radius:20px;
    font-size:10px;
    font-weight:700;
    display:inline-block;
}

.aprovado{
    background:#dcfce7;
    color:#166534;
}

.pendente{
    background:#fef3c7;
    color:#92400e;
}

.rejeitado{
    background:#fee2e2;
    color:#991b1b;
}

.cancelado{
    background:#e5e7eb;
    color:#374151;
}

/* ======================================================
EMPTY
====================================================== */

.empty{
    padding:30px;
    text-align:center;
    color:#6b7280;
    border:1px dashed #cbd5e1;
    border-radius:10px;
    margin-top:20px;
}

/* ======================================================
FOOTER
====================================================== */

.footer{
    position:fixed;
    bottom:-8px;
    left:0;
    right:0;
    font-size:9px;
    color:#6b7280;
}

.footer-left{
    float:left;
}

.footer-right{
    float:right;
}

</style>
</head>

<body>

<!-- =====================================================
HEADER
===================================================== -->

<div class='header'>

    <table class='header-table'>
        <tr>

            <td style='width:75%'>

                <div class='company-name'>
                    " . h($empresa['name'] ?? '') . "
                </div>

                <div class='company-info'>
                    NIF: " . h($empresa['registration_number'] ?? '-') . "<br>

                    " . h($empresa['address'] ?? '-') . ",
                    " . h($empresa['city'] ?? '-') . ",
                    " . h($empresa['country'] ?? '-') . "<br>

                    Tel: " . h($empresa['phone'] ?? '-') . " |
                    Email: " . h($empresa['email'] ?? '-') . "
                </div>

                <div class='report-title'>
                    $title
                </div>

            </td>

            <td style='width:25%; text-align:right;'>
                $logoHtml
            </td>

        </tr>
    </table>

</div>

<!-- =====================================================
INFO BOX
===================================================== -->

<div class='info-wrapper'>

    <table class='info-box'>
        <tr>

            <td width='25%'>
                <div class='label'>PERÍODO</div>
                <div class='value'>$periodLabel</div>
            </td>

            <td width='25%'>
                <div class='label'>TOTAL DE REGISTROS</div>
                <div class='value'>" . count($rows) . "</div>
            </td>

            <td width='25%'>
                <div class='label'>GERADO EM</div>
                <div class='value'>" . date('d/m/Y H:i') . "</div>
            </td>

            <td width='25%'>
                <div class='label'>DOCUMENTO</div>
                <div class='value'>RH / FÉRIAS</div>
            </td>

        </tr>
    </table>

</div>
";

/*
|--------------------------------------------------------------------------
| TABELA
|--------------------------------------------------------------------------
*/
if (!$rows) {

    $html .= "
        <div class='empty'>
            Nenhum registro encontrado para o período selecionado.
        </div>
    ";
} else {

    $html .= "

    <table class='main-table'>

        <thead>
            <tr>
                <th style='width:24%'>Funcionário</th>
                <th style='width:18%'>Cargo</th>
                <th style='width:14%'>Tipo</th>
                <th style='width:14%'>Data Início</th>
                <th style='width:14%'>Data Fim</th>
                <th style='width:16%'>Status</th>
            </tr>
        </thead>

        <tbody>
    ";

    foreach ($rows as $r) {

        $status = strtolower(trim($r['status'] ?? 'pendente'));

        if (!in_array($status, [
            'aprovado',
            'pendente',
            'rejeitado',
            'cancelado'
        ])) {
            $status = 'pendente';
        }

        $html .= "
            <tr>

                <td>
                    <strong>" . h($r['employee_name']) . "</strong>
                </td>

                <td>
                    " . h($r['position'] ?? '-') . "
                </td>

                <td>
                    " . h($r['type']) . "
                </td>

                <td>
                    " . formatDateAngola($r['start_date']) . "
                </td>

                <td>
                    " . formatDateAngola($r['end_date']) . "
                </td>

                <td>
                    <span class='badge $status'>
                        " . strtoupper(h($r['status'])) . "
                    </span>
                </td>

            </tr>
        ";
    }

    $html .= "
        </tbody>
    </table>
    ";
}

/*
|--------------------------------------------------------------------------
| FOOTER
|--------------------------------------------------------------------------
*/
$html .= "

<div class='footer'>

    <div class='footer-left'>
        Documento processado automaticamente pelo sistema
    </div>

    <div class='footer-right'>
        Página 1 de 1
    </div>

</div>

</body>
</html>
";

/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/
$options = new Options();

$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html, 'UTF-8');

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$dompdf->stream(
    'ferias_licencas_' .
        str_replace('-', '', $startDt) .
        '_' .
        str_replace('-', '', $endDt) .
        '.pdf',
    ['Attachment' => 0]
);
