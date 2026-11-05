<?php
/**
 * Emite uma nota de débito sobre uma fatura finalizada.
 *
 * POST invoice_id, reason, items = JSON [{description, quantity, unit_price, discount, tax}, ...]
 * Fatura com numeração AGT (validada) → ND "ND <série ND>/<n>" enviada à AGT depois do commit;
 * caso contrário fica com a numeração interna "ND <n>".
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/debit_note_calculation.php';
require_once __DIR__ . '/../../../app/helpers/agt_debit_note.php';
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void
{
    global $pdo;
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'Método inválido.');
}

if (empty($_SESSION['user']['company_id'])) {
    fail(401, 'Empresa não encontrada na sessão.');
}
$sessionCompany = (int)$_SESSION['user']['company_id'];

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$reason    = trim((string)($_POST['reason'] ?? ''));
$rawItems  = json_decode((string)($_POST['items'] ?? '[]'), true);

if (!$invoiceId) {
    fail(422, 'Fatura inválida.');
}
if ($reason === '') {
    fail(422, 'Indique o motivo da nota de débito.');
}
if (!is_array($rawItems) || !$rawItems) {
    fail(422, 'Adicione pelo menos uma linha à nota de débito.');
}
if (count($rawItems) > 50) {
    fail(422, 'Máximo de 50 linhas por nota de débito.');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1 FOR UPDATE");
    $stmt->execute([':id' => $invoiceId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        fail(404, 'Fatura não encontrada.');
    }
    if ((int)$inv['company_id'] !== $sessionCompany) {
        fail(403, 'Sem permissão para esta fatura.');
    }

    // Só faturas finalizadas (nem rascunho, nem canceladas) podem ter nota de débito
    $st = $pdo->prepare("SELECT name FROM invoice_status WHERE id = :sid LIMIT 1");
    $st->execute([':sid' => $inv['status']]);
    $statusName = (string)$st->fetchColumn();
    if ($statusName === 'Rascunho') {
        fail(422, 'Finalize a fatura antes de emitir a nota de débito.');
    }
    if (str_starts_with(strtolower(trim($statusName)), 'cancel')) {
        fail(422, 'Não é possível emitir nota de débito sobre uma fatura cancelada.');
    }

    // IVA segundo o regime da empresa (regime simplificado/exclusão → 0%), como na fatura.
    $regimeStmt = $pdo->prepare('SELECT vat_regime FROM companies WHERE id = ?');
    $regimeStmt->execute([(int)$inv['company_id']]);
    $regime = (string)$regimeStmt->fetchColumn();
    foreach ($rawItems as &$raw) {
        if (is_array($raw) && isset($raw['tax']) && is_numeric($raw['tax'])) {
            $raw['tax'] = bx_document_effective_tax_rate($raw['tax'], $regime);
        }
    }
    unset($raw);

    // Validar linhas e calcular totais no servidor (nunca confiar nos totais do browser)
    try {
        $limits = bx_debit_note_schema_limits($pdo);
        $totals = bx_calculate_debit_note_totals($rawItems, $limits['quantity_scale'], $limits['max_amount']);
    } catch (InvalidArgumentException $e) {
        fail(422, $e->getMessage());
    }
    $lines = $totals['lines'];
    $subtotal = $totals['subtotal_without_tax'];
    $totalDiscount = $totals['total_discount'];
    $totalTax = $totals['total_tax'];
    $final = $totals['final_total'];

    // Numeração: AGT ("ND <série>/<n>") se a fatura foi à AGT; senão interna "ND <n>".
    $agtNote = agt_debit_note_required($pdo, $inv);
    $reference = null;
    $agtSeriesId = null;
    if ($agtNote) {
        if ($inv['agt_status'] !== 'valid') {
            fail(422, $inv['agt_status'] === 'sent'
                ? 'A fatura ainda está a aguardar validação da AGT. Tente a nota de débito daqui a pouco.'
                : 'A fatura não foi validada pela AGT; só se emite nota de débito sobre uma fatura válida.');
        }
        try {
            $nd = agt_debit_note_next_number($pdo, (int)$inv['company_id']);
        } catch (RuntimeException $e) {
            fail($e->getCode() === 422 ? 422 : 500, $e->getMessage());
        }
        $serie = $nd['series_code'];
        $number = $nd['number'];
        $reference = $nd['reference'];
        $agtSeriesId = $nd['series_id'];
    } else {
        $serie = 'ND';
        $n = $pdo->prepare("SELECT COALESCE(MAX(number), 0) + 1 FROM debit_notes WHERE company_id = :c AND serie = :s FOR UPDATE");
        $n->execute([':c' => $inv['company_id'], ':s' => $serie]);
        $number = (int)$n->fetchColumn();
    }

    $ins = $pdo->prepare("
        INSERT INTO debit_notes
            (invoice_id, reference, agt_series_id, contact_id, company_id, serie, number, issue_date, reason,
             currency, subtotal_without_tax, total_discount, total_tax, final_total, user_id)
        VALUES
            (:invoice_id, :reference, :agt_series_id, :contact_id, :company_id, :serie, :number, :issue_date, :reason,
             :currency, :subtotal, :discount, :tax, :final, :user_id)
    ");
    $ins->execute([
        ':invoice_id' => $inv['id'],
        ':reference'  => $reference,
        ':agt_series_id' => $agtSeriesId,
        ':contact_id' => $inv['contact_id'],
        ':company_id' => $inv['company_id'],
        ':serie'      => $serie,
        ':number'     => $number,
        ':issue_date' => date('Y-m-d'),
        ':reason'     => $reason,
        ':currency'   => $inv['currency'],
        ':subtotal'   => $subtotal,
        ':discount'   => $totalDiscount,
        ':tax'        => $totalTax,
        ':final'      => $final,
        ':user_id'    => (int)($_SESSION['user']['id'] ?? $inv['user_id'] ?? 0) ?: null,
    ]);
    $ndId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("
        INSERT INTO debit_note_items (debit_note_id, description, quantity, unit_price, discount, tax)
        VALUES (:nd, :descr, :qty, :unit, :discount, :tax)
    ");
    foreach ($lines as $l) {
        $insItem->execute([
            ':nd'    => $ndId,
            ':descr' => $l['description'],
            ':qty'   => $l['quantity'],
            ':unit'  => $l['unit_price'],
            ':discount' => $l['discount'],
            ':tax'   => $l['tax'],
        ]);
    }

    $pdo->commit();

    $agt = $agtNote ? agt_submit_debit_note($pdo, $ndId) : null;
    echo json_encode([
        'success'       => true,
        'debit_note_id' => $ndId,
        'number'        => $reference ?? sprintf('%s %d', $serie, $number),
        'reference'     => $reference,
        'final_total'   => $final,
        'agt'           => $agt,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail(500, $e->getMessage());
}
