<?php

require_once '../../../app/config/db.php';

/*
|--------------------------------------------------------------------------
| Utils
|--------------------------------------------------------------------------
*/

function formatCurrency(
  float $value,
  string $currencySymbol,
  string $currencyPosition = 'left',
  bool $asCredit = false
): string {

  $formatted = number_format(abs($value), 2, ',', '.');

  $prefix = $asCredit ? '-' : '';

  return $currencyPosition === 'left'
    ? "{$prefix}{$currencySymbol} {$formatted}"
    : "{$prefix}{$formatted} {$currencySymbol}";
}

function dateBr(?string $sqlDate): string
{
  if (empty($sqlDate)) {
    return '-';
  }

  return date('d/m/Y', strtotime($sqlDate));
}

/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(422);
  exit('<h3>Nota de crédito inválida.</h3>');
}

/*
|--------------------------------------------------------------------------
| Consulta principal
|--------------------------------------------------------------------------
*/

$sql = "
SELECT 
    cn.*,

    i.issue_date AS invoice_issue_date,
    i.id AS invoice_id,
    i.reference,

    comp.name AS company_name,
    comp.address AS company_address,
    comp.city AS company_city,
    comp.country AS company_country,
    comp.registration_number,
    comp.email AS company_email,
    comp.phone AS company_phone,
    comp.logo_url,
    comp.vat_regime,

    c.name AS client_name,
    c.address AS client_address,
    c.contributor AS client_contributor,
    c.city AS client_city,
    c.country AS client_country,

    cr.symbol AS moneySymbol,
    cr.position AS moneyPos

FROM credit_notes cn

INNER JOIN invoices i 
    ON i.id = cn.invoice_id

INNER JOIN companies comp 
    ON comp.id = cn.company_id

INNER JOIN contact c 
    ON c.id = cn.contact_id

LEFT JOIN currencies cr 
    ON cr.iso_code = cn.currency

WHERE cn.id = :id

LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
  ':id' => $id
]);

$cn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cn) {
  http_response_code(404);
  exit('<h3>Nota de crédito não encontrada.</h3>');
}

/*
|--------------------------------------------------------------------------
| Segurança
|--------------------------------------------------------------------------
*/

$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);

if (
  $sessionCompanyId > 0 &&
  (int)$cn['company_id'] !== $sessionCompanyId
) {
  http_response_code(403);
  exit('<h3>Acesso negado.</h3>');
}

/*
|--------------------------------------------------------------------------
| Itens
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT 
        cni.*,
        it.code,
        it.name,
        it.description
    FROM credit_note_items cni
    LEFT JOIN items it 
        ON it.id = cni.item_id
    WHERE cni.credit_note_id = :id
");

$stmt->execute([
  ':id' => $id
]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
*/


$isHttps = (
  (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
);

$protocol = $isHttps ? 'https' : 'http';

/*
|--------------------------------------------------------------------------
| HOST
|--------------------------------------------------------------------------
| Online  -> domínio normal
| Offline -> localhost
*/

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

/*
|--------------------------------------------------------------------------
| BASE PATH
|--------------------------------------------------------------------------
| Ajusta automaticamente para localhost
*/

$isLocalhost = in_array($host, ['localhost', '127.0.0.1']);

$basePath = $isLocalhost
  ? '/projects/bxpert/fatura/public'
  : '';

$BASE_URL = "{$protocol}://{$host}{$basePath}";

/*
|--------------------------------------------------------------------------
| Dados padrão
|--------------------------------------------------------------------------
*/

$currencySymbol = $cn['moneySymbol'] ?? 'Kz';
$currencyPos    = $cn['moneyPos'] ?? 'left';

/*
|--------------------------------------------------------------------------
| LOGO
|--------------------------------------------------------------------------
*/

$logo = !empty($cn['logo_url'])
  ? $BASE_URL . '/assets/img/companies/' . rawurlencode($cn['logo_url'])
  : '';

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

  <meta charset="utf-8">

  <title>
    Nota de Crédito
    <?= htmlspecialchars(date('Y', strtotime($cn['issue_date'])) . '/' . $cn['id']) ?>
  </title>

  <<style>
    @page {
    margin: 15mm 12mm 18mm 12mm;
    }

    body {
    margin: 0;
    padding: 0;
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
    color: #000;
    }

    * {
    box-sizing: border-box;
    }

    table {
    border-collapse: collapse;
    }

    /* =========================
    HEADER
    ========================= */

    .inv-header-table {
    width: 100%;
    margin-bottom: 20px;
    }

    .inv-header-table td {
    vertical-align: top;
    }

    .cn-logo img {
    width: 110px;
    margin-bottom: 8px;
    float: right;
    }

    .cn-company h3 {
    margin: 0 0 6px 0;
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
    line-height: 1;
    }

    .cn-company p {
    margin: 2px 0;
    line-height: 1;
    }

    .cn-client {
    padding-top: 35px;
    text-align: left;
    line-height: 1;
    }

    .cn-client h4 {
    margin: 0 0 5px 0;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    }

    .cn-client p {
    margin: 2px 0;
    line-height: 1;
    }

    /* =========================
    TITLE
    ========================= */

    .title-line {
    margin-top: 20px;
    margin-bottom: 10px;
    font-size: 13px;
    font-weight: bold;
    border-bottom: 2px solid #000;
    padding-bottom: 4px;
    }

    /* =========================
    META INFO
    ========================= */

    .meta-table {
    width: 100%;
    margin-bottom: 12px;
    font-size: 10px;
    }

    .meta-table td {
    vertical-align: top;
    padding: 3px 0;
    }

    .meta-label {
    width: 120px;
    font-weight: bold;
    white-space: nowrap;
    }

    .reason-box {
    padding-left: 0px;
    margin-left: -80px;
    }

    /* =========================
    ITEMS
    ========================= */

    .items-table {
    width: 100%;
    font-size: 9.5px;
    margin-top: 10px;
    }

    .items-table th {
    border-top: 2px solid #000;
    border-bottom: 1px solid #000;
    padding: 6px 4px;
    font-weight: bold;
    }

    .items-table td {
    padding: 6px 4px;
    border-bottom: 1px solid #ddd;
    }

    .text-right {
    text-align: right;
    }

    .text-center {
    text-align: center;
    }

    .nowrap {
    white-space: nowrap;
    }

    /* =========================
    SUMMARY AREA
    ========================= */

    .summary-table {
    width: 100%;
    margin-top: 14px;
    }

    .summary-table td {
    vertical-align: top;
    }

    /* =========================
    IVA BOX
    ========================= */

    .tax-box {
    width: 100%;
    font-size: 9.5px;
    }

    .tax-box th {
    border-top: 2px solid #000;
    border-bottom: 1px solid #000;
    padding: 5px 4px;
    text-align: left;
    font-weight: bold;
    }

    .tax-box td {
    padding: 5px 4px;
    }

    /* =========================
    SUMMARY
    ========================= */

    .summary {
    width: 100%;
    font-size: 9.5px;
    }

    .summary-title {
    border-top: 2px solid #000;
    border-bottom: 1px solid #000;
    padding: 5px 4px;
    font-weight: bold;
    margin-bottom: 5px;
    }

    .summary td {
    padding: 4px 0;
    }

    .total-line td {
    border-top: 1px solid #000;
    padding-top: 5px;
    font-weight: bold;
    }

    /* =========================
    OBSERVAÇÃO
    ========================= */

    .tax-notes {
    margin-top: -30px;
    font-size: 10px;
    }

    .tax-notes .lbl {
    font-weight: bold;
    }

    /* =========================
    SIGNATURE
    ========================= */

    .signature {
    margin-top: 45px;
    width: 220px;
    border-top: 1px solid #000;
    text-align: center;
    padding-top: 5px;
    font-size: 9px;
    }

    /* =========================
    FOOTER
    ========================= */

    .inv-footer {
    margin-top: 30px;
    font-size: 8.5px;
    }

    .inv-footer-line {
    border-top: 1px solid #000;
    padding-top: 4px;
    }

    .page-number {
    text-align: right;
    margin-top: 3px;
    }
    </style>

<body>

  <main>

    <!-- =========================
         HEADER
    ========================= -->

    <table class="inv-header-table">

      <tr>

        <!-- EMPRESA -->
        <td style="width:60%;">
          <div class="cn-company">

            <h3>
              <?= htmlspecialchars($cn['company_name']) ?>
            </h3>

            <p>
              <?= nl2br(htmlspecialchars($cn['company_address'] ?? '-')) ?>
            </p>

            <p>
              <?= htmlspecialchars(
                trim(($cn['company_city'] ?? '') . ' - ' . ($cn['company_country'] ?? ''))
              ) ?>
            </p>

            <p>
              Tel: <?= htmlspecialchars($cn['company_phone'] ?? '-') ?>
            </p>

            <p>
              E-mail: <?= htmlspecialchars($cn['company_email'] ?? '-') ?>
            </p>

            <p>
              Contribuinte:
              <?= htmlspecialchars($cn['registration_number'] ?? '-') ?>
            </p>

          </div>

        </td>

        <!-- CLIENTE -->
        <td style="width:40%;">

          <div class="cn-client">
            <?php if (!empty($logo)): ?>
              <div class="cn-logo">
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo">
              </div>
            <?php endif; ?>
          </div>

        </td>

      </tr>

    </table>

    <!-- =========================
         TITLE
    ========================= -->

    <div class="title-line" style="margin-top: 50px;">
      Nota de Crédito n.º
      <?= date('Y', strtotime($cn['issue_date'])) ?>/<?= (int)$cn['id'] ?>
    </div>

    <!-- =========================
         META INFO
    ========================= -->

    <table class="meta-table">

      <tr>

        <!-- ESQUERDA -->
        <td style="width:65%; padding-right:20px;">

          <table style="width:100%;">

            <tr>
              <td class="meta-label">Cliente:</td>

              <td style="text-transform: uppercase; font-weight: bold;">
                <?= htmlspecialchars($cn['client_name']) ?>
              </td>
            </tr>

            <tr>
              <td class="meta-label">Contribuinte:</td>

              <td>
                <?= htmlspecialchars($cn['client_contributor'] ?? '-') ?>
              </td>
            </tr>

            <tr>
              <td class="meta-label" style="vertical-align:top;">
                Endereço:
              </td>

              <td>
                <?= nl2br(htmlspecialchars($cn['client_address'])) ?>,
                <?= htmlspecialchars("{$cn['client_city']} - {$cn['client_country']}") ?>
              </td>
            </tr>

          </table>

        </td>

        <!-- DIREITA -->
        <td style="vertical-align:top;">

          <table class="reason-box">

            <tr>

              <td
                style="
                  width:50px;
                  font-weight:bold;
                  vertical-align:top;
                  margin-left: -200px !important;
                ">
                Motivo:
              </td>

              <td>
                <?= nl2br(htmlspecialchars($cn['reason'] ?? '-')) ?>
              </td>

            </tr>

          </table>

        </td>

      </tr>

    </table>

    <!-- =========================
         ITEMS
    ========================= -->

    <table class="items-table">

      <thead>

        <tr>

          <th style="width:10%; text-align:left;">Código</th>
          <th style="width:34%; text-align:left;">Descrição</th>
          <th style="width:14%;" class="text-right">Preço Uni.</th>
          <th style="width:8%;" class="text-center">Qtd.</th>
          <th style="width:10%;" class="text-center">IVA %</th>
          <th style="width:10%;" class="text-center">Desc. %</th>
          <th style="width:14%;" class="text-right">Total</th>

        </tr>

      </thead>

      <tbody>

        <?php foreach ($items as $it):

          $qty = (float)($it['quantity'] ?? 0);
          $price = (float)($it['unit_price'] ?? 0);
          $tax = (float)($it['tax'] ?? 0);
          $disc = (float)($it['discount'] ?? 0);

          $line = $qty * $price;

        ?>

          <tr>

            <td class="nowrap">
              <?= htmlspecialchars($it['code'] ?? '-') ?>
            </td>

            <td>
              <?= htmlspecialchars($it['name'] ?: ($it['description'] ?: '-')) ?>
            </td>

            <td class="text-right nowrap">
              <?= formatCurrency($price, $currencySymbol, $currencyPos, true) ?>
            </td>

            <td class="text-center nowrap">
              <?= number_format($qty, 2, ',', '.') ?>
            </td>

            <td class="text-center nowrap">
              <?= number_format($tax, 2, ',', '.') ?>
            </td>

            <td class="text-center nowrap">
              <?= number_format($disc, 2, ',', '.') ?>
            </td>

            <td class="text-right nowrap">
              <?= formatCurrency($line, $currencySymbol, $currencyPos, true) ?>
            </td>

          </tr>

        <?php endforeach; ?>

      </tbody>

    </table>

    <!-- =========================
         SUMMARY
    ========================= -->

    <table class="summary-table">

      <tr>

        <!-- IVA -->
        <td style="width:58%; padding-right:20px;">

          <table class="tax-box">

            <thead>

              <tr>
                <th>Imposto/IVA %</th>
                <th>Incidência</th>
                <th>Valor</th>
              </tr>

            </thead>

            <tbody>

              <tr>

                <td>Isento - 0</td>

                <td>
                  <?= formatCurrency(
                    (float)($cn['final_total'] ?? 0),
                    $currencySymbol,
                    $currencyPos
                  ) ?>
                </td>

                <td>
                  <?= formatCurrency(0, $currencySymbol, $currencyPos) ?>
                </td>

              </tr>

            </tbody>

          </table>

          <div style="margin-top:12px;">

            <strong>Regime de IVA:</strong>

            <?= htmlspecialchars($cn['vat_regime'] ?? 'Regime simplificado') ?>

          </div>

        </td>

        <!-- SUMMARY -->
        <td style="width:42%;">

          <div class="summary-title">
            Sumário
          </div>

          <table class="summary">

            <tr>

              <td>Total líquido:</td>

              <td class="text-right">
                <?= formatCurrency(
                  (float)($cn['final_total'] ?? 0),
                  $currencySymbol,
                  $currencyPos
                ) ?>
              </td>

            </tr>

            <tr>

              <td>Desconto:</td>

              <td class="text-right">
                <?= formatCurrency(0, $currencySymbol, $currencyPos) ?>
              </td>

            </tr>

            <tr>

              <td>Total impostos:</td>

              <td class="text-right">
                <?= formatCurrency(0, $currencySymbol, $currencyPos) ?>
              </td>

            </tr>

            <tr class="total-line">

              <td>Total:</td>

              <td class="text-right">

                <?= formatCurrency(
                  (float)($cn['final_total'] ?? 0),
                  $currencySymbol,
                  $currencyPos,
                  true
                ) ?>

              </td>

            </tr>

          </table>

        </td>

      </tr>

    </table>

    <!-- =========================
         OBSERVAÇÃO
    ========================= -->

    <div class="tax-notes">

      <span class="lbl">Observação:</span>

      Documento associado à fatura
      <?= htmlspecialchars($cn['reference'] ?? '-') ?>

    </div>

    <!-- =========================
         ASSINATURA
    ========================= -->

    <div class="signature">
      Assinatura e carimbo do adquirente
    </div>

    <!-- =========================
         FOOTER
    ========================= -->

    <footer class="inv-footer">

      <div class="inv-footer-line">

        <?= htmlspecialchars($cn['company_name'] ?? '-') ?>

        |

        <?= htmlspecialchars($cn['company_address'] ?? '-') ?>

        |

        <?= htmlspecialchars(
          trim(($cn['company_city'] ?? '') . ' - ' . ($cn['company_country'] ?? ''))
        ) ?>

        |

        Tel:
        <?= htmlspecialchars($cn['company_phone'] ?? '-') ?>

      </div>

      <div class="page-number">
        1 de 1
      </div>

    </footer>

  </main>

</body>