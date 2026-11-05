<?php

require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/document_helper.php';
require_once __DIR__ . '/../../../app/helpers/agt_receipt.php';

header('Content-Type: application/json');

$invoice_id     = (int)($_POST['invoice_id'] ?? 0);
$serie          = trim($_POST['serie'] ?? '');
$amount         = round((float)($_POST['amount'] ?? 0), 2);
$pay_date       = trim($_POST['pay_date'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');
$notes          = trim($_POST['notes'] ?? '');
$companyId      = (int)($_SESSION['user']['company_id'] ?? 0);

if ($pay_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pay_date)) {
  $pay_date = date('Y-m-d');
}

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
            reference,
            document_type,
            issue_date,
            final_total,
            paid_total,
            company_id,
            status,
            agt_status
        FROM invoices
        WHERE id = ? AND company_id = ?
        FOR UPDATE
    ");

  $st->execute([$invoice_id, $companyId]);

  $invoice = $st->fetch(PDO::FETCH_ASSOC);

  if (!$invoice) {
    throw new Exception('Fatura não encontrada.', 404);
  }

  $statusStmt = $pdo->prepare('SELECT name FROM invoice_status WHERE id = ? LIMIT 1');
  $statusStmt->execute([$invoice['status']]);
  if (str_starts_with(strtolower(trim((string)$statusStmt->fetchColumn())), 'cancel')) {
    throw new Exception('Não é possível registar pagamentos numa fatura cancelada.', 422);
  }

  $finalTotal = round((float)$invoice['final_total'], 2);
  $paidTotal  = round((float)$invoice['paid_total'], 2);

  // Notas de crédito (parciais) já emitidas reduzem o valor em dívida.
  $creditedStmt = $pdo->prepare('SELECT COALESCE(SUM(final_total), 0) FROM credit_notes WHERE invoice_id = ?');
  $creditedStmt->execute([$invoice_id]);
  $creditedTotal = round((float)$creditedStmt->fetchColumn(), 2);

  // Notas de débito somam ao valor em dívida.
  $debitedStmt = $pdo->prepare('SELECT COALESCE(SUM(final_total), 0) FROM debit_notes WHERE invoice_id = ?');
  $debitedStmt->execute([$invoice_id]);
  $debitedTotal = round((float)$debitedStmt->fetchColumn(), 2);

  $currentPending = round(
    max(0, $finalTotal + $debitedTotal - $paidTotal - $creditedTotal),
    2
  );

  // ======================================================
  // VALIDAR VALOR
  // ======================================================

  if ($amount > $currentPending) {
    throw new Exception(
      "O valor pago não pode ser superior ao saldo pendente ({$currentPending}).",
      422
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
    max(0, $finalTotal + $debitedTotal - $newPaidTotal - $creditedTotal),
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
  // Fatura com numeração AGT → recibo RC da série AGT ("RC <série>/<n>"),
  // que depois é enviado à AGT. Senão, numeração interna (legado).
  // ======================================================

  $agtReceipt = agt_receipt_required($pdo, $invoice);
  $agtSeriesId = null;

  if ($agtReceipt) {
    if ($invoice['agt_status'] !== 'valid') {
      throw new Exception(
        $invoice['agt_status'] === 'sent'
          ? 'A fatura ainda está a aguardar validação da AGT. Tente o recibo daqui a pouco.'
          : 'A fatura não foi validada pela AGT; o recibo só pode ser emitido sobre uma fatura válida.',
        422
      );
    }
    if (strtotime($pay_date) < strtotime(substr((string)$invoice['issue_date'], 0, 10)) || strtotime($pay_date) > time()) {
      throw new Exception('A data do pagamento tem de estar entre a data da fatura e hoje.', 422);
    }
    $rc = agt_receipt_next_number($pdo, $companyId);
    $serie = $rc['series_code'];
    $number = $rc['number'];
    $reference = $rc['reference'];
    $agtSeriesId = $rc['series_id'];
  } else {
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
  }

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
            notes,
            agt_series_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
    $notes,
    $agtSeriesId
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

  // Envio à AGT depois do commit: o recibo fica registado mesmo que a AGT falhe
  // (pode reenviar-se depois); o estado fica em receipts.agt_*.
  $agt = $agtReceipt ? agt_submit_receipt($pdo, $receipt_id) : null;

  echo json_encode([
    'success' => true,
    'receipt_id' => $receipt_id,
    'number' => $number,
    'reference' => $reference,
    'amount_paid' => $amount,
    'paid_total' => $newPaidTotal,
    'pending_amount' => $newPendingAmount,
    'status' => $status,
    'agt' => $agt
  ]);
} catch (Throwable $e) {

  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(in_array($e->getCode(), [404, 422], true) ? $e->getCode() : 500);


  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
