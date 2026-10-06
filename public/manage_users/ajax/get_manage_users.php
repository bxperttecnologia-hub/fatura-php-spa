<?php
require_once '../../../app/config/db.php';

session_start();

header('Content-Type: application/json');

// Verificar se a sessão do usuário está ativa
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

// Receber o company_id via GET, validando se é um número válido
$company_id = isset($_GET['company_id']) ? (int) $_GET['company_id'] : null;

if (!$company_id) {
    echo json_encode(['error' => 'company_id é obrigatório']);
    exit;
}

// Verificar se o usuário tem a role 'owner' ou 'admin'
$user_id = $_SESSION['user']['id'];
$stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = :user_id AND company_id = :company_id");
$stmt->execute(['user_id' => $user_id, 'company_id' => $company_id]);
$user_role = $stmt->fetchColumn();

if ($user_role !== 'owner' && $user_role !== 'admin') {
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Listar os usuários vinculados à empresa com os campos solicitados
$stmt = $pdo->prepare("SELECT u.id, u.name, u.username, u.email, u.is_active, u.image, c.role 
                       FROM users u
                       JOIN company_has_user c ON u.id = c.user_id
                       WHERE c.company_id = :company_id");
$stmt->execute(['company_id' => $company_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sempre retorna users como array (evita quebrar DataTables)
echo json_encode([
    'success' => true,
    'users' => $users ?: []
]);
