<?php
require_once dirname(__DIR__, 3) . '/app/config/db.php';
require_once dirname(__DIR__, 3) . '/app/helpers/company_deletion.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function delete_company_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    delete_company_response(405, ['success' => false, 'message' => 'Método não permitido.']);
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
$activeCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
$companyId = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
if ($userId <= 0 || $activeCompanyId <= 0) {
    delete_company_response(401, ['success' => false, 'message' => 'Sessão inválida. Inicie sessão novamente.']);
}
if (!$companyId || $companyId <= 0) {
    delete_company_response(422, ['success' => false, 'message' => 'Seleccione uma empresa válida.']);
}

$sessionToken = (string)($_SESSION['csrf'] ?? '');
$requestToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
    delete_company_response(403, ['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.']);
}

try {
    $pdo->beginTransaction();

    $userLock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $userLock->execute([$userId]);
    if (!$userLock->fetchColumn()) {
        $pdo->rollBack();
        delete_company_response(401, ['success' => false, 'message' => 'Utilizador não encontrado.']);
    }

    $companyStmt = $pdo->prepare(
        "SELECT c.id, c.name
           FROM companies c
           JOIN company_has_user chu ON chu.company_id = c.id
          WHERE c.id = ? AND chu.user_id = ? AND chu.role IN ('3', 'owner')
          LIMIT 1
          FOR UPDATE"
    );
    $companyStmt->execute([$companyId, $userId]);
    $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        $pdo->rollBack();
        delete_company_response(403, ['success' => false, 'message' => 'Não tem permissão para eliminar esta empresa.']);
    }
    if ((int)$companyId === $activeCompanyId) {
        $pdo->rollBack();
        delete_company_response(409, ['success' => false, 'message' => 'Não é possível eliminar a empresa activa. Seleccione outra empresa.']);
    }

    $fallbackStmt = $pdo->prepare(
        "SELECT c.id
           FROM companies c
           JOIN company_has_user chu ON chu.company_id = c.id
           WHERE chu.user_id = ? AND c.id <> ? AND chu.role IN ('3', 'owner')
          LIMIT 1
          FOR UPDATE"
    );
    $fallbackStmt->execute([$userId, $companyId]);
    if (!$fallbackStmt->fetchColumn()) {
        $pdo->rollBack();
        delete_company_response(409, ['success' => false, 'message' => 'Não pode eliminar a sua única empresa. Adicione ou associe outra empresa antes de continuar.']);
    }

    $plan = companyDeletionPlan($pdo, (int)$companyId);
    if ($plan['clear_user_company_reference']) {
        $clearUserCompany = $pdo->prepare('UPDATE users SET company_id = NULL WHERE company_id = ?');
        $clearUserCompany->execute([$companyId]);
    }
    foreach ($plan['self_reference_updates'] as $table => $columns) {
        $assignments = array_map(
            static fn(string $column): string => companyDeletionQuote($column) . ' = NULL',
            array_keys($columns)
        );
        $update = $pdo->prepare(
            'UPDATE ' . companyDeletionQuote($table)
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . companyDeletionQuote('company_id') . ' = ?'
        );
        $update->execute([$companyId]);
    }

    foreach ($plan['delete_order'] as $table) {
        $delete = $pdo->prepare(
            'DELETE ' . companyDeletionQuote('target_row')
            . ' FROM ' . companyDeletionQuote($table) . ' AS ' . companyDeletionQuote('target_row')
            . ' WHERE ' . $plan['scopes'][$table]['sql']
        );
        $delete->execute($plan['scopes'][$table]['params']);
    }

    $deleteStmt = $pdo->prepare('DELETE FROM companies WHERE id = ?');
    $deleteStmt->execute([$companyId]);
    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException('A empresa não foi eliminada. Actualize a página e tente novamente.');
    }

    $pdo->commit();

    delete_company_response(200, [
        'success' => true,
        'company_id' => (int)$companyId,
        'company_name' => (string)$company['name'],
        'company_data_deleted' => true,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('assets/ajax/delete_company: ' . $error->getMessage());
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        delete_company_response(409, [
            'success' => false,
            'message' => 'A eliminação foi cancelada porque existem referências que não puderam ser removidas em segurança. Nenhum dado foi alterado.',
        ]);
    }
    delete_company_response(500, [
        'success' => false,
        'message' => 'Não foi possível verificar os registos ou eliminar a empresa. Nenhum dado foi removido. Tente novamente ou contacte o suporte.',
    ]);
}
