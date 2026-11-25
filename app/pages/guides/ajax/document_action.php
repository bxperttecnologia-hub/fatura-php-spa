<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../../app/helpers/document_workflow.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') delivery_workflow_fail($pdo, 405, 'Método inválido.');

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$id = (int)($_POST['id'] ?? 0);
$companyId = session_company_id();
$userId = (int)($_SESSION['user']['id'] ?? 0) ?: null;
if (!$id || !$companyId) delivery_workflow_fail($pdo, 422, 'Documento inválido.');

try {
    $pdo->beginTransaction();
    $guide = $pdo->prepare('SELECT * FROM guides WHERE id = ? AND company_id = ? LIMIT 1 FOR UPDATE');
    $guide->execute([$id, $companyId]);
    $row = $guide->fetch(PDO::FETCH_ASSOC);
    if (!$row) delivery_workflow_fail($pdo, 404, 'Guia não encontrada.');

    $oldStatus = (string)($row['status'] ?? 'rascunho');
    $oldTransport = (string)($row['transport_status'] ?? 'em_preparacao');
    if ($action === 'emit' || $action === 'emitir') {
        if ($oldStatus === 'cancelado') delivery_workflow_fail($pdo, 422, 'Documento cancelado não pode ser emitido.');
        if ($oldStatus === 'emitido') {
            echo json_encode(['success' => true, 'id' => $id, 'status' => 'emitido']);
            $pdo->rollBack();
            exit;
        }
        $pdo->prepare('UPDATE guides SET status = ?, emitted_at = NOW(), updated_at = NOW() WHERE id = ?')
            ->execute(['emitido', $id]);
        delivery_workflow_event($pdo, $companyId, 'guia', $id, 'EMITIDA', $oldStatus, 'emitido', [], $userId);
        $newStatus = 'emitido';
    } elseif ($action === 'depart' || $action === 'transito') {
        if ($oldStatus !== 'emitido') delivery_workflow_fail($pdo, 422, 'A guia tem de estar emitida antes da expedição.');
        if ($oldTransport === 'em_transito') {
            echo json_encode(['success' => true, 'id' => $id, 'status' => 'em_transito']);
            $pdo->rollBack();
            exit;
        }
        $originStockId = (int)($row['origin_warehouse_id'] ?? 0);
        if (!$originStockId) delivery_workflow_fail($pdo, 422, 'Informe o armazém de origem antes da expedição.');
        $items = $pdo->prepare('SELECT gi.*, i.name AS item_name FROM guide_items gi LEFT JOIN items i ON i.id = gi.item_id WHERE gi.guide_id = ?');
        $items->execute([$id]);
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $itemId = (int)$item['item_id'];
            $qty = (float)$item['quantity'];
            $stock = $pdo->prepare('SELECT id, quantity FROM stock_items WHERE stock_id = ? AND item_id = ? LIMIT 1 FOR UPDATE');
            $stock->execute([$originStockId, $itemId]);
            $stockRow = $stock->fetch(PDO::FETCH_ASSOC);
            if (!$stockRow || (float)$stockRow['quantity'] < $qty) delivery_workflow_fail($pdo, 422, 'Stock insuficiente para expedir o item ' . $itemId . '.');
            $pdo->prepare('UPDATE stock_items SET quantity = quantity - ? WHERE id = ?')
                ->execute([$qty, (int)$stockRow['id']]);
            $pdo->prepare(
                'INSERT INTO stock_movements
                 (company_id, stock_id, item_id, movement_type, quantity, source_stock_id,
                  destination_stock_id, reference_type, reference_id, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $companyId, $originStockId, $itemId, 'saida', $qty, $originStockId,
                (int)($row['destination_warehouse_id'] ?? 0) ?: null,
                'guia', $id, 'Expedição da guia ' . $id, $userId,
            ]);
            $pdo->prepare('UPDATE guide_items SET in_transit_quantity = quantity WHERE id = ?')->execute([(int)$item['id']]);
        }
        $departureAt = trim((string)($_POST['departure_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE guides SET transport_status = ?, departure_at = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['em_transito', $departureAt, $id]);
        delivery_workflow_event($pdo, $companyId, 'guia', $id, 'EXPEDIDA', $oldTransport, 'em_transito',
            ['origin_warehouse_id' => $originStockId, 'departure_at' => $departureAt], $userId);
        $newStatus = 'em_transito';
    } elseif ($action === 'deliver' || $action === 'entregue') {
        if ($oldTransport === 'cancelado') delivery_workflow_fail($pdo, 422, 'Guia cancelada.');
        $receiverName = trim((string)($_POST['receiver_name'] ?? ''));
        $receiverDocument = trim((string)($_POST['receiver_document'] ?? ''));
        $partial = $_POST['partial'] ?? null;
        $destinationStockId = (int)($row['destination_warehouse_id'] ?? 0);
        $items = $pdo->prepare('SELECT * FROM guide_items WHERE guide_id = ?');
        $items->execute([$id]);
        $anyPartial = false;
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $qtyRemaining = (float)$item['quantity'] - (float)$item['delivered_quantity'] - (float)$item['returned_quantity'];
            $deliveredQty = $partial === null ? $qtyRemaining : (float)($_POST['items'][$item['item_id']] ?? 0);
            if ($deliveredQty < 0 || $deliveredQty > $qtyRemaining) delivery_workflow_fail($pdo, 422, 'Quantidade entregue inválida.');
            if ($deliveredQty > 0) {
                $pdo->prepare('UPDATE guide_items SET delivered_quantity = delivered_quantity + ?, in_transit_quantity = GREATEST(in_transit_quantity - ?, 0) WHERE id = ?')
                    ->execute([$deliveredQty, $deliveredQty, (int)$item['id']]);
                if ($destinationStockId) {
                    $stockCheck = $pdo->prepare('SELECT id, quantity FROM stock_items WHERE stock_id = ? AND item_id = ? LIMIT 1 FOR UPDATE');
                    $stockCheck->execute([$destinationStockId, (int)$item['item_id']]);
                    $stockRow = $stockCheck->fetch(PDO::FETCH_ASSOC);
                    if ($stockRow) {
                        $pdo->prepare('UPDATE stock_items SET quantity = quantity + ? WHERE id = ?')->execute([$deliveredQty, (int)$stockRow['id']]);
                    } else {
                        $pdo->prepare('INSERT INTO stock_items (stock_id, item_id, quantity) VALUES (?, ?, ?)')->execute([$destinationStockId, (int)$item['item_id'], $deliveredQty]);
                    }
                    $pdo->prepare(
                        'INSERT INTO stock_movements (company_id, stock_id, item_id, movement_type, quantity, source_stock_id, destination_stock_id, reference_type, reference_id, notes, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    )->execute([
                        $companyId, $destinationStockId, (int)$item['item_id'], 'entrada', $deliveredQty,
                        (int)($row['origin_warehouse_id'] ?? 0) ?: null, $destinationStockId,
                        'guia', $id, 'Recepção da guia ' . $id, $userId,
                    ]);
                }
            }
            if ($deliveredQty < $qtyRemaining) $anyPartial = true;
        }
        $transportStatus = $anyPartial ? 'entrega_parcial' : 'entregue';
        $pdo->prepare('UPDATE guides SET transport_status = ?, delivered_at = NOW(), updated_at = NOW() WHERE id = ?')
            ->execute([$transportStatus, $id]);
        delivery_workflow_event($pdo, $companyId, 'guia', $id, 'ENTREGA', $oldTransport, $transportStatus,
            ['receiver_name' => $receiverName, 'receiver_document' => $receiverDocument], $userId);
        $newStatus = $transportStatus;
    } elseif ($action === 'cancel' || $action === 'cancelar') {
        if ($oldStatus === 'emitido' || $oldStatus === 'cancelado') {
            if ($oldStatus === 'cancelado') delivery_workflow_fail($pdo, 409, 'A guia já está cancelada.');
            if ($oldTransport === 'em_transito' || $oldTransport === 'entregue' || $oldTransport === 'entrega_parcial') {
                delivery_workflow_fail($pdo, 422, 'Não é possível cancelar uma guia já expedida ou entregue.');
            }
        }
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') delivery_workflow_fail($pdo, 422, 'Informe o motivo do cancelamento.');
        $pdo->prepare('UPDATE guides SET status = ?, cancelled_at = NOW(), cancelled_by = ?, updated_at = NOW(), transport_status = ? WHERE id = ?')
            ->execute(['cancelado', $userId, 'cancelado', $id]);
        delivery_workflow_event($pdo, $companyId, 'guia', $id, 'CANCELADA', $oldStatus, 'cancelado',
            ['reason' => $reason], $userId);
        $newStatus = 'cancelado';
    } else {
        delivery_workflow_fail($pdo, 422, 'Acção desconhecida.');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $id, 'status' => $newStatus], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (isset($e->errorInfo) && is_array($e->errorInfo)) {
        delivery_workflow_fail($pdo, 500, 'Não foi possível concluir a operação.');
    }
    delivery_workflow_fail($pdo, 500, 'Não foi possível concluir a operação.');
}
