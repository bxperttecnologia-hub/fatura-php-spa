<?php

require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php';

session_start();

use Dompdf\Dompdf;
use Dompdf\Options;

$mes = $_GET['mes'] ?? date('Y-m');
$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$mes || !$company_id) {
    die('Parâmetros inválidos.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
|--------------------------------------------------------------------------
| EMPRESA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);

$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    die('Empresa não encontrada.');
}

/*
|--------------------------------------------------------------------------
| FOLHAS
|--------------------------------------------------------------------------
*/

$sql = "
SELECT 
    p.*, 
    e.name AS employee_name,
    e.document_type AS document,
    e.birth_date,
    e.position,
    e.contract_type,
    e.admission_date,
    e.iban,
    e.bi
FROM payroll p
JOIN employees e ON e.id = p.employee_id
WHERE p.company_id = ?
AND p.reference_month = ?
ORDER BY e.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$company_id, $mes]);

$folhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$folhas) {
    die('Nenhuma folha encontrada.');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function kz($v)
{
    return 'Kz ' . number_format((float)$v, 2, ',', '.');
}

function esc($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatDateBr($date)
{
    if (!$date) return '-';

    return date('d/m/Y', strtotime($date));
}

function calcIrt($base)
{
    $b = (float)$base;

    if ($b <= 150000) {
        return 0.0;
    }

    $brackets = [
        [150001, 200000, 12500, 0.16],
        [200001, 300000, 31250, 0.18],
        [300001, 500000, 49250, 0.19],
        [500001, 1000000, 87250, 0.20],
        [1000001, 1500000, 187250, 0.21],
        [1500001, 2000000, 292250, 0.22],
        [2000001, 2500000, 402250, 0.23],
        [2500001, 5000000, 517250, 0.24],
        [5000001, 10000000, 1117250, 0.245],
        [10000001, PHP_INT_MAX, 2342250, 0.25],
    ];

    foreach ($brackets as $br) {

        [$min, $max, $fixed, $rate] = $br;

        if ($b >= $min && $b <= $max) {

            $lower = ($min === 150001)
                ? 150000
                : ($min - 1);

            $excess = max(0, $b - $lower);

            return (float)$fixed + ($rate * $excess);
        }
    }

    return 0;
}

/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$baseUrl = '';

if (!empty($_SERVER['HTTP_HOST'])) {

    $protocol = (
        !empty($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    )
        ? 'https://'
        : 'http://';

    $baseUrl = $protocol . $_SERVER['HTTP_HOST'];
}

$logoSrc = '';

if (!empty($empresa['logo_url']) && $baseUrl) {

    $logoSrc =
        $baseUrl .
        '/assets/img/companies/' .
        $empresa['logo_url'];
}

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

$html = "
<style>

@page{
    margin:20px 25px;
}

body{
    font-family: DejaVu Sans, sans-serif;
    font-size:11px;
    color:#1e293b;
}

.header{
    width:100%;
    margin-bottom:15px;
}

.logo{
    width:85px;
    height:85px;
    border-radius:50%;
    object-fit:cover;
}

.company-name{
    font-size:24px;
    font-weight:bold;
    color:#1e293b;
    margin-bottom:4px;
}

.title{
    font-size:28px;
    font-weight:bold;
    color:#2563eb;
    text-transform:uppercase;
}

.subtitle{
    color:#64748b;
    font-size:11px;
    margin-top:4px;
}

.line{
    height:2px;
    background:#dbeafe;
    margin:15px 0;
}

.employee-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:8px;
    padding:12px;
    margin-bottom:15px;
}

.info-table{
    width:100%;
    border-collapse:collapse;
}

.info-table td{
    padding:4px 0;
    font-size:11px;
}

.label{
    font-weight:bold;
    color:#334155;
    width:130px;
}

.payroll-table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.payroll-table th{
    background:#2563eb;
    color:#fff;
    padding:10px;
    font-size:11px;
    text-transform:uppercase;
    border:1px solid #dbeafe;
}

.payroll-table td{
    border:1px solid #e2e8f0;
    padding:8px;
    font-size:11px;
}

.payroll-table tr:nth-child(even){
    background:#f8fafc;
}

.right{
    text-align:right;
}

.center{
    text-align:center;
}

.total-row{
    background:#dbeafe !important;
    font-weight:bold;
}

.net-row{
    background:#2563eb !important;
    color:#fff;
    font-weight:bold;
    font-size:12px;
}

.summary-box{
    margin-top:15px;
    width:280px;
    margin-left:auto;
    border:1px solid #cbd5e1;
    border-radius:8px;
}

.summary-box table{
    width:100%;
    border-collapse:collapse;
}

.summary-box td{
    padding:10px;
    border-bottom:1px solid #e2e8f0;
}

.summary-box tr:last-child td{
    border-bottom:none;
}

.footer{
    margin-top:35px;
}

.signature{
    width:250px;
    text-align:center;
    margin-top:40px;
    float:right;
}

.signature-line{
    border-top:1px solid #334155;
    margin-bottom:6px;
}

.footer-info{
    margin-top:70px;
    font-size:10px;
    color:#64748b;
    text-align:center;
}

.badge{
    display:inline-block;
    background:#eff6ff;
    color:#2563eb;
    padding:4px 10px;
    border-radius:20px;
    font-size:10px;
    font-weight:bold;
}

</style>
";

/*
|--------------------------------------------------------------------------
| LOOP
|--------------------------------------------------------------------------
*/

foreach ($folhas as $index => $f) {

    /*
    |--------------------------------------------------------------------------
    | QUEBRA DE PÁGINA
    |--------------------------------------------------------------------------
    */

    if ($index > 0) {
        $html .= "<div style='page-break-before: always;'></div>";
    }

    $firstDay = $mes . "-01";
    $lastDay  = date('Y-m-t', strtotime($firstDay));

    /*
    |--------------------------------------------------------------------------
    | FALTAS
    |--------------------------------------------------------------------------
    */

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
        $f['employee_id'],
        $company_id,
        $firstDay,
        $lastDay,
        $company_id
    ]);

    $faltas = (int)$stmt->fetchColumn();

    $valorFaltas =
        ((float)$f['base_salary'] / 30) * $faltas;

    /*
    |--------------------------------------------------------------------------
    | VALORES
    |--------------------------------------------------------------------------
    */

    $baseSalary = (float)$f['base_salary'];

    $bonus      = (float)($f['bonuses'] ?? 0);
    $food       = (float)($f['food_allowance'] ?? 0);
    $transport  = (float)($f['transport_allowance'] ?? 0);
    $commission = (float)($f['commissions'] ?? 0);

    $vacPct = (int)($f['vacation_subsidy_pct'] ?? 0);
    $t13Pct = (int)($f['thirteenth_subsidy_pct'] ?? 0);

    $vacSub = $baseSalary * ($vacPct / 100);
    $t13Sub = $baseSalary * ($t13Pct / 100);

    $gross =
        $baseSalary +
        $bonus +
        $food +
        $transport +
        $commission +
        $vacSub +
        $t13Sub;

    /*
    |--------------------------------------------------------------------------
    | DESCONTOS
    |--------------------------------------------------------------------------
    */

    $inss = $gross * 0.03;

    $irtBase = max(0, $gross - $inss);

    $irt = calcIrt($irtBase);

    // 'discounts' aqui já é só o valor manual (ver save_payroll.php, Fase 0).
    // Antes da correção, esta coluna guardava o TOTAL (manual + faltas + INSS + IRT),
    // e este ficheiro somava tudo outra vez abaixo — ou seja, duplicava faltas/INSS/IRT
    // no total de descontos. Nenhuma alteração de lógica é necessária aqui além
    // deste comentário: o bug foi corrigido na origem, na gravação da folha.
    $otherDiscounts = (float)($f['discounts'] ?? 0);

    $discounts =
        $inss +
        $irt +
        $valorFaltas +
        $otherDiscounts;

    $netSalary = $gross - $discounts;

    /*
    |--------------------------------------------------------------------------
    | HTML
    |--------------------------------------------------------------------------
    */

    $html .= "

    <div>

        <table class='header'>
            <tr>

                <td width='18%' valign='top'>
                    " . (
        $logoSrc
        ? "<img src='" . esc($logoSrc) . "' class='logo'>"
        : ""
    ) . "
                </td>

                <td width='82%' valign='top' align='right'>

                    <div class='company-name'>
                        " . esc($empresa['name']) . "
                    </div>

                    <div class='subtitle'>
                        RECIBO DE SALÁRIO • " . esc($mes) . "
                    </div>

                    <div style='margin-top:8px'>
                        <span class='badge'>
                            Funcionário: " . esc($f['employee_name']) . "
                        </span>
                    </div>

                </td>

            </tr>
        </table>

        <div class='line'></div>

        <div class='employee-box'>

            <table class='info-table'>

                <tr>
                    <td class='label'>Funcionário</td>
                    <td>" . esc($f['employee_name']) . "</td>

                    <td class='label'>Função</td>
                    <td>" . esc($f['position']) . "</td>
                </tr>

                <tr>
                    <td class='label'>BI</td>
                    <td>" . esc($f['bi']) . "</td>

                    <td class='label'>Contrato</td>
                    <td>" . esc($f['contract_type']) . "</td>
                </tr>

                <tr>
                    <td class='label'>IBAN</td>
                    <td>" . esc($f['iban']) . "</td>

                    <td class='label'>Admissão</td>
                    <td>" . esc(formatDateBr($f['admission_date'])) . "</td>
                </tr>

            </table>

        </div>

        <table class='payroll-table'>

            <thead>

                <tr>
                    <th width='12%'>Código</th>
                    <th width='44%'>Descrição</th>
                    <th width='14%'>Referência</th>
                    <th width='15%'>Proventos</th>
                    <th width='15%'>Descontos</th>
                </tr>

            </thead>

            <tbody>

                <tr>
                    <td class='center'>001</td>
                    <td>Salário Base</td>
                    <td class='center'>-</td>
                    <td class='right'>" . kz($baseSalary) . "</td>
                    <td class='right'>-</td>
                </tr>

                " . ($bonus > 0 ? "
                <tr>
                    <td class='center'>101</td>
                    <td>Bónus</td>
                    <td class='center'>-</td>
                    <td class='right'>" . kz($bonus) . "</td>
                    <td class='right'>-</td>
                </tr>
                " : "") . "

                " . ($food > 0 ? "
                <tr>
                    <td class='center'>102</td>
                    <td>Subsídio Alimentação</td>
                    <td class='center'>-</td>
                    <td class='right'>" . kz($food) . "</td>
                    <td class='right'>-</td>
                </tr>
                " : "") . "

                " . ($transport > 0 ? "
                <tr>
                    <td class='center'>103</td>
                    <td>Subsídio Transporte</td>
                    <td class='center'>-</td>
                    <td class='right'>" . kz($transport) . "</td>
                    <td class='right'>-</td>
                </tr>
                " : "") . "

                " . ($commission > 0 ? "
                <tr>
                    <td class='center'>104</td>
                    <td>Comissões</td>
                    <td class='center'>-</td>
                    <td class='right'>" . kz($commission) . "</td>
                    <td class='right'>-</td>
                </tr>
                " : "") . "

                " . ($vacSub > 0 ? "
                <tr>
                    <td class='center'>105</td>
                    <td>Subsídio de Férias</td>
                    <td class='center'>{$vacPct}%</td>
                    <td class='right'>" . kz($vacSub) . "</td>
                    <td class='right'>-</td>
                </tr>
                " : "") . "

                " . ($t13Sub > 0 ? "
                <tr>
                    <td class='center'>106</td>
                    <td>Subsídio 13º</td>
                    <td class='center'>{$t13Pct}%</td>
                    <td class='right'>" . kz($t13Sub) . "</td>
                    <td class='right'>-</td>
                </tr>
                " : "") . "

                <tr>
                    <td class='center'>201</td>
                    <td>INSS (3%)</td>
                    <td class='center'>3%</td>
                    <td class='right'>-</td>
                    <td class='right'>" . kz($inss) . "</td>
                </tr>

                <tr>
                    <td class='center'>202</td>
                    <td>IRT</td>
                    <td class='center'>Tabela</td>
                    <td class='right'>-</td>
                    <td class='right'>" . kz($irt) . "</td>
                </tr>

                <tr>
                    <td class='center'>203</td>
                    <td>Faltas</td>
                    <td class='center'>{$faltas}</td>
                    <td class='right'>-</td>
                    <td class='right'>" . kz($valorFaltas) . "</td>
                </tr>

                " . ($otherDiscounts > 0 ? "
                <tr>
                    <td class='center'>204</td>
                    <td>Outros Descontos</td>
                    <td class='center'>-</td>
                    <td class='right'>-</td>
                    <td class='right'>" . kz($otherDiscounts) . "</td>
                </tr>
                " : "") . "

                <tr class='total-row'>
                    <td colspan='3' class='right'>
                        TOTAL
                    </td>

                    <td class='right'>
                        " . kz($gross) . "
                    </td>

                    <td class='right'>
                        " . kz($discounts) . "
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

        <div class='summary-box'>

            <table>

                <tr>
                    <td><strong>Total Bruto</strong></td>
                    <td align='right'>" . kz($gross) . "</td>
                </tr>

                <tr>
                    <td><strong>Total Descontos</strong></td>
                    <td align='right'>" . kz($discounts) . "</td>
                </tr>

                <tr style='background:#eff6ff'>
                    <td><strong>Líquido a Receber</strong></td>
                    <td align='right'>
                        <strong>" . kz($netSalary) . "</strong>
                    </td>
                </tr>

            </table>

        </div>

        <div class='footer'>

            <div class='signature'>

                <div class='signature-line'></div>

                <strong>Assinatura</strong>

            </div>

            <div class='footer-info'>
                Documento processado automaticamente por computador • 
                " . esc($empresa['name']) . "
            </div>

        </div>

    </div>
    ";
}

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
    "folha_geral_{$mes}.pdf",
    ['Attachment' => false]
);
