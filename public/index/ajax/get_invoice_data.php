<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');

session_start();

try {

    // =====================================================
    // VALIDAR SESSÃO
    // =====================================================

    if (!isset($_SESSION['user']['company_id'])) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'error' => 'Company ID não encontrado na sessão.'
        ]);

        exit;
    }

    $company_id = (int)$_SESSION['user']['company_id'];
    echo $company_id;

    // =====================================================
    // GRÁFICOS
    // apenas status diferentes de 1 e 2
    // =====================================================

    $queryGraphs = "
        SELECT 
            YEAR(i.issue_date) AS year,
            i.contact_id,
            ROUND(SUM(i.final_total), 2) AS total,
            COALESCE(c.name, 'Sem cliente') AS contact_name
        FROM invoices i

        LEFT JOIN contact c
            ON i.contact_id = c.id

        WHERE i.company_id = :company_id
        AND i.status NOT IN (1,2)

        GROUP BY 
            YEAR(i.issue_date),
            i.contact_id,
            c.name

        ORDER BY year DESC
    ";

    $stmtGraphs = $pdo->prepare($queryGraphs);

    $stmtGraphs->bindParam(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmtGraphs->execute();

    $results = $stmtGraphs->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // PREPARAR DADOS DOS GRÁFICOS
    // =====================================================

    $yearlyData = [];
    $contactData = [];

    foreach ($results as $row) {

        $year = $row['year'];
        $contactName = $row['contact_name'];
        $total = (float)$row['total'];

        // total por ano
        if (!isset($yearlyData[$year])) {
            $yearlyData[$year] = 0;
        }

        $yearlyData[$year] += $total;

        // total por cliente
        if (!isset($contactData[$contactName])) {
            $contactData[$contactName] = 0;
        }

        $contactData[$contactName] += $total;
    }

    // =====================================================
    // ORDENAR CLIENTES POR FATURAÇÃO
    // =====================================================

    arsort($contactData);

    // =====================================================
    // PREPARAR RESPONSE DOS GRÁFICOS
    // =====================================================

    $graphs = [

        'yearly' => [
            'labels' => array_keys($yearlyData),
            'values' => array_map(
                fn($value) => round($value, 2),
                array_values($yearlyData)
            )
        ],

        'contact' => [
            'labels' => array_keys($contactData),
            'values' => array_map(
                fn($value) => round($value, 2),
                array_values($contactData)
            )
        ]
    ];

    // =====================================================
    // Últimas Facturas
    // =====================================================

    $queryInvoices = "
        SELECT 
            i.*,

            c.name,

            CONCAT(
                YEAR(i.issue_date),
                '/',
                i.id
            ) AS codigo,

            cc.currency AS currency_name,
            cc.symbol,
            cc.position,
            cc.iso_code,

            ist.name AS status_name

        FROM invoices i

        LEFT JOIN contact c
            ON i.contact_id = c.id

        LEFT JOIN currencies cc
            ON cc.iso_code = i.currency

        LEFT JOIN invoice_status ist
            ON ist.id = i.status

        WHERE i.company_id = :company_id
        AND i.status NOT IN (1,2)

        ORDER BY i.issue_date DESC
    ";

    $stmtInvoices = $pdo->prepare($queryInvoices);

    $stmtInvoices->bindParam(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmtInvoices->execute();

    $invoices = $stmtInvoices->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // RESPONSE
    // =====================================================

    echo json_encode([
        'success' => true,

        'invoices' => $invoices,

        'graphs' => $graphs
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
