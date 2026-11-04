<?php
/*  invoice_public.php
 *  Pré-visualização HTML da FACTURA / FACTURA-RECIBO — réplica fiel do PDF (invoicePdfService.js).
 *
 *  Todas as posições estão em pontos (pt) e são as MESMAS do PDF:
 *  - logo na caixa 105x55 · empresa (x45,y100) · cliente (x435,y100)
 *  - "Original" y245 · título y263 · linha y288 · linhas de dados y298/316/334
 *  - tabela: itens em colunas fixas, máx. 10 por página, "Subtotal da página" + "A transportar…"
 *  - totais logo a seguir aos itens (Dados fiscais e bancários | Sumário), sem espaço vazio
 *  - rodapé: QR, texto AGT, "Powered by BXPERT" e "Página n de N"
 */
require_once '../../../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
require_once __DIR__ . '/../../../app/helpers/company_logo.php';

// ---------- configuração (igual ao PDF) ----------
const AGT_CERTIFICATE = 'FE/344/AGT/2026';
const ITEMS_PER_PAGE  = 10;
const ITEMS_BOTTOM    = 588;   // limite vertical (pt) das linhas: deixa espaço p/ "A transportar…", totais (136pt) e rodapé (QR em y776)
const LH              = 1.156; // altura de linha da Helvetica (pdfkit): 1,156 x tamanho
$copies = ['Original']; // para ver as duas cópias: ['Original', 'Duplicado']

// Colunas da tabela (x, largura) — iguais às do PDF
const COL = [
  'code'     => [50, 70],
  'desc'     => [125, 115],
  'price'    => [243, 80],
  'qty'      => [327, 28],
  'tax'      => [358, 38],
  'discount' => [399, 32],
  'total'    => [436, 109],
];

// ---------- utils ----------
function e($value): string
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// 1 234 567,89 Kz  (separador de milhares = espaço, como no PDF)
function formatCurrency(float $value, string $currencySymbol, string $currencyPosition = 'right'): string
{
  $formatted = number_format($value, 2, ',', ' ');
  $currencySymbol = trim($currencySymbol);
  if ($currencySymbol === '') return $formatted;
  return $currencyPosition === 'left'
    ? "{$currencySymbol} {$formatted}"
    : "{$formatted} {$currencySymbol}";
}

// 14.00 -> "14" · 5.5 -> "5.5" (como o Number() do PDF)
function plainNumber($value): string
{
  $s = number_format((float)$value, 2, '.', '');
  return rtrim(rtrim($s, '0'), '.');
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

// "Rua X, Edifício Y, Piso 9" -> cada parte numa <span> que nunca parte a meio:
// o navegador só quebra a linha nas vírgulas (igual ao wrapByComma do PDF)
function wrapByCommaHtml(string $value): string
{
  $parts = array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
  $out = [];
  foreach ($parts as $i => $part) {
    $out[] = '<span class="nb">' . e($part) . ($i < count($parts) - 1 ? ',' : '') . '</span>';
  }
  return implode(' ', $out);
}

// ---------- medição de texto (Helvetica, em 1/1000 do corpo) ----------
function helveticaWidths(): array
{
  static $w = null;
  if ($w !== null) return $w;
  $list = [
    32 => 278,
    33 => 278,
    34 => 355,
    35 => 556,
    36 => 556,
    37 => 889,
    38 => 667,
    39 => 191,
    40 => 333,
    41 => 333,
    42 => 389,
    43 => 584,
    44 => 278,
    45 => 333,
    46 => 278,
    47 => 278,
    58 => 278,
    59 => 278,
    60 => 584,
    61 => 584,
    62 => 584,
    63 => 556,
    64 => 1015,
    65 => 667,
    66 => 667,
    67 => 722,
    68 => 722,
    69 => 667,
    70 => 611,
    71 => 778,
    72 => 722,
    73 => 278,
    74 => 500,
    75 => 667,
    76 => 556,
    77 => 833,
    78 => 722,
    79 => 778,
    80 => 667,
    81 => 778,
    82 => 722,
    83 => 667,
    84 => 611,
    85 => 722,
    86 => 667,
    87 => 944,
    88 => 667,
    89 => 667,
    90 => 611,
    91 => 278,
    92 => 278,
    93 => 278,
    94 => 469,
    95 => 556,
    96 => 333,
    97 => 556,
    98 => 556,
    99 => 500,
    100 => 556,
    101 => 556,
    102 => 278,
    103 => 556,
    104 => 556,
    105 => 222,
    106 => 222,
    107 => 500,
    108 => 222,
    109 => 833,
    110 => 556,
    111 => 556,
    112 => 556,
    113 => 556,
    114 => 333,
    115 => 500,
    116 => 278,
    117 => 556,
    118 => 500,
    119 => 722,
    120 => 500,
    121 => 500,
    122 => 500,
    123 => 334,
    124 => 260,
    125 => 334,
    126 => 584,
  ];
  for ($c = 48; $c <= 57; $c++) $list[$c] = 556;
  $w = $list;
  return $w;
}

function charWidth(string $ch, float $size): float
{
  static $accents = null;
  if ($accents === null) {
    $accents = [];
    $map = [
      'a' => 'áàâãäå',
      'e' => 'éèêë',
      'i' => 'íìîï',
      'o' => 'óòôõö',
      'u' => 'úùûü',
      'c' => 'ç',
      'n' => 'ñ',
      'A' => 'ÁÀÂÃÄÅ',
      'E' => 'ÉÈÊË',
      'I' => 'ÍÌÎÏ',
      'O' => 'ÓÒÔÕÖ',
      'U' => 'ÚÙÛÜ',
      'C' => 'Ç',
      'N' => 'Ñ',
    ];
    foreach ($map as $base => $chars) {
      foreach (mb_str_split($chars) as $c) $accents[$c] = $base;
    }
  }
  $ch = $accents[$ch] ?? $ch;
  $code = strlen($ch) === 1 ? ord($ch) : 0;
  $w = helveticaWidths()[$code] ?? 556;
  return $w * $size / 1000;
}

function textWidth(string $s, float $size): float
{
  $t = 0.0;
  foreach (mb_str_split($s) as $ch) $t += charWidth($ch, $size);
  return $t;
}

// nº de linhas de um texto na largura dada (quebra em espaços/hífens; palavras
// maiores que a largura partem-se por carácter — como o pdfkit)
function wrapCount(string $text, float $width, float $size): int
{
  $lines = 0;
  foreach (preg_split('/\R/u', $text) ?: [''] as $para) {
    $lines++;
    $cur = 0.0;
    $tokens = preg_split('/(?<=[ \-])/u', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($tokens as $tok) {
      $full = textWidth($tok, $size);
      // o pdfkit conta o espaço que segue a palavra: só cabe se palavra + espaço couberem
      if ($cur + $full <= $width) {
        $cur += $full;
        continue;
      }
      if ($cur > 0) {
        $lines++;
        $cur = 0.0;
      }
      if ($full <= $width) {
        $cur = $full;
        continue;
      }
      foreach (mb_str_split(rtrim($tok, ' ')) as $ch) {
        $cw = charWidth($ch, $size);
        if ($cur + $cw > $width && $cur > 0) {
          $lines++;
          $cur = 0.0;
        }
        $cur += $cw;
      }
    }
  }
  return max(1, $lines);
}

// altura (pt) de uma linha da tabela: mínimo 17, cresce com descrições longas
function itemHeight(array $it): float
{
  return max(17.0, wrapCount(itemLabel($it), COL['desc'][1], 8) * 8 * LH + 7);
}

// quebra os itens em páginas (máx. ITEMS_PER_PAGE e limite vertical) — igual ao PDF
function paginateItems(array $items, float $firstRowY): array
{
  $pages = [];
  $current = [];
  $y = $firstRowY;

  foreach ($items as $it) {
    $h = itemHeight($it);
    if ($current && (count($current) >= ITEMS_PER_PAGE || $y + $h > ITEMS_BOTTOM)) {
      $pages[] = $current;
      $current = [];
      $y = $firstRowY;
    }
    $current[] = $it;
    $y += $h;
  }

  if ($current || !$pages) $pages[] = $current;
  return $pages;
}

// ---------- helpers de desenho (coordenadas da PÁGINA, em pt) ----------
// texto absoluto: invoiceText(x, y, html, classes, largura opcional)
function invoiceText(float $x, float $y, string $html, string $class = '', ?float $w = null): string
{
  $style = "left:{$x}pt;top:{$y}pt;" . ($w !== null ? "width:{$w}pt;" : '');
  return '<div class="t ' . $class . '" style="' . $style . '">' . $html . '</div>' . "\n";
}

// linha horizontal com a espessura do traço (centrada em y, como no PDF)
function HL(float $x1, float $x2, float $y, float $stroke, string $color): string
{
  $top = $y - $stroke / 2;
  $w = $x2 - $x1;
  return "<div class=\"hl\" style=\"left:{$x1}pt;top:{$top}pt;width:{$w}pt;height:{$stroke}pt;background:{$color}\"></div>\n";
}

// ---------- input ----------
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
// Tipo explícito (invoice | proforma). Nunca se deduz pelo id: as duas tabelas colidem.
$kind = strtolower((string)($_GET['kind'] ?? 'invoice'));
if (!in_array($kind, ['invoice', 'proforma'], true)) {
  http_response_code(400);
  die('<h3>Tipo de documento inválido.</h3>');
}
$isProforma = $kind === 'proforma';
$mainTable   = $isProforma ? 'proformas'       : 'invoices';
$itemsTable  = $isProforma ? 'proforma_items'  : 'invoice_items';
$itemsFk     = $isProforma ? 'proforma_id'     : 'invoice_id';
$statusTable = $isProforma ? 'proforma_status' : 'invoice_status';

if (!isset($_SESSION['user']['company_id'])) {
  http_response_code(401);
  die('<h3>Sessão expirada.</h3>');
}
$sessionCompanyId = (int)$_SESSION['user']['company_id'];

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
  die('<h3>' . ($isProforma ? 'Proforma' : 'Fatura') . ' não encontrada.</h3>');
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
FROM      {$mainTable} i
JOIN      companies   comp ON comp.id = i.company_id
JOIN      contact         c ON c.id    = i.contact_id
JOIN      {$statusTable} ivs ON ivs.id = i.status
JOIN      currencies    cr2 ON cr2.iso_code = i.currency
WHERE i.id = :id AND i.company_id = :company_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id, 'company_id' => $sessionCompanyId]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$inv) {
  http_response_code(404);
  die('<h3>' . ($isProforma ? 'Proforma' : 'Fatura') . ' não encontrada.</h3>');
}
if ($isProforma) {
  $inv['document_type'] = 'PF';
}

$scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$publicBasePath = str_replace('\\', '/', dirname(dirname(dirname($scriptPath))));
$publicBasePath = ($publicBasePath === '/' || $publicBasePath === '.') ? '' : rtrim($publicBasePath, '/');
$logoSrc = company_logo_src($inv['logo_url'] ?? '', $publicBasePath)
  ?: company_logo_src('default.png', $publicBasePath);

// ---------- itens ----------
$stmt = $pdo->prepare("
SELECT it.code, it.name, it.description, ii.quantity, ii.unit_price,
       ii.tax, ii.discount
FROM   {$itemsTable} ii
JOIN   items it ON it.id = ii.item_id
WHERE  ii.{$itemsFk} = :id");
$stmt->execute(['id' => $id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proformas guardam só a % de retenção; o valor calcula-se (igual a get_invoice.php).
$retentionValue = (float)($inv['retention_value'] ?? 0);
if ($isProforma) {
  $base = bx_document_tax_summary($items, (string)($inv['vat_regime'] ?? ''));
  $retentionValue = max(0.0, ($base['subtotal'] + $base['total_tax']) * (float)($inv['retention'] ?? 0) / 100);
}
$taxSummary = bx_document_tax_summary(
  $items,
  (string)($inv['vat_regime'] ?? ''),
  $retentionValue
);
$inv['total_sum'] = $taxSummary['total_sum'];
$inv['total_discount'] = $taxSummary['total_discount'];
$inv['total_tax'] = $taxSummary['total_tax'];
$inv['final_total'] = $taxSummary['final_total'];
$inv['retention_value'] = $taxSummary['retention'];

// ---------- QR ----------
$qrData = "https://bxpert.co.ao/sistema/invoice_public.php?id={$inv['id']}&kind={$kind}";
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
$pos    = strtolower((string)$inv['moneyPos']) === 'left' ? 'left' : 'right';
$money  = fn($v) => formatCurrency((float)$v, $symbol, $pos);

$issueBr = dateBr($inv['issue_date']);
$dueDays = (int)$inv['due_date'];
$dueBr = $inv['issue_date']
  ? dateBr((new DateTime($inv['issue_date']))->modify("+{$dueDays} days")->format('Y-m-d'))
  : '-';

$companyAddress = implode(', ', array_filter([$inv['company_address'], $inv['company_city'], $inv['company_country']]));
$clientAddress  = implode(', ', array_filter([$inv['client_address'], $inv['client_city'], $inv['client_country']])) ?: '-';
$clientName     = firstAndLastName($inv['client_name']);
$clientNif      = $inv['client_contributor'] ?: '-';
$clientPhone    = ($inv['client_phone'] ?? '') !== '' ? $inv['client_phone'] : '-';   // adicione c.phone AS client_phone à query para mostrar o telefone

$vatRegime = match (strtolower((string)($inv['vat_regime'] ?? ''))) {
  'geral'        => 'Regime Geral',
  'simplificado' => 'Regime Simplificado',
  default        => '-',
};

$goodsServices = trim((string)($inv['goods_services'] ?? ''));
$goodsServicesText = $goodsServices !== ''
  ? $goodsServices
  : 'Os bens/serviços foram colocados à disposição do adquirente na data e local do documento.';

$ibanRaw = preg_replace('/[.\s]/', '', (string)($inv['iban'] ?? ''));
$iban = $ibanRaw !== ''
  ? trim(substr($ibanRaw, 0, 4) . ' ' . trim(chunk_split(substr($ibanRaw, 4), 4, ' ')))
  : '-';

$isDraft  = $isProforma
  ? strcasecmp(trim((string)$inv['status_invoice']), 'Rascunho') === 0
  : (int)$inv['status'] === 1;
$documentType = $isProforma ? 'PF' : strtoupper((string)($inv['document_type'] ?? 'FT'));
$documentLabel = match ($documentType) {
  'PF'    => 'Proforma',
  'FR'    => 'Factura-Recibo',
  default => 'Factura',
};
$docTitle = ($isDraft ? $documentLabel . ' Rascunho' : $documentLabel) . ' n.º ' . ($isDraft ? '' : (string)($inv['reference'] ?? ''));

$observationText = trim((string)($inv['observation'] ?? ''));
$observationShown = $observationText !== '' ? $observationText : '-';

// Paginação: a 1.ª linha de itens começa onde a tabela realmente começa:
// 334 (observações) + altura das observações + 15 (espaço) + 36 (cabeçalho da tabela)
$obsHeight = wrapCount($observationShown, 405, 9) * 9 * LH;
$firstRowY = 334 + max(12.0, $obsHeight) + 15 + 36;
$pages = paginateItems($items, $firstRowY);
$pageCount = count($pages);

// Linha de itens: incidência (valor sem IVA, já com desconto) — base do "Subtotal da página"
function lineSubtotal(array $it): float
{
  $base = (float)$it['unit_price'] * (float)$it['quantity'];
  return $base - ($base * (float)$it['discount'] / 100);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
  <meta charset="utf-8">
  <title><?= e($documentLabel) ?> <?= e($inv['codigo'] ?? $inv['reference'] ?? '') ?></title>
  <style>
    @page {
      size: A4;
      margin: 0;
    }

    /* Reset com especificidade 0: não "vaza" para a página que embute este documento. */
    :where(.page, .page *) {
      box-sizing: border-box;
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

    /* A4 em pontos: todas as coordenadas abaixo são as do PDF */
    .page {
      position: relative;
      width: 595.28pt;
      height: 841.89pt;
      margin: 16pt auto;
      background: #fff;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0, 0, 0, .15);
      font-family: Arial, Helvetica, "Liberation Sans", sans-serif;
      line-height: 1.156;
      color: #707070;
      font-kerning: none;
      font-variant-ligatures: none;
      text-rendering: geometricPrecision;
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

    /* O pdfkit desenha o texto a partir do topo da linha e não aplica kerning.
       No navegador a linha centra o texto; o deslocamento abaixo (calibrado contra o PDF
       impresso) faz o texto cair no mesmo ponto, e font-kerning:none dá as mesmas larguras. */
    .px,
    .t {
      transform: translateY(-.09em);
    }

    .t {
      position: absolute;
      white-space: nowrap;
    }

    .t.wrap {
      white-space: normal;
    }

    .hl {
      position: absolute;
    }

    .f8 {
      font-size: 8pt;
    }

    .f85 {
      font-size: 8.5pt;
    }

    .f9 {
      font-size: 9pt;
    }

    .f10 {
      font-size: 10pt;
    }

    .f12 {
      font-size: 12pt;
    }

    .b {
      font-weight: bold;
    }

    .i {
      font-style: italic;
    }

    .c0 {
      color: #000;
    }

    .c4 {
      color: #444;
    }

    .c5 {
      color: #555;
    }

    .c6 {
      color: #666;
    }

    .cg {
      color: #707070;
    }

    .r {
      text-align: right;
    }

    .ce {
      text-align: center;
    }

    .nb {
      white-space: nowrap;
    }

    /* ---------- cabeçalho ---------- */
    .logo-box {
      position: absolute;
      left: 45pt;
      top: 35pt;
      width: 105pt;
      height: 55pt;
      overflow: hidden;
    }

    .logo-box img {
      display: block;
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
      object-position: left top;
    }

    .blk {
      position: absolute;
      top: 100pt;
    }

    .blk>div {
      margin-bottom: 3pt;
    }

    .company {
      left: 45pt;
      width: 365pt;
    }

    .client {
      left: 435pt;
      width: calc(115pt - .278 * 9pt);
      overflow-wrap: anywhere;
    }

    .company .addr {
      width: 190pt;
    }

    /* ---------- tabela (fluxo) ---------- */
    .flow {
      position: absolute;
      left: 0;
      top: 334pt;
      width: 595.28pt;
    }

    .obs {
      position: relative;
      min-height: 12pt;
      margin-bottom: 15pt;
      font-size: 9pt;
    }

    .obs .lab {
      position: absolute;
      left: 45pt;
      top: 0;
    }

    .obs .val {
      margin-left: 145pt;
      width: calc(405pt - .278em);
      overflow-wrap: anywhere;
    }

    .th {
      position: relative;
      height: 20pt;
      margin-top: -.5pt;
      margin-bottom: 16.5pt;
    }

    .th::before,
    .th::after {
      content: "";
      position: absolute;
      left: 45pt;
      width: 505pt;
    }

    .th::before {
      top: 0;
      height: 1pt;
      background: #8C8C8C;
    }

    .th::after {
      top: 20.1pt;
      height: .8pt;
      background: #D2D2D2;
    }

    .th span {
      position: absolute;
      top: 10.5pt;
      font-size: 8pt;
      font-weight: bold;
      color: #555;
      white-space: nowrap;
      transform: translateY(-.09em);
    }

    .irow {
      position: relative;
      display: block;
      min-height: 17pt;
      padding-bottom: 7pt;
      font-size: 8pt;
      color: #666;
    }

    .irow .desc {
      margin-left: 125pt;
      width: calc(115pt - .278em);
      overflow-wrap: anywhere;
      transform: translateY(-.09em);
    }

    .irow .c {
      position: absolute;
      top: 0;
      white-space: nowrap;
      transform: translateY(-.09em);
    }

    .irow .c.code {
      white-space: normal;
      overflow-wrap: anywhere;
    }

    .carry-wrap {
      position: relative;
      height: 36pt;
      /* subtotal + "A transportar…" + folga até aos totais */
    }

    /* bloco de totais: em fluxo, logo a seguir aos itens */
    .tot {
      position: relative;
      height: 136pt;
    }

    .tot-last {
      margin-top: 14pt;
    }

    /* ---------- rodapé ---------- */
    .qr {
      position: absolute;
      left: 49.2pt;
      top: 776.7pt;
      width: 45pt;
      height: 45pt;
    }
  </style>
</head>

<body class="invoice-public">
  <?php
  $carried = 0.0; // acumulado ("A transportar")
  foreach ($copies as $copyLabel):
    $carried = 0.0;
    foreach ($pages as $pageIndex => $pageItems):
      $isLast = $pageIndex === $pageCount - 1;

      // subtotal (incidência) desta página e acumulado até aqui
      $pageSubtotal = 0.0;
      foreach ($pageItems as $it) $pageSubtotal += lineSubtotal($it);
      $carried += $pageSubtotal;
      $sy = 8 + 16 + 10; // 34: início das linhas, relativo ao topo do bloco de totais
  ?>

      <section class="page">

        <!-- LOGO (cabe na caixa 105x55, sem deformar) -->
        <?php if ($logoSrc !== ''): ?>
          <div class="logo-box">
            <img src="<?= e($logoSrc) ?>" alt="Logótipo da empresa"
              onerror="this.parentNode.style.display='none'">
          </div>
        <?php endif; ?>

        <!-- EMPRESA -->
        <div class="blk company">
          <div class="px f12 b c0" style="text-transform:uppercase"><?= e($inv['company_name'] ?: '-') ?></div>
          <div class="px f9 cg addr"><?= $companyAddress !== '' ? wrapByCommaHtml($companyAddress) : '-' ?></div>
          <div class="px f9 cg">Tel: <?= e($inv['company_phone'] ?: '-') ?></div>
          <div class="px f9 cg">E-mail: <?= e($inv['company_email'] ?: '-') ?></div>
          <div class="px f9 cg">Contribuinte: <?= e($inv['registration_number'] ?: '-') ?></div>
        </div>

        <!-- CLIENTE -->
        <div class="blk client">
          <div class="px f12 b c0"><?= e($clientName) ?></div>
          <div class="px f9 cg">Contribuinte: <?= e($clientNif) ?></div>
          <div class="px f9 cg"><?= e($clientAddress) ?></div>
          <div class="px f9 cg"><?= e($clientPhone) ?></div>
        </div>

        <!-- DOCUMENTO -->
        <?= invoiceText(45, 245, e($copyLabel), 'f10 cg') ?>
        <?= invoiceText(45, 263, e($docTitle), 'f12 b c0') ?>
        <?= HL(45, 550, 288, 1.5, '#8C8C8C') ?>

        <?= invoiceText(45, 298, 'Cliente:', 'f9 cg') ?>
        <?= invoiceText(145, 298, e($clientName), 'f9 b c4', 200) ?>
        <?= invoiceText(365, 298, 'Data de emissão:', 'f9 cg') ?>
        <?= invoiceText(455, 298, e($issueBr), 'f9 cg') ?>

        <?= invoiceText(45, 316, 'Contribuinte:', 'f9 cg') ?>
        <?= invoiceText(145, 316, e($clientNif), 'f9 cg') ?>
        <?= invoiceText(365, 316, 'Vencimento:', 'f9 cg') ?>
        <?= invoiceText(455, 316, e($dueBr), 'f9 cg') ?>

        <!-- OBSERVAÇÕES + TABELA (em fluxo: se as observações crescerem, a tabela desce) -->
        <div class="flow">

          <div class="obs">
            <span class="lab px cg">Observações:</span>
            <div class="val px cg"><?= nl2br(e($observationShown)) ?></div>
          </div>

          <div class="th">
            <span style="left:50pt;width:70pt">Código</span>
            <span style="left:125pt;width:115pt">Descrição</span>
            <span style="left:243pt;width:80pt;text-align:right">Preço Uni.</span>
            <span style="left:327pt;width:28pt;text-align:center">Qtd.</span>
            <span style="left:358pt;width:38pt;text-align:center">Taxa/IVA</span>
            <span style="left:399pt;width:32pt;text-align:center">Desc.</span>
            <span style="left:436pt;width:109pt;text-align:right">Total</span>
          </div>

          <?php foreach ($pageItems as $it):
            $base = (float)$it['unit_price'] * (float)$it['quantity'];
            $discount = $base * ((float)$it['discount'] / 100);
            $effectiveRate = (float)bx_document_effective_tax_rate($it['tax'], (string)($inv['vat_regime'] ?? ''));
            $total = $base - $discount + (($base - $discount) * ($effectiveRate / 100)); ?>
            <div class="irow">
              <span class="c code" style="left:50pt;width:70pt"><?= e($it['code']) ?></span>
              <div class="desc"><?= e(itemLabel($it)) ?></div>
              <span class="c" style="left:243pt;width:80pt;text-align:right"><?= e($money($it['unit_price'])) ?></span>
              <span class="c" style="left:327pt;width:28pt;text-align:center"><?= e(plainNumber($it['quantity'])) ?></span>
              <span class="c" style="left:358pt;width:38pt;text-align:center"><?= e(plainNumber($effectiveRate)) ?>%</span>
              <span class="c" style="left:399pt;width:32pt;text-align:center"><?= e(plainNumber($it['discount'])) ?>%</span>
              <span class="c" style="left:436pt;width:109pt;text-align:right"><?= e($money($total)) ?></span>
            </div>
          <?php endforeach; ?>

          <?php if (!$isLast): ?>
            <div class="carry-wrap f8 i cg">
              <div class="t r" style="left:300pt;width:129pt;top:2pt">Subtotal da página:</div>
              <div class="t r" style="left:436pt;width:109pt;top:2pt"><?= e($money($pageSubtotal)) ?></div>
              <div class="t r" style="left:45pt;width:505pt;top:14pt">A transportar…</div>
            </div>
          <?php endif; ?>


          <div class="tot<?= $isLast ? ' tot-last' : '' ?>">
            <!-- DADOS FISCAIS E SUMÁRIO: seguem logo a seguir aos itens (sem espaço vazio) -->
            <?= HL(45, 550, 0, 1.5, '#8C8C8C') ?>
            <?= invoiceText(45, 8, 'Dados fiscais e bancários', 'f10 b c5') ?>
            <?= invoiceText(365, 8, 'Sumário', 'f10 b c5') ?>
            <?= HL(45, 340, 24, .8, '#C4C4C4') ?>
            <?= HL(365, 550, 24, .8, '#C4C4C4') ?>

            <!-- esquerda -->
            <?= invoiceText(45, $sy, 'Regime de IVA:', 'f85 cg') ?>
            <?= invoiceText(145, $sy, e($vatRegime), 'f85 cg', 180) ?>
            <?= invoiceText(45, $sy + 20, 'Bens e serviços:', 'f85 cg') ?>
            <?= invoiceText(145, $sy + 20, e($goodsServicesText), 'f85 cg wrap', 180 - 0.278 * 8.5) ?>
            <?= invoiceText(45, $sy + 62, 'Dados bancários:', 'f85 cg') ?>
            <?= invoiceText(145, $sy + 62, e($iban), 'f85 cg', 190) ?>

            <!-- direita -->
            <?php if (!$isLast): ?>
              <?= invoiceText(365, $sy + 14, 'Subtotal da página:', 'f85 cg') ?>
              <?= invoiceText(450, $sy + 14, e($money($pageSubtotal)), 'f85 cg r', 100) ?>
              <?= HL(365, 545, $sy + 72, 1, '#555') ?>
              <?= invoiceText(365, $sy + 82, 'A transportar:', 'f10 b c0') ?>
              <?= invoiceText(440, $sy + 82, e($money($carried)), 'f10 b c0 r', 110) ?>
              <?= HL(365, 545, $sy + 100, 2, '#555') ?>
            <?php else: ?>
              <?php foreach (
                [
                  ['Total líquido:', $inv['total_sum'], -2],
                  ['Desconto:', $inv['total_discount'], 14],
                  ['Sem Imposto/IVA c.Desc.:', $inv['total_sum'] - $inv['total_discount'], 28],
                  ['Imposto/IVA:', $inv['total_tax'], 42],
                  ['Retenção:', $inv['retention_value'], 56],
                ] as [$label, $value, $dy]
              ): ?>
                <?= invoiceText(365, $sy + $dy, e($label), 'f85 cg') ?>
                <?= invoiceText(450, $sy + $dy, e($money($value)), 'f85 cg r', 100) ?>
              <?php endforeach; ?>
              <?= HL(365, 545, $sy + 72, 1, '#555') ?>
              <?= invoiceText(365, $sy + 82, 'Total:', 'f12 b c0') ?>
              <?= invoiceText(440, $sy + 82, e($money($inv['final_total'])), 'f12 b c0 r', 110) ?>
              <?= HL(365, 545, $sy + 100, 2, '#555') ?>
            <?php endif; ?>
          </div>

        </div><!-- /.flow -->

        <!-- RODAPÉ (em todas as páginas) -->
        <img class="qr" src="<?= $qrSrc ?>" alt="QR">
        <?= invoiceText(112.3, 795.5, 'Software certificado pela AGT | Nº ' . e(AGT_CERTIFICATE), 'f8 c0') ?>
        <?= invoiceText(112.3, 804.75, 'Powered by BXPERT', 'f8 c0') ?>
        <?= invoiceText(450, 795.5, 'Página ' . ($pageIndex + 1) . ' de ' . $pageCount, 'f8 c0 r', 100) ?>

      </section>

  <?php endforeach;
  endforeach; ?>
</body>

</html>