<?php
declare(strict_types=1);

require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

try {
    if (!isset($_SESSION['user']['company_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Empresa não encontrada na sessão.']);
        exit;
    }
    $companyId = (int)$_SESSION['user']['company_id'];

    $stmt = $pdo->prepare("
        SELECT
            dn.id, dn.invoice_id, dn.serie, dn.number, dn.issue_date,
            dn.reason, dn.currency, dn.subtotal_without_tax, dn.total_tax,
            dn.final_total, dn.created_at,
            c.name AS client_name
        FROM debit_notes dn
        JOIN contact c ON c.id = dn.contact_id
        WHERE dn.company_id = :company_id
        ORDER BY dn.id DESC
    ");
    $stmt->execute([':company_id' => $companyId]);

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
