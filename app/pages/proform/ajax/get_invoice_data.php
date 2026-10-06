<?php
require_once __DIR__ . '../../../../app/config/db.php';
session_start();

// Verifique se o company_id está na sessão
if (!isset($_SESSION['user']['company_id'])) {
    echo json_encode(['error' => 'Company ID não encontrado na sessão.']);
    exit;
}

$company_id = $_SESSION['user']['company_id'];

// Consulta para obter os dados para os gráficos
$query = "SELECT 
        YEAR(issue_date) AS year,
        contact_id,
        SUM(final_total) AS total,
        name
    FROM invoices i
     Left JOIN contact c
ON i.contact_id = c.id
    WHERE i.company_id = :company_id
    GROUP BY year, contact_id
    ORDER BY year DESC
";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar dados para gráficos
$yearlyData = [];
$contactData = [];

foreach ($results as $row) {
    // Organizar os dados por ano
    if (!isset($yearlyData[$row['year']])) {
        $yearlyData[$row['year']] = 0;
    }
    $yearlyData[$row['year']] += $row['total'];

    // Organizar os dados por contato
    if (!isset($contactData[$row['name']])) {
        $contactData[$row['name']] = 0;
    }
    $contactData[$row['name']] += $row['total'];
}

// Preparar os dados para resposta
$graphs = [
    'yearly' => [
        'labels' => array_keys($yearlyData),
        'values' => array_values($yearlyData)
    ],
    'contact' => [
        'labels' => array_keys($contactData),
        'values' => array_values($contactData)
    ]
];

// Consulta para obter as Últimas Facturas emitidas
$queryInvoices = "SELECT i.*,c.name,
        concat(YEAR(i.issue_date),'/',i.id) AS codigo, cc.currency as currency_name, cc.symbol, cc.position,
        cc.iso_code, ist.name as status_name
         FROM invoices i
    Left JOIN contact c ON i.contact_id = c.id
    left join currencies cc on cc.iso_code = i.currency
    left join invoice_status ist on ist.id = i.status
    WHERE i.company_id = :company_id
    ORDER BY issue_date DESC
   
";

$stmtInvoices = $pdo->prepare($queryInvoices);
$stmtInvoices->bindParam(':company_id', $company_id, PDO::PARAM_INT);
$stmtInvoices->execute();
$invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

// Enviar os dados em formato JSON
echo json_encode([
    'invoices' => $invoices,
    'graphs' => $graphs
]);
