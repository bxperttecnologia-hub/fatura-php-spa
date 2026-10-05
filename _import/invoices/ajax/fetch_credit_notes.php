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
    // BUSCAR NOTAS DE CRÉDITO
    // traz TODAS independentemente do status
    // =====================================================

    $sql = "
SELECT 
        c.id,
        c.invoice_id,
        c.company_id,
        c.contact_id,
        c.currency,
        c.created_at,
        c.final_total,
        c.reason,
        c.retention,
        c.retention_value,
        c.subtotal_without_tax,
        c.total_discount,
        c.total_tax,
        c.total_sum,
        c.user_id,
        c.issue_date
        FROM credit_notes AS c
        INNER JOIN invoices AS i ON i.id = c.invoice_id
        WHERE i.reference IS NOT NULL
          AND c.company_id = :company_id
        ORDER BY c.id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $creditNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // FORMATAR DATAS
    // =====================================================

    $formattedCreditNotes = array_map(function ($creditNote) {

        try {

            if (!empty($creditNote['issue_date'])) {

                $issueDate = new DateTime(
                    $creditNote['issue_date']
                );

                $creditNote['issue_date'] = $issueDate->format('Y-m-d');
            }
        } catch (Throwable $e) {

            $creditNote['issue_date'] = null;
        }

        return $creditNote;
    }, $creditNotes);

    // =====================================================
    // RESPONSE
    // =====================================================

    echo json_encode([
        'success' => true,
        'data' => $formattedCreditNotes
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
