<?php
session_start();

require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$company_id) {
    die('Empresa não definida na sessão.');
}

/*
|--------------------------------------------------------------------------
| BUSCAR EMPRESA
|--------------------------------------------------------------------------
*/

$stmtCompany = $pdo->prepare("
    SELECT name
    FROM companies
    WHERE id = ?
");

$stmtCompany->execute([$company_id]);

$company = $stmtCompany->fetch(PDO::FETCH_ASSOC);

$companyName = $company['name'] ?? 'EMPRESA';

/*
|--------------------------------------------------------------------------
| PLANILHA
|--------------------------------------------------------------------------
*/

$spreadsheet = new Spreadsheet();

$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle('Mapa Salarial');

/*
|--------------------------------------------------------------------------
| CORES
|--------------------------------------------------------------------------
*/

$primaryColor   = '2563EB';
$secondaryColor = 'DBEAFE';
$borderColor    = 'D1D5DB';
$textDark       = '1E293B';

/*
|--------------------------------------------------------------------------
| TÍTULO
|--------------------------------------------------------------------------
*/

$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', strtoupper($companyName));

$sheet->mergeCells('A2:I2');
$sheet->setCellValue('A2', 'MAPA DE SALÁRIOS');

$sheet->mergeCells('A3:I3');
$sheet->setCellValue('A3', 'Gerado em: ' . date('d/m/Y H:i'));

$sheet->getStyle('A1:I3')->applyFromArray([

    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],

    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER
    ],

    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $primaryColor]
    ]

]);

$sheet->getStyle('A1')->getFont()->setSize(18);
$sheet->getStyle('A2')->getFont()->setSize(14);
$sheet->getStyle('A3')->getFont()->setSize(10);

$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->getRowDimension(2)->setRowHeight(24);
$sheet->getRowDimension(3)->setRowHeight(20);

/*
|--------------------------------------------------------------------------
| CABEÇALHO
|--------------------------------------------------------------------------
*/

$headers = [
    'Funcionário',
    'Referência',
    'Salário Base',
    'Bónus',
    'Descontos',
    'Salário Líquido',
    'IBAN',
    'Status',
    'Data Pagamento'
];

$sheet->fromArray($headers, null, 'A5');

$sheet->getStyle('A5:I5')->applyFromArray([

    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size'  => 11
    ],

    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $primaryColor]
    ],

    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER
    ],

    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['rgb' => $borderColor]
        ]
    ]

]);

$sheet->getRowDimension(5)->setRowHeight(24);

/*
|--------------------------------------------------------------------------
| FREEZE
|--------------------------------------------------------------------------
*/

$sheet->freezePane('A6');

/*
|--------------------------------------------------------------------------
| DADOS
|--------------------------------------------------------------------------
*/

$sql = "
SELECT 
    p.*,
    e.name AS employee_name,
    e.iban
FROM payroll p
JOIN employees e ON e.id = p.employee_id
WHERE p.company_id = ?
ORDER BY p.reference_month DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([$company_id]);

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$row = 6;

$totalBase       = 0;
$totalBonus      = 0;
$totalDiscounts  = 0;
$totalNet        = 0;

foreach ($dados as $linha) {

    $baseSalary = (float)$linha['base_salary'];
    $bonus      = (float)$linha['bonuses'];
    // total_discounts = manual + faltas + INSS + IRT (ver save_payroll.php).
    // Fallback para 'discounts' cobre folhas gravadas antes da migração da Fase 0.
    $discounts  = (float)($linha['total_discounts'] ?? $linha['discounts']);
    $netSalary  = (float)$linha['net_salary'];

    $sheet->fromArray([

        $linha['employee_name'],

        $linha['reference_month'],

        $baseSalary,

        $bonus,

        $discounts,

        $netSalary,

        $linha['iban'],

        strtoupper($linha['status']),

        !empty($linha['payment_date'])
            ? date('d/m/Y', strtotime($linha['payment_date']))
            : '-'

    ], null, "A{$row}");

    /*
    |--------------------------------------------------------------------------
    | ZEBRADO
    |--------------------------------------------------------------------------
    */

    if ($row % 2 == 0) {

        $sheet->getStyle("A{$row}:I{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('F8FAFC');
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS COLOR
    |--------------------------------------------------------------------------
    */

    $statusCell = "H{$row}";

    if ($linha['status'] === 'paid') {

        $sheet->getStyle($statusCell)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '15803D']
            ]
        ]);
    } else {

        $sheet->getStyle($statusCell)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'B91C1C']
            ]
        ]);
    }

    $totalBase      += $baseSalary;
    $totalBonus     += $bonus;
    $totalDiscounts += $discounts;
    $totalNet       += $netSalary;

    $row++;
}

/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

$sheet->setCellValue("A{$row}", 'TOTAL GERAL');

$sheet->mergeCells("A{$row}:B{$row}");

$sheet->setCellValue("C{$row}", $totalBase);
$sheet->setCellValue("D{$row}", $totalBonus);
$sheet->setCellValue("E{$row}", $totalDiscounts);
$sheet->setCellValue("F{$row}", $totalNet);

$sheet->getStyle("A{$row}:I{$row}")->applyFromArray([

    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],

    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['rgb' => $primaryColor]
    ],

    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER
    ],

    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['rgb' => $borderColor]
        ]
    ]

]);

$sheet->getRowDimension($row)->setRowHeight(24);

/*
|--------------------------------------------------------------------------
| FORMATAÇÃO MONETÁRIA
|--------------------------------------------------------------------------
*/

$sheet->getStyle("C6:F{$row}")
    ->getNumberFormat()
    ->setFormatCode('"Kz" #,##0.00');

/*
|--------------------------------------------------------------------------
| BORDAS
|--------------------------------------------------------------------------
*/

$sheet->getStyle("A6:I{$row}")
    ->applyFromArray([

        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => $borderColor]
            ]
        ],

        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]

    ]);

/*
|--------------------------------------------------------------------------
| ALINHAMENTOS
|--------------------------------------------------------------------------
*/

$sheet->getStyle("B6:B{$row}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("C6:F{$row}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getStyle("H6:H{$row}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("I6:I{$row}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

/*
|--------------------------------------------------------------------------
| AUTO SIZE
|--------------------------------------------------------------------------
*/

foreach (range('A', 'I') as $column) {

    $sheet->getColumnDimension($column)
        ->setAutoSize(true);
}

/*
|--------------------------------------------------------------------------
| MARGENS
|--------------------------------------------------------------------------
*/

$sheet->getSheetView()->setZoomScale(90);

/*
|--------------------------------------------------------------------------
| EXPORTAR
|--------------------------------------------------------------------------
*/

$filename = 'Mapa_Salarial_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

header("Content-Disposition: attachment; filename=\"{$filename}\"");

header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);

$writer->save('php://output');

exit;
