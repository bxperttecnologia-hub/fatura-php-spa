<?php

require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/agt_receipt.php';

header('Content-Type: application/json');

$invoiceId = (int)($_GET['invoice_id'] ?? 0);

if ($invoiceId <= 0) {
  echo json_encode([
    'success' => false,
    'message' => 'invoice_id é obrigatório'
  ]);
  exit;
}

try {

  $sql = "
        SELECT
            r.id,
            r.invoice_id,
            r.reference,
            r.serie,
            r.number,
            r.pay_date,
            r.amount_paid,
            r.payment_method,
            r.notes,
            r.created_at,
            r.agt_status,
            r.agt_request_id,
            r.agt_errors,
            r.agt_sent_at,
            r.agt_checked_at
        FROM receipts r
        JOIN invoices i ON i.id = r.invoice_id
        WHERE r.invoice_id = :id AND i.company_id = :company
        ORDER BY r.id DESC
    ";

  $st = $pdo->prepare($sql);

  $st->execute([
    ':id' => $invoiceId,
    ':company' => (int)($_SESSION['user']['company_id'] ?? 0),
  ]);

  $receipts = array_map(static function (array $r): array {
    $r['agt'] = agt_parse_document_no($r['reference']) ? agt_receipt_state($r) : null;
    unset($r['agt_request_id'], $r['agt_errors'], $r['agt_sent_at'], $r['agt_checked_at']);
    return $r;
  }, $st->fetchAll(PDO::FETCH_ASSOC));

  echo json_encode([
    'success' => true,
    'data' => $receipts
  ]);
} catch (Throwable $e) {

  http_response_code(500);

  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}
