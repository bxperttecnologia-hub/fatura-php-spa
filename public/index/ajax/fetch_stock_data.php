<?php

header('Content-Type: application/json');
require_once '../../../app/config/db.php';
session_start();

try {

    if (!isset($_SESSION['user']['company_id'])) {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Sessão inválida."
        ]);
        exit;
    }

    $companyId = (int) $_SESSION['user']['company_id'];

    $query = "
        SELECT 
            s.id,
            s.name,
            s.description,
            s.icon,
            s.address,
            s.city,
            s.country,

            COUNT(DISTINCT si.item_id) AS total_items,

            COALESCE(SUM(si.quantity), 0) AS total_quantity,

            COALESCE(SUM(i.unit_price * si.quantity), 0) AS total_stock_value,

            COUNT(DISTINCT CASE 
                WHEN si.quantity <= si.min_quantity THEN si.item_id 
            END) AS low_stock_items

        FROM stocks s

        LEFT JOIN stock_items si 
            ON si.stock_id = s.id

        LEFT JOIN items i 
            ON i.id = si.item_id
            AND i.company_id = s.company_id

        WHERE s.company_id = :company_id

        GROUP BY s.id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['company_id' => $companyId]);

    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($stocks),
        'data' => $stocks
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => "Erro ao carregar stocks.",
        'error' => $e->getMessage() // remove em produção
    ]);
}
