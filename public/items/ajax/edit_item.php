<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php'; // exige sessão iniciada (401/403 em JSON)
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido.");
    }

    // =========================
    // INPUTS
    // =========================
    // Antes: ($_POST['id'] || ...) é um OU lógico -> true -> (int) 1, ou seja, editava sempre o item 1.
    $id = (int)($_POST['id'] ?? $_POST['item_id'] ?? $_POST['product_id'] ?? 0);
    // A empresa vem da sessão; o id_company do formulário não é de confiança.
    $id_company = session_company_id();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['descricao'] ?? '');

    $item_type = $_POST['item_type'] ?? 'product';
    $unit_measure = $_POST['unit_measure'] ?? 'unit';

    $currency = $_POST['currency'] ?? 'AOA';

    // =========================
    // NORMALIZAÇÃO TAX
    // =========================
    $taxRaw = $_POST['tax'] ?? '0';
    $taxRaw = str_replace('%', '', $taxRaw);
    $taxRaw = str_replace(',', '.', $taxRaw);
    $tax = is_numeric($taxRaw) ? (float)$taxRaw : 0;

    // proteção decimal
    $tax = round($tax, 2);

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

    $descriptionLength = function_exists('mb_strlen')
        ? mb_strlen($description, 'UTF-8')
        : strlen($description);
    if ($descriptionLength > 500) {
        throw new Exception("A descrição não pode exceder 500 caracteres.");
    }

    // =========================
    // REGRA: código NÃO editável
    // =========================
    $owned = $pdo->prepare("SELECT 1 FROM items WHERE id = ? AND company_id = ?");
    $owned->execute([$id, $id_company]);
    if (!$owned->fetchColumn()) {
        throw new Exception("Item não encontrado.");
    }

    if ($stock_id !== null) {
        $ownedStock = $pdo->prepare("SELECT 1 FROM stocks WHERE id = ? AND company_id = ?");
        $ownedStock->execute([$stock_id, $id_company]);
        if (!$ownedStock->fetchColumn()) {
            throw new Exception("Armazém inválido.");
        }
    }

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
        name = :name,
        description = :description,
        item_type = :item_type,
        unit_measure = :unit_measure,
        currency = :currency,
        tax = :tax,
        unit_price = :unit_price,
        cost_price = :cost_price,
        sale_price = :sale_price,
        pvp = :pvp
        WHERE id = :id AND company_id = :id_company";

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
        ':unit_price' => $unit_price,
        ':cost_price' => $cost_price,
        ':sale_price' => $sale_price,
        ':pvp' => $pvp
    ]);

    if (!$executed) {
        $error = $stmt->errorInfo();
        throw new Exception("Erro ao atualizar item: " . $error[2]);
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
