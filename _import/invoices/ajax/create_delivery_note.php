<?php
declare(strict_types=1);

require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$address   = trim($_POST['delivery_address'] ?? '');
$notes     = trim($_POST['notes'] ?? '');

if (!$invoiceId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Fatura inválida.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $invoiceId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Fatura não encontrada.']);
        exit;
    }

    // Só faturas finalizadas (não rascunho) podem gerar nota de entrega
    $st = $pdo->prepare("SELECT ivs.name FROM invoice_status ivs WHERE ivs.id = :sid LIMIT 1");
    $statusId = $inv['status'] ?? null;
    if ($statusId !== null) {
        $st->execute([':sid' => $statusId]);
        $statusName = $st->fetchColumn();
        if ($statusName === 'Rascunho') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Finalize a fatura antes de emitir a nota de entrega.']);
            exit;
        }
    }

    // Itens da fatura + quantidade já entregue em notas anteriores
    $stmt = $pdo->prepare("
        SELECT ii.*,
               COALESCE((
                   SELECT SUM(dni.quantity)
                   FROM delivery_note_items dni
                   JOIN delivery_notes dn ON dn.id = dni.delivery_note_id
                   WHERE dni.invoice_item_id = ii.id AND dn.invoice_id = :inv
               ), 0) AS delivered_qty
        FROM invoice_items ii
        WHERE ii.invoice_id = :inv2
    ");
    $stmt->execute([':inv' => $invoiceId, ':inv2' => $invoiceId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Entrega apenas o que falta entregar
    $pending = [];
    foreach ($items as $it) {
        $remaining = (float)$it['quantity'] - (float)$it['delivered_qty'];
        if ($remaining > 0) {
            $pending[] = ['row' => $it, 'qty' => $remaining];
        }
    }

    if (!$pending) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Todos os itens desta fatura já foram entregues.']);
        exit;
    }

    // Endereço por defeito: morada do cliente
    if ($address === '') {
        $c = $pdo->prepare("SELECT address FROM contact WHERE id = :id LIMIT 1");
        $c->execute([':id' => $inv['contact_id']]);
        $address = (string)($c->fetchColumn() ?: '');
    }

    $pdo->beginTransaction();

    // Numeração sequencial por empresa e série (bloqueia para evitar duplicados)
    $serie = 'NE';
    $n = $pdo->prepare("SELECT COALESCE(MAX(number), 0) + 1 FROM delivery_notes WHERE company_id = :c AND serie = :s FOR UPDATE");
    $n->execute([':c' => $inv['company_id'], ':s' => $serie]);
    $number = (int)$n->fetchColumn();

    $ins = $pdo->prepare("
        INSERT INTO delivery_notes
            (invoice_id, contact_id, company_id, serie, number, issue_date, delivery_address, notes, user_id)
        VALUES
            (:invoice_id, :contact_id, :company_id, :serie, :number, :issue_date, :addr, :notes, :user_id)
    ");
    $ins->execute([
        ':invoice_id' => $inv['id'],
        ':contact_id' => $inv['contact_id'],
        ':company_id' => $inv['company_id'],
        ':serie'      => $serie,
        ':number'     => $number,
        ':issue_date' => date('Y-m-d'),
        ':addr'       => $address !== '' ? $address : null,
        ':notes'      => $notes !== '' ? $notes : null,
        ':user_id'    => (int)($_SESSION['user']['id'] ?? $inv['user_id'] ?? 0) ?: null,
    ]);
    $dnId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare("
        INSERT INTO delivery_note_items (delivery_note_id, invoice_item_id, item_id, description, quantity)
        VALUES (:dn, :iid, :item, :descr, :qty)
    ");
    foreach ($pending as $p) {
        $insItem->execute([
            ':dn'    => $dnId,
            ':iid'   => $p['row']['id'] ?? null,
            ':item'  => $p['row']['item_id'] ?? null,
            ':descr' => $p['row']['description'] ?? null,
            ':qty'   => $p['qty'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success'          => true,
        'delivery_note_id' => $dnId,
        'number'           => sprintf('%s %d', $serie, $number),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
