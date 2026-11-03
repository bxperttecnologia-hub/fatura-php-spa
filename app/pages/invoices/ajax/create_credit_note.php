<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Método inválido.']);
  exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$invoiceId) {
  http_response_code(422);
  echo json_encode(['success' => false, 'error' => 'Fatura inválida.']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Serializa a emissão de documentos associados a esta fatura.
  $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1 FOR UPDATE");
  $stmt->execute([':id' => $invoiceId]);
  $inv = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$inv) {
    throw new RuntimeException('Fatura não encontrada.', 404);
  }

  $statusStmt = $pdo->prepare("
    SELECT name FROM invoice_status WHERE id = :status_id LIMIT 1
  ");
  $statusStmt->execute([':status_id' => $inv['status']]);
  $statusName = strtolower(trim((string)$statusStmt->fetchColumn()));
  if (str_starts_with($statusName, 'cancel')) {
    throw new RuntimeException('Não é possível emitir documentos para uma fatura cancelada.', 422);
  }

  $existingStmt = $pdo->prepare('SELECT id FROM credit_notes WHERE invoice_id = :invoice_id LIMIT 1');
  $existingStmt->execute([':invoice_id' => $invoiceId]);
  if ($existingStmt->fetchColumn()) {
    throw new RuntimeException('Já existe uma nota de crédito para esta fatura.', 409);
  }

  $cancelledStatusStmt = $pdo->prepare("
    SELECT id FROM invoice_status WHERE LOWER(name) IN ('cancelada', 'cancelado') LIMIT 1
  ");
  $cancelledStatusStmt->execute();
  $cancelledStatusId = $cancelledStatusStmt->fetchColumn();
  if ($cancelledStatusId === false) {
    throw new RuntimeException('O estado Cancelada não está configurado.', 500);
  }

  // Itens da fatura
  $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = :id");
  $stmt->execute([':id' => $invoiceId]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Cria a nota de crédito
  $stmt = $pdo->prepare("INSERT INTO credit_notes (
      invoice_id, contact_id, company_id, issue_date, reason,
      currency, retention, retention_value, total_sum, total_discount,
      subtotal_without_tax, total_tax, final_total, tax_withholding_type,
      tax_withholding_rate, tax_withholding_base, tax_withholding_amount,
      tax_withholding_description, tax_withholding_confirmed, gross_total, user_id
    ) VALUES (
      :invoice_id, :contact_id, :company_id, :issue_date, :reason,
      :currency, :retention, :retention_value, :total_sum, :total_discount,
      :subtotal_without_tax, :total_tax, :final_total, :tax_withholding_type,
      :tax_withholding_rate, :tax_withholding_base, :tax_withholding_amount,
      :tax_withholding_description, :tax_withholding_confirmed, :gross_total, :user_id
    )");

  $stmt->execute([
    ':invoice_id' => $inv['id'],
    ':contact_id' => $inv['contact_id'],
    ':company_id' => $inv['company_id'],
    ':issue_date' => date('Y-m-d'),
    ':reason' => ($reason !== '' ? $reason : null),
    ':currency' => $inv['currency'],
    ':retention' => $inv['retention'],
    ':retention_value' => $inv['retention_value'],
    ':total_sum' => $inv['total_sum'],
    ':total_discount' => $inv['total_discount'],
    ':subtotal_without_tax' => $inv['subtotal_without_tax'],
    ':total_tax' => $inv['total_tax'],
    ':final_total' => $inv['final_total'],
    ':tax_withholding_type' => $inv['tax_withholding_type'] ?? 'none',
    ':tax_withholding_rate' => $inv['tax_withholding_rate'] ?? 0,
    ':tax_withholding_base' => $inv['tax_withholding_base'] ?? 0,
    ':tax_withholding_amount' => $inv['tax_withholding_amount'] ?? 0,
    ':tax_withholding_description' => $inv['tax_withholding_description'] ?? null,
    ':tax_withholding_confirmed' => $inv['tax_withholding_confirmed'] ?? 0,
    ':gross_total' => (float)($inv['gross_total'] ?? 0) > 0
      ? $inv['gross_total']
      : (float)$inv['subtotal_without_tax'] + (float)$inv['total_tax'],
    ':user_id' => (int)($_SESSION['user']['id'] ?? $inv['user_id']),
  ]);

  $cnId = (int)$pdo->lastInsertId();

  // Copia itens
  $stmtItem = $pdo->prepare("INSERT INTO credit_note_items (
      credit_note_id, item_id, description, quantity, unit_price, tax, discount, retention_rate
    ) VALUES (
      :credit_note_id, :item_id, :description, :quantity, :unit_price, :tax, :discount, :retention_rate
    )");

  foreach ($items as $it) {
    $stmtItem->execute([
      ':credit_note_id' => $cnId,
      ':item_id' => $it['item_id'] ?? null,
      ':description' => $it['description'] ?? null,
      ':quantity' => $it['quantity'] ?? 0,
      ':unit_price' => $it['unit_price'] ?? 0,
      ':tax' => $it['tax'] ?? null,
      ':discount' => $it['discount'] ?? null,
      ':retention_rate' => $it['retention_rate'] ?? null,
    ]);
  }

  $updateInvoice = $pdo->prepare('UPDATE invoices SET status = :status WHERE id = :id');
  $updateInvoice->execute([
    ':status' => (int)$cancelledStatusId,
    ':id' => $invoiceId,
  ]);

  $pdo->commit();
  echo json_encode(['success' => true, 'credit_note_id' => $cnId]);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  $statusCode = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 500;
  http_response_code($statusCode);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
