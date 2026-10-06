<?php
require_once '../../../app/config/db.php';

session_start();
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método inválido.');
    }

    if (!isset($_SESSION['user']['id'])) {
        throw new Exception('Usuário não autenticado.');
    }

    $company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
    $email = trim((string)($_GET['email'] ?? ''));

    if (!$company_id) {
        throw new Exception('company_id é obrigatório.');
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido.');
    }

    $requester_id = (int)$_SESSION['user']['id'];
    $stmt = $pdo->prepare("SELECT role FROM company_has_user WHERE user_id = :user_id AND company_id = :company_id");
    $stmt->execute(['user_id' => $requester_id, 'company_id' => $company_id]);
    $requester_role = $stmt->fetchColumn();
    if ($requester_role !== 'owner' && $requester_role !== 'admin') {
        throw new Exception('Acesso negado.');
    }

    // Busca usuário por email
    $stmt = $pdo->prepare('SELECT id, name, username, email, is_active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        echo json_encode(['success' => true, 'exists' => false]);
        exit;
    }

    $user_id = (int)$u['id'];

    // Verifica se já está vinculado na empresa
    $stmt = $pdo->prepare('SELECT role FROM company_has_user WHERE company_id = :company_id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['company_id' => $company_id, 'user_id' => $user_id]);
    $company_role = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'exists' => true,
        'already_linked' => $company_role ? true : false,
        'data' => [
            'id' => $user_id,
            'name' => $u['name'],
            'username' => $u['username'],
            'email' => $u['email'],
            'is_active' => (int)$u['is_active'],
            'role_in_company' => $company_role ?: null,
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
