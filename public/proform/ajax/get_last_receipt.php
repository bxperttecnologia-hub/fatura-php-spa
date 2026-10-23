<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php'; // exige sessão iniciada (401/403 em JSON)
require_once '../../../app/config/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
if(!$invoiceId){
  echo json_encode(['success'=>false,'message'=>'invoice_id é obrigatório']);
  exit;
}

try{
  $st = $pdo->prepare('SELECT id, serie, number, pay_date, amount_paid FROM receipts WHERE invoice_id = :id ORDER BY id DESC LIMIT 1');
  $st->execute(['id'=>$invoiceId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['success'=>true,'data'=>$r ?: null]);
}catch(Exception $e){
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
