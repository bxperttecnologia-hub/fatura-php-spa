<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$activeCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($userId <= 0 || $activeCompanyId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$query = "SELECT c.id, c.name, c.registration_number, c.email, c.logo_url, c.is_active, c.plan_code, c.plan_expires_at FROM users u 
          JOIN company_has_user chu ON chu.user_id = u.id 
          JOIN companies c ON c.id = chu.company_id 
          WHERE u.id = :userId";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeStmt = $pdo->prepare(
    "SELECT c.plan_code, c.plan_expires_at, c.plan_started_at, c.plan_status, c.is_active, chu.role
       FROM companies c
       JOIN company_has_user chu ON chu.company_id = c.id AND chu.user_id = ?
      WHERE c.id = ?
      LIMIT 1"
);
$activeStmt->execute([$userId, $activeCompanyId]);
$activeCompany = $activeStmt->fetch(PDO::FETCH_ASSOC);
if (!$activeCompany) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso à empresa ativa negado.']);
    exit;
}

$planCode = (string)($activeCompany['plan_code'] ?? 'BXPERT_BAZA');
$plans = subscription_plans();
$plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
$companyCount = subscription_owned_company_count($pdo, $userId);
$companyLimit = $plan['company_limit'];
$canAddCompany = in_array((string)$activeCompany['role'], ['3', 'owner'], true)
    && ($companyLimit === null || $companyCount < (int)$companyLimit)
    && ($activeCompany['plan_status'] ?? '') === 'active'
    && (int)($activeCompany['is_active'] ?? 0) === 1
    && (empty($activeCompany['plan_expires_at']) || strtotime($activeCompany['plan_expires_at']) >= strtotime(date('Y-m-d')));

foreach ($empresas as &$empresa) {
    $empresa['company_count'] = $companyCount;
    $empresa['company_limit'] = $companyLimit;
    $empresa['plan_name'] = $plan['name'];
    $empresa['can_add_company'] = $canAddCompany;
}
unset($empresa);

echo json_encode($empresas);
?>
