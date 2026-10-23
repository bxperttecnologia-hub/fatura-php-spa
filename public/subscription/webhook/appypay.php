<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription_payments.php';

subscription_payment_handle_webhook($pdo);
