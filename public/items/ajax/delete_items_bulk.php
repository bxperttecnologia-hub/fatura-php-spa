<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');
session_start();

try {

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido.');
    }

    $companyId = (int) ($_SESSION['user']['company_id'] ?? 0);
    $ids = $_POST['ids'] ?? [];

    if ($companyId <= 0) {
        throw new Exception('Sessão inválida.');
    }

    if (!is_array($ids) || empty($ids)) {
        throw new Exception('Nenhum item selecionado.');
    }

    $ids = array_map('intval', $ids);

    $pdo->beginTransaction();

    $deleted = [];
    $blocked = [];

    foreach ($ids as $itemId) {

        // =========================
        // 1. VERIFICAR SE ITEM EXISTE
        // =========================
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM items 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$itemId, $companyId]);

        if ($stmt->fetchColumn() == 0) {
            $blocked[] = [
                "id" => $itemId,
                "reason" => "Item não encontrado"
            ];
            continue;
        }

        // =========================
        // 2. VERIFICAR PROFORMA
        // =========================
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM proforma_items 
            WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);

        $inProforma = $stmt->fetchColumn();

        // =========================
        // 3. VERIFICAR INVOICE
        // =========================
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM invoice_items 
            WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);

        $inInvoice = $stmt->fetchColumn();

        if ($inProforma > 0 || $inInvoice > 0) {
            $blocked[] = [
                "id" => $itemId,
                "reason" => "Associado a documentos"
            ];
            continue;
        }

        // =========================
        // 4. BUSCAR STOCK
        // =========================
        $stmt = $pdo->prepare("
            SELECT si.stock_id, si.quantity, si.item_id, s.company_id
            FROM stock_items si
            INNER JOIN stocks s ON s.id = si.stock_id
            WHERE si.item_id = ?
        ");
        $stmt->execute([$itemId]);

        $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // =========================
        // 5. REGISTAR MOVIMENTOS
        // =========================
        $stmtInsert = $pdo->prepare("
            INSERT INTO stock_movements 
            (company_id, item_id, stock_id, type, quantity, reference_id)
            VALUES (?, ?, ?, 'delete', ?, ?)
        ");

        foreach ($stocks as $row) {
            $stmtInsert->execute([
                $row['company_id'],
                $row['item_id'],
                $row['stock_id'],
                $row['quantity'],
                $itemId
            ]);
        }

        // =========================
        // 6. DELETE STOCK
        // =========================
        $stmt = $pdo->prepare("
            DELETE FROM stock_items 
            WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);

        // =========================
        // 7. DELETE ITEM
        // =========================
        $stmt = $pdo->prepare("
            DELETE FROM items 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$itemId, $companyId]);

        $deleted[] = $itemId;
    }

    $pdo->commit();

    echo json_encode([
        "success" => count($deleted) > 0,
        "message" => count($deleted) . " item(s) removidos com sucesso!",
        "deleted" => $deleted,
        "blocked" => $blocked
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(200);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
