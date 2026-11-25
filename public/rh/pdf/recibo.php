<?php
/** Recibo de vencimento (PDF). GET id. Também servido por /api/documents/payroll/<id>/pdf. */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/rh_pdf.php';

ini_set('display_errors', '0');
$id = (int)($_GET['id'] ?? 0);
$html = $id ? rh_payslip_html($pdo, $company_id, $id) : null;
if (!$html) {
    rh_json(['success' => false, 'message' => 'Recibo não encontrado.'], 404);
}
$data = rh_payslip_data($pdo, $company_id, $id);
rh_pdf_render($html, rh_payslip_filename($data), ($_GET['download'] ?? '') === '1' ? 'download' : 'inline');
