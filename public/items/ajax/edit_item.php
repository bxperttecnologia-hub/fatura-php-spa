<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido.");
    }

    // =========================
    // INPUTS
    // =========================
    $id = (int)($_POST['id'] || $_POST['item_id'] || $_POST['product_id'] ?? 0);
    $id_company = (int)($_POST['id_company'] ?? 0);

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['descricao'] ?? '');

    $item_type = $_POST['item_type'] ?? 'product';
    $unit_measure = $_POST['unit_measure'] ?? 'unit';

    $stock_id = !empty($_POST['stock_id']) ? (int)$_POST['stock_id'] : null;
    $quantity = (int)($_POST['quantidade'] ?? 0);
    $min_quantity = (int)($_POST['min_stock'] ?? 1);

    $currency = $_POST['currency'] ?? 'AOA';

    // =========================
    // NORMALIZAÇÃO TAX
    // =========================
    $taxRaw = $_POST['tax'] ?? '0';
    $taxRaw = str_replace('%', '', $taxRaw);
    $taxRaw = str_replace(',', '.', $taxRaw);
    $tax = is_numeric($taxRaw) ? (float)$taxRaw : 0;

    // =========================
    // NORMALIZAÇÃO RETENTION
    // =========================
    $retentionRaw = $_POST['retention'] ?? 0;

    if (is_numeric($retentionRaw)) {
        $retention = (float)$retentionRaw;
    } elseif (strtolower($retentionRaw) === 'apply') {
        $retention = 6.5;
    } else {
        $retention = 0;
    }

    // proteção decimal
    $tax = round($tax, 2);
    $retention = round($retention, 2);

    // =========================
    // PREÇOS
    // =========================
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $sale_price = (float)($_POST['sale_price'] ?? 0);
    $pvp = (float)($_POST['pvp'] ?? 0);

    // =========================
    // VALIDATION
    // =========================
    if ($id <= 0) {
        throw new Exception("ID inválido.");
    }

    if ($id_company <= 0) {
        throw new Exception("Empresa inválida.");
    }

    if ($name === '') {
        throw new Exception("Nome é obrigatório.");
    }

    // =========================
    // REGRA: código NÃO editável
    // =========================
    if ($item_type === 'product') {

        $checkCode = $pdo->prepare("SELECT code FROM items WHERE id = ?");
        $checkCode->execute([$id]);
        $existingCode = $checkCode->fetchColumn();

        if (!$existingCode) {
            throw new Exception("Código do produto não encontrado.");
        }
    }

    // =========================
    // UPDATE ITEM
    // =========================
    $sql = "UPDATE items SET
        id_company = :id_company,
        name = :name,
        description = :description,
        item_type = :item_type,
        unit_measure = :unit_measure,
        currency = :currency,
        tax = :tax,
        retention = :retention,
        unit_price = :unit_price,
        cost_price = :cost_price,
        sale_price = :sale_price,
        pvp = :pvp
        WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $executed = $stmt->execute([
        ':id' => $id,
        ':id_company' => $id_company,
        ':name' => $name,
        ':description' => $description,
        ':item_type' => $item_type,
        ':unit_measure' => $unit_measure,
        ':currency' => $currency,
        ':tax' => $tax,
        ':retention' => $retention,
        ':unit_price' => $unit_price,
        ':cost_price' => $cost_price,
        ':sale_price' => $sale_price,
        ':pvp' => $pvp
    ]);

    if (!$executed) {
        $error = $stmt->errorInfo();
        throw new Exception("Erro ao atualizar item: " . $error[2]);
    }

    // =========================
    // STOCK
    // =========================
    if ($stock_id !== null) {

        $check = $pdo->prepare("SELECT id FROM stock_items WHERE item_id = ?");
        $check->execute([$id]);

        if ($check->fetch()) {
            $ok = $pdo->prepare("
                UPDATE stock_items 
                SET stock_id = ?, quantity = ?, min_quantity = ?
                WHERE item_id = ?
            ")->execute([$stock_id, $quantity, $min_quantity, $id]);
        } else {
            $ok = $pdo->prepare("
                INSERT INTO stock_items (stock_id, item_id, quantity, min_quantity)
                VALUES (?, ?, ?, ?)
            ")->execute([$stock_id, $id, $quantity, $min_quantity]);
        }

        if (!$ok) {
            throw new Exception("Erro ao atualizar stock.");
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Item atualizado com sucesso."
    ]);
} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
