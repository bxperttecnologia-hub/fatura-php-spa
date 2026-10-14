<?php
ob_start();
require_once '../../../app/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function responder_empresa(int $status, array $data): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_empresa(405, ['success' => false, 'message' => 'Método não permitido.']);
}
if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    responder_empresa(403, ['success' => false, 'message' => 'A página expirou. Recarregue e tente novamente.']);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$name = trim((string) ($_POST['company_name'] ?? ''));
$nif = strtoupper(trim((string) ($_POST['registration_number'] ?? '')));

if ($userId <= 0) {
    responder_empresa(401, ['success' => false, 'message' => 'A verificação do telefone expirou. Inicie o registo novamente.']);
}
if (mb_strlen($name) < 2 || mb_strlen($name) > 255) {
    responder_empresa(422, ['success' => false, 'message' => 'O nome da empresa deve ter entre 2 e 255 caracteres.']);
}
if (!preg_match('/^[A-Z0-9]{5,25}$/', $nif)) {
    responder_empresa(422, ['success' => false, 'message' => 'Indique um NIF válido (5 a 25 letras ou números).']);
}

try {
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare('SELECT phone FROM users WHERE id = ? AND phone_verified_at IS NOT NULL AND must_change_password = 1 FOR UPDATE');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $pdo->rollBack();
        responder_empresa(403, ['success' => false, 'message' => 'É necessário verificar o telefone antes de adicionar a empresa.']);
    }

    $membershipStmt = $pdo->prepare('SELECT 1 FROM company_has_user WHERE user_id = ? LIMIT 1');
    $membershipStmt->execute([$userId]);
    if ($membershipStmt->fetchColumn()) {
        $pdo->rollBack();
        responder_empresa(409, ['success' => false, 'message' => 'Este utilizador já tem uma empresa associada.']);
    }

    $duplicateStmt = $pdo->prepare('SELECT 1 FROM companies WHERE registration_number = ? LIMIT 1');
    $duplicateStmt->execute([$nif]);
    if ($duplicateStmt->fetchColumn()) {
        $pdo->rollBack();
        responder_empresa(409, ['success' => false, 'message' => 'Já existe uma empresa registada com este NIF.']);
    }

    $phone = (string) $user['phone'];
    $prefixes = ['+244', '+351', '+258', '+264', '+27', '+55', '+44', '+1'];
    $phoneDdi = '';
    foreach ($prefixes as $prefix) {
        if (strpos($phone, $prefix) === 0) {
            $phoneDdi = $prefix;
            break;
        }
    }
    if ($phoneDdi === '') {
        $pdo->rollBack();
        responder_empresa(422, ['success' => false, 'message' => 'Não foi possível identificar o indicativo do telefone verificado.']);
    }

    $now = date('Y-m-d H:i:s');
    $planStartedAt = date('Y-m-d');
    $planExpiresAt = date('Y-m-d', strtotime('+30 days'));
    $pdo->prepare(
        "INSERT INTO companies (name, registration_number, phone_ddi, phone, website, created_at, plan_code, plan_started_at, plan_expires_at, plan_status, is_active, blocked)
         VALUES (?, ?, ?, ?, '', ?, 'BXPERT_BAZA', ?, ?, 'active', 1, 0)"
    )->execute([$name, $nif, $phoneDdi, $phone, $now, $planStartedAt, $planExpiresAt]);
    $companyId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO company_has_user (company_id, user_id, role, created_at) VALUES (?, ?, 3, ?)')->execute([$companyId, $userId, $now]);
    $pdo->commit();

    responder_empresa(200, ['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('register create_company: ' . $e->getMessage());
    if ($e instanceof PDOException && $e->getCode() === '23000') {
        responder_empresa(409, ['success' => false, 'message' => 'Já existe uma empresa registada com este NIF.']);
    }
    responder_empresa(500, ['success' => false, 'message' => 'Erro ao criar a empresa. Tente novamente.']);
}
