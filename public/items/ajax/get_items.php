<?php

require_once '../../../app/config/db.php';
header('Content-Type: application/json');
session_start();

try {

    /*
    =========================================
    VALIDAÇÃO DE SESSÃO
    =========================================
    */
    if (!isset($_SESSION['user']['company_id'])) {
        echo json_encode([
            "status" => false,
            "message" => "Sessão inválida."
        ]);
        exit;
    }

    $companyId = (int) $_SESSION['user']['company_id'];

    /*
    =========================================
    QUERY
    =========================================
    */
    $query = "
        SELECT
            i.id,
            i.company_id,
            i.code,
            i.name,
            i.description,
            i.category,
            i.brand,
            i.barcode,
            i.sku,

            i.item_type,
            i.unit_measure,
            
            i.retention,
            i.unit_price,
            i.tax,
            
            COALESCE(i.cost_price, 0) AS cost_price,
            COALESCE(i.sale_price, 0) AS sale_price,
            COALESCE(i.pvp, 0) AS pvp,
            COALESCE(i.profit_margin, 0) AS profit_margin,

            COALESCE(i.track_stock, 1) AS track_stock,
            COALESCE(i.global_min_stock, 1) AS global_min_stock,
            i.status,

            i.currency,
            cr.symbol,
            cr.position,

            si.id AS stock_item_id,
            si.stock_id,

            COALESCE(si.quantity, 0) AS quantity,
            COALESCE(si.min_quantity, i.global_min_stock, 1) AS min_quantity,

            COALESCE(s.name, 'Sem stock associado') AS stock_name,

            /*
            STOCK ALERT
            */
            CASE
                WHEN COALESCE(si.quantity, 0) <= COALESCE(si.min_quantity, i.global_min_stock, 1)
                THEN 1
                ELSE 0
            END AS low_stock_alert

        FROM items i

        INNER JOIN currencies cr
            ON cr.iso_code = i.currency

        LEFT JOIN stock_items si
            ON si.item_id = i.id

        LEFT JOIN stocks s
            ON s.id = si.stock_id
            AND s.company_id = i.company_id

        WHERE
            i.company_id = :company_id
            AND i.status != 'archived'

        ORDER BY i.id DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /*
    =========================================
    PÓS PROCESSAMENTO
    =========================================
    */
    foreach ($items as &$item) {

        // descrição limpa
        $item['description_plain'] = trim(
            preg_replace('/\s+/u', ' ', strip_tags($item['description'] ?? ''))
        );

        // stock aplicável
        $item['stock_ready'] = in_array(
            $item['item_type'],
            ['product', 'raw_material', 'finished_good', 'consumable']
        );

        // existe stock
        $item['has_stock'] = !empty($item['stock_id']);

        // stock crítico
        $item['is_critical_stock'] =
            (int)$item['quantity'] <= (int)$item['min_quantity'];

        // valor stock
        $item['stock_total_value'] = round(
            (float)$item['quantity'] * (float)$item['cost_price'],
            2
        );

        /*
        =========================================
        REGRA FISCAL (IVA + RETENÇÃO FUTURA)
        =========================================
        */

        // $item['vat_applicable'] = ($item['tax'] !== 'exempt');

        $item['retention_applicable'] =
            ($item['retention'] === 'apply');
    }

    unset($item);

    echo json_encode([
        "status" => true,
        "total" => count($items),
        "data" => $items
    ]);
} catch (PDOException $e) {

    echo json_encode([
        "status" => false,
        "message" => "Erro de base de dados.",
        "error" => $e->getMessage()
    ]);
} catch (Exception $e) {

    echo json_encode([
        "status" => false,
        "message" => $e->getMessage()
    ]);
}
