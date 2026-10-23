<?php
require_once '../../../config/db.php';
require_once '../../../helpers/subscription_payments.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
subscription_payment_disable_manual_confirmation();
