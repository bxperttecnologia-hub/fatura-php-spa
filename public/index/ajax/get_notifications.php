<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once '../../../app/helpers/notifications.php'; // onde ficam as funções acima

header('Content-Type: application/json; charset=utf-8');

session_start(); // remove esta linha se a sessão já é iniciada mais acima no bootstrap

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão inválida']);
    exit;
}

$user_id = (int) $_SESSION['user']['id'];
$only_unread  = isset($_GET['only_unread']) && $_GET['only_unread'] === '1';
$limit        = isset($_GET['limit'])  ? max(1, min(100, (int) $_GET['limit']))  : 20;
$page         = isset($_GET['page'])   ? max(1, (int) $_GET['page'])            : 1;
$offset       = ($page - 1) * $limit;

try {
    $notifications = getNotifications($pdo, $user_id, $only_unread, $limit, $offset);
    $unread_count  = countUnreadNotifications($pdo, $user_id);

    echo json_encode([
        'success'        => true,
        'notifications'  => $notifications,
        'unread_count'   => $unread_count,
        'page'           => $page,
        'limit'          => $limit,
    ]);
} catch (Exception $e) {
    error_log('Erro ao buscar notificações: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar notificações.']);
}
