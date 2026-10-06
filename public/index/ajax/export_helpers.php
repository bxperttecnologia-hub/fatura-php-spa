<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * Envia o Spreadsheet para o browser como .xlsx ou .csv, consoante $formato,
 * define os headers corretos e termina a execução.
 *
 * Usado por: export_credit_notes.php, export_receipts.php,
 * export_debit_notes.php (export_sales_report.php gera sempre .xlsx
 * por ter várias folhas, que não fazem sentido em CSV).
 */
function export_spreadsheet(Spreadsheet $spreadsheet, string $formato, string $baseName): void
{
    $formato = strtolower(trim($formato));

    if ($formato === 'csv') {
        $fileName = "{$baseName}.csv";

        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment;filename=\"$fileName\"");
        header('Cache-Control: max-age=0');

        // BOM para o Excel abrir corretamente acentuação em UTF-8
        echo "\xEF\xBB\xBF";

        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(';');
        $writer->save('php://output');
        exit;
    }

    // Excel (.xlsx) por omissão
    $fileName = "{$baseName}.xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"$fileName\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Devolve o company_id da sessão atual, se existir.
 * Ajusta esta função à tua camada de autenticação real
 * (ex.: se guardas o id da empresa ativa noutra chave de sessão).
 */
function export_current_company_id(): ?int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['company_id']) ? (int) $_SESSION['company_id'] : null;
}
