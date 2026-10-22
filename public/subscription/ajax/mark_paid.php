<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription_payments.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
subscription_payment_disable_manual_confirmation();
