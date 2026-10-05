<?php

header('Content-Type: application/json');
require_once '../../../app/config/db.php';

$company_id = $_GET['company_id'] ?? null;

if (!$company_id) {
    echo json_encode([
        "success" => false,
        "message" => "company_id não informado."
    ]);
    exit;
}

try {

    /*
    ==================================================
    NOVA LÓGICA DE STOCK PROFISSIONAL
    ==================================================

    Ajustado para:

    - items.company_id
    - items.cost_price
    - items.sale_price
    - items.track_stock
    - items.global_min_stock

    removido:

    - stock_items.name
    - stock_items.unit_price

    agora usamos:

    stock_items + items + stocks
    */

    /*
    ==================================================
    TOTAL DEPÓSITOS
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM stocks
        WHERE company_id = ?
    ");
    $stmt->execute([$company_id]);
    $depots = (int)($stmt->fetch()['total'] ?? 0);

    /*
    ==================================================
    CRESCIMENTO DEPÓSITOS (últimos 3 meses)
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM stocks
        WHERE company_id = :company_id
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ");
    $stmt->execute([
        'company_id' => $company_id
    ]);
    $increase_depots = (int)($stmt->fetch()['total'] ?? 0);

    /*
    ==================================================
    TOTAL PRODUTOS CONTROLADOS POR STOCK
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT i.id) AS total
        FROM items i
        LEFT JOIN stock_items si
            ON si.item_id = i.id
        WHERE i.company_id = ?
        AND i.track_stock = 1
        AND i.item_type = 'product'
        AND i.status != 'archived'
    ");
    $stmt->execute([$company_id]);
    $products = (int)($stmt->fetch()['total'] ?? 0);

    /*
    ==================================================
    CRESCIMENTO PRODUTOS (últimos 3 meses)
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM items
        WHERE company_id = :company_id
        AND items.item_type = 'product'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        AND track_stock = 1
    ");
    $stmt->execute([
        'company_id' => $company_id
    ]);
    $increase_products = (int)($stmt->fetch()['total'] ?? 0);

    /*
    ==================================================
    VALOR TOTAL EM STOCK
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(
                COALESCE(si.quantity, 0) * COALESCE(i.unit_price, 0)
            ), 0) AS total
        FROM stock_items si
        INNER JOIN items i
            ON i.id = si.item_id
        INNER JOIN stocks s
            ON s.id = si.stock_id
        WHERE s.company_id = ?
        AND i.item_type = 'product'
        AND i.track_stock = 1
    ");
    $stmt->execute([$company_id]);
    $total_value = (float)($stmt->fetch()['total'] ?? 0);

    /*
==================================================
CRESCIMENTO VALOR MOVIMENTADO
==================================================

==================================================
PERÍODO ANTERIOR (3 a 6 meses)
==================================================
*/

    $stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            ABS(COALESCE(si.quantity, 0)) * COALESCE(i.cost_price, 0)
        ), 0) AS total

    FROM stock_items si

    INNER JOIN items i
        ON i.id = si.item_id

    INNER JOIN stocks s
        ON s.id = si.stock_id

    WHERE
        s.company_id = :company_id
        AND i.track_stock = 1
        AND i.item_type = 'product'
        AND i.status != 'archived'
        AND si.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND si.updated_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
");

    $stmt->execute([
        'company_id' => $company_id
    ]);

    $previous_total = (float) (
        $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
    );


    /*
==================================================
PERÍODO ATUAL (últimos 3 meses)
==================================================
*/

    $stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            ABS(COALESCE(si.quantity, 0)) * COALESCE(i.unit_price, 0)
        ), 0) AS total

    FROM stock_items si

    INNER JOIN items i
        ON i.id = si.item_id

    INNER JOIN stocks s
        ON s.id = si.stock_id

    WHERE
        s.company_id = :company_id
        AND i.track_stock = 1
        AND i.item_type = 'product'
        AND i.status != 'archived'
        AND si.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
");

    $stmt->execute([
        'company_id' => $company_id
    ]);

    $current_total = (float) (
        $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
    );


    /*
==================================================
CÁLCULO DE CRESCIMENTO (%)
==================================================
*/

    if ($previous_total > 0) {
        $growth_percent = (
            (($current_total - $previous_total) / $previous_total) * 100
        );
    } else {
        /*
    Se não havia valor anterior,
    evitamos divisão por zero
    */
        $growth_percent = $current_total > 0 ? 100 : 0;
    }

    /*
Arredondamento profissional
*/

    $growth_percent = round($growth_percent, 2);


    /*
    ==================================================
    STOCK BAIXO
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM stock_items si
        INNER JOIN items i
            ON i.id = si.item_id
        INNER JOIN stocks s
            ON s.id = si.stock_id
        WHERE s.company_id = ?
        AND i.item_type = 'product'
        AND COALESCE(si.quantity, 0)
            <= COALESCE(si.min_quantity, i.global_min_stock, 1)
    ");
    $stmt->execute([$company_id]);
    $low_stock = (int)($stmt->fetch()['total'] ?? 0);

    /*
    ==================================================
    DEPÓSITOS DETALHE
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.name,
            s.city,

            COUNT(DISTINCT si.id) AS total_items,

            COALESCE(SUM(
                COALESCE(si.quantity, 0) * COALESCE(i.unit_price, 0)
            ), 0) AS total_value

        FROM stocks s

        LEFT JOIN stock_items si
            ON si.stock_id = s.id

        LEFT JOIN items i
            ON i.id = si.item_id
            WHERE s.company_id = ?
            AND i.item_type = 'product'

        GROUP BY s.id
        ORDER BY total_value DESC
        LIMIT 3
    ");
    $stmt->execute([$company_id]);
    $depots_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==================================================
    PRODUTOS COM STOCK BAIXO
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT
            i.name,
            COALESCE(si.quantity, 0) AS quantity,
            COALESCE(si.min_quantity, i.global_min_stock, 1) AS min_quantity

        FROM stock_items si

        INNER JOIN items i
            ON i.id = si.item_id

        INNER JOIN stocks s
            ON s.id = si.stock_id
        WHERE s.company_id = ?
        AND i.item_type = 'product'
        AND COALESCE(si.quantity, 0)
            <= COALESCE(si.min_quantity, i.global_min_stock, 1)

        ORDER BY quantity ASC
        LIMIT 10
    ");
    $stmt->execute([$company_id]);
    $low_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==================================================
    LISTA DE COMPRAS SUGERIDA
    ==================================================
    */

    $stmt = $pdo->prepare("
        SELECT
            i.name,

            (
                (
                    COALESCE(si.min_quantity, i.global_min_stock, 1) * 2
                ) - COALESCE(si.quantity, 0)
            ) AS suggested_qty

        FROM stock_items si

        INNER JOIN items i
            ON i.id = si.item_id

        INNER JOIN stocks s
            ON s.id = si.stock_id

        WHERE s.company_id = ?
        AND i.item_type = 'product'
        AND COALESCE(si.quantity, 0)
            < COALESCE(si.min_quantity, i.global_min_stock, 1)

        ORDER BY suggested_qty DESC
        LIMIT 5
    ");
    $stmt->execute([$company_id]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    ==================================================
    RESPONSE FINAL
    ==================================================
    */

    echo json_encode([
        "success" => true,
        "data" => [
            "kpis" => [
                "depots" => $depots,
                "products" => $products,
                "total_value" => round($total_value, 2),
                "low_stock" => $low_stock,
                "increase_depots" => $increase_depots,
                "increase_products" => $increase_products,
                "increase_total_value" => round($growth_percent, 2)
            ],
            "depots" => $depots_list,
            "low_stock" => $low_items,
            "purchases" => $purchases
        ]
    ]);
} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
