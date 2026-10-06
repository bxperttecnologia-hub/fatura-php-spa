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

    if (
        !isset($_SESSION['user']['company_id'])
    ) {
        echo json_encode([
            "status" => false,
            "message" => "Sessão inválida."
        ]);
        exit;
    }

    $companyId = (int) $_SESSION['user']['company_id'];

    /*
    =========================================
    QUERY PRINCIPAL
    =========================================

    Corrigido:

    - company_id no lugar de id_company
    - unit_measure no lugar de unit
    - sale_price / cost_price no lugar de unit_price
    - removido JOIN desnecessário com company_has_user
      (já temos company_id via sessão)
    - LEFT JOIN com stock_items
    - LEFT JOIN com stocks
    - COALESCE para evitar NULL
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

            /*
            TIPO DE ITEM
            */
            CASE
                WHEN i.item_type = 'product' THEN 'produto'
                WHEN i.item_type = 'service' THEN 'service'
                WHEN i.item_type = 'raw_material' THEN 'Matéria-Prima'
                WHEN i.item_type = 'finished_good' THEN 'Produto Final'
                WHEN i.item_type = 'consumable' THEN 'Consumível'
                ELSE i.item_type
            END AS item_type,

            i.item_type AS item_type_raw,

            /*
            UNIDADE DE MEDIDA
            */
            CASE
                WHEN i.unit_measure = 'unit' THEN 'Unidade'
                WHEN i.unit_measure = 'kg' THEN 'Kg'
                WHEN i.unit_measure = 'g' THEN 'Grama'
                WHEN i.unit_measure = 'liter' THEN 'Litro'
                WHEN i.unit_measure = 'meter' THEN 'Metro'
                WHEN i.unit_measure = 'box' THEN 'Caixa'
                WHEN i.unit_measure = 'pack' THEN 'Pacote'
                WHEN i.unit_measure = 'service' THEN 'Serviço'
                ELSE i.unit_measure
            END AS unit_measure,

            i.unit_measure AS unit_measure_raw,

            /*
            RETENÇÃO
            */
            CASE
                WHEN i.retention = 'apply' THEN 'Aplicar'
                WHEN i.retention = 'do_not_apply' THEN 'Não Aplicar'
                ELSE i.retention
            END AS retention,

            i.retention AS retention_raw,

            /*
            IVA
            */
            CASE
                WHEN i.tax = '14' THEN '14% - Taxa 14'
                WHEN i.tax = 'exempt' THEN 'Isento'
                ELSE i.tax
            END AS tax,

            i.tax AS tax_raw,

            /*
            PREÇOS
            */
            COALESCE(i.cost_price, 0) AS cost_price,
            COALESCE(i.sale_price, 0) AS sale_price,
            COALESCE(i.unit_price, 0) AS unit_price,
            COALESCE(i.pvp, 0) AS pvp,
            COALESCE(i.profit_margin, 0) AS profit_margin,

            /*
            CONFIGURAÇÕES
            */
            COALESCE(i.track_stock, 1) AS track_stock,
            COALESCE(i.global_min_stock, 1) AS global_min_stock,
            i.status,

            i.created_at,
            i.updated_at,

            /*
            MOEDA
            */
            i.currency,
            cr.symbol,
            cr.position,

            /*
            CAMPOS DE STOCK
            */
            si.id AS stock_item_id,
            si.stock_id,

            COALESCE(si.quantity, 0) AS quantity,

            COALESCE(
                si.min_quantity,
                i.global_min_stock,
                1
            ) AS min_quantity,

            COALESCE(s.name, 'Sem stock associado') AS stock_name,

            /*
            ALERTA DE STOCK BAIXO
            */
            CASE
                WHEN COALESCE(si.quantity, 0)
                    <= COALESCE(si.min_quantity, i.global_min_stock, 1)
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

        /*
        Remove HTML da descrição
        */

        $plainDescription = strip_tags($item['description'] ?? '');
        $plainDescription = preg_replace('/\\s+/u', ' ', $plainDescription);

        $item['description_plain'] = trim($plainDescription);

        /*
        Apenas itens físicos entram em stock
        */

        $item['stock_ready'] = in_array(
            $item['item_type_raw'],
            [
                'product',
                'raw_material',
                'finished_good',
                'consumable'
            ]
        );

        /*
        Se já existe vínculo com stock
        */

        $item['has_stock'] = !empty($item['stock_id']);

        /*
        Produto com stock crítico
        */

        $item['is_critical_stock'] = (
            (int)$item['quantity'] <= (int)$item['min_quantity']
        );

        /*
        Valor total em stock
        */

        $item['stock_total_value'] = round(
            (float)$item['quantity'] * (float)$item['cost_price'],
            2
        );
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
        "message" => "Erro inesperado.",
        "error" => $e->getMessage()
    ]);
}
