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
    $itemId    = (int) ($_POST['id'] ?? 0);

    if ($companyId <= 0 || $itemId <= 0) {
        throw new Exception('Dados inválidos.');
    }

    $pdo->beginTransaction();

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
        throw new Exception('Item não encontrado.');
    }

    // =========================
    // 2. VERIFICAR SE ESTÁ EM PROFORMA
    // =========================
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM proforma_items 
        WHERE item_id = ?
    ");
    $stmt->execute([$itemId]);

    $inProforma = $stmt->fetchColumn();

    // =========================
    // 3. VERIFICAR SE ESTÁ EM INVOICE
    // =========================
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM invoice_items 
        WHERE item_id = ?
    ");
    $stmt->execute([$itemId]);

    $inInvoice = $stmt->fetchColumn();

    if ($inProforma > 0 || $inInvoice > 0) {
        throw new Exception('Não é possível eliminar este item porque está associado a documentos.');
    }

    // =========================
    // 4. BUSCAR STOCKS DO ITEM
    // =========================
    $stmt = $pdo->prepare("
        SELECT si.stock_id, si.quantity, si.item_id, s.company_id
        FROM stock_items si
        INNER JOIN stocks s ON s.id = si.stock_id
        WHERE si.item_id = ?
    ");
    $stmt->execute([$itemId]);

    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // // =========================
    // // 5. REGISTAR MOVIMENTOS
    // // =========================
    // $stmtInsert = $pdo->prepare("
    //     INSERT INTO stock_movements 
    //     (company_id, item_id, stock_id, movement_type, quantity, reference_id)
    //     VALUES (?, ?, ?, 'delete', ?, ?)
    // ");

    // foreach ($stocks as $row) {
    //     $stmtInsert->execute([
    //         $row['company_id'],
    //         $row['item_id'],
    //         $row['stock_id'],
    //         $row['quantity'],
    //         $itemId
    //     ]);
    // }

    // =========================
    // 6. APAGAR STOCK
    // =========================
    $stmt = $pdo->prepare("
        DELETE FROM stock_items 
        WHERE item_id = ?
    ");
    $stmt->execute([$itemId]);

    // =========================
    // 7. APAGAR ITEM
    // =========================
    $stmt = $pdo->prepare("
        DELETE FROM items 
        WHERE id = ? AND company_id = ?
    ");
    $stmt->execute([$itemId, $companyId]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Item removido com sucesso!'
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(200);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
