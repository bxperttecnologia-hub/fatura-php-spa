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
            'error' => 'Empresa não encontrada na sessão.'
        ]);

        exit;
    }

    $company_id = (int)$_SESSION['user']['company_id'];

    // =====================================================
    // BUSCAR FATURAS
    // traz TODAS independentemente do status
    // =====================================================

    $sql = "
        SELECT 
            i.id,
            i.company_id,

            ivs.name AS status_invoice,
            ivs.color,
            ivs.text_color,

            CONCAT(
                YEAR(i.issue_date),
                '/',
                i.id
            ) AS codigo,

            COALESCE(c.name, 'Sem cliente') AS cliente,

            i.issue_date,
            i.due_date,

            CASE 
                WHEN i.converted_total > 0
                    THEN ROUND(i.converted_total, 2)
                ELSE
                    ROUND(i.final_total, 2)
            END AS final_total,

            cr.symbol,
            cr.position,

            CONCAT(
                cr.currency,
                ' (',
                cr.iso_code,
                ')'
            ) AS currency

        FROM invoices i

        JOIN contact c
            ON c.id = i.contact_id

        JOIN currencies cr
            ON cr.iso_code = i.currency

        JOIN invoice_status ivs
            ON ivs.id = i.status

        WHERE i.company_id = :company_id

        ORDER BY i.id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // FORMATAR DATAS
    // =====================================================

    $formattedInvoices = array_map(function ($invoice) {

        try {

            if (!empty($invoice['issue_date'])) {

                $issueDate = new DateTime(
                    $invoice['issue_date']
                );

                $dueDate = clone $issueDate;

                $dueDate->modify(
                    '+' . (int)$invoice['due_date'] . ' days'
                );

                $invoice['due_date'] = $dueDate->format('Y-m-d');

                $invoice['issue_date'] = $issueDate->format('Y-m-d');
            }
        } catch (Throwable $e) {

            $invoice['due_date'] = null;
        }

        return $invoice;
    }, $invoices);

    // =====================================================
    // RESPONSE
    // =====================================================

    echo json_encode($formattedInvoices);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
