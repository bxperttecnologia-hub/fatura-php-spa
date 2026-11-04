<?php

declare(strict_types=1);

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/debit_note_calculation.php';
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!isset($_SESSION['user']['company_id'])) {
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

// Validar linhas e calcular totais no servidor (nunca confiar nos totais do browser)
try {
    $limits = bx_debit_note_schema_limits($pdo);
    $totals = bx_calculate_debit_note_totals(
        $rawItems,
        $limits['quantity_scale'],
        $limits['max_amount']
    );
} catch (InvalidArgumentException $e) {
    fail(422, $e->getMessage());
} catch (RuntimeException $e) {
    fail(500, $e->getMessage());
}

$lines = $totals['lines'];
$subtotal = $totals['subtotal_without_tax'];
$totalDiscount = $totals['total_discount'];
$totalTax = $totals['total_tax'];
$final = $totals['final_total'];

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

    // Numeração sequencial por empresa e série (bloqueia para evitar duplicados)
    $serie = 'ND';
    $n = $pdo->prepare("SELECT COALESCE(MAX(number), 0) + 1 FROM debit_notes WHERE company_id = :c AND serie = :s FOR UPDATE");
    $n->execute([':c' => $inv['company_id'], ':s' => $serie]);
    $number = (int)$n->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO debit_notes
            (invoice_id, contact_id, company_id, serie, number, issue_date, reason,
             currency, subtotal_without_tax, total_discount, total_tax, final_total, user_id)
        VALUES
            (:invoice_id, :contact_id, :company_id, :serie, :number, :issue_date, :reason,
             :currency, :subtotal, :discount, :tax, :final, :user_id)
    ");
    $ins->execute([
        ':invoice_id' => $inv['id'],
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

    echo json_encode([
        'success'       => true,
        'debit_note_id' => $ndId,
        'number'        => sprintf('%s %d', $serie, $number),
        'final_total'   => $final,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail(500, $e->getMessage());
}
