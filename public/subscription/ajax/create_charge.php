<?php
define('SUBSCRIPTION_CHARGE_API', true);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription_payments.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  subscription_payment_out(['success' => false, 'message' => 'Método não permitido.'], 405);
}

try {
  subscription_payment_create_charge($pdo, $_POST);
} catch (Throwable $e) {
  subscription_payment_log_charge_error(null, null, 'UNHANDLED_CHARGE_FAILURE', 503, $e);
  subscription_payment_out([
    'success' => false,
    'code' => 'CHARGE_CREATION_FAILED',
    'message' => 'Não foi possível criar a cobrança. Tente novamente mais tarde.',
  ], 503);
}
