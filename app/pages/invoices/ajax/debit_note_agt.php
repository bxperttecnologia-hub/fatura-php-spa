<?php
declare(strict_types=1);

// GET  ?action=status&id=…  → consulta (e atualiza) o estado na AGT
// POST action=send id=…     → (re)envia manualmente; exige CSRF
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function out(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
if ($companyId <= 0) {
    out(401, ['success' => false, 'message' => 'Sessão inválida.']);
}
$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) {
    out(422, ['success' => false, 'message' => 'Nota de débito inválida.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        out(403, ['success' => false, 'message' => 'Token CSRF inválido.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    out(405, ['success' => false, 'message' => 'Método inválido.']);
}

$stmt = $pdo->prepare('SELECT 1 FROM debit_notes WHERE id = ? AND company_id = ? LIMIT 1');
$stmt->execute([$id, $companyId]);
if (!$stmt->fetchColumn()) {
    out(404, ['success' => false, 'message' => 'Nota de débito não encontrada.']);
}

out(501, [
    'success' => false,
    'message' => 'A submissão de notas de débito à AGT está bloqueada até à validação do enquadramento e do esquema oficial.',
]);
