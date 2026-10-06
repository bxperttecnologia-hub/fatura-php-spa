<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
if (!$companyId) {
  http_response_code(401);
  die('Sessão inválida.');
}

$ndId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ndId) {
  die('Nota de débito não encontrada.');
}

/* ---------------- QUERY ---------------- */

$st = $pdo->prepare("
SELECT
    nd.id, nd.serie, nd.number, nd.issue_date, nd.reason, nd.currency,
    nd.subtotal_without_tax, nd.total_tax, nd.final_total,

    i.id AS invoice_id,
    i.reference AS invoice_reference,

    comp.name AS company_name,
    comp.address AS company_address,
    comp.city AS company_city,
    comp.country AS company_country,
    comp.phone AS company_phone,
    comp.email AS company_email,
    comp.registration_number AS company_nif,
    comp.logo_url,

    c.name AS client_name,
    c.email AS client_email,
    c.contributor AS client_contributor,
    c.address AS client_address

FROM debit_notes nd
JOIN invoices  i    ON i.id    = nd.invoice_id
JOIN companies comp ON comp.id = nd.company_id
JOIN contact   c    ON c.id    = nd.contact_id
WHERE nd.id = :id AND nd.company_id = :company
");
$st->execute(['id' => $ndId, 'company' => $companyId]);
$d = $st->fetch(PDO::FETCH_ASSOC);

if (!$d) {
  die('Nota de débito não encontrada.');
}

$st = $pdo->prepare("
  SELECT description, quantity, unit_price, tax
  FROM debit_note_items
  WHERE debit_note_id = :id
  ORDER BY id ASC
");
$st->execute(['id' => $ndId]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- HELPERS ---------------- */

function h($v)
{
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function qty($v)
{
  // 2 -> "2"; 2.5 -> "2,5"
  return rtrim(rtrim(number_format((float)$v, 3, ',', '.'), '0'), ',');
}

function money($v)
{
  return number_format((float)$v, 2, ',', '.');
}

/* ---------------- LOGO (mesmo esquema do recibo) ---------------- */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = in_array($host, ['localhost', '127.0.0.1']);
$basePath = $isLocalhost ? '/projects/bxpert/fatura/public' : '';
$BASE_URL = "{$protocol}://{$host}{$basePath}";

$logo = !empty($d['logo_url'])
  ? $BASE_URL . '/assets/img/companies/' . rawurlencode($d['logo_url'])
  : '';

/* ---------------- HTML ---------------- */

$number   = h($d['serie'] . ' ' . $d['number']);
$issue    = $d['issue_date'] ? date('d/m/Y', strtotime($d['issue_date'])) : '-';
$invRef   = $d['invoice_reference'] ?: $d['invoice_id'];
$currency = h($d['currency']);

$rows = '';
foreach ($items as $n => $it) {
  $lineBase = round((float)$it['quantity'] * (float)$it['unit_price'], 2);
  $rows .= '<tr>'
    . '<td class="c">' . ($n + 1) . '</td>'
    . '<td>' . h($it['description']) . '</td>'
    . '<td class="r">' . qty($it['quantity']) . '</td>'
    . '<td class="r">' . money($it['unit_price']) . '</td>'
    . '<td class="r">' . rtrim(rtrim(number_format((float)$it['tax'], 2, ',', '.'), '0'), ',') . '%</td>'
    . '<td class="r">' . money($lineBase) . '</td>'
    . '</tr>';
}

$html = '
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 30px 35px; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
  .head td { padding-bottom: 14px; }
  .title { font-size: 20px; font-weight: bold; text-align: right; }
  .muted { color: #555; }
  .box { border: 1px solid #ccc; padding: 8px 10px; }
  .items { margin-top: 18px; }
  .items th { background: #f0f0f0; border: 1px solid #ccc; padding: 7px; text-align: left; }
  .items td { border: 1px solid #ccc; padding: 7px; }
  .c { text-align: center; width: 36px; }
  .r { text-align: right; width: 80px; }
  .totals { margin-top: 14px; width: 45%; margin-left: 55%; }
  .totals td { padding: 5px 7px; border: 1px solid #ccc; }
  .totals .grand td { background: #f0f0f0; font-weight: bold; font-size: 13px; }
</style>
</head>
<body>

<table class="head">
  <tr>
    <td style="width:55%">'
  . ($logo ? '<img src="' . h($logo) . '" style="max-height:60px;max-width:200px"><br>' : '')
  . '<strong>' . h($d['company_name']) . '</strong><br>
      ' . h($d['company_address']) . '<br>
      ' . h(trim($d['company_city'] . ' ' . $d['company_country'])) . '<br>
      NIF: ' . h($d['company_nif']) . '<br>
      ' . h($d['company_phone']) . ' ' . h($d['company_email']) . '
    </td>
    <td>
      <div class="title">NOTA DE DÉBITO</div>
      <div style="text-align:right;margin-top:6px">
        <strong>N.º ' . $number . '</strong><br>
        Data: ' . h($issue) . '<br>
        <span class="muted">Fatura: ' . h($invRef) . '</span>
      </div>
    </td>
  </tr>
</table>

<table>
  <tr>
    <td style="width:49%" class="box">
      <strong>Cliente</strong><br>
      ' . h($d['client_name']) . '<br>
      NIF: ' . h($d['client_contributor']) . '<br>
      ' . h($d['client_email']) . '<br>
      ' . nl2br(h($d['client_address'])) . '
    </td>
    <td style="width:2%"></td>
    <td style="width:49%" class="box">
      <strong>Motivo</strong><br>
      ' . nl2br(h($d['reason'])) . '
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th class="c">#</th>
      <th>Descrição</th>
      <th class="r">Qtd.</th>
      <th class="r">Preço unit.</th>
      <th class="r">IVA</th>
      <th class="r">Total</th>
    </tr>
  </thead>
  <tbody>' . $rows . '</tbody>
</table>

<table class="totals">
  <tr><td>Subtotal (sem IVA)</td><td style="text-align:right">' . money($d['subtotal_without_tax']) . ' ' . $currency . '</td></tr>
  <tr><td>IVA</td><td style="text-align:right">' . money($d['total_tax']) . ' ' . $currency . '</td></tr>
  <tr class="grand"><td>Total a débito</td><td style="text-align:right">' . money($d['final_total']) . ' ' . $currency . '</td></tr>
</table>

</body>
</html>';

/* ---------------- DOMPDF ---------------- */

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$dompdf->stream(
  'NotaDebito_' . $d['serie'] . '-' . $d['number'] . '.pdf',
  ['Attachment' => true]
);
