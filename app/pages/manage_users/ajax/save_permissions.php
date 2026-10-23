<?php
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    Auth::require();
    Csrf::check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new HttpException(405, 'Método inválido.');
    }

    $pdo = Database::pdo();
    $requester = Auth::user();
    $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
    $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $allowedModules = $_POST['allowed_modules'] ?? [];
    if (!$requester || !$companyId || !$targetId || !is_array($allowedModules)) {
        throw new HttpException(400, 'Dados de permissões inválidos.');
    }

    $requesterId = (int)$requester['id'];
    $requesterRole = Permission::requireCompanyManager($pdo, $requesterId, (int)$companyId);
    if (!Permission::hasAccess($pdo, $requesterId, (int)$companyId, 'users')) {
        throw new HttpException(403, 'Não tem permissão para gerir utilizadores.');
    }

    Permission::saveUserPermissions(
        $pdo,
        (int)$companyId,
        (int)$targetId,
        $allowedModules,
        $requesterId,
        $requesterRole
    );

    echo json_encode(['success' => true, 'message' => 'Permissões atualizadas.'], JSON_UNESCAPED_UNICODE);
} catch (HttpException $e) {
    http_response_code($e->status);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível guardar as permissões.'], JSON_UNESCAPED_UNICODE);
}