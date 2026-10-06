<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');
session_start();

$item_id = (int)($_POST['item_id'] ?? 0);
$from_stock = (int)($_POST['from_stock'] ?? 0);
$to_stock = (int)($_POST['to_stock'] ?? 0);
$qtd = (int)($_POST['quantidade'] ?? 0);
$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$item_id || !$from_stock || !$to_stock || !$qtd || !$company_id) {
  echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
  return;
}

// Consulta item original
$stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ? AND stock_id = ?");
$stmt->execute([$item_id, $from_stock]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item || $item['quantity'] < $qtd) {
  echo json_encode(['success' => false, 'message' => 'Quantidade inválida']);
  return;
}

// Se a quantidade for igual, move o item inteiro
if ($item['quantity'] == $qtd) {
  $stmt = $pdo->prepare("UPDATE stock_items SET stock_id = ? WHERE id = ?");
  $success = $stmt->execute([$to_stock, $item_id]);
} else {
  // Atualiza item original com nova quantidade
  $stmt1 = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
  $stmt1->execute([$qtd, $item_id]);

  // Insere nova entrada no estoque de destino
  $stmt2 = $pdo->prepare("INSERT INTO stock_items (stock_id, name, category, quantity, unit_price, currency)
                          VALUES (?, ?, ?, ?, ?, ?)");
  $success = $stmt2->execute([
    $to_stock,
    $item['name'],
    $item['category'],
    $qtd,
    $item['unit_price'],
    $item['currency']
  ]);
}

if ($success) {
    $user = $_SESSION['user']['name'] ?? 'Sistema';
    // Atualiza stock de origem
    $stmtUpd = $pdo->prepare("UPDATE stocks SET updated_at = NOW(), updated_by = ? WHERE id = ?");
    $stmtUpd->execute([$user, $from_stock]);
    // Atualiza stock de destino
    $stmtUpd->execute([$user, $to_stock]);
}

echo json_encode(['success' => $success]);
