<?php

require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription_payments.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  subscription_payment_out(['success' => false, 'message' => 'Método não permitido.'], 405);
}

subscription_payment_retry_crm_order_sync($pdo, $_POST);
