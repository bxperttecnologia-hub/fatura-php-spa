<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription_payments.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  subscription_payment_out(['success' => false, 'message' => 'Método não permitido.'], 405);
}

subscription_payment_sync_orders($pdo);
