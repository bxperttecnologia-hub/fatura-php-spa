<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/config/db.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

// =========================
// VALIDAR MÉTODO
// =========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

  http_response_code(405);

  echo json_encode([
    "status"  => "error",
    "message" => "Método inválido"
  ]);

  exit;
}

try {

  // =========================
  // PDO
  // =========================
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // =========================
  // INPUTS
  // =========================
  $item_id = isset($_POST['product_id'])
    ? (int)$_POST['product_id']
    : 0;

  $company_id = (int)($_POST['id_company'] ?? 0);

  $stock_id = !empty($_POST['stock_id'])
    ? (int)$_POST['stock_id']
    : null;

  $code = trim($_POST['codigo'] ?? '');

  $name = trim($_POST['name'] ?? '');

  $description = trim($_POST['descricao'] ?? '');

  $item_type = trim($_POST['item_type'] ?? 'product');

  $unit_measure = trim($_POST['unit_measure'] ?? 'unit');

  $category = !empty($_POST['subcategory'])
    ? trim($_POST['subcategory'])
    : null;

  $currency = trim($_POST['currency'] ?? 'AOA');


  // =========================
  // TAX
  // =========================
  $tax = trim($_POST['tax_vat'] ?? 0);


  // =========================
  // PREÇOS
  // =========================
  $unit_price = (float)($_POST['unit_price'] ?? 0);

  $cost_price = (float)($_POST['cost_price'] ?? 0);

  $sale_price = (float)($_POST['sale_price'] ?? 0);

  $pvp = (float)($_POST['pvp'] ?? 0);

  // =========================
  // RETENÇÃO
  // =========================
  $retention = isset($_POST['retention'])
    ? (float)$_POST['retention']
    : 0.0;

  // =========================
  // STOCK
  // =========================
  $quantity = (int)($_POST['quantidade'] ?? 0);

  $min_quantity = (int)($_POST['min_stock'] ?? 1);

  // =========================
  // VALIDAÇÕES
  // =========================
  if ($company_id <= 0) {
    throw new Exception("Empresa inválida.");
  }

  if ($name === '') {
    throw new Exception("O nome do item é obrigatório.");
  }

  if ($code === '') {
    throw new Exception("O código do item é obrigatório.");
  }

  // =========================
  // UPDATE
  // =========================
  if ($item_id > 0) {

    $sql = "CALL sp_update_item_with_stock(
      ?,?,?,?,?,?,?,?,?,?,
      ?,?,?,?,?,?,?,?
    )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
      $item_id,
      $company_id,
      $stock_id,
      $code,
      $name,
      $description,
      $item_type,
      $unit_measure,
      $category,
      $currency,
      $tax,

      $unit_price,
      $cost_price,
      $sale_price,
      $pvp,

      $retention,
      $quantity,
      $min_quantity
    ]);

    $message = "Item atualizado com sucesso";
  } else {

    // =========================
    // CREATE
    // =========================
    $sql = "CALL sp_create_item_with_stock(
      ?,?,?,?,?,?,?,?,?,?,
      ?,?,?,?,?,?,?
    )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
      $company_id,
      $stock_id,
      $code,
      $name,
      $description,
      $item_type,
      $unit_measure,
      $category,
      $currency,
      $tax,

      $unit_price,
      $cost_price,
      $sale_price,
      $pvp,

      $retention,
      $quantity,
      $min_quantity
    ]);

    $message = "Item criado com sucesso";
  }

  // =========================
  // RESULTADO
  // =========================
  $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  while ($stmt->nextRowset()) {
  }

  // =========================
  // RESPONSE
  // =========================
  echo json_encode([
    "status"  => "success",
    "message" => $message,
    "item_id" => $result['item_id'] ?? $item_id,
    "tax"     => $tax
  ]);
} catch (Throwable $e) {

  http_response_code(500);

  echo json_encode([
    "status"  => "error",
    "message" => $e->getMessage(),
    "debug"   => [
      "file" => $e->getFile(),
      "line" => $e->getLine()
    ]
  ]);
}
