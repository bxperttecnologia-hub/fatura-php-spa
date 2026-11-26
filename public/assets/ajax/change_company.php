<?php
/**
 * Troca a empresa ativa da sessão.
 * Só para empresas a que o utilizador pertence (company_has_user); os dados da empresa e o papel (role)
 * vêm da base de dados, nunca do browser — antes aceitava qualquer company_id e mantinha o role anterior.
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';   // 401 sem sessão; 403 se não pertencer à empresa
header('Content-Type: application/json; charset=utf-8');

$companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$companyId || $companyId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT chu.role, c.name, c.registration_number, c.email
       FROM company_has_user chu
       JOIN companies c ON c.id = chu.company_id
      WHERE chu.user_id = ? AND chu.company_id = ?
      LIMIT 1'
);
$stmt->execute([(int)$_SESSION['user']['id'], $companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$company) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem acesso a esta empresa.']);
    exit;
}

$_SESSION['user']['company_id'] = $companyId;
$_SESSION['user']['role'] = $company['role'];
$_SESSION['user']['name_company'] = $company['name'];
$_SESSION['user']['registration_number'] = $company['registration_number'];
$_SESSION['user']['email_company'] = $company['email'];

echo json_encode(['success' => true, 'company' => $company['name']]);
