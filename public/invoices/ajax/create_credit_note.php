<?php
/**
 * Emite uma nota de crédito sobre uma fatura — total ou só de algumas linhas/quantidades.
 *
 * POST invoice_id, reason, lines (opcional) = JSON [{"invoice_item_id": 12, "quantity": 1}, ...]
 * Sem "lines" credita tudo o que ainda falta creditar. A fatura só passa a Cancelada quando
 * todas as linhas ficam totalmente creditadas. Fatura com numeração AGT → NC "NC <série>/<n>"
 * enviada à AGT depois do commit.
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/agt_credit_note.php';
require_once __DIR__ . '/../../../app/helpers/credit_note.php';
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Método inválido.']);
  exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
$requestedLines = isset($_POST['lines']) && $_POST['lines'] !== '' ? json_decode((string)$_POST['lines'], true) : null;

if (!$invoiceId) {
  http_response_code(422);
  echo json_encode(['success' => false, 'error' => 'Fatura inválida.']);
  exit;
}
if ($requestedLines !== null && !is_array($requestedLines)) {
  http_response_code(422);
  echo json_encode(['success' => false, 'error' => 'Linhas inválidas.']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Serializa a emissão de documentos associados a esta fatura.
  $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id AND company_id = :company LIMIT 1 FOR UPDATE");
  $stmt->execute([':id' => $invoiceId, ':company' => $companyId]);
  $inv = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$inv) {
    throw new RuntimeException('Fatura não encontrada.', 404);
  }

  $statusStmt = $pdo->prepare("SELECT name FROM invoice_status WHERE id = :status_id LIMIT 1");
  $statusStmt->execute([':status_id' => $inv['status']]);
  $statusName = strtolower(trim((string)$statusStmt->fetchColumn()));
  if (str_starts_with($statusName, 'cancel')) {
    throw new RuntimeException('Não é possível emitir documentos para uma fatura cancelada.', 422);
  }
  if (str_starts_with($statusName, 'rascunho')) {
    throw new RuntimeException('Um rascunho não leva nota de crédito: elimine-o ou edite-o.', 422);
  }

  $cancelledStatusStmt = $pdo->prepare("SELECT id FROM invoice_status WHERE LOWER(name) IN ('cancelada', 'cancelado') LIMIT 1");
  $cancelledStatusStmt->execute();
  $cancelledStatusId = $cancelledStatusStmt->fetchColumn();
  if ($cancelledStatusId === false) {
    throw new RuntimeException('O estado Cancelada não está configurado.', 500);
  }

  // ---- Linhas a creditar -------------------------------------------------
  $invoiceLines = bx_credit_note_invoice_lines($pdo, $invoiceId);
  $byId = array_column($invoiceLines, null, 'id');
  $selected = [];   // invoice_items.id => quantidade a creditar

  if ($requestedLines === null) {
    foreach ($invoiceLines as $line) {
      if ($line['remaining_quantity'] > 0) {
        $selected[(int)$line['id']] = (float)$line['remaining_quantity'];
      }
    }
  } else {
    foreach ($requestedLines as $req) {
      $lineId = (int)($req['invoice_item_id'] ?? 0);
      $qty = round((float)($req['quantity'] ?? 0), 4);
      if ($qty <= 0) {
        continue;
      }
      if (!isset($byId[$lineId])) {
        throw new RuntimeException('Linha da fatura inválida.', 422);
      }
      $line = $byId[$lineId];
      if ($qty > $line['remaining_quantity'] + 0.00001) {
        throw new RuntimeException(sprintf(
          'Linha %d (%s): só pode creditar até %s.',
          $line['line_no'],
          $line['name'] ?: 'artigo',
          rtrim(rtrim(number_format($line['remaining_quantity'], 4, ',', ''), '0'), ',')
        ), 422);
      }
      $selected[$lineId] = ($selected[$lineId] ?? 0) + $qty;
    }
  }
  if (!$selected) {
    throw new RuntimeException(
      $requestedLines === null ? 'Esta fatura já está totalmente creditada.' : 'Escolha pelo menos uma linha e quantidade a creditar.',
      422
    );
  }

  // A fatura fica totalmente creditada com esta NC?
  $fullyCredited = true;
  foreach ($invoiceLines as $line) {
    if ($line['remaining_quantity'] - ($selected[(int)$line['id']] ?? 0) > 0.00001) {
      $fullyCredited = false;
      break;
    }
  }
  $isWholeInvoice = $fullyCredited && !array_filter($invoiceLines, static fn ($l) => $l['credited_quantity'] > 0);

  // ---- Totais da NC --------------------------------------------------------
  $regimeStmt = $pdo->prepare('SELECT vat_regime FROM companies WHERE id = ?');
  $regimeStmt->execute([$companyId]);
  $regime = (string)$regimeStmt->fetchColumn();

  $noteItems = [];
  foreach ($selected as $lineId => $qty) {
    $noteItems[] = ['quantity' => $qty] + $byId[$lineId];
  }

  if ($isWholeInvoice) {
    // Anulação integral numa só NC: copia os totais da fatura.
    $totals = [
      'total_sum' => $inv['total_sum'],
      'total_discount' => $inv['total_discount'],
      'subtotal_without_tax' => $inv['subtotal_without_tax'],
      'total_tax' => $inv['total_tax'],
      'retention_value' => $inv['retention_value'],
      'final_total' => $inv['final_total'],
      'withholding_base' => $inv['tax_withholding_base'] ?? 0,
      'withholding_amount' => $inv['tax_withholding_amount'] ?? 0,
      'gross_total' => (float)($inv['gross_total'] ?? 0) > 0
        ? $inv['gross_total']
        : (float)$inv['subtotal_without_tax'] + (float)$inv['total_tax'],
    ];
  } else {
    $sum = bx_document_tax_summary($noteItems, $regime, null, true);
    $subtotal = round((float)$sum['subtotal'], 2);
    $tax = round((float)$sum['total_tax'], 2);
    $lineRetention = round((float)$sum['retention'], 2);
    // Retenção ao nível do documento (tax_withholding_*): na proporção da base creditada.
    $share = (float)$inv['subtotal_without_tax'] > 0 ? min(1, $subtotal / (float)$inv['subtotal_without_tax']) : 0;
    $withholding = round((float)($inv['tax_withholding_amount'] ?? 0) * $share, 2);
    $totals = [
      'total_sum' => round((float)$sum['total_sum'], 2),
      'total_discount' => round((float)$sum['total_discount'], 2),
      'subtotal_without_tax' => $subtotal,
      'total_tax' => $tax,
      'retention_value' => $lineRetention,
      'final_total' => round($subtotal + $tax - $lineRetention - $withholding, 2),
      'withholding_base' => round((float)($inv['tax_withholding_base'] ?? 0) * $share, 2),
      'withholding_amount' => $withholding,
      'gross_total' => round($subtotal + $tax, 2),
    ];
  }

  // ---- Numeração AGT -------------------------------------------------------
  agt_require_series($pdo, (int)$inv['company_id'], 'NC');   // AGT ativa sem série NC → não emite
  $agtNote = agt_credit_note_required($pdo, $inv);
  $noteReference = null;
  $noteSeriesId = null;
  if ($agtNote) {
    if ($inv['agt_status'] !== 'valid') {
      throw new RuntimeException(
        $inv['agt_status'] === 'sent'
          ? 'A fatura ainda está a aguardar validação da AGT. Tente a nota de crédito daqui a pouco.'
          : 'A fatura não foi validada pela AGT; só se emite nota de crédito sobre uma fatura válida.',
        422
      );
    }
    if ($reason === '') {
      throw new RuntimeException('Indique o motivo da nota de crédito (obrigatório na AGT).', 422);
    }
    $nc = agt_credit_note_next_number($pdo, $companyId);
    $noteReference = $nc['reference'];
    $noteSeriesId = $nc['series_id'];
  }

  // ---- Gravar ----------------------------------------------------------------
  $stmt = $pdo->prepare("INSERT INTO credit_notes (
      invoice_id, reference, agt_series_id, contact_id, company_id, issue_date, reason,
      currency, retention, retention_value, total_sum, total_discount,
      subtotal_without_tax, total_tax, final_total, tax_withholding_type,
      tax_withholding_rate, tax_withholding_base, tax_withholding_amount,
      tax_withholding_description, tax_withholding_confirmed, gross_total, user_id
    ) VALUES (
      :invoice_id, :reference, :agt_series_id, :contact_id, :company_id, :issue_date, :reason,
      :currency, :retention, :retention_value, :total_sum, :total_discount,
      :subtotal_without_tax, :total_tax, :final_total, :tax_withholding_type,
      :tax_withholding_rate, :tax_withholding_base, :tax_withholding_amount,
      :tax_withholding_description, :tax_withholding_confirmed, :gross_total, :user_id
    )");

  $stmt->execute([
    ':invoice_id' => $inv['id'],
    ':reference' => $noteReference,
    ':agt_series_id' => $noteSeriesId,
    ':contact_id' => $inv['contact_id'],
    ':company_id' => $inv['company_id'],
    ':issue_date' => date('Y-m-d'),
    ':reason' => ($reason !== '' ? $reason : null),
    ':currency' => $inv['currency'],
    ':retention' => $inv['retention'],
    ':retention_value' => $totals['retention_value'],
    ':total_sum' => $totals['total_sum'],
    ':total_discount' => $totals['total_discount'],
    ':subtotal_without_tax' => $totals['subtotal_without_tax'],
    ':total_tax' => $totals['total_tax'],
    ':final_total' => $totals['final_total'],
    ':tax_withholding_type' => $inv['tax_withholding_type'] ?? 'none',
    ':tax_withholding_rate' => $inv['tax_withholding_rate'] ?? 0,
    ':tax_withholding_base' => $totals['withholding_base'],
    ':tax_withholding_amount' => $totals['withholding_amount'],
    ':tax_withholding_description' => $inv['tax_withholding_description'] ?? null,
    ':tax_withholding_confirmed' => $inv['tax_withholding_confirmed'] ?? 0,
    ':gross_total' => $totals['gross_total'],
    ':user_id' => (int)($_SESSION['user']['id'] ?? $inv['user_id']),
  ]);

  $cnId = (int)$pdo->lastInsertId();

  $stmtItem = $pdo->prepare("INSERT INTO credit_note_items (
      credit_note_id, invoice_item_id, item_id, description, quantity, unit_price, tax, discount, retention_rate
    ) VALUES (
      :credit_note_id, :invoice_item_id, :item_id, :description, :quantity, :unit_price, :tax, :discount, :retention_rate
    )");

  foreach ($noteItems as $it) {
    $stmtItem->execute([
      ':credit_note_id' => $cnId,
      ':invoice_item_id' => $it['id'],
      ':item_id' => $it['item_id'] ?? null,
      ':description' => $it['name'] ?? null,
      ':quantity' => $it['quantity'],
      ':unit_price' => $it['unit_price'] ?? 0,
      ':tax' => $it['tax'] ?? null,
      ':discount' => $it['discount'] ?? null,
      ':retention_rate' => $it['retention_rate'] ?? null,
    ]);
  }

  if ($fullyCredited) {
    $pdo->prepare('UPDATE invoices SET status = :status WHERE id = :id')
      ->execute([':status' => (int)$cancelledStatusId, ':id' => $invoiceId]);
  }

  $pdo->commit();

  $agt = $agtNote ? agt_submit_credit_note($pdo, $cnId) : null;
  echo json_encode([
    'success' => true,
    'credit_note_id' => $cnId,
    'reference' => $noteReference,
    'partial' => !$fullyCredited,
    'final_total' => (float)$totals['final_total'],
    'agt' => $agt,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  $statusCode = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 500;
  if ($statusCode === 500) {
    error_log('create_credit_note: ' . $e->getMessage());
  }
  http_response_code($statusCode);
  echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
