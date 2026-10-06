<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$dnId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$dnId) {
  die('Nota de entrega não encontrada.');
}

/* ---------------- QUERY ---------------- */

$st = $pdo->prepare("
SELECT
    dn.id, dn.serie, dn.number, dn.issue_date, dn.delivery_address, dn.notes,

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

FROM delivery_notes dn
JOIN invoices  i    ON i.id    = dn.invoice_id
JOIN companies comp ON comp.id = dn.company_id
JOIN contact   c    ON c.id    = dn.contact_id
WHERE dn.id = :id
");
$st->execute(['id' => $dnId]);
$d = $st->fetch(PDO::FETCH_ASSOC);

if (!$d) {
  die('Nota de entrega não encontrada.');
}

$st = $pdo->prepare("
  SELECT description, quantity
  FROM delivery_note_items
  WHERE delivery_note_id = :id
  ORDER BY id ASC
");
$st->execute(['id' => $dnId]);
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

$number  = h($d['serie'] . ' ' . $d['number']);
$issue   = $d['issue_date'] ? date('d/m/Y', strtotime($d['issue_date'])) : '-';
$address = $d['delivery_address'] ?: $d['client_address'];
$invRef  = $d['invoice_reference'] ?: $d['invoice_id'];

$rows = '';
foreach ($items as $n => $it) {
  $rows .= '<tr>'
    . '<td class="c">' . ($n + 1) . '</td>'
    . '<td>' . h($it['description']) . '</td>'
    . '<td class="r">' . qty($it['quantity']) . '</td>'
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
  .c { text-align: center; width: 40px; }
  .r { text-align: right; width: 90px; }
  .sign { margin-top: 70px; }
  .sign td { width: 50%; text-align: center; padding: 0 20px; }
  .line { border-top: 1px solid #000; padding-top: 5px; }
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
      <div class="title">NOTA DE ENTREGA</div>
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
      ' . h($d['client_email']) . '
    </td>
    <td style="width:2%"></td>
    <td style="width:49%" class="box">
      <strong>Local de entrega</strong><br>
      ' . nl2br(h($address)) . '
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr><th class="c">#</th><th>Descrição</th><th class="r">Qtd.</th></tr>
  </thead>
  <tbody>' . $rows . '</tbody>
</table>'
  . ($d['notes']
    ? '<p style="margin-top:14px"><strong>Observações:</strong><br>' . nl2br(h($d['notes'])) . '</p>'
    : '')
  . '
<table class="sign">
  <tr>
    <td><div class="line">Entregue por</div></td>
    <td><div class="line">Recebido por (nome, data e assinatura)</div></td>
  </tr>
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
  'NotaEntrega_' . $d['serie'] . '-' . $d['number'] . '.pdf',
  ['Attachment' => true]
);
