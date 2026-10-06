<?php
/*  invoice_public.php
 *  Pré-visualização HTML da fatura, com o MESMO layout do PDF (invoicePdfService.js):
 *  - logo, empresa, "Exmo Sr." (cliente), Original/Duplicado
 *  - tabela com no máximo 15 itens por página ("A transportar…")
 *  - bloco "Dados fiscais e bancários" + "Sumário" em todas as páginas
 *  - rodapé: QR code à esquerda, texto AGT à direita do QR, paginação por cópia
 */
require_once '../../../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};

require_once '../../../app/config/db.php';

// ---------- configuração (igual ao PDF) ----------
const AGT_CERTIFICATE = 'FE/344/AGT/2026';
const ITEMS_PER_PAGE  = 15;
const ITEMS_BOTTOM    = 640;   // limite vertical (pt) das linhas da tabela
const FIRST_ROW_Y     = 360;   // y (pt) da primeira linha da tabela
$copies = ['Original']; // para ver as duas cópias: ['Original', 'Duplicado']

// ---------- utils ----------
function e($value): string
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $value, string $currencySymbol, string $currencyPosition = 'left'): string
{
  $formatted = number_format($value, 2, ',', '.');
  return $currencyPosition === 'left'
    ? "{$currencySymbol} {$formatted}"
    : "{$formatted} {$currencySymbol}";
}

function dateBr($sqlDate): string
{
  return $sqlDate ? date('d/m/Y', strtotime($sqlDate)) : '-';
}

function firstAndLastName(?string $name): string
{
  $name = trim((string)$name);
  if ($name === '') return '-';
  $parts = preg_split('/\s+/', $name);
  return count($parts) === 1 ? $parts[0] : $parts[0] . ' ' . $parts[count($parts) - 1];
}

function itemLabel(array $it): string
{
  return (string)(($it['name'] ?? '') !== '' ? $it['name'] : ($it['description'] ?? ''));
}

// altura estimada (pt) de uma linha: mínimo 17, cresce com descrições longas
function itemHeight(array $it): float
{
  $lines = max(1, (int)ceil(mb_strlen(itemLabel($it)) / 36));
  return max(17, $lines * 9.6 + 7);
}

// quebra os itens em páginas (máx. 15 por página e limite vertical)
function paginateItems(array $items): array
{
  $pages = [];
  $current = [];
  $y = FIRST_ROW_Y;

  foreach ($items as $it) {
    $h = itemHeight($it);
    if ($current && (count($current) >= ITEMS_PER_PAGE || $y + $h > ITEMS_BOTTOM)) {
      $pages[] = $current;
      $current = [];
      $y = FIRST_ROW_Y;
    }
    $current[] = $it;
    $y += $h;
  }

  if ($current || !$pages) $pages[] = $current;
  return $pages;
}

// ---------- input ----------
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
  die('<h3>Fatura não encontrada.</h3>');
}

// ---------- consulta fatura ----------
$sql = "
SELECT  i.*,
        comp.id   AS company_id,
        comp.name AS company_name,   comp.address AS company_address,
        comp.city AS company_city,   comp.country AS company_country,
        comp.registration_number,    comp.email   AS company_email,
        comp.website,
        comp.phone AS company_phone, comp.logo_url,
        comp.vat_regime, comp.goods_services, comp.bank_details, comp.bank_name, comp.iban,
        c.name    AS client_name,    c.address  AS client_address,
        c.contributor AS client_contributor, c.city AS client_city,
        c.country AS client_country,
        ivs.name  AS status_invoice, ivs.color, ivs.text_color,
        cr2.symbol AS moneySymbol,   cr2.position AS moneyPos
FROM      invoices i
JOIN      companies   comp ON comp.id = i.company_id
JOIN      contact         c ON c.id    = i.contact_id
JOIN      invoice_status ivs ON ivs.id = i.status
JOIN      currencies    cr2 ON cr2.iso_code = i.currency
WHERE i.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
  die('<h3>Fatura não encontrada.</h3>');
}

$publicBasePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$logoFile = basename((string)($inv['logo_url'] ?? ''));

// ---------- itens ----------
$stmt = $pdo->prepare("
SELECT it.code, it.name, it.description, ii.quantity, ii.unit_price,
       ii.tax, ii.discount
FROM   invoice_items ii
JOIN   items it ON it.id = ii.item_id
WHERE  ii.invoice_id = :id");
$stmt->execute(['id' => $id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- QR ----------
$qrData = "https://bxpert.co.ao/sistema/invoice_public.php?id={$inv['id']}";
$opts = new QROptions([
  'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
  'eccLevel'     => QRCode::ECC_L,
  'scale'        => 3,
  'addQuietzone' => false,
  'imageBase64'  => true,
]);
$qrSrc = (new QRCode($opts))->render($qrData);

// ---------- dados preparados para o layout ----------
$symbol = (string)$inv['moneySymbol'];
$pos    = (string)$inv['moneyPos'];
$money  = fn($v) => formatCurrency((float)$v, $symbol, $pos);

$issueBr = dateBr($inv['issue_date']);
$dueDays = (int)$inv['due_date'];
$dueBr = $inv['issue_date']
  ? dateBr((new DateTime($inv['issue_date']))->modify("+{$dueDays} days")->format('Y-m-d'))
  : '-';

$companyAddress = implode(', ', array_filter([$inv['company_address'], $inv['company_city'], $inv['company_country']]));
$clientAddress  = implode(', ', array_filter([$inv['client_address'], $inv['client_city'], $inv['client_country']])) ?: 'Luanda - Angola';
$clientName     = firstAndLastName($inv['client_name']);
$clientNif      = $inv['client_contributor'] ?: '999999999';
$clientPhone    = $inv['client_phone'] ?? '-';   // adicione c.phone AS client_phone à query para mostrar o telefone

$vatRegime = match (strtolower((string)($inv['vat_regime'] ?? ''))) {
  'geral'        => 'Regime Geral',
  'simplificado' => 'Regime Simplificado',
  default        => '-',
};

$ibanRaw = preg_replace('/[.\s]/', '', (string)($inv['iban'] ?? ''));
$iban = $ibanRaw !== ''
  ? trim(substr($ibanRaw, 0, 4) . ' ' . trim(chunk_split(substr($ibanRaw, 4), 4, ' ')))
  : '-';

$isDraft  = (int)$inv['status'] === 1;
$docTitle = ($isDraft ? 'Factura Rascunho' : 'Factura') . ' n.º ' . ($isDraft ? '' : (string)$inv['reference']);

$pages = paginateItems($items);
$pageCount = count($pages);
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
  <meta charset="utf-8">
  <title>Fatura <?= e($inv['codigo']) ?></title>
  <style>
    @page {
      size: A4;
      margin: 0;
    }

    /* Reset com especificidade 0: não "vaza" para a página que embute esta fatura.
       As regras de p/h1/h2 abaixo (.page p ...) vencem os estilos do Bootstrap. */
    :where(.page, .page *) {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    .page h1,
    .page h2,
    .page p {
      margin: 0;
      padding: 0;
    }

    /* fundo cinzento só quando o ficheiro é aberto sozinho */
    html:has(body.invoice-public),
    body.invoice-public {
      background: #e9e9e9;
    }

    body.invoice-public {
      margin: 0;
    }

    /* A4 em pontos: as coordenadas abaixo são as mesmas do PDF */
    .page {
      position: relative;
      width: 595.28pt;
      height: 841.89pt;
      margin: 16pt auto;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .15);
      font-family: Helvetica, Arial, sans-serif;
      color: #707070;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    @media print {

      html:has(body.invoice-public),
      body.invoice-public {
        background: #fff;
      }

      .page {
        margin: 0;
        box-shadow: none;
        page-break-after: always;
        break-after: page;
      }

      .page:last-child {
        page-break-after: auto;
        break-after: auto;
      }
    }

    .abs {
      position: absolute;
    }

    /* ---------- cabeçalho ---------- */
    .logo {
      position: absolute;
      left: 45pt;
      top: 35pt;
      width: 70pt;
    }

    .company {
      position: absolute;
      left: 45pt;
      top: 120pt;
      width: 390pt;
    }

    .company h1 {
      font-size: 17pt;
      line-height: 20pt;
      color: #000;
      text-transform: uppercase;
    }

    .company p {
      font-size: 9pt;
      line-height: 14pt;
      color: #707070;
    }

    .client {
      position: absolute;
      left: 457pt;
      top: 120pt;
      width: 93pt;
    }

    .client h2 {
      font-size: 12pt;
      line-height: 14pt;
      color: #000;
    }

    .client p {
      font-size: 9pt;
      line-height: 14pt;
      color: #707070;
    }

    /* ---------- documento ---------- */
    .copy {
      position: absolute;
      left: 45pt;
      top: 220pt;
      font-size: 10pt;
      color: #707070;
    }

    .doc-title {
      position: absolute;
      left: 45pt;
      top: 238pt;
      font-size: 12pt;
      font-weight: bold;
      color: #000;
    }

    .rule {
      position: absolute;
      left: 45pt;
      top: 263pt;
      width: 505pt;
      border-top: 1.5pt solid #8C8C8C;
    }

    .drow {
      position: absolute;
      left: 0;
      width: 100%;
      font-size: 9pt;
      line-height: 11pt;
    }

    .drow span {
      position: absolute;
      top: 0;
    }

    .r1 {
      top: 273pt;
    }

    .r2 {
      top: 291pt;
    }

    .r3 {
      top: 309pt;
    }

    .x1 {
      left: 45pt;
    }

    .x2 {
      left: 145pt;
    }

    .x3 {
      left: 365pt;
    }

    .x4 {
      left: 455pt;
    }

    .strong {
      font-weight: bold;
      color: #444;
    }

    /* ---------- tabela + totais (fluxo) ---------- */
    .flow {
      position: absolute;
      left: 45pt;
      top: 324pt;
      width: 505pt;
    }

    .t-head {
      position: relative;
      height: 20pt;
      margin-bottom: 16pt;
      padding-top: 10pt;
      border-top: 1pt solid #8C8C8C;
      border-bottom: .8pt solid #D2D2D2;
      font-size: 8pt;
      font-weight: bold;
      color: #555;
    }

    .t-head span {
      position: absolute;
      top: 10pt;
      line-height: 10pt;
    }

    .t-body {
      min-height: 130pt;
      padding-bottom: 10pt;
    }

    .t-body.has-carry {
      padding-bottom: 0;
    }

    /* .irow (antes .row): evita conflito com o .row do Bootstrap */
    .irow {
      position: relative;
      display: block;
      min-height: 17pt;
      padding-bottom: 7pt;
      font-size: 8pt;
      line-height: 9.6pt;
      color: #666;
    }

    .irow .desc {
      margin-left: 90pt;
      width: 155pt;
    }

    .irow span.c {
      position: absolute;
      top: 0;
      white-space: nowrap;
    }

    .c-code {
      left: 5pt;
      width: 80pt;
      white-space: normal !important;
    }

    .c-price {
      left: 250pt;
      width: 55pt;
      text-align: right;
    }

    .c-qty {
      left: 310pt;
      width: 30pt;
      text-align: center;
    }

    .c-tax {
      left: 343pt;
      width: 42pt;
      text-align: center;
    }

    .c-disc {
      left: 388pt;
      width: 35pt;
      text-align: center;
    }

    .c-total {
      left: 430pt;
      width: 70pt;
      text-align: right;
    }

    .carry {
      margin-top: 2pt;
      height: 14pt;
      text-align: right;
      font-size: 8pt;
      font-style: italic;
      color: #707070;
    }

    /* ---------- totais ---------- */
    .totals {
      position: relative;
      height: 136pt;
      border-top: 1.5pt solid #8C8C8C;
      font-size: 8.5pt;
      line-height: 10pt;
      color: #707070;
    }

    .totals .t,
    .totals .a {
      position: absolute;
    }

    .totals .title {
      font-size: 10pt;
      font-weight: bold;
      color: #555;
      top: 8pt;
    }

    .totals .hline {
      position: absolute;
      top: 24pt;
      border-top: .8pt solid #C4C4C4;
    }

    .lv {
      left: 100pt;
      width: 190pt;
    }

    .rv {
      left: 405pt;
      width: 100pt;
      text-align: right;
    }

    .rl {
      left: 320pt;
    }

    .totals .sep1 {
      position: absolute;
      left: 320pt;
      width: 180pt;
      top: 106pt;
      border-top: 1pt solid #555;
    }

    .totals .final {
      position: absolute;
      top: 116pt;
      font-size: 12pt;
      font-weight: bold;
      color: #000;
      line-height: 14pt;
    }

    .totals .sep2 {
      position: absolute;
      left: 320pt;
      width: 180pt;
      top: 134pt;
      border-top: 2pt solid #555;
    }

    /* ---------- rodapé ---------- */
    .qr {
      position: absolute;
      left: 45pt;
      top: 772pt;
      width: 55pt;
      height: 55pt;
    }

    .agt {
      position: absolute;
      left: 112pt;
      top: 796pt;
      width: 340pt;
      font-size: 8pt;
      color: #000;
      white-space: nowrap;
    }

    .pg {
      position: absolute;
      left: 500pt;
      top: 796pt;
      width: 50pt;
      text-align: right;
      font-size: 8pt;
      color: #000;
    }
  </style>
</head>

<body class="invoice-public">
  <?php foreach ($copies as $copyLabel): ?>
    <?php foreach ($pages as $pageIndex => $pageItems):
      $isLast = $pageIndex === $pageCount - 1; ?>

      <section class="page">

        <!-- LOGO -->
        <?php if ($logoFile !== ''): ?>
          <img class="logo" src="<?= e($publicBasePath . '/assets/img/companies/' . rawurlencode($logoFile)) ?>" alt="Logo">
        <?php endif; ?>

        <!-- EMPRESA -->
        <div class="company">
          <h1><?= e($inv['company_name']) ?></h1>
          <p><?= e($companyAddress ?: '-') ?></p>
          <p>Tel: <?= e($inv['company_phone'] ?: '-') ?></p>
          <p>E-mail: <?= e($inv['company_email'] ?: '-') ?></p>
          <p>Contribuinte: <?= e($inv['registration_number'] ?: '-') ?></p>
        </div>

        <!-- CLIENTE -->
        <div class="client">
          <h2>Exmo Sr.</h2>
          <p><?= e($clientName) ?></p>
          <p>NIF: <?= e($clientNif) ?></p>
          <p><?= e($clientPhone) ?></p>
        </div>

        <!-- DOCUMENTO -->
        <div class="copy"><?= e($copyLabel) ?></div>
        <div class="doc-title"><?= e($docTitle) ?></div>
        <div class="rule"></div>

        <div class="drow r1">
          <span class="x1">Cliente:</span>
          <span class="x2 strong" style="width:200pt"><?= e($clientName) ?></span>
          <span class="x3">Data de emissão:</span>
          <span class="x4"><?= e($issueBr) ?></span>
        </div>
        <div class="drow r2">
          <span class="x1">Contribuinte:</span>
          <span class="x2"><?= e($clientNif) ?></span>
          <span class="x3">Vencimento:</span>
          <span class="x4"><?= e($dueBr) ?></span>
        </div>
        <div class="drow r3">
          <span class="x1">Endereço:</span>
          <span class="x2" style="width:180pt"><?= e($clientAddress) ?></span>
          <span class="x3">Observações:</span>
          <span class="x4" style="width:95pt"><?= e($inv['observation'] ?: '-') ?></span>
        </div>

        <!-- TABELA + TOTAIS -->
        <div class="flow">

          <div class="t-head">
            <span style="left:5pt;width:80pt">Código</span>
            <span style="left:90pt;width:155pt">Descrição</span>
            <span style="left:250pt;width:55pt;text-align:right">Preço Uni.</span>
            <span style="left:310pt;width:30pt;text-align:center">Qtd.</span>
            <span style="left:343pt;width:42pt;text-align:center">Taxa/IVA</span>
            <span style="left:388pt;width:35pt;text-align:center">Desc.</span>
            <span style="left:430pt;width:70pt;text-align:right">Total</span>
          </div>

          <div class="t-body<?= $isLast ? '' : ' has-carry' ?>">
            <?php foreach ($pageItems as $it):
              $base = (float)$it['unit_price'] * (float)$it['quantity'];
              $discount = $base * ((float)$it['discount'] / 100);
              $total = $base - $discount + (($base - $discount) * ((float)$it['tax'] / 100)); ?>
              <div class="irow">
                <span class="c c-code"><?= e($it['code']) ?></span>
                <div class="desc"><?= e(itemLabel($it)) ?></div>
                <span class="c c-price"><?= e($money($it['unit_price'])) ?></span>
                <span class="c c-qty"><?= e($it['quantity']) ?></span>
                <span class="c c-tax"><?= e($it['tax']) ?>%</span>
                <span class="c c-disc"><?= e($it['discount']) ?>%</span>
                <span class="c c-total"><?= e($money($total)) ?></span>
              </div>
            <?php endforeach; ?>

            <?php if (!$isLast): ?>
              <div class="carry">A transportar…</div>
            <?php endif; ?>
          </div>

          <!-- DADOS FISCAIS E SUMÁRIO (em todas as páginas) -->
          <div class="totals">
            <div class="t title" style="left:0">Dados fiscais e bancários</div>
            <div class="t title" style="left:320pt">Sumário</div>
            <div class="hline" style="left:0;width:295pt"></div>
            <div class="hline" style="left:320pt;width:185pt"></div>

            <!-- esquerda -->
            <div class="t" style="left:0;top:34pt">Regime de IVA:</div>
            <div class="t lv" style="top:34pt"><?= e($vatRegime) ?></div>

            <div class="t" style="left:0;top:54pt">Bens e serviços:</div>
            <div class="t lv" style="top:54pt;width:180pt">Os bens e serviços foram colocados à disposição<br>do adquirente na data do documento.</div>

            <div class="t" style="left:0;top:96pt">Dados bancários:</div>
            <div class="t lv" style="top:96pt"><?= e($iban) ?></div>

            <!-- direita -->
            <div class="t rl" style="top:32pt">Total líquido:</div>
            <div class="t rv" style="top:32pt"><?= e($money($inv['total_sum'])) ?></div>

            <div class="t rl" style="top:48pt">Desconto:</div>
            <div class="t rv" style="top:48pt"><?= e($money($inv['total_discount'])) ?></div>

            <div class="t rl" style="top:62pt">Sem Imposto/IVA c.Desc.:</div>
            <div class="t rv" style="top:62pt"><?= e($money($inv['total_sum'] - $inv['total_discount'])) ?></div>

            <div class="t rl" style="top:76pt">Imposto/IVA:</div>
            <div class="t rv" style="top:76pt"><?= e($money($inv['total_tax'])) ?></div>

            <div class="t rl" style="top:90pt">Retenção:</div>
            <div class="t rv" style="top:90pt"><?= e($money($inv['retention_value'])) ?></div>

            <div class="sep1"></div>
            <div class="final rl">Total:</div>
            <div class="final" style="left:395pt;width:110pt;text-align:right"><?= e($money($inv['final_total'])) ?></div>
            <div class="sep2"></div>
          </div>

        </div><!-- /.flow -->

        <!-- RODAPÉ (em todas as páginas) -->
        <img class="qr" src="<?= $qrSrc ?>" alt="QR">
        <div class="agt">Factura processada pelo software certificado pela AGT | Nº <?= e(AGT_CERTIFICATE) ?></div>
        <div class="pg"><?= $pageIndex + 1 ?>/<?= $pageCount ?></div>

      </section>

    <?php endforeach; ?>
  <?php endforeach; ?>
</body>

</html>