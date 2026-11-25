<?php

header('Content-Type: application/json');
require_once '../../../app/config/db.php';
session_start();

try {

    /*
    =========================================
    VALIDAÇÃO DE SESSÃO
    =========================================
    */

    if (
        !isset($_SESSION['user']['company_id'])
    ) {
        echo json_encode([
            "success" => false,
            "message" => "Sessão inválida."
        ]);
        exit;
    }

    $companyId = (int) $_SESSION['user']['company_id'];

    /*
    =========================================
    OBJETIVO

    Inserir automaticamente todos os items
    da empresa em stock_items com:

    - quantity = 3 (mínimo inicial)
    - min_quantity = global_min_stock ou 1
    - stock_id = primeiro stock disponível

    Evita duplicação:
    só insere se ainda não existir vínculo

    =========================================
    */

    /*
    =========================================
    BUSCAR PRIMEIRO STOCK DISPONÍVEL
    =========================================
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM stocks
        WHERE company_id = ?
        ORDER BY id ASC
        LIMIT 1
    ");

    $stmt->execute([$companyId]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock) {
        echo json_encode([
            "success" => false,
            "message" => "Nenhum stock encontrado para esta empresa."
        ]);
        exit;
    }

    $stockId = (int) $stock['id'];

    /*
    =========================================
    INSERIR ITEMS EM STOCK_ITEMS
    =========================================
    */

    $stmt = $pdo->prepare("
        INSERT INTO stock_items (
            stock_id,
            item_id,
            quantity,
            min_quantity,
            updated_at
        )
        SELECT
            :stock_id,
            i.id,
            3,
            COALESCE(i.global_min_stock, 1),
            NOW()

        FROM items i

        LEFT JOIN stock_items si
            ON si.item_id = i.id
            AND si.stock_id = :stock_id_check

        WHERE
            i.company_id = :company_id
            AND i.track_stock = 1
            AND i.status != 'archived'
            AND si.id IS NULL
    ");

    $stmt->execute([
        'stock_id' => $stockId,
        'stock_id_check' => $stockId,
        'company_id' => $companyId
    ]);

    $insertedRows = $stmt->rowCount();

    /*
    =========================================
    RESPONSE
    =========================================
    */

    echo json_encode([
        "success" => true,
        "message" => "{$insertedRows} itens adicionados ao stock com quantidade inicial de 3.",
        "stock_id" => $stockId,
        "inserted_items" => $insertedRows
    ]);

} catch (PDOException $e) {

    echo json_encode([
        "success" => false,
        "message" => "Erro de base de dados.",
        "error" => $e->getMessage()
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Erro inesperado.",
        "error" => $e->getMessage()
    ]);
}
