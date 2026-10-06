<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
session_start();
header('Content-Type: application/json');

$company_id = (int)($_POST['company_id'] ?? 0);
if (!$company_id) {
  echo json_encode(['success' => false, 'message' => 'Empresa inválida']);
  exit;
}

// Só owner/admin pode
$requester_id = (int)($_SESSION['user']['id'] ?? 0);
$stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1");
$stmt->execute([$requester_id, $company_id]);
$role = $stmt->fetchColumn();
if (!$role) {
  echo json_encode(['success' => false, 'message' => 'Acesso negado']);
  exit;
}

$c = subscription_get_company($pdo, $company_id);
$planCode = $c['plan_code'] ?? 'BXPERT_BAZA';
$plans = subscription_plans();
$plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
$amount = (float)($plan['price_quarter'] ?? 0);

$stmt = $pdo->prepare("INSERT INTO subscription_orders (company_id, plan_code, period_months, amount, status) VALUES (?, ?, 3, ?, 'pending')");
$stmt->execute([$company_id, $planCode, $amount]);

echo json_encode(['success' => true, 'order_id' => (int)$pdo->lastInsertId()]);
