<?php
/* guide_public.php - Exibe a Guia de Transporte em HTML Personalité-like */
require_once '../../../app/config/db.php';

// ---------- utils ----------
function formatCurrency(float $value, string $currencySymbol, string $currencyPosition = 'left'): string
{
    $formatted = number_format($value, 2, ',', '.');
    return $currencyPosition === 'left'
        ? "{$currencySymbol} {$formatted}"
        : "{$formatted} {$currencySymbol}";
}

function dateBr($sqlDate)
{
    return date('d/m/Y', strtotime($sqlDate));
}
function randomHash($len = 2)
{
    return substr(bin2hex(random_bytes($len)), 0, $len);
}

// ---------- input ----------
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo '<h3>Guia não encontrada.</h3>';
    return;
}
 
// ---------- consulta guia ----------
$sql = "
SELECT
  g.*,
  c.name           AS client_name,
  c.address        AS client_address,
  c.contributor    AS client_contributor,
  c.city           AS client_city,
  c.country        AS client_country,
  c.email          AS client_email,
  c.po_box         AS client_po_box,
  comp.id          AS company_id,
  comp.name        AS company_name,
  comp.address     AS company_address,
  comp.city        AS company_city,
  comp.country     AS company_country,
  comp.logo_url    AS company_logo,
  comp.phone       AS company_phone,
  comp.email       AS company_email,
  comp.registration_number,
  cr.symbol        AS moneySymbol,
  cr.position      AS moneyPos
FROM guides g
JOIN contact c         ON c.id = g.contact_id
JOIN companies comp    ON comp.id = c.company_id
JOIN currencies cr ON cr.iso_code COLLATE utf8mb4_general_ci = g.currency COLLATE utf8mb4_general_ci
WHERE g.id = :id
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guide) die('<h3>Guia não encontrada.</h3>');


// ---------- itens ----------
$stmt = $pdo->prepare("
  SELECT
    gi.*,
    it.code
  FROM guide_items gi
  LEFT JOIN items it ON it.id = gi.item_id
  WHERE gi.guide_id = :id
");
$stmt->execute(['id' => $id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ---------- taxas ----------
$stmt = $pdo->prepare("
SELECT  gi.tax AS tax_rate,
        ROUND(SUM(gi.unit_price*gi.quantity)
              * (1 - (gi.discount/100)),2) AS tax_base,
        SUM(gi.unit_price*gi.quantity* (gi.tax/100)) AS tax_value
FROM   guide_items gi
WHERE  gi.guide_id = :id
GROUP BY gi.tax");
$stmt->execute(['id' => $id]);
$taxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- QR ----------
$hash = randomHash();
$qrData = "../public/guide_public.php?id={$hash}_{$guide['company_id']}/{$guide['id']}";

$qrSrc = "https://chart.googleapis.com/chart?chs=120x120&cht=qr&chl=" . urlencode($qrData);

// ---------- HTML ----------
?>
<main class="invoice-page">
      <link rel="stylesheet" href="guides/guides_public.css">

    <!-- ===== HEADER ===== -->
    <div class="inv-header d-flex justify-content-between pb-2">

        <!-- ESQUERDA: logo + dados empresa -->
        <div class="inv-left d-flex flex-column align-items-start">
            
            <div id="company-info">
                <h6 class="fw-bold mb-1"><?= htmlspecialchars($guide['company_name']) ?></h6>
                <p class="mb-0"><?= nl2br(htmlspecialchars($guide['company_address'])) ?><br>
                    <?= htmlspecialchars("{$guide['company_city']} - {$guide['company_country']}") ?></p>
                <p class="mb-0">Tel: <?= htmlspecialchars($guide['company_phone']) ?></p>
                <p class="mb-0">E-mail: <?= htmlspecialchars($guide['company_email']) ?></p>
                <p class="mb-0">Registro: <?= htmlspecialchars($guide['registration_number']) ?></p>
            </div>
        </div>

        <!-- DIREITA: QR + dados cliente -->
        <div class="inv-right d-flex flex-column align-items-start justify-content-end">
             
            <div id="client-info" class="text-start">
                <small>Destinatário</small>
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($guide['client_name']) ?></h6>
                <p class="mb-0">
                    <?= nl2br(htmlspecialchars($guide['client_address'])) ?><br>
                    <?= htmlspecialchars("{$guide['client_city']} - {$guide['client_country']}") ?>
                </p>
            </div>
        </div>
    </div><!-- /inv-header -->

    <!-- ===== META ===== -->
    <section class="inv-meta pt-2">
        <div class="d-flex justify-content-between">
            <span class="d-block">Guia de Transporte</span> 
        </div>
        <span class="d-block title-line">
            Guia n.º <?= date('Y', strtotime($guide['document_date'])) ?>/<?= $guide['id'] ?>
        </span>
        <!-- Bloco flex com 2 colunas -->
        <div class="meta-row">
            <!-- ESQUERDA – Datas & Ref -->
            <div class="meta-mini">
                <div class="head">
                    <span>Data de Emissão</span><span>Placa do Veículo</span>
                </div>
                <div class="vals">
                    <span><?= dateBr($guide['document_date']) ?></span><span><?= htmlspecialchars($guide['vehicle_plate']) ?></span>
                </div>
                <div class="head top">
                    <span>Carga</span><span>Entrega</span>
                </div>
                <div class="vals">
                    <span><?= htmlspecialchars($guide['cargo_address']) ?>, <?= htmlspecialchars($guide['cargo_city']) ?></span>
                    <span><?= htmlspecialchars($guide['delivery_address']) ?>, <?= htmlspecialchars($guide['delivery_city']) ?></span>
                </div>
            </div>
            <!-- DIREITA – Observações -->
            <div class="meta-obs">
                <div class="head">
                    <span>Motivo do Transporte</span>
                </div>
                <div class="vals">
                    <span><?= htmlspecialchars($guide['transport_reason'] ?: '-') ?></span>
                </div>
                <div class="head top">
                    <span>Observações</span>
                </div>
                <div class="vals">
                    <span><?= htmlspecialchars($guide['observations'] ?: '-') ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== ITENS ===== -->
    <div class="items-grid mt-2 title-line pb-5">
        <div class="items-row items-head">
            <span>Código</span>
            <span>Descrição</span>
            <span>Preço Uni.</span>
            <span>Qtd.</span>
            <span>Taxa/IVA %</span>
            <span>Desc. %</span>
            <span>Total</span>
        </div>
        <?php foreach($items as $it): $total = $it['unit_price'] * $it['quantity']; ?>
        <div class="items-row">
            <span><?= $it['code'] ?></span>
            <span><?= htmlspecialchars($it['description']) ?></span>
            <span class="right"><?= formatCurrency($it['unit_price'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            <span class="center"><?= $it['quantity'] ?></span>
            <span class="center"><?= $it['tax'] ?>%</span>
            <span class="center"><?= $it['discount'] ?>%</span>
            <span class="right"><?= formatCurrency($total, $guide['moneySymbol'], $guide['moneyPos']) ?></span>
        </div>
        <?php endforeach;?>
    </div>

    <!-- ===== TAXAS & RESUMO ===== -->
    <div class="totals-wrap">
        <div class="tax-grid">
            <div class="tax-head">
                <span>Imposto/IVA</span>
                <span class="right">Incidência</span>
                <span class="right">Valor</span>
            </div>
            <?php foreach($taxes as $tx):?>
                <div class="tax-row">
                    <span><?= $tx['tax_rate'] ? $tx['tax_rate'].'%' : 'Isento (0%)' ?></span>
                    <span class="right"><?= formatCurrency($tx['tax_base'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
                    <span class="right"><?= formatCurrency($tx['tax_value'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
                </div>
            <?php endforeach;?>
            <?php if($guide['retention'] > 0): ?>
            <div class="tax-row">
                <span>Retenção (<?= $guide['retention'] ?>%)</span>
                <span></span>
                <span class="right"><?= formatCurrency($guide['retention_value'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="sum-grid">
            <div class="sum-head">Sumário</div>
            <div class="sum-row">
                <span>Total líquido:</span>
                <span class="right"><?= formatCurrency($guide['total_sum'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
            <div class="sum-row">
                <span>Desconto:</span>
                <span class="right"><?= formatCurrency($guide['total_discount'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
            <div class="sum-row">
                <span>Sem Imposto/IVA c Desc.:</span>
                <span class="right"><?= formatCurrency($guide['subtotal_without_tax'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
            <div class="sum-row">
                <span>Imposto/IVA:</span>
                <span class="right"><?= formatCurrency($guide['total_tax'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
            <div class="sum-row">
                <span>Retenção:</span>
                <span class="right"><?= formatCurrency($guide['retention_value'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
            <div class="sum-row sep"></div>
            <div class="sum-row bold">
                <span>Total:</span>
                <span class="right"><?= formatCurrency($guide['final_total'], $guide['moneySymbol'], $guide['moneyPos']) ?></span>
            </div>
        </div>
    </div>

    <!-- ===== ASSINATURAS ===== -->
    <div class="signatures" style="background:#fff;">
        <table class="signatures-table" style="background:#fff;">
            <tr style="background:#fff;">
                <td style="background:#fff;" >
                    <div class="signature-space"></div>
                    <div class="signature-blank">______________________________</div>
                    <div class="signature-label">Assinatura do Emitente</div>
                </td>
                <td style="background:#fff;" >
                    <div class="signature-space"></div>
                    <div class="signature-blank">______________________________</div>
                    <div class="signature-label">Assinatura do Destinatário</div>
                </td>
            </tr>
        </table>
    </div>
</main>
