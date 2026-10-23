<?php
/**
 * Guarda para endpoints AJAX legados (public/<modulo>/ajax/*.php) que não podem ser chamados sem sessão.
 * Uso, na primeira linha útil do endpoint:
 *     require_once __DIR__ . '/../../../app/helpers/require_login.php';
 *
 * - Sem utilizador na sessão → 401 JSON (a SPA leva ao /login).
 * - Se o pedido trouxer company_id (GET/POST) de uma empresa a que o utilizador não pertence → 403.
 *   Os endpoints continuam a usar o company_id que já usavam; isto só impede pedir dados de outra empresa.
 */
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

(function () use ($pdo) {
    $deny = function (int $status, string $message): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'         => false,
            'session_expired' => $status === 401,
            'message'         => $message,
            'error'           => $message,
            'redirect'        => $status === 401 ? '/login' : null,
        ]);
        exit;
    };

    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        $deny(401, 'Sessão expirada. Inicie sessão novamente.');
    }

    $requested = $_GET['company_id'] ?? $_POST['company_id'] ?? null;
    if ($requested !== null && $requested !== '' && (int)$requested !== (int)($_SESSION['user']['company_id'] ?? 0)) {
        $stmt = $pdo->prepare('SELECT 1 FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
        $stmt->execute([$userId, (int)$requested]);
        if (!$stmt->fetchColumn()) {
            $deny(403, 'Sem acesso a esta empresa.');
        }
    }
})();

/** Empresa ativa da sessão — usar no WHERE de qualquer consulta por id (evita ler/alterar registos de outra empresa). */
function session_company_id(): int
{
    return (int)($_SESSION['user']['company_id'] ?? 0);
}

/**
 * Termina com 404 JSON se o registo não pertencer à empresa ativa da sessão.
 * Usar nos endpoints que alteram/leem um registo pelo id recebido do browser.
 */
function require_owned(string $table, $id): void
{
    global $pdo;
    $allowed = ['invoices', 'proformas', 'contact', 'items', 'stocks', 'employees', 'attendance'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException("require_owned: tabela não suportada: $table");
    }
    $stmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE id = ? AND company_id = ? LIMIT 1");
    $stmt->execute([(int)$id, session_company_id()]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Registo não encontrado.', 'error' => 'Registo não encontrado.']);
        exit;
    }
}
