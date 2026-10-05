<?php
require_once '../../../app/config/db.php';
require '../../../vendor/autoload.php'; // Autoload do PhpSpreadsheet
require_once __DIR__ . '/export_helpers.php';

header('Content-Type: application/json; charset=utf-8');

session_start(); // remove esta linha se a sessão já é iniciada mais acima no bootstrap

if (empty($_SESSION['user']['id']) || empty($_SESSION['user']['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão inválida']);
    exit;
}

$user_id    = (int) $_SESSION['user']['id'];
$company_id = (int) $_SESSION['user']['company_id'];

// Campos obrigatórios do perfil da empresa (coluna => rótulo em PT)
const REQUIRED_COMPANY_FIELDS = [
    'country'   => 'país',
    'address'   => 'endereço',
    'city'      => 'cidade',
    'zip_code'  => 'código postal',
    'phone'     => 'telefone',
    'email'     => 'email',
];

try {
    // 1) Confirma que a empresa da sessão pertence mesmo a este utilizador
    //    (defesa extra: nunca confiar apenas no valor guardado na sessão).
    $stmt = $pdo->prepare("
        SELECT c.*
        FROM companies c
        INNER JOIN company_has_user chu ON chu.company_id = c.id
        WHERE c.id = :company_id
          AND chu.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute(['company_id' => $company_id, 'user_id' => $user_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Empresa inválida para este utilizador']);
        exit;
    }

    // 2) Verifica quais campos estão em falta.
    $missing = [];
    foreach (REQUIRED_COMPANY_FIELDS as $column => $label) {
        if (trim((string) ($company[$column] ?? '')) === '') {
            $missing[] = $label;
        }
    }

    if (!empty($missing)) {
        // Já foi notificado hoje para ESTA empresa?
        $stmt = $pdo->prepare("
            SELECT 1
            FROM notifications
            WHERE user_id = :user_id
              AND company_id = :company_id
              AND type = 'missing_company_data'
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $user_id, 'company_id' => $company_id]);

        if (!$stmt->fetch()) {
            $title   = sprintf('Complete o perfil da empresa "%s"', $company['name']);
            $message = sprintf(
                'Detetámos que os seguintes dados da empresa "%s" estão em falta: %s. Por favor, atualize o perfil da empresa.',
                $company['name'],
                implode(', ', $missing)
            );

            $insert = $pdo->prepare("
                INSERT INTO notifications (user_id, company_id, type, title, message, created_at)
                VALUES (:user_id, :company_id, 'missing_company_data', :title, :message, NOW())
            ");
            $insert->execute([
                'user_id'    => $user_id,
                'company_id' => $company_id,
                'title'      => $title,
                'message'    => $message,
            ]);
        }
    }

    // 3) Vai buscar as notificações por ler (mais recentes primeiro).
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
