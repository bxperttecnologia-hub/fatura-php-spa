<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/document_workflow.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') delivery_workflow_fail($pdo, 405, 'Método inválido.');
$data = $_POST['guide'] ?? null;
$items = $_POST['items'] ?? null;
if (is_string($data)) $data = json_decode($data, true);
if (is_string($items)) $items = json_decode($items, true);
if (!is_array($data) || !is_array($items) || !$items) delivery_workflow_fail($pdo, 422, 'Dados da guia ou itens inválidos.');

$companyId = session_company_id();
$contactId = (int)($data['contact_id'] ?? 0);
$series = trim((string)($data['serie'] ?? date('Y')));
$originAddress = trim((string)($data['endereco_carga'] ?? ''));
$destinationAddress = trim((string)($data['endereco_entrega'] ?? ''));
if (!$contactId || !$companyId || $series === '' || $originAddress === '' || $destinationAddress === '') {
    delivery_workflow_fail($pdo, 422, 'Preencha destinatário, série, origem e destino.');
}

try {
    $pdo->beginTransaction();
    $check = $pdo->prepare('SELECT 1 FROM contact WHERE id = ? AND company_id = ? LIMIT 1');
    $check->execute([$contactId, $companyId]);
    if (!$check->fetchColumn()) delivery_workflow_fail($pdo, 404, 'Destinatário não encontrado.');

    $originType = strtolower(trim((string)($data['documento_origem_tipo'] ?? '')));
    $originId = (int)($data['documento_origem_id'] ?? 0);
    if ($originType !== '' && $originId > 0) {
        $seen = [];
        if ($originType === 'factura') {
            $docStmt = $pdo->prepare('SELECT contact_id, status FROM invoices WHERE id = ? AND company_id = ? LIMIT 1 FOR UPDATE');
            $docStmt->execute([$originId, $companyId]);
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) delivery_workflow_fail($pdo, 404, 'Documento de origem não encontrado.');
            if ((int)$doc['contact_id'] !== $contactId) delivery_workflow_fail($pdo, 422, 'O documento pertence a outro cliente.');
            $itemStmt = $pdo->prepare('
                SELECT ii.id, ii.item_id, ii.quantity,
                       COALESCE((SELECT SUM(dni.quantity) FROM delivery_note_items dni
                                 JOIN delivery_notes dn ON dn.id = dni.delivery_note_id
                                 WHERE dn.invoice_id = ii.invoice_id
                                   AND dni.invoice_item_id = ii.id
                                   AND dn.status <> "cancelado"), 0) AS delivered
                FROM invoice_items ii
                WHERE ii.invoice_id = ?
                FOR UPDATE
            ');
            $itemStmt->execute([$originId]);
            $originItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $docStmt = $pdo->prepare('SELECT contact_id FROM proformas WHERE id = ? AND company_id = ? LIMIT 1 FOR UPDATE');
            $docStmt->execute([$originId, $companyId]);
            $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) delivery_workflow_fail($pdo, 404, 'Documento de origem não encontrado.');
            if ((int)$doc['contact_id'] !== $contactId) delivery_workflow_fail($pdo, 422, 'O documento pertence a outro cliente.');
            $itemStmt = $pdo->prepare('SELECT id, item_id, quantity, 0 AS delivered FROM proforma_items WHERE proforma_id = ? FOR UPDATE');
            $itemStmt->execute([$originId]);
            $originItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($originItems as $originItem) {
            $pending = max(0, (float)$originItem['quantity'] - (float)$originItem['delivered']);
            $seen[(int)$originItem['item_id']] = $pending;
        }
        foreach ($items as $item) {
            $itemId = (int)($item['id'] ?? 0);
            $qty = (float)($item['quantity'] ?? 0);
            if (!array_key_exists($itemId, $seen)) delivery_workflow_fail($pdo, 422, 'Existe um bem que não pertence ao documento de origem.');
            if ($qty <= 0 || $qty > (float)$seen[$itemId]) delivery_workflow_fail($pdo, 422, 'Quantidade superior ao saldo pendente do documento de origem.');
        }
    }
    $numberStmt = $pdo->prepare('SELECT COALESCE(MAX(number), 0) + 1 FROM guides WHERE company_id = :company AND series = :series FOR UPDATE');
    $numberStmt->execute([':company' => $companyId, ':series' => $series]);
    $number = (int)$numberStmt->fetchColumn();
    $insert = $pdo->prepare(
        'INSERT INTO guides (
            company_id, contact_id, number, status, transport_status, user_id,
            vehicle_plate, cargo_date, transport_reason, observations, retention,
            cargo_address, cargo_city, cargo_po_box, delivery_address, delivery_city, delivery_po_box,
            document_date, reference, series, currency,
            transporter_name, transporter_document, driver_name, driver_document, driver_phone,
            vehicle_description, departure_at, origin_warehouse_id, destination_warehouse_id,
            origin_document_type, origin_document_id, invoice_id, proforma_id,
            total_sum, total_discount, subtotal_without_tax, total_tax, retention_value, final_total, created_at
        ) VALUES (
            :company_id, :contact_id, :number, :status, :transport_status, :user_id,
            :vehicle_plate, :cargo_date, :transport_reason, :observations, :retention,
            :cargo_address, :cargo_city, :cargo_po_box, :delivery_address, :delivery_city, :delivery_po_box,
            :document_date, :reference, :series, :currency,
            :transporter_name, :transporter_document, :driver_name, :driver_document, :driver_phone,
            :vehicle_description, :departure_at, :origin_warehouse_id, :destination_warehouse_id,
            :origin_document_type, :origin_document_id, :invoice_id, :proforma_id,
            :total_sum, :total_discount, :subtotal_without_tax, :total_tax, :retention_value, :final_total, NOW()
        )'
    );
    $insert->execute([
        ':company_id' => $companyId, ':contact_id' => $contactId, ':number' => $number,
        ':status' => 'rascunho', ':transport_status' => 'em_preparacao',
        ':user_id' => (int)($_SESSION['user']['id'] ?? 0) ?: null,
        ':vehicle_plate' => trim((string)($data['matricula'] ?? '')) ?: null,
        ':cargo_date' => $data['data_carga'] ?? date('Y-m-d'),
        ':transport_reason' => trim((string)($data['motivo_transporte'] ?? '')) ?: null,
        ':observations' => trim((string)($data['observacoes'] ?? '')) ?: null,
        ':retention' => (float)($data['retencao'] ?? 0),
        ':cargo_address' => $originAddress, ':cargo_city' => trim((string)($data['cidade_carga'] ?? '')) ?: '',
        ':cargo_po_box' => trim((string)($data['caixa_postal_carga'] ?? '')) ?: null,
        ':delivery_address' => $destinationAddress, ':delivery_city' => trim((string)($data['cidade_entrega'] ?? '')) ?: '',
        ':delivery_po_box' => trim((string)($data['caixa_postal_entrega'] ?? '')) ?: null,
        ':document_date' => $data['data_documento'] ?? date('Y-m-d'),
        ':reference' => trim((string)($data['referencia'] ?? '')) ?: null,
        ':series' => $series, ':currency' => trim((string)($data['moeda'] ?? 'AOA')),
        ':transporter_name' => trim((string)($data['transportador'] ?? '')) ?: null,
        ':transporter_document' => trim((string)($data['transportador_nif'] ?? '')) ?: null,
        ':driver_name' => trim((string)($data['motorista'] ?? '')) ?: null,
        ':driver_document' => trim((string)($data['motorista_documento'] ?? '')) ?: null,
        ':driver_phone' => trim((string)($data['motorista_contacto'] ?? '')) ?: null,
        ':vehicle_description' => trim((string)($data['veiculo'] ?? '')) ?: null,
        ':departure_at' => $data['inicio_transporte'] ?? null,
        ':origin_warehouse_id' => (int)($data['armazem_origem'] ?? 0) ?: null,
        ':destination_warehouse_id' => (int)($data['armazem_destino'] ?? 0) ?: null,
        ':origin_document_type' => $originType !== '' ? $originType : null,
        ':origin_document_id' => $originId > 0 ? $originId : null,
        ':invoice_id' => $originType === 'factura' ? $originId : ((int)($data['invoice_id'] ?? 0) ?: null),
        ':proforma_id' => $originType === 'proforma' ? $originId : ((int)($data['proforma_id'] ?? 0) ?: null),
        ':total_sum' => (float)($data['total_sum'] ?? 0), ':total_discount' => (float)($data['total_discount'] ?? 0),
        ':subtotal_without_tax' => (float)($data['subtotal_without_tax'] ?? 0),
        ':total_tax' => (float)($data['total_tax'] ?? 0),
        ':retention_value' => (float)($data['retention_value'] ?? 0),
        ':final_total' => (float)($data['final_total'] ?? 0),
    ]);
    $guideId = (int)$pdo->lastInsertId();
    $itemInsert = $pdo->prepare('INSERT INTO guide_items (guide_id, item_id, description, unit_price, quantity, tax, discount) VALUES (?, ?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $itemId = (int)($item['id'] ?? 0);
        $quantity = (float)($item['quantity'] ?? 0);
        if (!$itemId || $quantity <= 0) delivery_workflow_fail($pdo, 422, 'Item ou quantidade inválida.');
        $itemInsert->execute([$guideId, $itemId, trim((string)($item['description'] ?? '')), (float)($item['unit_price'] ?? 0), $quantity, (string)($item['tax'] ?? ''), (float)($item['discount'] ?? 0)]);
    }
    delivery_workflow_event($pdo, $companyId, 'guia', $guideId, 'CRIADA', null, 'rascunho', ['series' => $series, 'number' => $number], (int)($_SESSION['user']['id'] ?? 0) ?: null);
    $pdo->commit();
    echo json_encode(['success' => true, 'guide_id' => $guideId, 'number' => $number, 'display_number' => $series . ' ' . $number], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    delivery_workflow_fail($pdo, 500, 'Não foi possível guardar a guia.');
}
