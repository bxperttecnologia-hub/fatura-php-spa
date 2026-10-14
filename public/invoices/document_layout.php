<?php
require_once __DIR__ . '/../../app/helpers/company_logo.php';

$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$currencySymbol = strtoupper((string)($inv['currency'] ?? '')) === 'AOA'
  ? 'KZ'
  : (string)($inv['moneySymbol'] ?? $inv['company_symbol'] ?? 'KZ');
$currencyPosition = (string)($inv['moneyPos'] ?? $inv['company_position'] ?? 'right');
$money = static function ($value) use ($currencySymbol, $currencyPosition): string {
  $amount = number_format((float)$value, 2, ',', ' ');
  return $currencyPosition === 'left' ? "{$currencySymbol} {$amount}" : "{$amount} {$currencySymbol}";
};
$issueDate = !empty($inv['issue_date']) ? date('d/m/Y', strtotime($inv['issue_date'])) : '-';
$dueDays = (int)($inv['due_date'] ?? 0);
$dueDate = !empty($inv['issue_date'])
  ? date('d/m/Y', strtotime("+{$dueDays} days", strtotime($inv['issue_date'])))
  : '-';
$companyAddress = implode(', ', array_filter([
  $inv['company_address'] ?? '',
  $inv['company_city'] ?? '',
  $inv['company_country'] ?? '',
]));
$clientAddress = implode(', ', array_filter([
  $inv['client_address'] ?? '',
  $inv['client_city'] ?? '',
  $inv['client_country'] ?? '',
])) ?: '-';
$clientNameParts = preg_split('/\s+/', trim((string)($inv['client_name'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
$clientName = count($clientNameParts) > 1
  ? $clientNameParts[0] . ' ' . $clientNameParts[count($clientNameParts) - 1]
  : ($clientNameParts[0] ?? '-');
$clientNif = trim((string)($inv['client_contributor'] ?? '')) ?: '-';
$clientPhone = trim((string)($inv['client_phone'] ?? '')) ?: '-';
$vatRegime = trim((string)($inv['vat_regime'] ?? '')) ?: '-';
$vatRegime = preg_replace('/^Regime\s+/i', '', $vatRegime);
$vatRegime = $vatRegime !== '-' ? mb_convert_case($vatRegime, MB_CASE_TITLE, 'UTF-8') : $vatRegime;
$iban = preg_replace('/[.\s]/', '', (string)($inv['iban'] ?? ''));
$iban = $iban !== '' ? trim(substr($iban, 0, 4) . ' ' . chunk_split(substr($iban, 4), 4, ' ')) : '-';
$documentType = strtoupper((string)($inv['document_type'] ?? 'FT'));
$isProforma = $documentType === 'PF';
$title = ($isProforma ? 'Proforma' : 'Factura') . ' n.º ' . (string)($inv['reference'] ?? '');
$certificate = defined('AGT_CERTIFICATE') ? AGT_CERTIFICATE : 'FE/344/AGT/2026';
$logoSrc = company_logo_src($inv['logo_url'] ?? '', $publicBasePath);
$itemPages = [];
$currentPageItems = [];
$currentPageHeight = 0.0;
foreach ($items as $item) {
  $description = (string)(($item['name'] ?? '') !== '' ? $item['name'] : ($item['description'] ?? ''));
  $itemHeight = max(17.0, ceil(mb_strlen($description) / 36) * 9.6 + 7);
  if ($currentPageItems && $currentPageHeight + $itemHeight > 130) {
    $itemPages[] = $currentPageItems;
    $currentPageItems = [];
    $currentPageHeight = 0.0;
  }
  $currentPageItems[] = $item;
  $currentPageHeight += $itemHeight;
}
if ($currentPageItems || !$itemPages) {
  $itemPages[] = $currentPageItems;
}
$pageCount = count($itemPages);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <title><?= $escape($title) ?></title>
  <style>
    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body.document-public { background: #e9e9e9; font-family: Helvetica, Arial, sans-serif; }
    .document-page {
      position: relative;
      width: 595.28pt;
      height: 841.89pt;
      margin: 16pt auto;
      overflow: hidden;
      background: #fff;
      color: #777;
      font-family: Helvetica, Arial, sans-serif;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .company, .client, .copy, .doc-title, .rule, .drow, .flow, .qr, .agt, .pg { position: absolute; }
    .logo { position: absolute; left: 44pt; top: 35pt; width: 70pt; max-height: 55pt; object-fit: contain; }
    .company { left: 44pt; top: 120pt; width: 190pt; }
    .company h1, .client h2 { margin: 0; color: #111; font-size: 12pt; line-height: 15pt; font-weight: 700; text-transform: uppercase; }
    .company p, .client p { margin: 0; color: #777; font-size: 9pt; line-height: 12.5pt; }
    .client { left: 435pt; top: 120pt; width: 125pt; }
    .copy { left: 44pt; top: 220pt; font-size: 10pt; line-height: 12pt; }
    .doc-title { left: 44pt; top: 238pt; color: #111; font-size: 12pt; line-height: 15pt; font-weight: 700; }
    .rule { left: 44pt; top: 263pt; width: 507pt; border-top: 1.5pt solid #888; }
    .drow { left: 0; width: 100%; height: 11pt; font-size: 9pt; line-height: 11pt; }
    .drow span { position: absolute; top: 0; white-space: nowrap; }
    .r1 { top: 273pt; } .r2 { top: 291pt; } .r3 { top: 309pt; }
    .x1 { left: 44pt; } .x2 { left: 145pt; } .x3 { left: 365pt; } .x4 { left: 455pt; }
    .strong { color: #444; font-weight: 700; }
    .flow { left: 44pt; top: 338pt; width: 507pt; }
    .t-head { position: relative; height: 20pt; margin-bottom: 16pt; padding-top: 10pt; border-top: 1pt solid #888; border-bottom: .8pt solid #d2d2d2; color: #555; font-size: 8pt; font-weight: 700; }
    .t-head span { position: absolute; top: 10pt; line-height: 10pt; white-space: nowrap; }
    .t-body { position: relative; min-height: 130pt; padding-bottom: 10pt; }
    .irow { position: relative; min-height: 17pt; padding-bottom: 7pt; color: #666; font-size: 8pt; line-height: 9.6pt; }
    .irow .desc { margin-left: 90pt; width: 155pt; }
    .irow span { position: absolute; top: 0; white-space: nowrap; }
    .c-code { left: 5pt; width: 80pt; white-space: normal !important; }
    .c-price { left: 205pt; width: 60pt; text-align: right; }
    .c-qty { left: 310pt; width: 30pt; text-align: center; }
    .c-tax { left: 343pt; width: 42pt; text-align: center; }
    .c-disc { left: 388pt; width: 35pt; text-align: center; }
    .c-total { left: 435pt; width: 70pt; text-align: right; }
    .carry { position: absolute; right: 0; bottom: 0; color: #707070; font-size: 8pt; font-style: italic; text-align: right; }
    .totals { position: relative; height: 170pt; border-top: 1.5pt solid #888; color: #777; font-size: 8.5pt; line-height: 10pt; }
    .totals .t, .totals .a { position: absolute; }
    .totals .title { top: 8pt; color: #555; font-size: 10pt; font-weight: 700; }
    .totals .hline { position: absolute; top: 24pt; border-top: .8pt solid #c4c4c4; }
    .lv { left: 100pt; width: 190pt; }
    .rv { left: 320pt; width: 185pt; text-align: right; }
    .totals .sep1 { position: absolute; left: 320pt; top: 106pt; width: 185pt; border-top: 1pt solid #555; }
    .totals .final { position: absolute; top: 116pt; color: #000; font-size: 12pt; font-weight: 700; line-height: 14pt; }
    .totals .sep2 { position: absolute; left: 320pt; top: 134pt; width: 185pt; border-top: 2pt solid #555; }
    .qr { left: 48pt; top: 772pt; width: 47pt; height: 47pt; }
    .agt { left: 112pt; top: 798pt; width: 350pt; color: #111; font-size: 8pt; line-height: 10pt; }
    .pg { left: 500pt; top: 798pt; width: 50pt; color: #111; font-size: 8pt; text-align: right; }
    @media print {
      body.document-public { background: #fff; }
      .document-page { margin: 0; box-shadow: none; page-break-after: always; break-after: page; }
      .document-page:last-child { page-break-after: auto; break-after: auto; }
    }
  </style>
</head>
<body class="document-public">
  <?php foreach ($itemPages as $pageIndex => $pageItems): ?>
  <main class="document-page">
    <?php if ($logoSrc !== ''): ?>
      <img class="logo" src="<?= $escape($logoSrc) ?>" alt="Logo">
    <?php endif; ?>

    <div class="company">
      <h1><?= $escape($inv['company_name'] ?? '-') ?></h1>
      <p><?= $escape($companyAddress ?: '-') ?></p>
      <p>Tel: <?= $escape($inv['company_phone'] ?? '-') ?></p>
      <p>E-mail: <?= $escape($inv['company_email'] ?? '-') ?></p>
      <p>Contribuinte: <?= $escape($inv['registration_number'] ?? '-') ?></p>
    </div>

    <div class="client">
      <h2><?= $escape($clientName) ?></h2>
      <p>NIF: <?= $escape($clientNif) ?></p>
      <p><?= $escape($clientAddress) ?></p>
      <p><?= $escape($clientPhone) ?></p>
    </div>

    <div class="copy">Duplicado</div>
    <div class="doc-title"><?= $escape($title) ?></div>
    <div class="rule"></div>

    <div class="drow r1">
      <span class="x1">Cliente:</span><span class="x2 strong"><?= $escape($clientName) ?></span>
      <span class="x3">Data de emissão:</span><span class="x4"><?= $escape($issueDate) ?></span>
    </div>
    <div class="drow r2">
      <span class="x1">Contribuinte:</span><span class="x2"><?= $escape($clientNif) ?></span>
      <span class="x3">Vencimento:</span><span class="x4"><?= $escape($dueDate) ?></span>
    </div>
    <div class="drow r3">
      <span class="x1">Observações:</span><span class="x2"><?= $escape($inv['observation'] ?: '-') ?></span>
    </div>

    <div class="flow">
      <div class="t-head">
        <span style="left:5pt;width:80pt">Código</span>
        <span style="left:90pt;width:155pt">Descrição</span>
        <span style="left:250pt;width:55pt;text-align:right">Preço Uni.</span>
        <span style="left:310pt;width:30pt;text-align:center">Qtd.</span>
        <span style="left:343pt;width:42pt;text-align:center">Taxa/IVA</span>
        <span style="left:388pt;width:35pt;text-align:center">Desc.</span>
        <span style="left:435pt;width:70pt;text-align:right">Total</span>
      </div>
      <div class="t-body">
        <?php foreach ($pageItems as $item):
          $base = (float)$item['unit_price'] * (float)$item['quantity'];
          $discount = $base * ((float)$item['discount'] / 100);
          $rate = bx_document_effective_tax_rate($item['tax'] ?? 0, (string)($inv['vat_regime'] ?? ''));
          $total = $base - $discount + (($base - $discount) * ($rate / 100));
          $rateDecimals = abs($rate - round($rate)) < 0.00001 ? 0 : 2;
          $description = ($item['name'] ?? '') !== '' ? $item['name'] : ($item['description'] ?? ''); ?>
          <div class="irow">
            <span class="c-code"><?= $escape($item['code'] ?? '-') ?></span>
            <div class="desc"><?= $escape($description) ?></div>
            <span class="c-price"><?= $escape($money($item['unit_price'])) ?></span>
            <span class="c-qty"><?= $escape($item['quantity']) ?></span>
            <span class="c-tax"><?= $escape(number_format($rate, $rateDecimals, ',', '')) ?>%</span>
            <span class="c-disc"><?= $escape(number_format((float)$item['discount'], 0, ',', '')) ?>%</span>
            <span class="c-total"><?= $escape($money($total)) ?></span>
          </div>
        <?php endforeach; ?>
        <?php if ($pageIndex < $pageCount - 1): ?>
          <div class="carry">A transportar...</div>
        <?php endif; ?>
      </div>

      <div class="totals">
        <div class="t title" style="left:0">Dados fiscais e bancários</div>
        <div class="t title" style="left:320pt">Sumário</div>
        <div class="hline" style="left:0;width:295pt"></div>
        <div class="hline" style="left:320pt;width:185pt"></div>

        <div class="t" style="left:0;top:34pt">Regime de IVA:</div>
        <div class="t lv" style="top:34pt"><?= $escape($vatRegime) ?></div>
        <div class="t" style="left:0;top:54pt">Bens e serviços:</div>
        <div class="t lv" style="top:54pt;width:190pt"><?= $escape($inv['goods_services'] ?? '-') ?></div>
        <div class="t" style="left:0;top:94pt">Dados bancários:</div>
        <div class="t lv" style="top:94pt"><?= $escape($iban) ?></div>

        <div class="t" style="left:320pt;top:34pt">Total líquido:</div>
        <div class="t rv" style="top:34pt"><?= $escape($money($inv['total_sum'])) ?></div>
        <div class="t" style="left:320pt;top:50pt">Desconto:</div>
        <div class="t rv" style="top:50pt"><?= $escape($money($inv['total_discount'])) ?></div>
        <div class="t" style="left:320pt;top:66pt">Sem Imposto/IVA c/Desc.:</div>
        <div class="t rv" style="top:66pt"><?= $escape($money($inv['subtotal_without_tax'] ?? ($inv['total_sum'] - $inv['total_discount']))) ?></div>
        <div class="t" style="left:320pt;top:82pt">Imposto/IVA:</div>
        <div class="t rv" style="top:82pt"><?= $escape($money($inv['total_tax'])) ?></div>
        <div class="t" style="left:320pt;top:98pt">Retenção:</div>
        <div class="t rv" style="top:98pt"><?= $escape($money($inv['retention_value'] ?? 0)) ?></div>
        <div class="sep1"></div>
        <div class="t final" style="left:320pt">Total:</div>
        <div class="t final rv"><?= $escape($money($inv['final_total'])) ?></div>
        <div class="sep2"></div>
      </div>
    </div>

    <img class="qr" src="<?= $escape($qrSrc) ?>" alt="QR">
    <div class="agt">
      Factura processada pelo software certificado pela AGT | Nº <?= $escape($certificate) ?><br>
      Powered by BXPERT
    </div>
    <div class="pg"><?= $pageIndex + 1 ?>/<?= $pageCount ?></div>
  </main>
  <?php endforeach; ?>
</body>
</html>
