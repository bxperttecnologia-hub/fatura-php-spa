<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once __DIR__ . '/export_helpers.php';

header('Content-Type: application/json; charset=utf-8');

session_start(); // remove esta linha se a sessão já é iniciada mais acima no bootstrap

if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão inválida']);
    exit;
}

$user_id = (int) $_SESSION['user']['id'];

try {
    // 1) Gera (se necessário) a notificação de "perfil incompleto".
    //    A condição NOT EXISTS já evita duplicados no mesmo dia.
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, created_at)
        SELECT
            u.id,
            'missing_profile_data',
            'Complete o seu perfil',
            CONCAT(
                'Detetámos que os seguintes dados estão em falta: ',
                TRIM(BOTH ', ' FROM CONCAT(
                    IF(u.country IS NULL OR u.country = '', 'país, ', ''),
                    IF(u.address IS NULL OR u.address = '', 'endereço, ', ''),
                    IF(u.city IS NULL OR u.city = '', 'cidade, ', '')
                )),
                '. Por favor, atualize o seu perfil.'
            ),
            NOW()
        FROM users u
        WHERE u.id = :user_id
          AND (
                (u.country IS NULL OR u.country = '')
                OR (u.address IS NULL OR u.address = '')
                OR (u.city IS NULL OR u.city = '')
              )
          AND NOT EXISTS (
                SELECT 1
                FROM notifications n
                WHERE n.user_id = u.id
                  AND n.type = 'missing_profile_data'
                  AND DATE(n.created_at) = CURDATE()
              )
    ");
    $stmt->execute(['user_id' => $user_id]);

    // 2) Vai buscar as notificações por ler (mais recentes primeiro).
    //    Ajusta os nomes de colunas (is_read / read_at) ao teu schema real.
    $stmt = $pdo->prepare("
        SELECT id, type, title, message, created_at
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute(['user_id' => $user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'       => true,
        'count'         => count($notifications),
        'notifications' => $notifications,
    ]);
} catch (Throwable $e) {
    error_log('data_user_notify.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar notificações']);
}
