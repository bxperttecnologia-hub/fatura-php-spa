<?php
session_start();
require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Verifica se é uma consulta de progresso (mantido por compatibilidade; a lista já não o usa)
if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['progress' => $_SESSION['export_progress'] ?? 0]);
    exit;
}

// ---------------------------------------------------------------------------
// CORREÇÕES (marcadas com [FIX]):
//  1. exigia sessão? não — e exportava as faturas de TODAS as empresas.
//  2. o parâmetro "situacao" (pago/pendente) era enviado pela lista mas ignorado.
//  3. no CSV, $sqlItems e $totalFaturas não existiam (erro fatal).
//  4. novo: "ids" opcional (exportar só selecionados / filtrados). Aceita GET ou POST.
//  5. formato desconhecido (ex.: "pdf") devolvia um ficheiro vazio.
// ---------------------------------------------------------------------------

header('Content-Type: application/json'); // só para respostas de erro; sobrescrito abaixo

// [FIX 1] empresa SEMPRE da sessão
if (!isset($_SESSION['user']['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Inicie sessão novamente.']);
    exit;
}
$companyId = (int)$_SESSION['user']['company_id'];

$_SESSION['export_progress'] = 0;

$formato = $_REQUEST['formato'] ?? 'excel';
// [FIX 5]
if (!in_array($formato, ['excel', 'csv'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de exportação não suportado.']);
    exit;
}

try {
    $_SESSION['export_progress'] = 10;

    $where  = ['i.company_id = ?'];
    $params = [$companyId];

    // [FIX 4] ids opcionais: "12,15,18"
    if (!empty($_REQUEST['ids'])) {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string)$_REQUEST['ids']))
        )));
        $ids = array_slice($ids, 0, 5000);
        if ($ids) {
            $where[]  = 'i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params   = array_merge($params, $ids);
        }
    }

    // [FIX 2] situacao: mesma lógica de nomes usada pela lista (JS)
    $situacao = strtolower(trim((string)($_REQUEST['situacao'] ?? '')));
    if ($situacao === 'pago') {
        $where[] = "(LOWER(ivs.name) LIKE '%pago%' OR LOWER(ivs.name) LIKE '%quit%') AND LOWER(ivs.name) NOT LIKE '%parcial%'";
    } elseif ($situacao === 'pendente') {
        $where[] = "LOWER(ivs.name) LIKE '%pend%'";
    }

    $sql = "SELECT
                i.id AS invoice_id,
                CONCAT(YEAR(i.issue_date), '/', i.id) AS codigo,
                i.issue_date,
                i.due_date,
                i.final_total,
                i.total_tax,
                i.subtotal_without_tax,
                c.name AS client_name,
                comp.name AS company_name
            FROM invoices i
            JOIN companies comp ON comp.id = i.company_id
            JOIN contact c ON c.id = i.contact_id
            JOIN invoice_status ivs ON ivs.id = i.status
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $_SESSION['export_progress'] = 30;

    // [FIX 3] definido uma vez, fora dos ciclos, para os dois formatos
    $sqlItems = "SELECT it.description, ii.quantity, ii.unit_price, ii.tax
                 FROM invoice_items ii
                 JOIN items it ON it.id = ii.item_id
                 WHERE ii.invoice_id = :invoiceId";
    $stmtItems = $pdo->prepare($sqlItems);
    $totalFaturas = max(1, count($invoices));

    $headers = ['Número da Fatura', 'Data Emissão', 'Data Vencimento', 'Cliente', 'Empresa', 'Total Líquido', 'Total Imposto', 'Total Final', 'Produtos/Serviços da Fatura'];

    $buildRow = function (array $invoice) use ($stmtItems): array {
        $stmtItems->bindValue(':invoiceId', $invoice['invoice_id'], PDO::PARAM_INT);
        $stmtItems->execute();
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $itemsString = implode(', ', array_map(function ($item) {
            return "{$item['description']} ({$item['quantity']}x " . number_format($item['unit_price'], 2, ',', '.') . " - {$item['tax']}% IVA)";
        }, $items));

        return [
            $invoice['codigo'],
            $invoice['issue_date'],
            $invoice['due_date'],
            $invoice['client_name'],
            $invoice['company_name'],
            number_format($invoice['subtotal_without_tax'], 2, ',', '.'),
            number_format($invoice['total_tax'], 2, ',', '.'),
            number_format($invoice['final_total'], 2, ',', '.'),
            $itemsString,
        ];
    };

    if ($formato === 'excel') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        $n = 0;
        foreach ($invoices as $invoice) {
            $sheet->fromArray($buildRow($invoice), null, "A$row");
            $row++;
            $n++;
            $_SESSION['export_progress'] = 30 + intval(($n / $totalFaturas) * 50);
        }

        $_SESSION['export_progress'] = 90;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="faturas.xlsx"');
        (new Xlsx($spreadsheet))->save('php://output');
    } else { // csv
        $_SESSION['export_progress'] = 50;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="faturas.csv"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos corretamente
        fputcsv($output, $headers);

        $n = 0;
        foreach ($invoices as $invoice) {
            fputcsv($output, $buildRow($invoice));
            $n++;
            $_SESSION['export_progress'] = 50 + intval(($n / $totalFaturas) * 50);
        }
        fclose($output);
    }

    $_SESSION['export_progress'] = 100;
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao gerar o arquivo: ' . $e->getMessage()]);
    exit;
}
