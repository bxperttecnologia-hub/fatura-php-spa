<?php

function updateStockQuantity($pdo, $stockId, $itemId, $quantityChange)
{
    $check = $pdo->prepare("SELECT id, quantity FROM stock_items WHERE stock_id = ? AND item_id = ?");
    $check->execute([$stockId, $itemId]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $newQty = (int)$existing['quantity'] + (int)$quantityChange;

        $update = $pdo->prepare("UPDATE stock_items SET quantity = ? WHERE id = ?");
        $update->execute([$newQty, $existing['id']]);
    } else {
        $insert = $pdo->prepare("INSERT INTO stock_items (stock_id, item_id, quantity) VALUES (?, ?, ?)");
        $insert->execute([$stockId, $itemId, $quantityChange]);
    }
}
