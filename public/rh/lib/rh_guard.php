<?php
/**
 * Guarda comum dos endpoints de RH (public/rh/ajax|export|pdf/*.php).
 *  - exige sessão (401 JSON) e impede pedir dados de outra empresa (require_login.php);
 *  - exige permissão de acesso ao módulo "rh" (403 JSON).
 * Define $company_id e $rh_user_id para o endpoint.
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';

$company_id = session_company_id();
$rh_user_id = (int)($_SESSION['user']['id'] ?? 0);

if (!class_exists('Permission') || !Permission::hasAccess($pdo, $rh_user_id, $company_id, 'rh')) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Não tem permissão para o módulo de Recursos Humanos.']);
    exit;
}

if (!function_exists('rh_json')) {
    function rh_json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('rh_error_message')) {
    /** Mensagem segura para o utilizador: erros de BD vão para o log e não são mostrados. */
    function rh_error_message(Throwable $e, string $context = 'rh'): string
    {
        if ($e instanceof PDOException || !($e instanceof RuntimeException)) {
            error_log("$context: " . $e->getMessage());
            return 'Erro interno ao processar o pedido.';
        }
        return $e->getMessage();
    }
}

if (!function_exists('rh_valid_month')) {
    function rh_valid_month(?string $month): bool
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;
    }
}
