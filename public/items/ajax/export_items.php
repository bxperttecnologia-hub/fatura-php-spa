<?php

require_once(__DIR__ . '/../../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Mpdf\Mpdf;

// ==========================================
// DADOS
// ==========================================

$json = file_get_contents("items.json");

$response = json_decode($json, true);

$items = $response["data"] ?? [];

// ==========================================
// PREPARAR DADOS
// ==========================================

function prepareData($items)
{
    $rows = [];

    foreach ($items as $item) {

        $rows[] = [
            "ID"           => $item["id"] ?? "",
            "Código"       => $item["code"] ?? "-",
            "Nome"         => $item["name"] ?? "-",
            "Tipo"         => $item["item_type"] ?? "-",
            "Categoria"    => $item["category"] ?? "-",
            "Unidade"      => $item["unit_measure"] ?? "-",
            "Preço"        => number_format((float)($item["unit_price"] ?? 0), 2, ",", "."),
            "PVP"          => number_format((float)($item["pvp"] ?? 0), 2, ",", "."),
            "Quantidade"   => $item["quantity"] ?? 0,
            "Stock"        => $item["stock_name"] ?? "-",
            "Estado"       => $item["status"] ?? "-",
            "Moeda"        => $item["currency"] ?? "AOA",
        ];
    }

    return $rows;
}

// ==========================================
// EXPORTAR EXCEL
// ==========================================

function exportExcel($items)
{
    $rows = prepareData($items);

    $spreadsheet = new Spreadsheet();

    $sheet = $spreadsheet->getActiveSheet();

    // CABEÇALHO
    $headers = array_keys($rows[0]);

    $columns = range('A', 'Z');

    foreach ($headers as $index => $header) {

        $cell = $columns[$index] . '1';

        $sheet->setCellValue($cell, $header);
    }

    // DADOS
    $rowNumber = 2;

    foreach ($rows as $row) {

        $colIndex = 0;

        foreach ($row as $value) {

            $cell = $columns[$colIndex] . $rowNumber;

            $sheet->setCellValue($cell, $value);

            $colIndex++;
        }

        $rowNumber++;
    }

    // AUTO SIZE
    foreach ($columns as $column) {

        $sheet->getColumnDimension($column)
            ->setAutoSize(true);
    }

    // DOWNLOAD
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    header('Content-Disposition: attachment; filename="produtos.xlsx"');

    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);

    $writer->save('php://output');

    exit;
}

// ==========================================
// EXPORTAR PDF
// ==========================================

function exportPDF($items)
{
    $rows = prepareData($items);

    $mpdf = new Mpdf([
        "orientation" => "L"
    ]);

    $html = '
    <h2 style="text-align:center;">
        Relatório de Produtos
    </h2>

    <table border="1" width="100%" cellpadding="6" cellspacing="0">

        <thead>
            <tr style="background:#eaeaea;">';

    foreach (array_keys($rows[0]) as $header) {

        $html .= "<th>{$header}</th>";
    }

    $html .= '
            </tr>
        </thead>

        <tbody>';

    foreach ($rows as $row) {

        $html .= "<tr>";

        foreach ($row as $value) {

            $html .= "<td>{$value}</td>";
        }

        $html .= "</tr>";
    }

    $html .= '
        </tbody>
    </table>';

    $mpdf->WriteHTML($html);

    $mpdf->Output("produtos.pdf", "D");

    exit;
}

// ==========================================
// EXPORTAR CSV
// ==========================================

function exportCSV($items)
{
    $rows = prepareData($items);

    header("Content-Type: text/csv; charset=utf-8");

    header("Content-Disposition: attachment; filename=produtos.csv");

    $output = fopen("php://output", "w");

    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, array_keys($rows[0]), ";");

    foreach ($rows as $row) {

        fputcsv($output, $row, ";");
    }

    fclose($output);

    exit;
}

// ==========================================
// ESCOLHER TIPO
// ==========================================

$type = $_GET["type"] ?? "xlsx";

switch ($type) {

    case "xlsx":
        exportExcel($items);
        break;

    case "pdf":
        exportPDF($items);
        break;

    case "csv":
        exportCSV($items);
        break;

    default:
        echo "Tipo inválido";
}
