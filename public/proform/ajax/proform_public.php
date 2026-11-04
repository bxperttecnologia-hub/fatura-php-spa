<?php
<<<<<<< Updated upstream
/*  invoice_public.php
 *  Gera a fatura completa em HTML (modelo Personalité‑like) 
=======
/*  proform_public.php
 *  Pré-visualização HTML da PROFORMA — réplica fiel do PDF (invoicePdfService.js).
 *
 *  Todas as posições estão em pontos (pt) e são as MESMAS do PDF:
 *  - logo na caixa 105x55 · empresa (x45,y100) · cliente (x435,y100)
 *  - "Original" y245 · título y263 · linha y288 · linhas de dados y298/316/334
 *  - tabela: itens em colunas fixas, máx. 10 por página, "Subtotal da página" + "A transportar…"
 *  - totais logo a seguir aos itens (Dados fiscais e bancários | Sumário), sem espaço vazio
 *  - rodapé: QR, texto AGT, "Powered by BXPERT" e "Página n de N"
>>>>>>> Stashed changes
 */
require_once '../../../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};

require_once '../../../app/config/db.php';
<<<<<<< Updated upstream
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
=======
// document_tax.php: procura nas localizações possíveis (local XAMPP / produção)
foreach (
  [
    __DIR__ . '/../../../app/helpers/document_tax.php',
    __DIR__ . '/../../../helpers/document_tax.php',
    __DIR__ . '/../../app/helpers/document_tax.php',
    __DIR__ . '/../../../../app/helpers/document_tax.php',
  ] as $bxHelperFile
) {
  if (is_file($bxHelperFile)) {
    require_once $bxHelperFile;
    break;
  }
}
if (!function_exists('bx_document_tax_summary')) {
  die('<h3>Não foi possível carregar o ficheiro document_tax.php.</h3>');
}

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
>>>>>>> Stashed changes

// ---------- utils ----------
function formatCurrency(float $value, string $currencySymbol, string $currencyPosition = 'left'): string
{
  // formata 1 000 000.5 → 1.000.000,50
  $formatted = number_format($value, 2, ',', '.');

  return $currencyPosition === 'left'
    ? "{$currencySymbol} {$formatted}"
    : "{$formatted} {$currencySymbol}";
}

function dateBr($sqlDate)
{
  return $sqlDate ? date('d/m/Y', strtotime($sqlDate)) : '-';
}
function randomHash($len = 2)
{
  return substr(bin2hex(random_bytes($len)), 0, $len);
}

// ---------- valor por extenso (pt) ----------
function extenso_pt(int $n): string
{
  $n = (int)$n;
  if ($n === 0) return 'zero';

  $u = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
  $d10 = ['dez', 'onze', 'doze', 'treze', 'catorze', 'quinze', 'dezasseis', 'dezassete', 'dezoito', 'dezanove'];
  $t = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
  $c = ['', 'cem', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

  $parts = [];

  $bilhao = intdiv($n, 1000000000);
  $n %= 1000000000;
  if ($bilhao) {
    $parts[] = ($bilhao === 1 ? 'um bilião' : extenso_pt($bilhao) . ' biliões');
  }

<<<<<<< Updated upstream
  $milhao = intdiv($n, 1000000);
  $n %= 1000000;
  if ($milhao) {
    $parts[] = ($milhao === 1 ? 'um milhão' : extenso_pt($milhao) . ' milhões');
  }

  $mil = intdiv($n, 1000);
  $n %= 1000;
  if ($mil) {
    $parts[] = ($mil === 1 ? 'mil' : extenso_pt($mil) . ' mil');
  }

  if ($n) {
    $cent = intdiv($n, 100);
    $dez = $n % 100;

    $chunk = [];
    if ($cent) {
      if ($cent === 1 && $dez > 0) {
        $chunk[] = 'cento';
      } else {
        $chunk[] = $c[$cent];
      }
=======
// ---------- medição de texto (Helvetica, em 1/1000 do corpo) ----------
function helveticaWidths(): array
{
  static $w = null;
  if ($w !== null) return $w;
  $list = [
    32 => 278, 33 => 278, 34 => 355, 35 => 556, 36 => 556, 37 => 889, 38 => 667, 39 => 191,
    40 => 333, 41 => 333, 42 => 389, 43 => 584, 44 => 278, 45 => 333, 46 => 278, 47 => 278,
    58 => 278, 59 => 278, 60 => 584, 61 => 584, 62 => 584, 63 => 556, 64 => 1015,
    65 => 667, 66 => 667, 67 => 722, 68 => 722, 69 => 667, 70 => 611, 71 => 778, 72 => 722,
    73 => 278, 74 => 500, 75 => 667, 76 => 556, 77 => 833, 78 => 722, 79 => 778, 80 => 667,
    81 => 778, 82 => 722, 83 => 667, 84 => 611, 85 => 722, 86 => 667, 87 => 944, 88 => 667,
    89 => 667, 90 => 611, 91 => 278, 92 => 278, 93 => 278, 94 => 469, 95 => 556, 96 => 333,
    97 => 556, 98 => 556, 99 => 500, 100 => 556, 101 => 556, 102 => 278, 103 => 556,
    104 => 556, 105 => 222, 106 => 222, 107 => 500, 108 => 222, 109 => 833, 110 => 556,
    111 => 556, 112 => 556, 113 => 556, 114 => 333, 115 => 500, 116 => 278, 117 => 556,
    118 => 500, 119 => 722, 120 => 500, 121 => 500, 122 => 500, 123 => 334, 124 => 260,
    125 => 334, 126 => 584,
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
      'a' => 'áàâãäå', 'e' => 'éèêë', 'i' => 'íìîï', 'o' => 'óòôõö', 'u' => 'úùûü', 'c' => 'ç', 'n' => 'ñ',
      'A' => 'ÁÀÂÃÄÅ', 'E' => 'ÉÈÊË', 'I' => 'ÍÌÎÏ', 'O' => 'ÓÒÔÕÖ', 'U' => 'ÚÙÛÜ', 'C' => 'Ç', 'N' => 'Ñ',
    ];
    foreach ($map as $base => $chars) {
      foreach (mb_str_split($chars) as $c) $accents[$c] = $base;
>>>>>>> Stashed changes
    }

    if ($dez) {
      if ($dez < 10) {
        $chunk[] = $u[$dez];
      } elseif ($dez < 20) {
        $chunk[] = $d10[$dez - 10];
      } else {
        $dezenas = intdiv($dez, 10);
        $unid = $dez % 10;
        if ($unid) {
          $chunk[] = $t[$dezenas] . ' e ' . $u[$unid];
        } else {
          $chunk[] = $t[$dezenas];
        }
      }
    }

    $parts[] = implode(' e ', $chunk);
  }

  // junta com " e " apenas no último elo quando fizer sentido
  if (count($parts) === 1) return $parts[0];
  $last = array_pop($parts);
  return implode(' ', $parts) . ' e ' . $last;
}

<<<<<<< Updated upstream
function moneyToWords(float $value, string $currencyIso = 'AOA'): string
=======
// ---------- helpers de desenho (coordenadas da PÁGINA, em pt) ----------
// texto absoluto: proformText(x, y, html, classes, largura opcional)
function proformText(float $x, float $y, string $html, string $class = '', ?float $w = null): string
>>>>>>> Stashed changes
{
  $value = round($value, 2);
  $int = (int)floor($value);
  $cents = (int)round(($value - $int) * 100);

  // Se intl estiver disponível no PHP-FPM, usa NumberFormatter.
  // Caso contrário, usa fallback local (evita página "cortar" no meio por Fatal error).
  if (class_exists('NumberFormatter')) {
    $locale = 'pt';
    $fmt = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
    $intWords = trim((string)$fmt->format($int));
    $centWords = $cents > 0 ? trim((string)$fmt->format($cents)) : '';
  } else {
    $intWords = extenso_pt($int);
    $centWords = $cents > 0 ? extenso_pt($cents) : '';
  }

  // moeda (AOA -> Kz)
  $currencyName = match (strtoupper($currencyIso)) {
    'AOA' => 'Kz',
    'BRL' => ($int === 1 ? 'real' : 'reais'),
    'EUR' => ($int === 1 ? 'euro' : 'euros'),
    'USD' => ($int === 1 ? 'dólar' : 'dólares'),
    default => 'unidades',
  };

  $centName = ($cents === 1 ? 'centavo' : 'centavos');

  $out = $intWords . ' ' . $currencyName;
  if ($cents > 0) {
    $out .= ' e ' . $centWords . ' ' . $centName;
  }

  // primeira letra maiúscula
  $out = mb_strtoupper(mb_substr($out, 0, 1)) . mb_substr($out, 1);
  return $out;
}
// ---------- input ----------
$rawId = trim((string)($_GET['id'] ?? ''), '/');
if (!preg_match('~(?:^|/)(\d+)$~', $rawId, $idMatch)) {
  $id = 0;
} else {
  $id = (int)$idMatch[1];
}
if (!$id) {
  die('<h3>Fatura não encontrada.</h3>');
}

// ---------- consulta fatura ----------
$sql = "
SELECT
    p.*,
    comp.id AS company_id,
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
    comp.bank_name,
    comp.iban,
    'PF' as document_type,
    c.name AS client_name,
    c.address AS client_address,
    c.contributor AS client_contributor,
    c.city AS client_city,
    c.country AS client_country,

    ps.name AS status_invoice,
    ps.color,
    ps.text_color,

    cr2.symbol AS moneySymbol,
    cr2.position AS moneyPos

FROM proformas p

INNER JOIN companies comp
    ON comp.id = p.company_id

INNER JOIN contact c
    ON c.id = p.contact_id

INNER JOIN proforma_status ps
    ON ps.id = p.status

LEFT JOIN currencies cr2
    ON cr2.iso_code = 'AOA'

WHERE p.id = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);

$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
  die('<h3>Proforma não encontrada.</h3>');
}

$scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$publicBasePath = str_replace('\\', '/', dirname(dirname(dirname($scriptPath))));
$publicBasePath = ($publicBasePath === '/' || $publicBasePath === '.') ? '' : rtrim($publicBasePath, '/');
$logoFile = basename((string)($inv['logo_url'] ?? ''));

if (empty(trim($inv['goods_services'] ?? ''))) {
  $inv['goods_services'] =
    'Os bens e serviços foram colocados à disposição do adquirente na data do documento.';
}

$stmt = $pdo->prepare("
    SELECT
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

    WHERE pi.proforma_id = :id
");

$stmt->execute([
  'id' => $id
]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        pi.tax AS tax_rate,

        ROUND(
            SUM(
                pi.unit_price
                * pi.quantity
                * (1 - (pi.discount / 100))
            ),
            2
        ) AS tax_base,

        ROUND(
            SUM(
                pi.unit_price
                * pi.quantity
                * (1 - (pi.discount / 100))
                * (pi.tax / 100)
            ),
            2
        ) AS tax_value

    FROM proforma_items pi

    WHERE pi.proforma_id = :id

    GROUP BY pi.tax

    ORDER BY pi.tax
");

$stmt->execute([
  'id' => $id
]);

$taxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$taxSummary = bx_document_tax_summary(
  $items,
  (string)($inv['vat_regime'] ?? '')
);
$retentionValue = max(
  0.0,
  ($taxSummary['subtotal'] + $taxSummary['total_tax'])
    * (float)($inv['retention'] ?? 0) / 100
);
$taxSummary = bx_document_tax_summary(
  $items,
  (string)($inv['vat_regime'] ?? ''),
  $retentionValue
);
$inv['total_sum'] = $taxSummary['total_sum'];
$inv['total_discount'] = $taxSummary['total_discount'];
$inv['subtotal_without_tax'] = $taxSummary['subtotal'];
$inv['total_tax'] = $taxSummary['total_tax'];
$inv['final_total'] = $taxSummary['final_total'];
$inv['retention_value'] = $taxSummary['retention'];

$isProforma = !empty($_GET['proforma']);

$tableMain   = $isProforma ? 'proformas' : 'invoices';
$tableItems  = $isProforma ? 'proforma_items' : 'invoice_items';
$tableStatus = $isProforma ? 'proforma_status' : 'invoice_status';
$statusField = $isProforma ? 'status_id' : 'status';
// ---------- QR ----------
$hash = randomHash();
$qrData = "https://teusite.com/invoice_public.php?id={$inv['id']}";

$opts = new QROptions([
  'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
  'eccLevel'     => QRCode::ECC_L,
  'scale'        => 3,
  'addQuietzone'   => false,
  'imageBase64'  => true,   // a lib já põe 'data:image/png;base64,...'
]);

$qrSrc = (new QRCode($opts))->render($qrData); // já vem pronta

/* ================================================================
 * 1. DEPOIS de buscar a fatura ($inv) calcule quanto já foi pago
 * --------------------------------------------------------------- */
$stmt = $pdo->prepare("
    SELECT  COALESCE(SUM(amount_paid),0) AS paid_total   
    FROM    receipts
    WHERE   invoice_id = :id
");
$stmt->execute(['id' => $id]);
$paid_total = (float)$stmt->fetchColumn();

$saldo = max(0, $inv['final_total'] - $paid_total);

/*  define um rótulo para exibir no cabeçalho  ------------------- */
if ($paid_total >= $inv['final_total']) {  // quitada
  $payLabel = 'Pago';
  $payClass = 'pago';       // usa .badge.pago (verde) que já existe
} elseif ($paid_total > 0) {                      // parcial
  $payLabel = 'Pago parcial';
  $payClass = 'pendente';   // amarelo – já tinha no CSS
} else {                                        // nada pago
  $payLabel = 'Pendente';
  $payClass = 'pendente';
}


require __DIR__ . '/../../invoices/document_layout.php';
return; // não usar exit: também incluído para gerar o PDF

// ---------- HTML legado ----------
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <title>Fatura <?= htmlspecialchars($inv['codigo']) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath . '/invoices/invoice.css') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath . '/invoices/invoice_footer.css') ?>">
  <style>
    .document-tax-summary {
      margin: 12px 0 16px;
      color: #555;
      font-size: 9px;
    }
    .document-tax-summary h2 {
      margin: 0 0 5px;
      font-size: 10px;
      font-weight: 700;
    }
    .document-tax-summary table {
      width: 100%;
      border-collapse: collapse;
      table-layout: auto;
    }
    .document-tax-summary th,
    .document-tax-summary td {
      padding: 4px 6px;
      border-bottom: 1px solid #d2d2d2;
      text-align: right;
      white-space: nowrap;
    }
    .document-tax-summary th:first-child,
    .document-tax-summary td:first-child {
      text-align: left;
    }
<<<<<<< Updated upstream
    @media (max-width: 640px) {
      .document-tax-summary table {
        min-width: 520px;
      }
=======

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
      height: 36pt;   /* subtotal + "A transportar…" + folga até aos totais */
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
>>>>>>> Stashed changes
    }
  </style>
</head>


<<<<<<< Updated upstream
<body>
  <main class="invoice-page">

    <!-- ===== HEADER (100% flex) ===== -->
    <div class="inv-header d-flex justify-content-between pb-2">
=======
      // subtotal (incidência) desta página e acumulado até aqui
      $pageSubtotal = 0.0;
      foreach ($pageItems as $it) $pageSubtotal += lineSubtotal($it);
      $carried += $pageSubtotal;
      $sy = 8 + 16 + 10; // 34: início das linhas, relativo ao topo do bloco de totais
  ?>

      <section class="page invoice-page">
>>>>>>> Stashed changes

      <!-- ESQUERDA: logo + dados empresa -->
      <div class="inv-left d-flex flex-column align-items-start">
        <div id="company-info">
          <!-- PHP ancora o conteúdo aqui -->
          <h6 style="width: 250px !important;" class="fw-bold fs-6 mb-1 tag-title text-uppercase"><?= htmlspecialchars($inv['company_name']) ?></h6>
          <p class="mb-0">
            <?= nl2br(htmlspecialchars(
              $inv['company_address'] . ', ' .
                $inv['company_city'] . ' - ' .
                $inv['company_country']
            )) ?>
            <br>
          </p> <?= htmlspecialchars("") ?></p>
          <p class="mb-0">Tel: <?= htmlspecialchars($inv['company_phone']) ?></p>
          <p class="mb-0">E-mail: <?= htmlspecialchars($inv['company_email']) ?></p>
          <p class="mb-0">Website: <?= htmlspecialchars($inv['website'] ?? "") ?></p>
          <p class="mb-0">Contribuinte: <?= htmlspecialchars($inv['registration_number']) ?></p>
        </div>
      </div>

      <!-- DIREITA: QR + dados cliente -->
      <div class="inv-right d-flex flex-column align-items-start justify-content-end">
        <div id="invoice_logoCompanies" class="me-3" style="margin-left: 120px;">
          <img src="<?= htmlspecialchars($publicBasePath . '/assets/img/companies/' . rawurlencode($logoFile)) ?>" alt="Logo">
        </div>

      </div>

    </div><!-- /inv-header -->


    <?php
    $issueBr = dateBr($inv['issue_date']);
    $dueDays = (int)$inv['due_date'];
    $dueBr = dateBr((new DateTime($inv['issue_date']))
      ->modify("+{$dueDays} days")
      ->format('Y-m-d'));
    ?>
    <!-- ===== META ===== -->
    <section class="inv-meta pt-2 mt-5">

      <div class="d-flex justify-content-between">
        <span class="d-block">Original</span>

      </div>

      <span class="d-block fs-6 title-line tag-title">
        <?= $inv["document_type"] === "PF" ? "Proforma" : "Factura" ?> n.º <?= htmlspecialchars($inv['reference'] ??  " ") ?>
      </span>

      <!-- Bloco flex com 2 colunas -->
      <div class="meta-row lh-1">

        <!-- ===== COLUNA ESQUERDA – DATAS & REF ===== -->
        <div class="meta-mini mt-2">

          <div class="vals">
            <span class="small">Cliente:</span><span class="small opacity-75 fw-semibold text-uppercase" style="margin-left: -50px !important; color: black;"><?= htmlspecialchars($inv['client_name']) ?></span>
          </div>
          <div class="vals">
            <span class="mb-0 small">Contribuinte:</span><span class="small" style="margin-left: -50px !important;"><?= htmlspecialchars($inv['client_contributor']) ?></span>
          </div>

          <div class="vals">
            <span class="small">Endereço:</span><span class="small" class="lh-1" style="text-wrap: wrap; margin-left: -50px !important; width: 200px !important; overflow: hidden !important;"><?= nl2br(htmlspecialchars($inv['client_address'])) ?>, <?= htmlspecialchars("{$inv['client_city']} - {$inv['client_country']}") ?></span>
          </div>

        </div>

<<<<<<< Updated upstream
        <!-- ===== COLUNA DIREITA – OBSERVAÇÕES ===== -->
        <div class="meta-mini mt-2">
          <div class="vals">
            <span class="small" style="margin-left: 55px">Data de emissão:</span><span class="small" style="margin-left: 30px !important;"><?= $issueBr ?></span>
          </div>
          <div class="vals">
            <span class="small" style="margin-left: 55px">Vencimento:</span><span class="small" style="margin-left: 30px !important;"><?= $dueBr ?></span>
          </div>
          <div class="vals">
            <span class="small" style="margin-left: 55px">Observações:</span><span class="small" style="text-wrap: wrap; margin-left: 67px !important; width: 200px !important; overflow: hidden !important;" class="lh-1"><?= $inv['observation'] ? htmlspecialchars($inv['observation']) : '-' ?></span>
          </div>
        </div>

      </div>
    </section>

=======
        <!-- DOCUMENTO -->
        <?= proformText(45, 245, e($copyLabel), 'f10 cg') ?>
        <?= proformText(45, 263, e($docTitle), 'f12 b c0') ?>
        <?= HL(45, 550, 288, 1.5, '#8C8C8C') ?>

        <?= proformText(45, 298, 'Cliente:', 'f9 cg') ?>
        <?= proformText(145, 298, e($clientName), 'f9 b c4', 200) ?>
        <?= proformText(365, 298, 'Data de emissão:', 'f9 cg') ?>
        <?= proformText(455, 298, e($issueBr), 'f9 cg') ?>

        <?= proformText(45, 316, 'Contribuinte:', 'f9 cg') ?>
        <?= proformText(145, 316, e($clientNif), 'f9 cg') ?>
        <?= proformText(365, 316, 'Vencimento:', 'f9 cg') ?>
        <?= proformText(455, 316, e($dueBr), 'f9 cg') ?>
>>>>>>> Stashed changes

    <!-- ===== ITENS (sem <table>) ===== -->
    <div class="items-grid mt-4 title-line pb-5 lh-1" style="border-bottom: 2.5px solid #8b8b8b !important; border-top: 2.5px solid #8b8b8b !important; border-bottom: 2.5px solid #8b8b8b !important">

      <!-- cabeçalho -->
      <div class="items-row items-head">
        <span class="fw-bold tag-title" style="margin-left: -10px; opacity: .6;">Código</span>
        <span class="fw-bold mt-0 tag-title" style="margin-left: -65px !important; font-weight: bold; opacity: .6; font-size: 10px !important;">Descrição</span>
        <span class="fw-bold tag-title" style="margin-left: 58px; width: 100px; opacity: .6;">Preço&nbsp;Uni.</span>
        <span class="fw-bold tag-title" style="margin-left: 72px; opacity: .6;">Qtd.</span>
        <span class="fw-bold tag-title" style="margin-left: 56px; opacity: .6;">Taxa/IVA&nbsp;</span>
        <span class="fw-bold tag-title" style="margin-left: 35px; opacity: .6;">Desc.&nbsp;</span>
        <span style="float: right !important; text-align: right !important; opacity: .6;" class="fw-bold tag-title">Total</span>
      </div>

      <!-- linhas dinâmicas -->
      <?php foreach ($items as $it):
        $base = $it['unit_price'] * $it['quantity'];
        $discount = $base * ($it['discount'] / 100);
        $effectiveRate = bx_document_effective_tax_rate($it['tax'], (string)($inv['vat_regime'] ?? ''));
        $tax = ($base - $discount) * ($effectiveRate / 100);
        $total = $base - $discount + $tax; ?>
        <div class="items-row mb-3">
          <span class="fw-light lh-1 mt-1" style="width: 90px !important; font-size: 10px !important;"><?= htmlspecialchars($it['code']) ?></span>
          <span class="fw-light lh-1 mt-1" style="width: 250px !important; margin-left: 50px !important; text-wrap: wrap !important; font-size: 10.5px !important;"><?= htmlspecialchars($it['name'] ? $it['name'] : $it['description']) ?></span>
          <span class="fw-light lh-1 mt-1" style="margin-left: 10px; font-size: 10px !important;">
            <?= formatCurrency($it['unit_price'], $inv['moneySymbol'], $inv['moneyPos']) ?>
          </span>
          <span class="center fw-light lh-sm" style="margin-left: -38px; font-size: 10px !important;"><?= $it['quantity'] ?></span>
          <span class="center fw-light lh-sm" style="margin-left: -70px; font-size: 10px !important;"><?= number_format($effectiveRate, 2, ',', '') ?>%</span>
          <span class="center fw-light lh-sm" style="margin-left: -120px; font-size: 10px !important;"><?= $it['discount'] ?>%</span>
          <span class="right fw-light lh-sm" style="margin-left: -66px; width: 100px; font-size: 10px !important;">
            <?= formatCurrency($total, $inv['moneySymbol'], $inv['moneyPos']) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>


    <section class="document-tax-summary" aria-labelledby="taxSummaryTitle">
      <h2 id="taxSummaryTitle">Resumo por taxa de IVA</h2>
      <div class="table-responsive">
        <table>
          <thead>
            <tr><th>Taxa</th><th>Base</th><th>Valor (IVA)</th><th>Retenção</th><th>Líquido</th></tr>
          </thead>
          <tbody>
            <?php foreach ($taxSummary['rows'] as $taxRow): ?>
              <tr>
                <td><?= number_format($taxRow['rate'], 2, ',', '') ?>%</td>
                <td><?= formatCurrency($taxRow['base'], $inv['moneySymbol'], $inv['moneyPos']) ?></td>
                <td><?= formatCurrency($taxRow['iva'], $inv['moneySymbol'], $inv['moneyPos']) ?></td>
                <td><?= formatCurrency($taxRow['retention'], $inv['moneySymbol'], $inv['moneyPos']) ?></td>
                <td><?= formatCurrency($taxRow['net'], $inv['moneySymbol'], $inv['moneyPos']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ===== TAXAS & RESUMO (sem <table>) ===== -->
    <div class="totals-wrap">

      <!-- ===== ESQUERDA – Impostos/IVA ===== -->
      <div style="width: 384px !important;">

        <div class="sum-head tag-title small" style="opacity: .6;">Dados fiscais e bancários</div>

        <div class="lh-1">

          <div class="sum-row mt-2">
            <span class="small">Regime de IVA:</span>
            <span class="small" style="margin-left: -90px; width: 250px !important;"><?= match ($inv["vat_regime"]) {
                                                                                        "geral" => "Regime Geral",
                                                                                        "simplificado" => "Regime Simplificado",
                                                                                        default => ""
                                                                                      } ?>
            </span>
          </div>

          <div class="sum-row mt-1">
            <span class="small">Bens e serviços:</span>
            <span class="small" style="margin-left: -90px; width: 250px !important;">Os bens e serviços foram colocados à disposição do adquirente na data do documento.</span>
          </div>

          <div class="sum-row mt-2">
            <span class="small">Dados bancários:</span>
            <span
              class="small"
              style="margin-left: -90px; width: 250px !important;"
              id="ibanNumber">
              <?php
              $iban = preg_replace('/[.\s]/', '', $inv["iban"]);

              $prefix = substr($iban, 0, 4);
              $rest = substr($iban, 4);

              $formatted = trim(chunk_split($rest, 4, ' '));

              echo $prefix . ' ' . $formatted;
              ?>
            </span>
          </div>

          <!-- barra inferior grossa -->
          <div class="mt-1" style="border-bottom: 1.5px solid #8b8b8b !important;"></div>

          <!-- …antes do separador grosso -->
          <!-- <?php if ($paid_total > 0): ?>
            <div class="sum-row">
              <span>Pago:</span>
              <span class="right">
                <?= formatCurrency($paid_total, $inv['moneySymbol'], $inv['moneyPos']) ?>
              </span>
            </div>

            <?php if ($saldo > 0): // só mostra saldo se ainda houver 
            ?>
              <div class="sum-row">
                <span>Saldo:</span>
                <span class="right">
                  <?= formatCurrency($saldo, $inv['moneySymbol'], $inv['moneyPos']) ?>
                </span>
              </div>
            <?php endif; ?>
          <?php endif; ?> -->

        </div>

      </div><!-- /.sum-grid -->

      <!-- ===== DIREITA – Sumário ===== -->
      <div style="width: 270px !important;">
        <?php $totalPagar = (float)$inv['final_total']; ?>
        <div class="sum-head tag-title small" style="opacity: .6;">Sumário</div>

        <div class="lh-1">

          <div class="sum-row mt-1">
            <span class="small">Total ílíquido:</span>
            <span class="right small"> <?= formatCurrency($inv['total_sum'], $inv['moneySymbol'], $inv['moneyPos']) ?>
            </span>
          </div>

          <div class="sum-row">
            <span class="small">Desconto:</span>
            <span class="right small"><?= formatCurrency($inv['total_discount'], $inv['moneySymbol'], $inv['moneyPos']) ?></span>
          </div>

          <div class="sum-row">
            <span class="small">Sem Imposto/IVA c Desc.:</span>
            <span class="right small">
              <?= formatCurrency($inv['total_sum'] - $inv['total_discount'], $inv['moneySymbol'], $inv['moneyPos']) ?>
            </span>
          </div>

          <div class="sum-row">
            <span class="small">Imposto/IVA:</span>
            <span class="right small"><?= formatCurrency($inv['total_tax'], $inv['moneySymbol'], $inv['moneyPos']) ?></span>
          </div>

          <div class="sum-row">
            <span class="small">Retenção:</span>
            <span class="right small"><?= formatCurrency($inv['retention_value'], $inv['moneySymbol'], $inv['moneyPos']) ?></span>
          </div>

          <!-- separador grosso -->
          <div class="mt-1" style="border-bottom: 2.5px solid #8b8b8b !important;"></div>

          <div class="sum-row bold mt-1 tag-title">
            <span class="fs-6">Total:</span>
            <span class="right fs-6">
              <?= formatCurrency($totalPagar, $inv['moneySymbol'], $inv['moneyPos']) ?>
            </span>
          </div>
          <!-- <div class="sum-row" style="margin-top:2px;">
            <span style="grid-column:1 / -1; font-size:.72rem;">
              <?= htmlspecialchars(moneyToWords($totalPagar, $inv['currency'] ?? 'AOA')) ?>
            </span>
          </div> -->

          <!-- barra inferior grossa -->
          <div class="sum-bottom mt-1" style="border-bottom: 2.5px solid #8b8b8b !important;"></div>

          <!-- …antes do separador grosso -->
          <!-- <?php if ($paid_total > 0): ?>
            <div class="sum-row">
              <span>Pago:</span>
              <span class="right">
                <?= formatCurrency($paid_total, $inv['moneySymbol'], $inv['moneyPos']) ?>
              </span>
            </div>

<<<<<<< Updated upstream
            <?php if ($saldo > 0): // só mostra saldo se ainda houver 
            ?>
              <div class="sum-row">
                <span>Saldo:</span>
                <span class="right">
                  <?= formatCurrency($saldo, $inv['moneySymbol'], $inv['moneyPos']) ?>
                </span>
              </div>
            <?php endif; ?>
          <?php endif; ?> -->

        </div>

      </div><!-- /.sum-grid -->

    </div><!-- /.totals-wrap -->

    <!-- ===== REGIME IVA / BENS E SERVIÇOS / DADOS BANCÁRIOS ===== -->
=======

        <div class="tot<?= $isLast ? ' tot-last' : '' ?>">
          <!-- DADOS FISCAIS E SUMÁRIO: seguem logo a seguir aos itens (sem espaço vazio) -->
          <?= HL(45, 550, 0, 1.5, '#8C8C8C') ?>
          <?= proformText(45, 8, 'Dados fiscais e bancários', 'f10 b c5') ?>
          <?= proformText(365, 8, 'Sumário', 'f10 b c5') ?>
          <?= HL(45, 340, 24, .8, '#C4C4C4') ?>
          <?= HL(365, 550, 24, .8, '#C4C4C4') ?>

          <!-- esquerda -->
          <?= proformText(45, $sy, 'Regime de IVA:', 'f85 cg') ?>
          <?= proformText(145, $sy, e($vatRegime), 'f85 cg', 180) ?>
          <?= proformText(45, $sy + 20, 'Bens e serviços:', 'f85 cg') ?>
          <?= proformText(145, $sy + 20, e($goodsServicesText), 'f85 cg wrap', 180 - 0.278 * 8.5) ?>
          <?= proformText(45, $sy + 62, 'Dados bancários:', 'f85 cg') ?>
          <?= proformText(145, $sy + 62, e($iban), 'f85 cg', 190) ?>

          <!-- direita -->
          <?php if (!$isLast): ?>
            <?= proformText(365, $sy + 14, 'Subtotal da página:', 'f85 cg') ?>
            <?= proformText(450, $sy + 14, e($money($pageSubtotal)), 'f85 cg r', 100) ?>
            <?= HL(365, 545, $sy + 72, 1, '#555') ?>
            <?= proformText(365, $sy + 82, 'A transportar:', 'f10 b c0') ?>
            <?= proformText(440, $sy + 82, e($money($carried)), 'f10 b c0 r', 110) ?>
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
              <?= proformText(365, $sy + $dy, e($label), 'f85 cg') ?>
              <?= proformText(450, $sy + $dy, e($money($value)), 'f85 cg r', 100) ?>
            <?php endforeach; ?>
            <?= HL(365, 545, $sy + 72, 1, '#555') ?>
            <?= proformText(365, $sy + 82, 'Total:', 'f12 b c0') ?>
            <?= proformText(440, $sy + 82, e($money($inv['final_total'])), 'f12 b c0 r', 110) ?>
            <?= HL(365, 545, $sy + 100, 2, '#555') ?>
          <?php endif; ?>
        </div>

        </div><!-- /.flow -->

        <!-- RODAPÉ (em todas as páginas) -->
        <img class="qr" src="<?= $qrSrc ?>" alt="QR">
        <?= proformText(112.3, 795.5, 'Software certificado pela AGT | Nº ' . e(AGT_CERTIFICATE), 'f8 c0') ?>
        <?= proformText(112.3, 804.75, 'Powered by BXPERT', 'f8 c0') ?>
        <?= proformText(450, 795.5, 'Página ' . ($pageIndex + 1) . ' de ' . $pageCount, 'f8 c0 r', 100) ?>
>>>>>>> Stashed changes


    <!-- ===== RODAPÉ (dados da empresa emissora) ===== -->
    <footer class="inv-footer">
      <div class="d-flex justify-content-between align-items-end inv-footer"
        style="font-size: 8pt; margin-top: 350px !important; border-bottom: 1px solid none; padding-bottom: 8px;">

        <!-- ESQUERDA: DADOS BANCÁRIOS -->

        <div class="d-flex gap-3"></div>


        <!-- DIREITA: QR CODE -->
        <div id="invoice-qr">
          <img src="<?= $qrSrc ?>"
            alt="QR"
            style="width:75px;height:75px;">
        </div>

      </div>

      <div class="inv-footer" style="font-size: 8pt; margin-top: -4px; border: 0px solid none !important;">
        <span id="address" class="opacity-50">Powered By BXpert</span>
      </div>
      <br><br>
    </footer>

    <!-- numeração de página no PDF (Dompdf) -->
    <script type="text/php">
      if (isset($pdf)) {
      $font = $fontMetrics->get_font("Helvetica", "normal");
      $pdf->page_text(520, 820, "{PAGE_NUM} / {PAGE_COUNT}", $font, 8, array(0,0,0));
    }
  </script>

  </main>
</body>

<script>
  // =========================
  // MÁSCARA CARTÃO DE CRÉDITO
  // =========================

  // =========================
  // FORMATAÇÃO IBAN ANGOLA
  // AO06.0006.0000.1234.5678.9012.1
  // =========================

  const ibanElement = document.getElementById("ibanNumber");

  let value = ibanElement.textContent.trim();


  // remove espaços e pontos
  value = value.replace(/[.\s]/g, "");

  // separa prefixo AO06
  const prefix = value.substring(0, 4);
  const rest = value.substring(4);

  // agrupa em blocos de 4
  const formatted = rest.match(/.{1,4}/g)?.join(".") || "";

  // resultado final
  ibanElement.textContent = `${prefix}.${formatted}`;
</script>

</html>