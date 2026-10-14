<?php
require_once '../../../app/config/db.php';
require_once '../../../app/helpers/subscription.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function create_company_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    create_company_response(405, ['success' => false, 'message' => 'Método não permitido.']);
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
$activeCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($userId <= 0 || $activeCompanyId <= 0) {
    create_company_response(401, ['success' => false, 'message' => 'Sessão inválida. Inicie sessão novamente.']);
}

$csrfToken = (string)($_SESSION['csrf'] ?? '');
$sentToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrfToken === '' || !hash_equals($csrfToken, $sentToken)) {
    create_company_response(403, ['success' => false, 'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.']);
}

$name = trim((string)($_POST['name'] ?? ''));
$registrationNumber = strtoupper(trim((string)($_POST['registration_number'] ?? '')));
if (mb_strlen($name) < 2 || mb_strlen($name) > 255) {
    create_company_response(422, ['success' => false, 'message' => 'O nome da empresa deve ter entre 2 e 255 caracteres.']);
}
if (!preg_match('/^[A-Z0-9]{5,25}$/', $registrationNumber)) {
    create_company_response(422, ['success' => false, 'message' => 'Indique um NIF válido (5 a 25 letras ou números).']);
}

try {
    $pdo->beginTransaction();

    // Serializa inclusões do mesmo utilizador para impedir ultrapassar o limite com pedidos simultâneos.
    $userLock = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $userLock->execute([$userId]);
    if (!$userLock->fetchColumn()) {
        $pdo->rollBack();
        create_company_response(401, ['success' => false, 'message' => 'Utilizador não encontrado.']);
    }

    $activeStmt = $pdo->prepare(
        "SELECT c.id, c.phone_ddi, c.phone, c.plan_code, c.plan_started_at, c.plan_expires_at, c.plan_status, c.is_active, chu.role
           FROM companies c
           JOIN company_has_user chu ON chu.company_id = c.id
          WHERE c.id = ? AND chu.user_id = ?
          LIMIT 1"
    );
    $activeStmt->execute([$activeCompanyId, $userId]);
    $activeCompany = $activeStmt->fetch(PDO::FETCH_ASSOC);
    if (!$activeCompany || !in_array((string)$activeCompany['role'], ['3', 'owner'], true)) {
        $pdo->rollBack();
        create_company_response(403, ['success' => false, 'message' => 'Apenas o proprietário pode adicionar empresas à subscrição.']);
    }

    $expiresAt = $activeCompany['plan_expires_at'] ?? null;
    if (($activeCompany['plan_status'] ?? '') !== 'active'
        || (int)($activeCompany['is_active'] ?? 0) !== 1
        || ($expiresAt && strtotime((string)$expiresAt) < strtotime(date('Y-m-d')))) {
        $pdo->rollBack();
        create_company_response(403, ['success' => false, 'message' => 'A subscrição da empresa ativa está expirada ou inativa. Renove-a antes de adicionar empresas.']);
    }

    $planCode = (string)($activeCompany['plan_code'] ?? 'BXPERT_BAZA');
    $plans = subscription_plans();
    if (!isset($plans[$planCode])) {
        $planCode = 'BXPERT_BAZA';
    }
    $plan = $plans[$planCode];
    $companyLimit = $plan['company_limit'];
    $companyCount = subscription_owned_company_count($pdo, $userId);
    if ($companyLimit !== null && $companyCount >= (int)$companyLimit) {
        $pdo->rollBack();
        create_company_response(409, [
            'success' => false,
            'message' => sprintf(
                'O plano %s permite até %d empresa(s) e já atingiu esse limite. Faça upgrade para adicionar mais.',
                $plan['name'],
                (int)$companyLimit
            ),
            'company_count' => $companyCount,
            'company_limit' => (int)$companyLimit,
        ]);
    }

    $duplicateStmt = $pdo->prepare('SELECT 1 FROM companies WHERE registration_number = ? LIMIT 1');
    $duplicateStmt->execute([$registrationNumber]);
    if ($duplicateStmt->fetchColumn()) {
        $pdo->rollBack();
        create_company_response(409, ['success' => false, 'message' => 'Já existe uma empresa registada com este NIF.']);
    }

    $createdAt = date('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        "INSERT INTO companies
            (name, registration_number, phone_ddi, phone, website, created_at, plan_code, plan_started_at, plan_expires_at, plan_status, is_active, blocked)
         VALUES (?, ?, ?, ?, '', ?, ?, ?, ?, 'active', 1, 0)"
    );
    $insert->execute([
        $name,
        $registrationNumber,
        $activeCompany['phone_ddi'] ?? '',
        $activeCompany['phone'] ?? '',
        $createdAt,
        $planCode,
        $activeCompany['plan_started_at'] ?? date('Y-m-d'),
        $expiresAt,
    ]);
    $companyId = (int)$pdo->lastInsertId();

    $membership = $pdo->prepare(
        "INSERT INTO company_has_user (company_id, user_id, role, created_at)
         VALUES (?, ?, '3', ?)"
    );
    $membership->execute([$companyId, $userId, $createdAt]);

    $pdo->commit();
    create_company_response(201, [
        'success' => true,
        'company_id' => $companyId,
        'company_count' => $companyCount + 1,
        'company_limit' => $companyLimit,
    ]);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('assets/ajax/create_company: ' . $error->getMessage());
    if ($error instanceof PDOException && $error->getCode() === '23000') {
        create_company_response(409, ['success' => false, 'message' => 'Já existe uma empresa registada com este NIF.']);
    }
    create_company_response(500, ['success' => false, 'message' => 'Erro ao adicionar a empresa. Tente novamente.']);
}
