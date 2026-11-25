<?php
/** Documento do funcionário em PDF. GET employee_id, type=declaracao|rendimentos|contrato, purpose (opcional), download=1 */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/rh_documents.php';

ini_set('display_errors', '0');
$doc = rh_employee_document($pdo, $company_id, (int)($_GET['employee_id'] ?? 0), (string)($_GET['type'] ?? ''), [
    'purpose' => mb_substr((string)($_GET['purpose'] ?? ''), 0, 120),
]);
if (!$doc) {
    rh_json(['success' => false, 'message' => 'Documento ou funcionário não encontrado.'], 404);
}
rh_pdf_render($doc['html'], $doc['filename'], ($_GET['download'] ?? '') === '1' ? 'download' : 'inline');
