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
        r.id, 
        r.invoice_id, 
        r.serie, 
        r.reference, 
        r.number, 
        r.notes, 
        r.pay_date, 
        r.payment_method, 
        r.amount_paid, 
        r.pending_amount, 
        r.created_at AS issue_date,
        i.due_date
        FROM receipts AS r
        INNER JOIN invoices AS i ON i.id = r.invoice_id
        WHERE r.reference IS NOT NULL
          AND i.company_id = :company_id
        ORDER BY r.id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // FORMATAR DATAS
    // =====================================================

    $formattedReceipts = array_map(function ($receipt) {

        try {

            if (!empty($receipt['issue_date'])) {

                $issueDate = new DateTime(
                    $receipt['issue_date']
                );

                $dueDate = clone $issueDate;

                $dueDate->modify(
                    '+' . (int)$receipt['due_date'] . ' days'
                );

                $receipt['due_date'] = $dueDate->format('Y-m-d');

                $receipt['issue_date'] = $issueDate->format('Y-m-d');
            }
        } catch (Throwable $e) {

            $receipt['due_date'] = null;
        }

        return $receipt;
    }, $receipts);

    // =====================================================
    // RESPONSE
    // =====================================================

    echo json_encode([
        'success' => true,
        'data' => $formattedReceipts
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}