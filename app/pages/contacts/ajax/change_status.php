<?php

/**
 * Arquivar / restaurar um contacto.
 *
 * Convenção da coluna contact.is_active:
 *   1 = ativo
 *   0 = arquivado
 *
 * POST: id (int), status (0 = arquivar, 1 = restaurar)
 *
 * Resposta: {"status":"success"|"error", "message":"...", "is_active":0|1}
 */

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

function respond(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Método inválido.'], 405);
}

// A empresa vem sempre da sessão: sem isto, qualquer pessoa conseguia
// arquivar/restaurar contactos de outra empresa só conhecendo o ID.
$companyId = $_SESSION['user']['company_id'] ?? null;
if (!$companyId) {
    respond(['status' => 'error', 'message' => 'Sessão expirada. Faça login novamente.'], 401);
}

$contactId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($contactId <= 0) {
    respond(['status' => 'error', 'message' => 'ID do contato inválido.'], 400);
}

// Só aceita 0 (arquivar) ou 1 (restaurar). Antes, qualquer valor não numérico
// virava 0 em silêncio e o contacto era arquivado sem querer.
if (!isset($_POST['status']) || !in_array((string) $_POST['status'], ['0', '1'], true)) {
    respond(['status' => 'error', 'message' => 'Estado inválido.'], 400);
}
$status = (int) $_POST['status'];

try {
    $stmt = $pdo->prepare(
        'UPDATE contact SET is_active = :status WHERE id = :id AND company_id = :company_id'
    );
    $stmt->execute([
        ':status'     => $status,
        ':id'         => $contactId,
        ':company_id' => $companyId,
    ]);

    // rowCount() = 0 tanto pode ser "não existe / é de outra empresa" como
    // "já estava neste estado" (o MySQL não conta linhas sem alteração).
    // Só o primeiro caso é erro.
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM contact WHERE id = :id AND company_id = :company_id');
        $check->execute([':id' => $contactId, ':company_id' => $companyId]);

        if ((int) $check->fetchColumn() === 0) {
            respond(['status' => 'error', 'message' => 'Contato não encontrado.'], 404);
        }
    }

    respond([
        'status'    => 'success',
        // status 1 = ativo => "reativado"; status 0 = arquivado.
        // (A mensagem estava invertida: dizia "arquivado" ao reativar.)
        'message'   => $status === 1 ? 'Contato reativado!' : 'Contato arquivado!',
        'is_active' => $status,
    ]);
} catch (PDOException $e) {
    respond([
        'status'  => 'error',
        'message' => 'Erro ao ' . ($status === 1 ? 'reativar' : 'arquivar') . ' contato: ' . $e->getMessage(),
    ], 500);
}
