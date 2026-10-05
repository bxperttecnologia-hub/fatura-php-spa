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

    $company_id = (int) $_SESSION['user']['company_id'];

    // =====================================================
    // CONSULTA
    // =====================================================

    $sql = "
        SELECT 
            i.id,
            i.reference,
            c.name AS client_name,
            i.final_total,
            i.issue_date,
            COALESCE(SUM(r.amount_paid), 0) AS paid_total,
            (
                i.final_total - COALESCE(SUM(r.amount_paid), 0)
            ) AS saldo

        FROM invoices i

        INNER JOIN contact c 
            ON c.id = i.contact_id

        LEFT JOIN receipts r 
            ON r.invoice_id = i.id

        WHERE i.company_id = :company_id

        GROUP BY 
            i.id,
            i.reference,
            c.name,
            i.final_total,
            i.issue_date

        ORDER BY i.issue_date DESC          

        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // CALCULAR STATUS
    // =====================================================

    foreach ($data as &$inv) {

        $paid_total = (float) $inv['paid_total'];
        $saldo      = (float) $inv['saldo'];

        if ($paid_total <= 0) {

            $inv['status'] = 'pendente';
        } elseif ($saldo <= 0.01) {

            $inv['status'] = 'pago';
        } else {

            $inv['status'] = 'parcial';
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados.',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
