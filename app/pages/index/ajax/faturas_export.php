<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 🔹 FILTRO (podes adaptar via GET)
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

// 📘 CRIAR EXCEL
$spreadsheet = new Spreadsheet();


// =====================================================
// 📊 1. RESUMO
// =====================================================
$sheetResumo = $spreadsheet->getActiveSheet();
$sheetResumo->setTitle('Resumo');

// NOTA: parênteses adicionados — sem eles, "OR ... AND YEAR(...) = $ano"
// só aplicava o filtro de ano ao status = 3, devido à precedência do AND sobre o OR.
$resumo = $pdo->query("
    SELECT 
        SUM(total_sum) AS bruto,
        SUM(total_discount) AS desconto,
        SUM(total_tax) AS imposto,
        SUM(final_total) AS liquido
    FROM invoices
    WHERE (status = 5 OR status = 4 OR status = 3) AND YEAR(issue_date) = $ano
")->fetch(PDO::FETCH_ASSOC);

$creditos = $pdo->query("
    SELECT SUM(final_total) as total FROM credit_notes
    WHERE YEAR(issue_date) = $ano
")->fetch(PDO::FETCH_ASSOC);

$sheetResumo->fromArray([
    ['Indicador', 'Valor'],
    ['Total Bruto', $resumo['bruto']],
    ['Total Desconto', $resumo['desconto']],
    ['Total Imposto', $resumo['imposto']],
    ['Total Líquido', $resumo['liquido']],
    ['Total Notas de Crédito', $creditos['total']],
    ['Vendas Líquidas', $resumo['liquido'] - $creditos['total']]
]);


// =====================================================
// 📅 2. MENSAL
// =====================================================
$sheetMensal = $spreadsheet->createSheet();
$sheetMensal->setTitle('Mensal');

$mensal = $pdo->query("
    SELECT 
        DATE_FORMAT(issue_date, '%Y-%m') as mes,
        SUM(final_total) as total
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = $ano
    GROUP BY mes
")->fetchAll(PDO::FETCH_ASSOC);

$sheetMensal->fromArray(['Mês', 'Total'], NULL, 'A1');

$row = 2;
foreach ($mensal as $m) {
    $sheetMensal->setCellValue("A$row", $m['mes']);
    $sheetMensal->setCellValue("B$row", $m['total']);
    $row++;
}


// =====================================================
// 📆 3. TRIMESTRAL
// =====================================================
$sheetTrim = $spreadsheet->createSheet();
$sheetTrim->setTitle('Trimestral');

$trim = $pdo->query("
    SELECT 
        CONCAT(YEAR(issue_date), '-T', QUARTER(issue_date)) as trimestre,
        SUM(final_total) as total
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = $ano
    GROUP BY trimestre
")->fetchAll(PDO::FETCH_ASSOC);

$sheetTrim->fromArray(['Trimestre', 'Total'], NULL, 'A1');

$row = 2;
foreach ($trim as $t) {
    $sheetTrim->setCellValue("A$row", $t['trimestre']);
    $sheetTrim->setCellValue("B$row", $t['total']);
    $row++;
}


// =====================================================
// 📄 4. FATURAS
// =====================================================
$sheetInvoices = $spreadsheet->createSheet();
$sheetInvoices->setTitle('Faturas');

$invoices = $pdo->query("
    SELECT id, issue_date, reference, final_total, total_tax
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = $ano
")->fetchAll(PDO::FETCH_ASSOC);

$sheetInvoices->fromArray(['ID', 'Data', 'Ref', 'Total', 'Imposto'], NULL, 'A1');

$row = 2;
foreach ($invoices as $inv) {
    $sheetInvoices->fromArray(array_values($inv), NULL, "A$row");
    $row++;
}


// =====================================================
// 🧾 5. NOTAS DE CRÉDITO
// =====================================================
$sheetCredit = $spreadsheet->createSheet();
$sheetCredit->setTitle('Notas Crédito');

$credits = $pdo->query("
    SELECT id, issue_date, final_total
    FROM credit_notes
    WHERE YEAR(issue_date) = $ano
")->fetchAll(PDO::FETCH_ASSOC);

$sheetCredit->fromArray(['ID', 'Data', 'Total'], NULL, 'A1');

$row = 2;
foreach ($credits as $c) {
    $sheetCredit->fromArray(array_values($c), NULL, "A$row");
    $row++;
}


// =====================================================
// 📉 6. VENDAS LÍQUIDAS (REAL)
// =====================================================
$sheetLiquido = $spreadsheet->createSheet();
$sheetLiquido->setTitle('Liquido Real');

$liquido = $pdo->query("
    SELECT 
        i.id,
        i.issue_date,
        i.final_total,
        IFNULL(SUM(cn.final_total),0) as creditos,
        (i.final_total - IFNULL(SUM(cn.final_total),0)) as liquido_real
    FROM invoices i
    LEFT JOIN credit_notes cn ON cn.invoice_id = i.id
    WHERE i.status = 1 AND YEAR(i.issue_date) = $ano
    GROUP BY i.id
")->fetchAll(PDO::FETCH_ASSOC);

$sheetLiquido->fromArray(['ID', 'Data', 'Total', 'Créditos', 'Líquido'], NULL, 'A1');

$row = 2;
foreach ($liquido as $l) {
    $sheetLiquido->fromArray(array_values($l), NULL, "A$row");
    $row++;
}


// =====================================================
// 📈 7. INDICADORES
// =====================================================
$sheetIndicadores = $spreadsheet->createSheet();
$sheetIndicadores->setTitle('Indicadores');

$indicadores = $pdo->query("
    SELECT 
        COUNT(*) as total_faturas,
        AVG(final_total) as ticket_medio,
        MAX(final_total) as maior_venda
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = $ano
")->fetch(PDO::FETCH_ASSOC);

$sheetIndicadores->fromArray([
    ['Indicador', 'Valor'],
    ['Total Faturas', $indicadores['total_faturas']],
    ['Ticket Médio', $indicadores['ticket_medio']],
    ['Maior Venda', $indicadores['maior_venda']]
]);


// =====================================================
// 💾 SALVAR
// =====================================================
$fileName = "relatorio_vendas_$ano.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$fileName\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
