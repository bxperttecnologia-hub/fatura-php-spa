<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');

session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {

    // =========================
    // METHOD
    // =========================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido.");
    }

    // =========================
    // PDO
    // =========================
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // =========================
    // COMPANY
    // =========================
    $companyId = (int)($_SESSION['user']['company_id'] ?? 0);

    if ($companyId <= 0) {
        throw new Exception("Empresa inválida.");
    }

    // =========================
    // ITEMS
    // =========================
    $items = $_POST['items'] ?? [];

    // suporte:
    // items: JSON.stringify(...)
    // items: [...]
    if (is_string($items)) {
        $items = json_decode($items, true);
    }

    if (!is_array($items) || empty($items)) {
        throw new Exception("Nenhum item enviado.");
    }

    // =========================
    // TRANSACTION
    // =========================
    $pdo->beginTransaction();

    // =========================
    // CHECK ITEM
    // =========================
    $checkStmt = $pdo->prepare("
        SELECT id
        FROM items
        WHERE id = ?
        AND company_id = ?
        LIMIT 1
    ");

    // =========================
    // UPDATE ITEM
    // =========================
    $updateItemStmt = $pdo->prepare("
        UPDATE items
        SET
            name = ?,
            unit_price = ?,
            tax = ?,
            pvp = ?,
            updated_at = NOW()
        WHERE id = ?
        AND company_id = ?
    ");

    // =========================
    // UPDATE STOCK
    // =========================
    $updateStockStmt = $pdo->prepare("
        UPDATE stock_items
        SET
            quantity = ?,
            min_quantity = ?
        WHERE item_id = ?
        AND EXISTS (
            SELECT 1
            FROM items
            WHERE items.id = stock_items.item_id
            AND items.company_id = ?
        )
    ");

    $updated = 0;
    $skipped = [];
    $updatedItems = [];

    // =========================
    // LOOP ITEMS
    // =========================
    foreach ($items as $item) {

        $id = (int)($item['id'] ?? 0);

        $name = trim($item['name'] ?? '');

        $tax = (float)($item['tax'] ?? 0);

        $quantity = (int)($item['quantity'] ?? 0);

        $min_quantity = (int)($item['min_quantity'] ?? 1);

        $price = (float)($item['price'] ?? $item['unit_price'] ?? 0);

        $pvp = (float)($item['pvp'] ?? 0);

        // =========================
        // VALIDATIONS
        // =========================
        if (
            $id <= 0 ||
            $name === '' ||
            $price < 0 ||
            $quantity < 0 ||
            $min_quantity < 0
        ) {
            $skipped[] = $id;
            continue;
        }

        // =========================
        // VERIFY OWNERSHIP
        // =========================
        $checkStmt->execute([
            $id,
            $companyId
        ]);

        if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
            $skipped[] = $id;
            continue;
        }

        // =========================
        // UPDATE ITEM
        // =========================
        $updateItemStmt->execute([
            $name,
            $price,
            $tax,
            $pvp,
            $id,
            $companyId
        ]);

        // =========================
        // UPDATE STOCK
        // =========================
        $updateStockStmt->execute([
            $quantity,
            $min_quantity,
            $id,
            $companyId
        ]);

        $updated++;

        $updatedItems[] = [
            "id" => $id,
            "name" => $name
        ];
    }

    // =========================
    // COMMIT
    // =========================
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "updated" => $updated,
        "skipped" => $skipped,
        "items" => $updatedItems
    ]);
} catch (Throwable $e) {

    // =========================
    // ROLLBACK
    // =========================
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    http_response_code(200);

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "debug" => [
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]
    ]);
}
