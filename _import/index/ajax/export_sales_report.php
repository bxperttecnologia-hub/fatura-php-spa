<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once __DIR__ . '/export_helpers.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 🔹 FILTROS
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
$companyId = export_current_company_id();

$companyFilterInvoices = $companyId !== null ? " AND company_id = :company_id" : "";
$companyFilterCredits  = $companyId !== null ? " AND company_id = :company_id" : "";
$bindCompany = $companyId !== null ? [':company_id' => $companyId] : [];

// 📘 CRIAR EXCEL
$spreadsheet = new Spreadsheet();


// =====================================================
// 📊 1. RESUMO
// =====================================================
$sheetResumo = $spreadsheet->getActiveSheet();
$sheetResumo->setTitle('Resumo');

// NOTA: parênteses obrigatórios — sem eles, "OR ... AND YEAR(...) = $ano"
// só aplicava o filtro de ano ao status = 3, por precedência do AND sobre o OR.
$stmt = $pdo->prepare("
    SELECT 
        SUM(total_sum) AS bruto,
        SUM(total_discount) AS desconto,
        SUM(total_tax) AS imposto,
        SUM(final_total) AS liquido
    FROM invoices
    WHERE (status = 5 OR status = 4 OR status = 3)
      AND YEAR(issue_date) = :ano
      {$companyFilterInvoices}
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$resumo = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT SUM(final_total) as total FROM credit_notes
    WHERE YEAR(issue_date) = :ano
      {$companyFilterCredits}
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$creditos = $stmt->fetch(PDO::FETCH_ASSOC);

$totalLiquido = (float) ($resumo['liquido'] ?? 0);
$totalCreditos = (float) ($creditos['total'] ?? 0);

$sheetResumo->fromArray([
    ['Indicador', 'Valor'],
    ['Total Bruto', (float) ($resumo['bruto'] ?? 0)],
    ['Total Desconto', (float) ($resumo['desconto'] ?? 0)],
    ['Total Imposto', (float) ($resumo['imposto'] ?? 0)],
    ['Total Líquido', $totalLiquido],
    ['Total Notas de Crédito', $totalCreditos],
    ['Vendas Líquidas', $totalLiquido - $totalCreditos],
]);


// =====================================================
// 📅 2. MENSAL
// =====================================================
$sheetMensal = $spreadsheet->createSheet();
$sheetMensal->setTitle('Mensal');

$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(issue_date, '%Y-%m') as mes,
        SUM(final_total) as total
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = :ano
      {$companyFilterInvoices}
    GROUP BY mes
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$mensal = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sheetMensal->fromArray(['Mês', 'Total'], NULL, 'A1');

$row = 2;
foreach ($mensal as $m) {
    $sheetMensal->setCellValue("A$row", $m['mes']);
    $sheetMensal->setCellValue("B$row", (float) $m['total']);
    $row++;
}


// =====================================================
// 📆 3. TRIMESTRAL
// =====================================================
$sheetTrim = $spreadsheet->createSheet();
$sheetTrim->setTitle('Trimestral');

$stmt = $pdo->prepare("
    SELECT 
        CONCAT(YEAR(issue_date), '-T', QUARTER(issue_date)) as trimestre,
        SUM(final_total) as total
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = :ano
      {$companyFilterInvoices}
    GROUP BY trimestre
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$trim = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sheetTrim->fromArray(['Trimestre', 'Total'], NULL, 'A1');

$row = 2;
foreach ($trim as $t) {
    $sheetTrim->setCellValue("A$row", $t['trimestre']);
    $sheetTrim->setCellValue("B$row", (float) $t['total']);
    $row++;
}


// =====================================================
// 📄 4. FATURAS
// =====================================================
$sheetInvoices = $spreadsheet->createSheet();
$sheetInvoices->setTitle('Faturas');

$stmt = $pdo->prepare("
    SELECT id, issue_date, reference, final_total, total_tax
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = :ano
      {$companyFilterInvoices}
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$stmt = $pdo->prepare("
    SELECT id, issue_date, final_total
    FROM credit_notes
    WHERE YEAR(issue_date) = :ano
      {$companyFilterCredits}
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$stmt = $pdo->prepare("
    SELECT 
        i.id,
        i.issue_date,
        i.final_total,
        IFNULL(SUM(cn.final_total),0) as creditos,
        (i.final_total - IFNULL(SUM(cn.final_total),0)) as liquido_real
    FROM invoices i
    LEFT JOIN credit_notes cn ON cn.invoice_id = i.id
    WHERE i.status = 1 AND YEAR(i.issue_date) = :ano
      " . ($companyId !== null ? " AND i.company_id = :company_id" : "") . "
    GROUP BY i.id
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$liquido = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_faturas,
        AVG(final_total) as ticket_medio,
        MAX(final_total) as maior_venda
    FROM invoices
    WHERE status = 1 AND YEAR(issue_date) = :ano
      {$companyFilterInvoices}
");
$stmt->execute(array_merge([':ano' => $ano], $bindCompany));
$indicadores = $stmt->fetch(PDO::FETCH_ASSOC);

$sheetIndicadores->fromArray([
    ['Indicador', 'Valor'],
    ['Total Faturas', (int) ($indicadores['total_faturas'] ?? 0)],
    ['Ticket Médio', (float) ($indicadores['ticket_medio'] ?? 0)],
    ['Maior Venda', (float) ($indicadores['maior_venda'] ?? 0)],
]);


// =====================================================
// 💾 SALVAR (sempre .xlsx — o relatório tem várias folhas,
// o que não se traduz bem para CSV)
// =====================================================
$fileName = "relatorio_vendas_$ano.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$fileName\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
