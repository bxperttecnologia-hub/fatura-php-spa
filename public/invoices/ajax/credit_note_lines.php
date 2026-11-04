<?php
/** GET invoice_id → linhas da fatura com a quantidade que ainda se pode creditar (NC parcial). */
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/credit_note.php';
require_once __DIR__ . '/../../../app/helpers/agt.php';
header('Content-Type: application/json; charset=utf-8');

$invoiceId = (int)($_GET['invoice_id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, company_id, reference FROM invoices WHERE id = ? AND company_id = ?');
$stmt->execute([$invoiceId, (int)($_SESSION['user']['company_id'] ?? 0)]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
  http_response_code(404);
  echo json_encode(['success' => false, 'error' => 'Fatura não encontrada.']);
  exit;
}

$lines = array_map(static fn ($l) => [
  'invoice_item_id' => (int)$l['id'],
  'line_no' => $l['line_no'],
  'name' => $l['name'] ?: 'Artigo',
  'code' => $l['code'],
  'quantity' => (float)$l['quantity'],
  'credited_quantity' => $l['credited_quantity'],
  'remaining_quantity' => $l['remaining_quantity'],
  'unit_price' => (float)$l['unit_price'],
  'tax' => (float)$l['tax'],
  'discount' => (float)$l['discount'],
], bx_credit_note_invoice_lines($pdo, $invoiceId));

$stmt = $pdo->prepare('SELECT id, reference, issue_date, final_total, reason, agt_status FROM credit_notes WHERE invoice_id = ? ORDER BY id');
$stmt->execute([$invoiceId]);
$notes = array_map(static fn ($n) => [
  'id' => (int)$n['id'],
  'reference' => $n['reference'] ?: date('Y', strtotime($n['issue_date'])) . '/' . $n['id'],
  'issue_date' => $n['issue_date'],
  'final_total' => (float)$n['final_total'],
  'reason' => $n['reason'],
  'agt_status' => $n['agt_status'],
], $stmt->fetchAll(PDO::FETCH_ASSOC));

echo json_encode([
  'success' => true,
  'credit_notes' => $notes,
  'agt' => agt_parse_document_no($invoice['reference']) !== null && agt_enabled($pdo, (int)$invoice['company_id']),
  'lines' => $lines,
], JSON_UNESCAPED_UNICODE);
