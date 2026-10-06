<?php
declare(strict_types=1);

require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

function fail(int $code, string $msg): void
{
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
$lines = [];
$subtotal = 0.0;
$totalTax = 0.0;

foreach ($rawItems as $i => $r) {
    $n    = $i + 1;
    $desc = trim((string)($r['description'] ?? ''));
    $qty  = (float)($r['quantity'] ?? 0);
    $unit = (float)($r['unit_price'] ?? 0);
    $tax  = (float)($r['tax'] ?? 0);

    $descLen = function_exists('mb_strlen') ? mb_strlen($desc) : strlen($desc);
    if ($desc === '' || $descLen > 500) {
        fail(422, "Linha $n: descrição obrigatória (máx. 500 caracteres).");
    }
    if ($qty <= 0 || $qty > 1000000) {
        fail(422, "Linha $n: quantidade inválida.");
    }
    if ($unit <= 0 || $unit > 1000000000000) {
        fail(422, "Linha $n: preço unitário inválido.");
    }
    if ($tax < 0 || $tax > 100) {
        fail(422, "Linha $n: IVA deve estar entre 0 e 100.");
    }

    $base    = round($qty * $unit, 2);
    $taxVal  = round($base * $tax / 100, 2);
    $subtotal += $base;
    $totalTax += $taxVal;

    $lines[] = ['description' => $desc, 'quantity' => $qty, 'unit_price' => $unit, 'tax' => $tax];
}

$subtotal = round($subtotal, 2);
$totalTax = round($totalTax, 2);
$final    = round($subtotal + $totalTax, 2);

try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1");
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
    if ($statusName === 'Cancelado') {
        fail(422, 'Não é possível emitir nota de débito sobre uma fatura cancelada.');
    }

    $pdo->beginTransaction();

    // Numeração sequencial por empresa e série (bloqueia para evitar duplicados)
    $serie = 'ND';
    $n = $pdo->prepare("SELECT COALESCE(MAX(number), 0) + 1 FROM debit_notes WHERE company_id = :c AND serie = :s FOR UPDATE");
    $n->execute([':c' => $inv['company_id'], ':s' => $serie]);
    $number = (int)$n->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO debit_notes
            (invoice_id, contact_id, company_id, serie, number, issue_date, reason,
             currency, subtotal_without_tax, total_tax, final_total, user_id)
        VALUES
            (:invoice_id, :contact_id, :company_id, :serie, :number, :issue_date, :reason,
             :currency, :subtotal, :tax, :final, :user_id)
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
        ':tax'        => $totalTax,
        ':final'      => $final,
        ':user_id'    => (int)($_SESSION['user']['id'] ?? $inv['user_id'] ?? 0) ?: null,
    ]);
    $ndId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("
        INSERT INTO debit_note_items (debit_note_id, description, quantity, unit_price, tax)
        VALUES (:nd, :descr, :qty, :unit, :tax)
    ");
    foreach ($lines as $l) {
        $insItem->execute([
            ':nd'    => $ndId,
            ':descr' => $l['description'],
            ':qty'   => $l['quantity'],
            ':unit'  => $l['unit_price'],
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
