<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');
session_start();

$stock_id = (int)($_POST['stock_id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);

if (!$stock_id || !$id) {
  echo json_encode(['success' => false, 'message' => 'ID inválido']);
  exit;
}

try {
  $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ? AND stock_id = ?");
  $success = $stmt->execute([$id, $stock_id]);
  
  if ($success) {
      $user = $_SESSION['user']['name'] ?? 'Sistema';
      $stmtUpd = $pdo->prepare("UPDATE stocks SET updated_at = NOW(), updated_by = ? WHERE id = ?");
      $stmtUpd->execute([$user, $stock_id]);
  }
  
  echo json_encode(['success' => $success]);
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
}
