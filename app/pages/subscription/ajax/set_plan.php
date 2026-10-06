<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
session_start();
header('Content-Type: application/json');

$company_id = (int)($_POST['company_id'] ?? 0);
$plan_code = (string)($_POST['plan_code'] ?? '');
if (!$company_id || $plan_code === '') {
  echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
  exit;
}

$requester_id = (int)($_SESSION['user']['id'] ?? 0);
$stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1");
$stmt->execute([$requester_id, $company_id]);
$role = $stmt->fetchColumn();
if (!$role) {
  echo json_encode(['success' => false, 'message' => 'Acesso negado']);
  exit;
}

$plans = subscription_plans();
if (!isset($plans[$plan_code])) {
  echo json_encode(['success' => false, 'message' => 'Plano inválido']);
  exit;
}

$stmt = $pdo->prepare("UPDATE companies SET plan_code = ? WHERE id = ?");
$ok = $stmt->execute([$plan_code, $company_id]);

echo json_encode(['success' => (bool)$ok]);
