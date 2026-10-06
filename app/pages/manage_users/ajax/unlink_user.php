<?php
require_once '../../../app/config/db.php';

session_start();
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.');
    }

    if (!isset($_SESSION['user']['id'])) {
        throw new Exception('Usuário não autenticado.');
    }

    $company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    $target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if (!$company_id || !$target_user_id) {
        throw new Exception('Parâmetros inválidos.');
    }

    $requester_id = (int)$_SESSION['user']['id'];

    // requester precisa ser owner/admin
    $stmt = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = :uid AND company_id = :cid');
    $stmt->execute(['uid' => $requester_id, 'cid' => $company_id]);
    $requester_role = $stmt->fetchColumn();
    if ($requester_role !== 'owner' && $requester_role !== 'admin') {
        throw new Exception('Acesso negado.');
    }

    // role do target
    $stmt = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = :uid AND company_id = :cid');
    $stmt->execute(['uid' => $target_user_id, 'cid' => $company_id]);
    $target_role = $stmt->fetchColumn();
    if (!$target_role) {
        throw new Exception('Usuário não está vinculado a esta empresa.');
    }

    // admin não pode remover owner
    if ($requester_role === 'admin' && $target_role === 'owner') {
        throw new Exception('Administrador não pode remover um owner.');
    }

    $stmt = $pdo->prepare('DELETE FROM company_has_user WHERE user_id = :uid AND company_id = :cid');
    $stmt->execute(['uid' => $target_user_id, 'cid' => $company_id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
