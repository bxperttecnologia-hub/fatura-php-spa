<?php

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/document_helper.php';

header('Content-Type: application/json');

$invoice_id     = (int)($_POST['invoice_id'] ?? 0);
$serie          = trim($_POST['serie'] ?? '');
$amount         = round((float)($_POST['amount'] ?? 0), 2);
$pay_date       = trim($_POST['pay_date'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$notes          = trim($_POST['notes'] ?? '');

if ($invoice_id <= 0) {
  http_response_code(422);
  exit(json_encode([
    'success' => false,
    'error' => 'Fatura inválida.'
  ]));
}

if ($amount <= 0) {
  http_response_code(422);
  exit(json_encode([
    'success' => false,
    'error' => 'O valor do pagamento deve ser maior que zero.'
  ]));
}

try {

  $pdo->beginTransaction();

  // ======================================================
  // BUSCAR FATURA
  // ======================================================

  $st = $pdo->prepare("
        SELECT
            id,
            final_total,
            paid_total,
            company_id
        FROM invoices
        WHERE id = ?
        FOR UPDATE
    ");

  $st->execute([$invoice_id]);

  $invoice = $st->fetch(PDO::FETCH_ASSOC);

  if (!$invoice) {
    throw new Exception('Fatura não encontrada.');
  }

  $finalTotal = round((float)$invoice['final_total'], 2);
  $paidTotal  = round((float)$invoice['paid_total'], 2);

  $currentPending = round(
    max(0, $finalTotal - $paidTotal),
    2
  );

  // ======================================================
  // VALIDAR VALOR
  // ======================================================

  if ($amount > $currentPending) {
    throw new Exception(
      "O valor pago não pode ser superior ao saldo pendente ({$currentPending})."
    );
  }

  // ======================================================
  // CALCULAR NOVOS VALORES
  // ======================================================

  $newPaidTotal = round(
    $paidTotal + $amount,
    2
  );

  $newPendingAmount = round(
    max(0, $finalTotal - $newPaidTotal),
    2
  );

  // ======================================================
  // STATUS
  // ======================================================

  // 4 = Pago
  // 5 = Parcialmente Pago

  $status = ($newPendingAmount <= 0)
    ? 4
    : 5;

  // ======================================================
  // GERAR NÚMERO DO RECIBO
  // ======================================================

  $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(number), 0) + 1
        FROM receipts
        FOR UPDATE
    ");

  $stmt->execute();

  $number = (int)$stmt->fetchColumn();

  $reference = 'FR-' . str_pad(
    $number,
    6,
    '0',
    STR_PAD_LEFT
  );

  // ======================================================
  // INSERIR RECIBO
  // ======================================================

  $stmt = $pdo->prepare("
        INSERT INTO receipts (
            invoice_id,
            number,
            reference,
            serie,
            pay_date,
            amount_paid,
            pending_amount,
            payment_method,
            notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

  $stmt->execute([
    $invoice_id,
    $number,
    $reference,
    $serie,
    $pay_date,
    $amount,
    $newPendingAmount,
    $payment_method,
    $notes
  ]);

  $receipt_id = (int)$pdo->lastInsertId();

  // ======================================================
  // ATUALIZAR FATURA
  // ======================================================

  $upd = $pdo->prepare("
        UPDATE invoices
        SET
            paid_total = ?,
            status = ?
        WHERE id = ?
    ");

  $upd->execute([
    $newPaidTotal,
    $status,
    $invoice_id
  ]);

  // ======================================================
  // COMMIT
  // ======================================================

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'receipt_id' => $receipt_id,
    'number' => $number,
    'reference' => $reference,
    'amount_paid' => $amount,
    'paid_total' => $newPaidTotal,
    'pending_amount' => $newPendingAmount,
    'status' => $status
  ]);
} catch (Throwable $e) {

  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(500);

  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
