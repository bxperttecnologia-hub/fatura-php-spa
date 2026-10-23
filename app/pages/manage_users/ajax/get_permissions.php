<?php
require_once dirname(__DIR__, 3) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    Auth::require();
    $pdo = Database::pdo();
    $requester = Auth::user();
    $companyId = filter_var($_GET['company_id'] ?? null, FILTER_VALIDATE_INT);
    $targetId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$requester || !$companyId || !$targetId) {
        throw new HttpException(400, 'Empresa e utilizador são obrigatórios.');
    }

    $requesterId = (int)$requester['id'];
    $requesterRole = Permission::requireCompanyManager($pdo, $requesterId, (int)$companyId);
    if (!Permission::hasAccess($pdo, $requesterId, (int)$companyId, 'users')) {
        throw new HttpException(403, 'Não tem permissão para gerir utilizadores.');
    }

    $permissions = Permission::userPermissions($pdo, (int)$targetId, (int)$companyId);
    $targetRoleQuery = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
    $targetRoleQuery->execute([(int)$targetId, (int)$companyId]);
    $targetRole = (string)$targetRoleQuery->fetchColumn();
    if ($requesterRole === 'admin' && $targetRole === 'owner') {
        throw new HttpException(403, 'Um administrador não pode gerir as permissões do proprietário.');
    }

    echo json_encode([
        'success' => true,
        'role' => $targetRole,
        'modules' => Permission::modules(),
        'permissions' => $permissions,
        'locked' => $targetRole === 'owner',
    ], JSON_UNESCAPED_UNICODE);
} catch (HttpException $e) {
    http_response_code($e->status);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar as permissões.'], JSON_UNESCAPED_UNICODE);
}