<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/helpers/require_login.php';
header('Content-Type: application/json; charset=utf-8');

$type = strtolower(trim((string)($_GET['type'] ?? $_POST['type'] ?? '')));
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$companyId = session_company_id();
if (!in_array($type, ['factura', 'invoice', 'ft', 'fr', 'proforma', 'pf'], true) || $id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Documento de origem inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo->beginTransaction();

try {
    $isInvoice = in_array($type, ['factura', 'invoice', 'ft', 'fr'], true);
    if ($isInvoice) {
        $stmt = $pdo->prepare("
            SELECT i.*, c.name AS contact_name, c.contributor, c.address, c.telephone, c.email, c.po_box,
                   c.country, c.city, s.name AS status_name
            FROM invoices i
            JOIN contact c ON c.id = i.contact_id
            LEFT JOIN invoice_status s ON s.id = i.status
            WHERE i.id = ? AND i.company_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$id, $companyId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$document) throw new RuntimeException('Documento não encontrado.', 404);

        $statusName = (string)($document['status_name'] ?? '');
        if ($statusName === 'Rascunho') throw new RuntimeException('Finalize a factura antes de utilizar como origem.', 422);
        if (stripos($statusName, 'cancel') !== false) throw new RuntimeException('Não é possível utilizar uma factura cancelada.', 422);

        $itemsStmt = $pdo->prepare("
            SELECT ii.id AS invoice_item_id, ii.item_id, ii.quantity AS original_quantity,
                   COALESCE(i.code, '') AS code,
                   COALESCE(i.description, '') AS description,
                   i.unit_measure AS unit,
                   i.name AS item_name,
                   COALESCE((
                     SELECT SUM(dni.quantity)
                     FROM delivery_note_items dni
                     JOIN delivery_notes dn ON dn.id = dni.delivery_note_id
                     WHERE dn.invoice_id = ii.invoice_id
                       AND dni.invoice_item_id = ii.id
                       AND dn.status <> 'cancelado'
                   ), 0) AS delivered_quantity
            FROM invoice_items ii
            LEFT JOIN items i ON i.id = ii.item_id
            WHERE ii.invoice_id = ?
            ORDER BY ii.id
            FOR UPDATE
        ");
        $itemsStmt->execute([$id]);
        $sourceItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS contact_name, c.contributor, c.address, c.telephone, c.email, c.po_box,
                   c.country, c.city
            FROM proformas p
            JOIN contact c ON c.id = p.contact_id
            WHERE p.id = ? AND p.company_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$id, $companyId]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$document) throw new RuntimeException('Documento não encontrado.', 404);

        $itemsStmt = $pdo->prepare("
            SELECT pi.id AS invoice_item_id, pi.item_id, pi.quantity AS original_quantity,
                   COALESCE(i.code, '') AS code,
                   COALESCE(i.description, '') AS description,
                   i.unit_measure AS unit,
                   i.name AS item_name,
                   0 AS delivered_quantity
            FROM proforma_items pi
            LEFT JOIN items i ON i.id = pi.item_id
            WHERE pi.proforma_id = ?
            ORDER BY pi.id
            FOR UPDATE
        ");
        $itemsStmt->execute([$id]);
        $sourceItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $items = [];
    $hasPending = false;
    foreach ($sourceItems as $item) {
        $original = (float)$item['original_quantity'];
        $delivered = (float)$item['delivered_quantity'];
        $pending = max(0, $original - $delivered);
        if ($pending > 0) $hasPending = true;
        $description = trim((string)$item['description']);
        if ($description === '') $description = trim((string)$item['item_name']);
        $items[] = [
            'source_item_id' => (int)$item['invoice_item_id'],
            'item_id' => (int)$item['item_id'],
            'code' => (string)$item['code'],
            'description' => $description,
            'unit' => (string)$item['unit'],
            'original_quantity' => $original,
            'delivered_quantity' => $delivered,
            'pending_quantity' => $pending,
            'suggested_quantity' => $pending,
        ];
    }

    if (!$hasPending) throw new RuntimeException('Este documento já não possui quantidades pendentes de entrega.', 422);

    $payload = [
        'document_type' => $isInvoice ? 'factura' : 'proforma',
        'document_subtype' => $isInvoice ? (string)$document['document_type'] : 'PF',
        'document_id' => $id,
        'series' => (string)($document['series'] ?? ''),
        'number' => (string)($document['reference'] ?? ''),
        'issue_date' => (string)$document['issue_date'],
        'status' => $isInvoice ? (string)($document['status_name'] ?? '') : 'Elegível',
        'reference' => (string)($document['reference'] ?? ''),
        'observation' => (string)($document['observation'] ?? ''),
        'contact' => [
            'id' => (int)$document['contact_id'],
            'name' => (string)$document['contact_name'],
            'contributor' => (string)$document['contributor'],
            'address' => (string)$document['address'],
            'telephone' => (string)($document['telephone'] ?? ''),
            'email' => (string)($document['email'] ?? ''),
            'po_box' => (string)($document['po_box'] ?? ''),
            'country' => (string)($document['country'] ?? ''),
            'city' => (string)($document['city'] ?? ''),
        ],
        'items' => $items,
    ];

    $pdo->commit();
    echo json_encode(['success' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $code = in_array($e->getCode(), [404, 422], true) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $code === 500 ? 'Não foi possível carregar o documento de origem.' : $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
