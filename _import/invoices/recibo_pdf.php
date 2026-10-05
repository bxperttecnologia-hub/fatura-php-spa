<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/*
|--------------------------------------------------------------------------
| RECEIPT ID
|--------------------------------------------------------------------------
*/

$receiptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$receiptId) {
  die('Recibo não encontrado.');
}

/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$sql = "
SELECT 
    r.id AS receipt_id,
    r.serie,
    r.number,
    r.pay_date,
    r.amount_paid,
    r.payment_method,
    r.notes,
    r.pending_amount,

    i.id AS invoice_id,
    i.issue_date,
    i.reference AS invoice_reference,
    i.final_total,
    i.currency AS invoice_currency,

    cr.symbol AS moneySymbol,
    cr.position AS moneyPos,

    comp.name AS company_name,
    comp.address AS company_address,
    comp.city AS company_city,
    comp.country AS company_country,
    comp.phone AS company_phone,
    comp.email AS company_email,
    comp.registration_number AS company_nif,
    comp.logo_url,
    comp.website,

    c.name AS client_name,
    c.email AS client_email,
    c.contributor AS client_contributor,
    c.address AS client_address

FROM receipts r

JOIN invoices i 
    ON i.id = r.invoice_id

JOIN companies comp 
    ON comp.id = i.company_id

JOIN contact c 
    ON c.id = i.contact_id

JOIN currencies cr 
    ON cr.iso_code = i.currency

WHERE r.id = :rid
";

$st = $pdo->prepare($sql);
$st->execute([
  'rid' => $receiptId
]);

$d = $st->fetch(PDO::FETCH_ASSOC);

if (!$d) {
  die('Recibo não encontrado.');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($value)
{
  return number_format((float)$value, 2, ',', '.');
}

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
| LOGO
|--------------------------------------------------------------------------
*/

$logo = !empty($d['logo_url'])
  ? $BASE_URL . '/assets/img/companies/' . rawurlencode($d['logo_url'])
  : '';


/*
|--------------------------------------------------------------------------
| VALORES
|--------------------------------------------------------------------------
*/

$facturado = (float)$d['final_total'];
$pago = (float)$d['amount_paid'];

$retencaoPercent = 6.5;
$retencaoValor = ($facturado * $retencaoPercent) / 100;

$valorPendente = (float)$d['pending_amount'];

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

$html = '
<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8">

  <style>
    @page {
      margin: 30px 35px;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      font-size: 12px;
      color: #000;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    td {
      vertical-align: top;
    }

    table,
    tr,
    td,
    div,
    p {
      page-break-inside: avoid;
    }

    .bold {
      font-weight: bold;
    }

    .right {
      text-align: right;
    }

    .center {
      text-align: center;
    }

    .fs11 {
      font-size: 11px;
    }

    .fs12 {
      font-size: 12px;
    }

    .fs13 {
      font-size: 13px;
    }

    .fs15 {
      font-size: 15px;
    }

    .mt5 {
      margin-top: 5px;
    }

    .mt10 {
      margin-top: 10px;
    }

    .mt15 {
      margin-top: 15px;
    }

    .mt20 {
      margin-top: 20px;
    }

    .mt25 {
      margin-top: 25px;
    }

    .mt30 {
      margin-top: 30px;
    }

    .line {
      border-bottom: 2px solid #000;
      margin-top: 6px;
      margin-bottom: 6px;
    }

    .small-line {
      border-bottom: 1px solid #000;
      margin-top: 5px;
      margin-bottom: 5px;
    }

    .logo {
      width: 100px;
    }

    .header-table {
      width: 100%;
    }

    .header-left {
      width: 60%;
      vertical-align: top;
    }

    .header-right {
      width: 40%;
      vertical-align: top;
      padding-top: 26px;
    }

    .company-name {
      font-size: 15px;
      font-weight: bold;
      line-height: 18px;
    }

    .company-info {
      font-size: 12px;
      line-height: 16px;
    }

    .client-title {
      font-size: 13px;
      font-weight: bold;
      margin-bottom: 4px;
    }

    .client-name {
      font-size: 15px;
      font-weight: bold;
      line-height: 18px;
      margin-bottom: 8px;
    }

    .client-info {
      font-size: 12px;
      line-height: 18px;
    }

    .doc-table {
      margin-top: 10px;
    }

    .doc-table td {
      padding: 3px 0;
    }

    .items-table {
      margin-top: 15px;
    }

    .items-table th {
      border-top: 2px solid #000;
      border-bottom: 1px solid #000;
      text-align: left;
      padding: 5px 2px;
      font-size: 12px;
    }

    .items-table td {
      padding: 7px 2px;
      font-size: 12px;
    }

    .tax-wrapper {
      margin-top: 140px;
    }

    .tax-table {
      width: 55%;
      float: left;
    }

    .tax-table th {
      border-bottom: 1px solid #000;
      text-align: left;
      padding-bottom: 4px;
    }

    .tax-table td {
      padding-top: 6px;
    }

    .total-table {
      width: 35%;
      float: right;
    }

    .total-table td {
      padding: 6px 0;
    }

    .total-line {
      border-top: 1px solid #000;
      border-bottom: 1px solid #000;
    }

    .clear {
      clear: both;
    }

    .received-text {
      margin-top: 35px;
      font-size: 13px;
    }

    .footer-section {
      position: fixed;
      left: 0;
      right: 0;
      bottom: 15px;
      page-break-inside: avoid;
    }

    .footer-line {
      border-bottom: 2px solid #000;
      margin-top: 2px;
    }
  </style>

</head>

<body>

  <!-- HEADER -->
  <table class="header-table">

    <tr>

      <td class="header-left">

        <table>
          <tr>

            <td width="110">

              ';

if ($logo) {
  $html .= '
              <img src="' . h($logo) . '" class="logo">
              ';
}

$html .= '


              <div class="company-name">
                ' . h($d['company_name']) . '
              </div>

              <div class="company-info">
                ' . h($d['company_address']) . '
              </div>

              <div class="company-info">
                ' . h($d['company_city']) . '
              </div>

              <div class="company-info">
                Tel: ' . h($d['company_phone']) . '
              </div>

              <div class="company-info">
                Web: ' . h($d['website'] ?? '') . '
              </div>

              <div class="company-info">
                E-mail: ' . h($d['company_email']) . '
              </div>

              <div class="company-info">
                Contribuinte: ' . h($d['company_nif']) . '
              </div>

            </td>

          </tr>
        </table>

      </td>

      <td class="header-right">

        <div class="client-name">
          ' . h($d['client_name']) . '
        </div>

        <div class="client-info">
          ' . nl2br(h($d['client_address'] ?? '')) . '
        </div>

        <div class="client-info">
          Contribuinte: ' . h($d['client_contributor']) . '
        </div>

      </td>

    </tr>

  </table>

  <!-- RECIBO -->
  <div class="mt25 fs12">
    Original
  </div>

  <div class="bold fs15 mt10">
    Recibo n.º RG ' . h($d['number']) . '/' . h($d['serie']) . '
  </div>

  <div class="line"></div>

  <!-- DADOS -->
  <table class="doc-table">

    <tr>

      <td width="20%" class="bold">
        Data do Documento
      </td>

      <td width="20%" class="bold">
        Contribuinte
      </td>

      <td width="20%" class="bold">
        V/ Ref.
      </td>

      <td width="40%"></td>

    </tr>

    <tr>

      <td>
        ' . date('Y-m-d', strtotime($d['pay_date'])) . '
      </td>

      <td>
        ' . h($d['client_contributor']) . '
      </td>

      <td>
      </td>

      <td></td>

    </tr>

  </table>

  <div class="small-line"></div>

  <div class="bold mt10">
    Observações
  </div>

  <div class="small-line"></div>

  <!-- TABELA -->
  <table class="items-table">

    <tr>

      <th width="20%">
        Documento
      </th>

      <th width="15%">
        Número
      </th>

      <th width="23%">
        Facturado
      </th>

      <th width="22%">
        Pago
      </th>

      <th width="20%">
        Valor Pendente
      </th>

    </tr>

    <tr>

      <td>
        Factura
      </td>

      <td>
        ' . h($d['invoice_reference']) . '
      </td>

      <td>
        ' . money($facturado) . ' ' . h($d['moneySymbol']) . '
      </td>

      <td>
        ' . money($pago) . ' ' . h($d['moneySymbol']) . '
      </td>

      <td>
        ' . money($valorPendente) . ' ' . h($d['moneySymbol']) . '
      </td>

    </tr>

  </table>

  <!-- TAXAS -->
  <div class="tax-wrapper">

    <div class="tax-table">

      <table>

        <tr>

          <th width="35%">
            Imposto/IVA %
          </th>

          <th width="35%">
            Incidência
          </th>

          <th width="30%" class="right">
            Valor
          </th>

        </tr>

        <tr>

          <td>
            Isento - 0
          </td>

          <td>
            ' . money($facturado) . ' Kz
          </td>

          <td class="right">
            0,00 Kz
          </td>

        </tr>

        <tr>

          <td>
            Retenção (6,50%)
          </td>

          <td>
            ' . money($facturado) . ' Kz
          </td>

          <td class="right">
            ' . money($retencaoValor) . ' Kz
          </td>

        </tr>

      </table>

    </div>

    <div class="total-table">

      <table>

        <tr>

          <td class="total-line bold">
            Total Pago
          </td>

          <td class="total-line bold right">
            ' . money($pago) . ' Kz
          </td>

        </tr>

      </table>

    </div>

    <div class="clear"></div>

  </div>

  <!-- TEXTO -->
  <div class="received-text">

    Recebi de
    <span class="bold">
      ' . h($d['client_name']) . '
    </span>,
    o montante líquido de
    <span class="bold">
      ' . money($pago) . ' kwanzas
    </span>.

  </div>

  <!-- FOOTER -->
  <div class="footer-section">

    <div class="bold fs12">
      Meio de pagamento
    </div>

    <div class="footer-line"></div>

    <div class="fs12 mt10">
      ' . h($d['payment_method']) . '
    </div>

    <div class="mt20 bold fs12">
      Regime de IVA
    </div>

    <div class="footer-line"></div>

    <div class="fs12 mt10">
      Regime simplificado
    </div>

  </div>

</body>

</html>
';

/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/

$options = new Options();

$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');

$dompdf->loadHtml($html, 'UTF-8');

$dompdf->render();

$dompdf->stream(
  'Recibo_' . $d['serie'] . '-' . $d['number'] . '.pdf',
  [
    'Attachment' => true
  ]
);
