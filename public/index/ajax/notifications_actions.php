<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);

        if ($notificationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Identificador da notificação inválido.']);
            exit;
        }

        $updated = markNotificationAsRead($pdo, $notificationId, $userId);

        echo json_encode([
            'success' => $updated,
            'message' => $updated ? 'Notificação marcada como lida.' : 'Não foi possível atualizar a notificação.',
        ]);
        exit;
    }

    if ($action === 'mark_all_read') {
        $updatedCount = markAllNotificationsAsRead($pdo, $userId);

        echo json_encode([
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => 'Todas as notificações foram marcadas como lidas.'
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    exit;
} catch (Throwable $e) {
    error_log('Erro em notifications_actions.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao processar a notificação.'
    ]);
}
