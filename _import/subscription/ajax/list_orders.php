<?php
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/subscription.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

function out(array $p, int $code = 200): void
{
  http_response_code($code);
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

if (!$company_id) {
  out(['success' => false, 'message' => 'Empresa inválida (sessão)'], 400);
}

$requester_id = (int)($_SESSION['user']['id'] ?? 0);

$stmt = $pdo->prepare("
  SELECT role 
  FROM company_has_user 
  WHERE user_id = ? AND company_id = ? 
  LIMIT 1
");
$stmt->execute([$requester_id, $company_id]);

$role = $stmt->fetchColumn();

if (!$role) {
  out(['success' => false, 'message' => 'Acesso negado'], 403);
}

/**
 * =========================
 * TOTAL COUNT (PAGINATION)
 * =========================
 */
$countStmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM subscription_orders 
  WHERE company_id = ?
");
$countStmt->execute([$company_id]);
$total = (int)$countStmt->fetchColumn();

/**
 * =========================
 * DATA QUERY (LIMIT SAFE)
 * =========================
 */
$st = $pdo->prepare("
  SELECT 
    id,
    plan_code,
    period_months,
    amount,
    status,
    gateway,
    payment_method,
    merchant_transaction_id,
    gateway_charge_id,
    gateway_status,
    payment_ref,
    created_at,
    paid_at
  FROM subscription_orders 
  WHERE company_id = ?
  ORDER BY created_at DESC
  LIMIT $limit OFFSET $offset
");

$st->execute([$company_id]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/**
 * =========================
 * RESPONSE
 * =========================
 */
echo json_encode([
  'success' => true,
  'orders' => $rows,
  'page' => $page,
  'limit' => $limit,
  'total' => $total,
  'total_pages' => (int)ceil($total / $limit)
], JSON_UNESCAPED_UNICODE);
