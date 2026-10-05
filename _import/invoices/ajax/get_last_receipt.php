<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');
session_start();

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
            id,
            invoice_id,
            reference,
            serie,
            number,
            pay_date,
            amount_paid,
            payment_method,
            notes,
            created_at
        FROM receipts
        WHERE invoice_id = :id
        ORDER BY id DESC
    ";

  $st = $pdo->prepare($sql);

  $st->execute([
    ':id' => $invoiceId
  ]);

  $receipts = $st->fetchAll(PDO::FETCH_ASSOC);

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
