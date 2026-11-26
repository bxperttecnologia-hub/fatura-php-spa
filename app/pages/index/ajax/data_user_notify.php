<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/notification_service.php';
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
    // 1) Gera (se necessário) a notificação de "perfil incompleto" — no máximo uma por dia.
    $stmt = $pdo->prepare('SELECT country, address, city FROM users WHERE id = :user_id');
    $stmt->execute(['user_id' => $user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $missing = [];
    foreach (['country' => 'país', 'address' => 'endereço', 'city' => 'cidade'] as $column => $label) {
        if (trim((string) ($profile[$column] ?? '')) === '') {
            $missing[] = $label;
        }
    }
    if ($missing) {
        bx_notify($pdo, [
            'user_id'    => $user_id,
            'type'       => 'missing_profile_data',
            'title'      => 'Complete o seu perfil',
            'message'    => 'Detetámos que os seguintes dados estão em falta: ' . implode(', ', $missing)
                . '. Por favor, atualize o seu perfil.',
            'dedupe_key' => 'missing_profile_data:' . $user_id . ':' . date('Y-m-d'),
        ]);
    }

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
