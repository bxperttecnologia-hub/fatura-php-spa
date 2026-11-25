<?php
/* guide_public.php - Exibe a Guia de Transporte em HTML Personalité-like */
require_once __DIR__ . '/../../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../../app/helpers/company_logo.php';

$escapeGuide = static function ($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$dateGuide = static function ($value): string {
    return !empty($value) ? date('d/m/Y', strtotime((string)$value)) : '-';
};

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
  comp.registration_number AS company_nif,
  origin_invoice.reference AS invoice_reference,
  origin_invoice.document_type AS invoice_document_type,
  origin_invoice.series AS invoice_series,
  origin_proforma.reference AS proforma_reference,
  origin_proforma.series AS proforma_series
FROM guides g
JOIN contact c         ON c.id = g.contact_id
JOIN companies comp    ON comp.id = c.company_id
LEFT JOIN invoices origin_invoice
  ON origin_invoice.id = COALESCE(
    g.invoice_id,
    CASE WHEN LOWER(g.origin_document_type) IN ('factura', 'ft', 'fr') THEN g.origin_document_id END
  )
LEFT JOIN proformas origin_proforma
  ON origin_proforma.id = COALESCE(
    g.proforma_id,
    CASE WHEN LOWER(g.origin_document_type) IN ('proforma', 'pf') THEN g.origin_document_id END
  )
WHERE g.id = :id AND g.company_id = :company_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id, 'company_id' => session_company_id()]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guide) die('<h3>Guia não encontrada.</h3>');


// ---------- itens ----------
$stmt = $pdo->prepare("
  SELECT
    gi.*,
    it.code,
    it.unit_measure AS unit
  FROM guide_items gi
  LEFT JOIN items it ON it.id = gi.item_id
  WHERE gi.guide_id = :id
");
$stmt->execute(['id' => $id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$invoiceReference = trim((string)($guide['invoice_reference'] ?? ''));
$proformaReference = trim((string)($guide['proforma_reference'] ?? ''));
if ($invoiceReference !== '') {
    $originReference = $invoiceReference;
} elseif ($proformaReference !== '') {
    $originReference = $proformaReference;
} else$originType = strtolower((string)($guide['origin_document_type'] ?? ''));
if (in_array($originType, ['factura', 'ft', 'fr'], true)) {
    $originReference = trim((string)($guide['invoice_document_type'] ?? 'FT'))
        . trim((string)($guide['invoice_series'] ?? ''))
        . '/' . (int)($guide['origin_document_id'] ?? $guide['invoice_id'] ?? 0);
} elseif (in_array($originType, ['proforma', 'pf'], true)) {
    $originReference = 'PF' . trim((string)($guide['proforma_series'] ?? ''))
        . '/' . (int)($guide['origin_document_id'] ?? $guide['proforma_id'] ?? 0);
} else {
    $originReference = trim((string)($guide['reference'] ?? '')) ?: '-';
}

$logoBaseUrl = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
    $logoBaseUrl = ($isHttps ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
}
$companyLogo = company_logo_src($guide['company_logo'] ?? '', $logoBaseUrl);
$guideNumber = trim((string)($guide['series'] ?? '') . ' ' . (string)($guide['number'] ?? $guide['id']));

// ---------- HTML ----------
?>
<link rel="stylesheet" href="/guides/guides_public.css?v=4">
<article class="guide-document">
    <div class="guide-document-header">
        <section class="guide-issuer">
            <div class="guide-logo">
                <?php if ($companyLogo !== ''): ?>
                    <img src="<?= $escapeGuide($companyLogo) ?>" alt="Logótipo">
                <?php endif; ?>
            </div>
            <div class="guide-issuer-details">
                <strong><?= $escapeGuide($guide['company_name']) ?></strong>
                <span><?= nl2br($escapeGuide($guide['company_address'])) ?></span>
                <span><?= $escapeGuide(trim((string)$guide['company_city'] . ', ' . (string)$guide['company_country'], ', ')) ?></span>
                <?php if (!empty($guide['company_phone'])): ?><span>Tel: <?= $escapeGuide($guide['company_phone']) ?></span><?php endif; ?>
                <?php if (!empty($guide['company_email'])): ?><span>E-mail: <?= $escapeGuide($guide['company_email']) ?></span><?php endif; ?>
                <?php if (!empty($guide['company_nif'])): ?><span>Contribuinte: <?= $escapeGuide($guide['company_nif']) ?></span><?php endif; ?>
            </div>
        </section>
        <section class="guide-recipient">
            <strong><?= $escapeGuide($guide['client_name']) ?></strong>
            <?php if (!empty($guide['client_contributor'])): ?><span>NIF: <?= $escapeGuide($guide['client_contributor']) ?></span><?php endif; ?>
            <span><?= $escapeGuide(trim((string)$guide['client_address'])) ?></span>
            <span><?= $escapeGuide(trim((string)$guide['client_city'] . ', ' . (string)$guide['client_country'], ', ')) ?></span>
        </section>
    </div>

    <h1 class="guide-document-title">GUIA DE TRANSPORTE n.º <?= $escapeGuide($guideNumber) ?></h1>

    <section class="guide-document-meta" aria-label="Dados do documento">
        <div><strong>Data:</strong><span><?= $escapeGuide($dateGuide($guide['document_date'] ?? null)) ?></span></div>
        <div><strong>Documento associado:</strong><span><?= $escapeGuide($originReference) ?></span></div>
        <div><strong>Local de entrega:</strong><span><?= $escapeGuide(trim((string)$guide['delivery_address'] . ', ' . (string)$guide['delivery_city'], ', ')) ?></span></div>
    </section>

    <table class="guide-items">
        <thead>
            <tr><th>Código</th><th>Descrição</th><th>Qtd.</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $escapeGuide($item['code'] ?? '') ?></td>
                    <td><?= $escapeGuide($item['description'] ?? '') ?></td>
                    <td><?= number_format((float)$item['quantity'], 3, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <section class="guide-signatures" aria-label="Assinaturas">
        <div><strong>Emitente</strong><span>Assinatura e carimbo</span></div>
        <div><strong>Adquirente</strong><span>Assinatura e carimbo</span></div>
    </section>
</article>
