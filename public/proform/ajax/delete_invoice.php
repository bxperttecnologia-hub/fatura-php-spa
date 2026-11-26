<?php
/**
 * Elimina uma proforma em rascunho da empresa ativa.
 * Antes apagava da tabela `invoices` (com o id da proforma) e os itens de qualquer empresa.
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';
header('Content-Type: application/json; charset=utf-8');

$proformaId = (int)($_POST['invoice_id'] ?? 0);
$companyId  = session_company_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $proformaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'invoice_id é obrigatório']);
    exit;
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT status FROM proformas WHERE id = ? AND company_id = ? FOR UPDATE');
    $st->execute([$proformaId, $companyId]);
    $status = $st->fetchColumn();

    if ($status === false) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Proforma não encontrada.']);
        exit;
    }
    if ((int)$status !== 1) {   // 1 = Rascunho (proforma_status)
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Só é possível eliminar proformas em rascunho.']);
        exit;
    }

    $pdo->prepare('DELETE FROM proforma_items WHERE proforma_id = ?')->execute([$proformaId]);
    $pdo->prepare('DELETE FROM proformas WHERE id = ? AND company_id = ?')->execute([$proformaId, $companyId]);
    $pdo->commit();

    echo json_encode(['success' => true, 'data' => null]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[proform/delete_invoice] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível eliminar a proforma.']);
}
