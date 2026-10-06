<?php
require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

$id = $_GET['id'] ?? null;
$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$id) {
    die('Parâmetros inválidos.');
}

// Nunca continuar sem company_id na sessão: sem isto, o filtro abaixo
// deixava de ser aplicado e qualquer pessoa autenticada em qualquer empresa
// conseguia ver o recibo de outra empresa só sabendo o ID.
if (empty($company_id)) {
    http_response_code(401);
    die('Sessão expirada ou inválida. Por favor, faça login novamente.');
}

/*
|--------------------------------------------------------------------------
| BUSCAR DADOS
|--------------------------------------------------------------------------
*/

$sql = "
SELECT 
    p.*,
    e.id AS employee_id,
    e.name AS employee_name,
    e.position,
    e.admission_date,
    e.iban,
    e.bi,
    comp.name AS company_name,
    comp.logo_url
FROM payroll p
JOIN employees e ON e.id = p.employee_id
JOIN companies comp ON comp.id = p.company_id
WHERE p.id = ?
";

$params = [$id, $company_id];
$sql .= " AND p.company_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    die('Registro não encontrado.');
}

/*
|--------------------------------------------------------------------------
| FALTAS
|--------------------------------------------------------------------------
*/

$reference = $dados['reference_month'];

$yearMonth = explode('-', $reference);

$firstDay = $yearMonth[0] . '-' . $yearMonth[1] . '-01';
$lastDay  = date('Y-m-t', strtotime($firstDay));

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM attendance
    WHERE employee_id = ?
    AND company_id = ?
    AND type = 'falta'
    AND date BETWEEN ? AND ?
    AND date NOT IN (SELECT date FROM holidays WHERE company_id = ?)
");

$stmt->execute([
    $dados['employee_id'],
    $company_id,
    $firstDay,
    $lastDay,
    $company_id
]);

$absences = (int)$stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| VALORES
|--------------------------------------------------------------------------
*/

$base  = (float)$dados['base_salary'];

$inss = (float)($dados['inss_value'] ?? 0);
$irt  = (float)($dados['irt_value'] ?? 0);

// total_discounts = manual + faltas + INSS + IRT (ver save_payroll.php).
// Fallback para 'discounts' cobre folhas gravadas antes da migração da Fase 0.
$discountsTotal = (float)($dados['total_discounts'] ?? $dados['discounts'] ?? 0);

$otherDiscounts = max(
    0,
    $discountsTotal - $inss - $irt
);

$netSalary = (float)$dados['net_salary'];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function kz($v)
{
    return 'Kz ' . number_format((float)$v, 2, ',', '.');
}

function esc($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$baseUrl = '';

if (!empty($_SERVER['HTTP_HOST'])) {
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
}

$logoSrc = '';

if (!empty($dados['logo_url']) && $baseUrl) {
    $logoSrc = $baseUrl . '/assets/img/companies/' . $dados['logo_url'];
}

/*
|--------------------------------------------------------------------------
| DOMPDF
|--------------------------------------------------------------------------
*/

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

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
    margin:22px;
}

body{
    font-family: DejaVu Sans, sans-serif;
    font-size:11px;
    color:#1e293b;
}

.wrapper{
    width:100%;
}

/* =========================================================
HEADER
========================================================= */

.top{
    width:100%;
    border-collapse:collapse;
}

.top td{
    vertical-align:top;
}

.logo{
    width:78px;
    height:78px;
    border-radius:50%;
}

.logo-placeholder{
    width:78px;
    height:78px;
    border:1px solid #cbd5e1;
    border-radius:50%;
    text-align:center;
    line-height:78px;
    color:#94a3b8;
}

.company{
    text-align:right;
}

.company-title{
    font-size:24px;
    font-weight:bold;
    color:#1e293b;
    margin-bottom:6px;
}

.receipt-title{
    font-size:13px;
    color:#64748b;
}

.badge{
    display:inline-block;
    margin-top:8px;
    background:#dbeafe;
    color:#2563eb;
    padding:5px 14px;
    border-radius:14px;
    font-size:11px;
    font-weight:bold;
}

.hr{
    margin-top:14px;
    margin-bottom:16px;
    border-bottom:2px solid #dbeafe;
}

/* =========================================================
EMPLOYEE BOX
========================================================= */

.info-box{
    border:1px solid #dbeafe;
    border-radius:8px;
    padding:14px;
    margin-bottom:16px;
}

.info-table{
    width:100%;
    border-collapse:collapse;
}

.info-table td{
    padding:5px 0;
    font-size:11px;
}

.label{
    font-weight:bold;
    color:#334155;
    width:130px;
}

.value{
    color:#1e293b;
}

/* =========================================================
TABLE
========================================================= */

.salary-table{
    width:100%;
    border-collapse:collapse;
}

.salary-table th{
    background:#2563eb;
    color:#ffffff;
    padding:10px;
    border:1px solid #cbd5e1;
    font-size:11px;
    text-transform:uppercase;
}

.salary-table td{
    border:1px solid #dbeafe;
    padding:9px;
    font-size:11px;
}

.center{
    text-align:center;
}

.right{
    text-align:right;
}

.total-row{
    background:#dbeafe;
    font-weight:bold;
}

.net-row{
    background:#2563eb;
    color:#ffffff;
    font-weight:bold;
    font-size:12px;
}

.net-row td{
    padding:12px;
}

/* =========================================================
SUMMARY BOX
========================================================= */

.summary{
    width:280px;
    float:right;
    margin-top:18px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    overflow:hidden;
}

.summary table{
    width:100%;
    border-collapse:collapse;
}

.summary td{
    padding:11px;
    border-bottom:1px solid #e2e8f0;
    font-size:11px;
}

.summary tr:last-child td{
    border-bottom:none;
    background:#eff6ff;
    font-weight:bold;
}

/* =========================================================
SIGNATURE
========================================================= */

.signature{
    width:250px;
    float:right;
    margin-left: 200px;
    margin-top:210px;
    text-align:center;
    
}

.signature-line{
    border-top:1px solid #64748b;
    margin-bottom:8px;
    width:100%;
}

/* =========================================================
FOOTER
========================================================= */

.footer{
    clear:both;
    margin-top:120px;
    text-align:center;
    color:#94a3b8;
    font-size:10px;
}

</style>
</head>

<body>

<div class='wrapper'>

    <!-- HEADER -->
    <table class='top'>
        <tr>

            <td width='20%'>

                " . (
    $logoSrc
    ? "<img src='" . esc($logoSrc) . "' class='logo'>"
    : "<div class='logo-placeholder'>LOGO</div>"
) . "

            </td>

            <td width='80%' class='company'>

                <div class='company-title'>
                    " . esc($dados['company_name']) . "
                </div>

                <div class='receipt-title'>
                    RECIBO DE SALÁRIO • " . esc($dados['reference_month']) . "
                </div>

                <div class='badge'>
                    Funcionário: " . esc($dados['employee_name']) . "
                </div>

            </td>

        </tr>
    </table>

    <div class='hr'></div>

    <!-- INFO -->
    <div class='info-box'>

        <table class='info-table'>

            <tr>

                <td class='label'>Funcionário</td>
                <td class='value'>
                    " . esc($dados['employee_name']) . "
                </td>

                <td class='label'>Função</td>
                <td class='value'>
                    " . esc($dados['position']) . "
                </td>

            </tr>

            <tr>

                <td class='label'>BI</td>
                <td class='value'>
                    " . esc($dados['bi']) . "
                </td>

                <td class='label'>Contrato</td>
                <td class='value'>
                    efetivo
                </td>

            </tr>

            <tr>

                <td class='label'>IBAN</td>
                <td class='value'>
                    " . esc($dados['iban']) . "
                </td>

                <td class='label'>Admissão</td>
                <td class='value'>
                    " . (
    !empty($dados['admission_date'])
    ? date('d/m/Y', strtotime($dados['admission_date']))
    : ''
) . "
                </td>

            </tr>

        </table>

    </div>

    <!-- TABLE -->
    <table class='salary-table'>

        <thead>

            <tr>
                <th width='90'>CÓDIGO</th>
                <th>DESCRIÇÃO</th>
                <th width='110'>REFERÊNCIA</th>
                <th width='120'>PROVENTOS</th>
                <th width='120'>DESCONTOS</th>
            </tr>

        </thead>

        <tbody>

            <tr>

                <td class='center'>001</td>

                <td>Salário Base</td>

                <td class='center'>-</td>

                <td class='right'>
                    " . kz($base) . "
                </td>

                <td class='right'>-</td>

            </tr>

            <tr>

                <td class='center'>201</td>

                <td>INSS (3%)</td>

                <td class='center'>3%</td>

                <td class='right'>-</td>

                <td class='right'>
                    " . kz($inss) . "
                </td>

            </tr>

            <tr>

                <td class='center'>202</td>

                <td>IRT</td>

                <td class='center'>Tabela</td>

                <td class='right'>-</td>

                <td class='right'>
                    " . kz($irt) . "
                </td>

            </tr>

            <tr>

                <td class='center'>203</td>

                <td>Faltas</td>

                <td class='center'>
                    " . $absences . "
                </td>

                <td class='right'>-</td>

                <td class='right'>
                    Kz 0,00
                </td>

            </tr>

            <tr>

                <td class='center'>204</td>

                <td>Outros Descontos</td>

                <td class='center'>-</td>

                <td class='right'>-</td>

                <td class='right'>
                    " . kz($otherDiscounts) . "
                </td>

            </tr>

            <tr class='total-row'>

                <td colspan='3' class='right'>
                    TOTAL
                </td>

                <td class='right'>
                    " . kz($base) . "
                </td>

                <td class='right'>
                    " . kz($discountsTotal) . "
                </td>

            </tr>

            <tr class='net-row'>

                <td colspan='4'>
                    SALÁRIO LÍQUIDO
                </td>

                <td class='right'>
                    " . kz($netSalary) . "
                </td>

            </tr>

        </tbody>

    </table>

    <!-- SUMMARY -->
    <div class='summary'>

        <table>

            <tr>
                <td><strong>Total Bruto</strong></td>
                <td class='right'>" . kz($base) . "</td>
            </tr>

            <tr>
                <td><strong>Total Descontos</strong></td>
                <td class='right'>" . kz($discountsTotal) . "</td>
            </tr>

            <tr>
                <td><strong>Líquido a Receber</strong></td>
                <td class='right'>" . kz($netSalary) . "</td>
            </tr>

        </table>

    </div>

    <!-- SIGNATURE -->
    <div class='signature'>

        <div class='signature-line'></div>

        <strong>Assinatura</strong>

    </div>

    <!-- FOOTER -->
    <div class='footer'>
        Documento processado automaticamente por computador • 
        " . esc($dados['company_name']) . "
    </div>

</div>

</body>
</html>
";

/*
|--------------------------------------------------------------------------
| GERAR PDF
|--------------------------------------------------------------------------
*/

$dompdf->loadHtml($html, 'UTF-8');

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$dompdf->stream(
    'recibo_salario_' . $dados['id'] . '.pdf',
    ['Attachment' => false]
);
