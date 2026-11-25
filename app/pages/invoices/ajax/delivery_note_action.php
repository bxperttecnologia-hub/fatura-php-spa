<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../../app/helpers/document_workflow.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') delivery_workflow_fail($pdo, 405, 'Método inválido.');
$id = (int)($_POST['id'] ?? 0);
$action = strtolower(trim((string)($_POST['action'] ?? '')));
$companyId = session_company_id();
$userId = (int)($_SESSION['user']['id'] ?? 0) ?: null;
if (!$id) delivery_workflow_fail($pdo, 422, 'Nota inválida.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM delivery_notes WHERE id = ? AND company_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$id, $companyId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) delivery_workflow_fail($pdo, 404, 'Nota de entrega não encontrada.');
    $oldStatus = (string)$note['delivery_status'];

    if ($action === 'deliver' || $action === 'entregue') {
        $requested = $_POST['items'] ?? null;
        $partial = is_array($requested);
        $items = $pdo->prepare('SELECT * FROM delivery_note_items WHERE delivery_note_id = ?');
        $items->execute([$id]);
        $anyPartial = false;
        foreach ($items->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $remaining = (float)$item['quantity'] - (float)$item['delivered_quantity'] - (float)$item['returned_quantity'];
            $delivered = $partial ? (float)($requested[(string)$item['id']] ?? 0) : $remaining;
            if ($delivered < 0 || $delivered > $remaining) delivery_workflow_fail($pdo, 422, 'Quantidade entregue inválida.');
            if ($delivered > 0) {
                $pdo->prepare('UPDATE delivery_note_items SET delivered_quantity = delivered_quantity + ? WHERE id = ?')
                    ->execute([$delivered, (int)$item['id']]);
            }
            if ($delivered < $remaining) $anyPartial = true;
        }
        $newStatus = $anyPartial ? 'entrega_parcial' : 'entregue';
        $pdo->prepare('UPDATE delivery_notes SET delivery_status = ?, delivered_at = NOW(), receiver_name = ?, receiver_document = ?, updated_at = NOW() WHERE id = ?')
            ->execute([
                $newStatus,
                trim((string)($_POST['receiver_name'] ?? '')) ?: null,
                trim((string)($_POST['receiver_document'] ?? '')) ?: null,
                $id,
            ]);
        delivery_workflow_event($pdo, $companyId, 'nota_entrega', $id, 'ENTREGA', $oldStatus, $newStatus, $_POST, $userId);
    } elseif ($action === 'refuse' || $action === 'recusada') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') delivery_workflow_fail($pdo, 422, 'Informe o motivo da recusa.');
        $pdo->prepare('UPDATE delivery_notes SET delivery_status = ?, delivered_at = NOW(), updated_at = NOW(), notes = CONCAT(COALESCE(notes, \'\'), ?) WHERE id = ?')
            ->execute(['nao_entregue', "\nRecusa: " . $reason, $id]);
        delivery_workflow_event($pdo, $companyId, 'nota_entrega', $id, 'RECUSADA', $oldStatus, 'nao_entregue',
            ['reason' => $reason], $userId);
        $newStatus = 'nao_entregue';
    } elseif ($action === 'return' || $action === 'devolucao') {
        $itemId = (int)($_POST['delivery_note_item_id'] ?? 0);
        $qty = (float)($_POST['quantity'] ?? 0);
        $itemStmt = $pdo->prepare('SELECT * FROM delivery_note_items WHERE id = ? AND delivery_note_id = ? LIMIT 1');
        $itemStmt->execute([$itemId, $id]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) delivery_workflow_fail($pdo, 404, 'Item da nota não encontrado.');
        $available = (float)$item['delivered_quantity'] - (float)$item['returned_quantity'];
        if ($qty <= 0 || $qty > $available) delivery_workflow_fail($pdo, 422, 'Quantidade devolvida inválida.');
        $pdo->prepare('UPDATE delivery_note_items SET returned_quantity = returned_quantity + ? WHERE id = ?')
            ->execute([$qty, $itemId]);
        $destinationStockId = (int)($note['origin_warehouse_id'] ?? 0);
        if ($destinationStockId && (int)$item['item_id'] > 0) {
            $stockStmt = $pdo->prepare('SELECT id, quantity FROM stock_items WHERE stock_id = ? AND item_id = ? LIMIT 1 FOR UPDATE');
            $stockStmt->execute([$destinationStockId, (int)$item['item_id']]);
            $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);
            if ($stock) {
                $pdo->prepare('UPDATE stock_items SET quantity = quantity + ? WHERE id = ?')->execute([$qty, (int)$stock['id']]);
            } else {
                $pdo->prepare('INSERT INTO stock_items (stock_id, item_id, quantity) VALUES (?, ?, ?)')->execute([$destinationStockId, (int)$item['item_id'], $qty]);
            }
            $pdo->prepare(
                'INSERT INTO stock_movements (company_id, stock_id, item_id, movement_type, quantity, reference_type, reference_id, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$companyId, $destinationStockId, (int)$item['item_id'], 'devolucao', $qty, 'nota_entrega', $id, 'Devolução parcial/total', $userId]);
        }
        delivery_workflow_event($pdo, $companyId, 'nota_entrega', $id, 'DEVOLVIDA', $oldStatus, $oldStatus,
            ['item_id' => $itemId, 'quantity' => $qty], $userId);
        $newStatus = $oldStatus;
    } elseif ($action === 'cancel' || $action === 'cancelar') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') delivery_workflow_fail($pdo, 422, 'Informe o motivo do cancelamento.');
        if ($oldStatus === 'entregue' || $oldStatus === 'entrega_parcial') delivery_workflow_fail($pdo, 422, 'Nota parcialmente ou totalmente entregue não pode ser cancelada.');
        $pdo->prepare('UPDATE delivery_notes SET status = ?, delivery_status = ?, cancelled_at = NOW(), cancelled_by = ?, updated_at = NOW() WHERE id = ?')
            ->execute(['cancelado', 'cancelado', $userId, $id]);
        delivery_workflow_event($pdo, $companyId, 'nota_entrega', $id, 'CANCELADA', $oldStatus, 'cancelado',
            ['reason' => $reason], $userId);
        $newStatus = 'cancelado';
    } else {
        delivery_workflow_fail($pdo, 422, 'Acção desconhecida.');
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $id, 'status' => $newStatus], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    delivery_workflow_fail($pdo, 500, 'Não foi possível concluir a operação.');
}
