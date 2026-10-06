<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');

$proformaId = (int)($_GET['id'] ?? 0);

if ($proformaId <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Proforma não encontrada.'
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | PROFORMA
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            p.id,
            p.reference,
            CONCAT(YEAR(p.issue_date), '/', p.id) AS codigo,

            p.issue_date,
            p.due_date,

            p.observation,
            p.series,

            p.currency,
            p.manual_exchange_rate,

            p.total_sum,
            p.total_discount,
            p.subtotal_without_tax,
            p.total_tax,
            p.final_total,

            ps.name AS status_invoice,
            ps.color,
            ps.text_color,

            c.id AS contact_id,
            c.name AS client_name,
            c.address AS client_address,
            c.contributor AS client_contributor,
            c.country AS client_country,
            c.city AS client_city,

            comp.name AS company_name,
            comp.address AS company_address,
            comp.city AS company_city,
            comp.country AS company_country,
            comp.registration_number,
            comp.email AS company_email,
            comp.phone AS company_phone,
            comp.logo_url,
            comp.vat_regime,
            comp.goods_services,
            comp.bank_details,

            cur.symbol,
            cur.position,

            cur2.symbol AS company_symbol,
            cur2.position AS company_position,

            comp.currency AS currency_company

        FROM proformas p

        INNER JOIN contact c
            ON c.id = p.contact_id

        INNER JOIN companies comp
            ON comp.id = p.company_id

        INNER JOIN proforma_status ps
            ON ps.id = p.status

        LEFT JOIN currencies cur
            ON cur.iso_code = 'AOA'

        LEFT JOIN currencies cur2
            ON cur2.iso_code = comp.currency

        WHERE p.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$proformaId]);

    $proforma = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proforma) {
        throw new Exception('Proforma não encontrada.');
    }

    /*
    |--------------------------------------------------------------------------
    | TEXTO PADRÃO
    |--------------------------------------------------------------------------
    */

    if (empty(trim($proforma['goods_services'] ?? ''))) {
        $proforma['goods_services'] =
            "Os bens e serviços foram colocados à disposição do adquirente na data do documento.";
    }

    /*
    |--------------------------------------------------------------------------
    | ITENS
    |--------------------------------------------------------------------------
    */

    $sqlItems = "
        SELECT
            pi.item_id,

            it.code,
            it.name,
            it.description,

            pi.quantity,
            pi.unit_price,
            pi.tax,
            pi.discount

        FROM proforma_items pi

        INNER JOIN items it
            ON it.id = pi.item_id

        WHERE pi.proforma_id = ?
    ";

    $stmtItems = $pdo->prepare($sqlItems);
    $stmtItems->execute([$proformaId]);

    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $proforma['items'] = $items;

    /*
    |--------------------------------------------------------------------------
    | RESUMO DE IMPOSTOS
    |--------------------------------------------------------------------------
    */

    $sqlTaxes = "
        SELECT
            pi.tax AS tax_rate,

            ROUND(
                SUM(
                    (pi.unit_price * pi.quantity)
                    * (1 - (pi.discount / 100))
                ),
                2
            ) AS tax_base,

            ROUND(
                SUM(
                    (
                        (pi.unit_price * pi.quantity)
                        * (1 - (pi.discount / 100))
                    )
                    * (pi.tax / 100)
                ),
                2
            ) AS tax_value,

            cur.symbol,
            cur.position

        FROM proforma_items pi

        INNER JOIN proformas p
            ON p.id = pi.proforma_id

        INNER JOIN companies comp
            ON comp.id = p.company_id

        LEFT JOIN currencies cur
            ON cur.iso_code = comp.currency

        WHERE pi.proforma_id = ?

        GROUP BY
            pi.tax,
            cur.symbol,
            cur.position

        ORDER BY pi.tax
    ";

    $stmtTaxes = $pdo->prepare($sqlTaxes);
    $stmtTaxes->execute([$proformaId]);

    $proforma['tax_details'] = $stmtTaxes->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | PROFORMA NÃO TEM PAGAMENTOS
    |--------------------------------------------------------------------------
    */

    $proforma['paid_total'] = 0;
    $proforma['saldo'] = $proforma['final_total'];
    $proforma['pay_status'] = 'proforma';

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'data' => $proforma
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}