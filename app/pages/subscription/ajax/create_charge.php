<?php
require_once '../../../config/db.php';
require_once '../../../helpers/subscription_payments.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  subscription_payment_out(['success' => false, 'message' => 'Método não permitido.'], 405);
}

subscription_payment_create_charge($pdo, $_POST);
