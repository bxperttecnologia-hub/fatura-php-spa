<?php
require_once '../../../config/db.php';
require_once '../../../helpers/subscription_payments.php';

subscription_payment_list_orders($pdo, $_GET);
