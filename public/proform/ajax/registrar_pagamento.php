<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');

$invoice_id     = (int)($_POST['invoice_id']     ?? 0);
$amount         = (float)($_POST['amount']       ?? 0);
$pay_date       = $_POST['pay_date']             ?? '';
$serie          = trim($_POST['serie']           ?? '');
$payment_method = trim($_POST['payment_method']  ?? '');
$notes          = trim($_POST['notes']           ?? '');

if(!$invoice_id || $amount <= 0){
  http_response_code(422);
  exit(json_encode(['error'=>'Dados inválidos.']));
}

try{
  $pdo->beginTransaction();

/* --------------------------------------------------------------------------
   1) informações da fatura + total já pago (LOCK pessimista: FOR UPDATE)
---------------------------------------------------------------------------*/
  $sql = "
    SELECT final_total,
           /* já pago */
           COALESCE((
             SELECT SUM(amount_paid)
               FROM receipts r
              WHERE r.invoice_id = i.id
           ),0) AS paid_total
      FROM invoices i
     WHERE i.id = :inv
     FOR UPDATE
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':inv'=>$invoice_id]);
  $inv = $st->fetch(PDO::FETCH_ASSOC);

  if(!$inv){
    throw new Exception('Fatura não encontrada.', 404);
  }

  $saldo = $inv['final_total'] - $inv['paid_total'];

  if($amount > $saldo + 0.01){
    throw new Exception("Valor informado ({$amount}) excede o saldo ({$saldo}).", 422);
  }

/* --------------------------------------------------------------------------
   2) gera próximo número da série
---------------------------------------------------------------------------*/
  $next = $pdo->query("
      SELECT COALESCE(MAX(number),0)+1
        FROM receipts
       WHERE serie = ".$pdo->quote($serie)
  )->fetchColumn();

/* --------------------------------------------------------------------------
   3) insere recibo
---------------------------------------------------------------------------*/
  $sql = "
    INSERT INTO receipts
      (invoice_id, serie, number, pay_date,
       amount_paid, payment_method, notes)
    VALUES
      (:inv, :se, :nu, :dt, :am, :pm, :no)
  ";
  $pdo->prepare($sql)->execute([
      ':inv'=>$invoice_id,
      ':se' =>$serie,
      ':nu' =>$next,
      ':dt' =>$pay_date,
      ':am' =>$amount,
      ':pm' =>$payment_method,
      ':no' =>$notes
  ]);

  $receipt_id = $pdo->lastInsertId();

/* --------------------------------------------------------------------------
   4) actualiza status e (opcional) coluna paid_total
---------------------------------------------------------------------------*/
  $novoSaldo = $saldo - $amount;
  $status    = $novoSaldo <= 0.009 ? 4 : 5;   // 4 = pago; 5 = parcial

  // ------ escolha a abordagem --------------------------
  $usarColunaPaid = true;   // ← MUDE para false se não criou a coluna
  // ------------------------------------------------------

  if($usarColunaPaid){
      // com coluna paid_total
      $pdo->prepare("
          UPDATE invoices
             SET paid_total = paid_total + :am,
                 status     = :st
           WHERE id = :inv
      ")->execute([':am'=>$amount, ':st'=>$status, ':inv'=>$invoice_id]);
  }else{
      // sem coluna (só status)
      $pdo->prepare("
          UPDATE invoices
             SET status = :st
           WHERE id = :inv
      ")->execute([':st'=>$status, ':inv'=>$invoice_id]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>1,'receipt_id'=>$receipt_id]);

}catch(Exception $e){
  if($pdo->inTransaction()) $pdo->rollBack();

  $code = $e->getCode();
  if($code < 100 || $code > 599) $code = 500;      // fallback
  http_response_code($code);

  echo json_encode(['error'=>$e->getMessage()]);
}
