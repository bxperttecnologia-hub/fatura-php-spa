<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
header('Content-Type: application/json');
session_start();

$company_id = (int)($_GET['company_id'] ?? ($_SESSION['user']['company_id'] ?? 0));
if (!$company_id) {
    echo json_encode(['success' => false, 'message' => 'Empresa inválida']);
    exit;
}

$c = subscription_get_company($pdo, $company_id);
$plans = subscription_plans();
$planCode = $c['plan_code'] ?? 'BXPERT_BAZA';
$plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
$usage = subscription_usage($pdo, $company_id);

$daysLeft = subscription_days_left($c['plan_expires_at'] ?? null);

echo json_encode([
    'success' => true,
    'company_id' => $company_id,
    'plan_code' => $planCode,
    'plan_name' => $plan['name'],
    'plan_expires_at' => $c['plan_expires_at'] ?? null,
    'days_left' => $daysLeft,
    'limits' => [
        'invoice_limit_month' => $plan['invoice_limit_month'],
        'user_limit' => $plan['user_limit'],
        'rh_employee_limit' => $plan['rh_employee_limit'],
        'stock_item_limit' => $plan['stock_item_limit'],
    ],
    'usage' => $usage,
]);
