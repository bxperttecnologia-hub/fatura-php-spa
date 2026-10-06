<?php
require_once __DIR__ . '../../../../app/config/db.php';
header('Content-Type: application/json');

try {

    $sql = "SELECT 
                i.id,
                i.reference,
                c.name as client_name,
                i.final_total,
                i.issue_date,
                COALESCE(SUM(r.amount_paid),0) as paid_total,
                (i.final_total - COALESCE(SUM(r.amount_paid),0)) as saldo
            FROM invoices i
            JOIN contact c ON c.id = i.contact_id
            LEFT JOIN receipts r ON r.invoice_id = i.id
            GROUP BY i.id
            ORDER BY i.issue_date DESC
            LIMIT 10";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // calcular status direto no backend
    foreach ($data as &$inv) {
        if ($inv['paid_total'] <= 0) {
            $inv['status'] = 'pendente';
        } elseif ($inv['saldo'] <= 0.01) {
            $inv['status'] = 'pago';
        } else {
            $inv['status'] = 'parcial';
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
