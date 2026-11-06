<?php
ob_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/invoice_emit.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$companyIdSession = (int)($_SESSION['user']['company_id'] ?? 0);
if ($companyIdSession <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}
if (
    empty($_SESSION['csrf'])
    || !hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido");
    }

    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $newStatusName = (string)($_POST['new_status'] ?? 'Pendente');
    $documentType = (string)($_POST['document_type'] ?? 'FT');

    if ($invoiceId <= 0) {
        throw new Exception("ID inválido");
    }

    $pdo->beginTransaction();
    $result = bx_invoice_emit($pdo, $companyIdSession, $invoiceId, $newStatusName, $documentType);
    $pdo->commit();

    // Faturação eletrónica: envia à AGT depois de emitida (não bloqueia a emissão se falhar).
    $agt = bx_invoice_send_agt($pdo, $invoiceId, $newStatusName);

    $output = ob_get_clean();

    echo json_encode([
        'success' => true,
        'reference' => $result['reference'],
        'hash' => $result['hash'],
        'agt' => $agt,
        'debug' => $output
    ]);
} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $output = ob_get_clean();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $output
    ]);
}
