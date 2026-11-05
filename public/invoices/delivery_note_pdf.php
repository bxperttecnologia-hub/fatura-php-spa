<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/company_logo.php';
require_once __DIR__ . '/../../app/helpers/document_print.php';

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
    c.address AS client_address,
    c.telephone_ddi AS client_telephone_ddi,
    c.telephone AS client_telephone,
    c.cellphone_ddi AS client_cellphone_ddi,
    c.cellphone AS client_cellphone

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
  SELECT
      dni.quantity,
      COALESCE(
          NULLIF(TRIM(dni.description), ''),
          NULLIF(TRIM(it.name), ''),
          NULLIF(TRIM(it.description), ''),
          'Item sem descrição'
      ) AS item_description,
      NULLIF(TRIM(it.description), '') AS product_details,
      COALESCE(NULLIF(TRIM(it.code), ''), '-') AS item_code,
      COALESCE(NULLIF(TRIM(it.unit_measure), ''), 'Unidade') AS unit_measure
  FROM delivery_note_items dni
  LEFT JOIN invoice_items ii ON ii.id = dni.invoice_item_id
  LEFT JOIN items it ON it.id = COALESCE(dni.item_id, ii.item_id)
  WHERE dni.delivery_note_id = :id
  ORDER BY dni.id ASC
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

function unitLabel($value)
{
  return [
    'unit' => 'Unidade',
    'kg' => 'Kg',
    'liter' => 'Litro',
    'meter' => 'Metro',
    'service' => 'Serviço',
  ][$value] ?? $value;
}

/* ---------------- LOGO (mesmo esquema do recibo) ---------------- */

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocalhost = in_array($host, ['localhost', '127.0.0.1']);
$basePath = $isLocalhost ? '/projects/bxpert/fatura/public' : '';
$BASE_URL = "{$protocol}://{$host}{$basePath}";

$logo = company_logo_pdf_src($d['logo_url'] ?? '') ?: company_logo_src($d['logo_url'] ?? '', $BASE_URL);   // data URI: o Dompdf não pede o logo ao próprio servidor

/* ---------------- HTML ---------------- */

$number  = h($d['serie'] . ' ' . $d['number']);
$issue   = $d['issue_date'] ? date('d/m/Y', strtotime($d['issue_date'])) : '-';
$address = $d['delivery_address'] ?: $d['client_address'];
$invRef  = $d['invoice_reference'] ?: $d['invoice_id'];
$clientPhone = trim(
  trim((string)$d['client_telephone_ddi'] . ' ' . (string)$d['client_telephone'])
  ?: trim((string)$d['client_cellphone_ddi'] . ' ' . (string)$d['client_cellphone'])
);

$rows = '';
foreach ($items as $it) {
  $description = h($it['item_description']);
  if (
    !empty($it['product_details'])
    && trim((string)$it['product_details']) !== trim((string)$it['item_description'])
  ) {
    $description .= '<br><span class="muted">' . h($it['product_details']) . '</span>';
  }
  $rows .= '<tr>'
    . '<td class="code">' . h($it['item_code']) . '</td>'
    . '<td>' . $description . '</td>'
    . '<td class="unit">' . h(unitLabel($it['unit_measure'])) . '</td>'
    . '<td class="r">' . qty($it['quantity']) . '</td>'
    . '</tr>';
}

$html = '
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
  /* cabeçalho e rodapé iguais aos da fatura (bx_pdf_header / bx_pdf_footer) */
  @page { margin: ' . BX_PDF_HEADER_BOTTOM . 'pt 45pt 80pt 45pt; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
  .muted { color: #888; }
  .info td { font-size: 9pt; color: #707070; padding: 0 0 6pt 0; }
  .info .lab { width: 100pt; }
  .info .lab2 { width: 78pt; }
  .info .val { width: 220pt; color: #444; }
  .info td:last-child { white-space: nowrap; }
  .r { text-align: right; }
  .items { margin-top: 10pt; font-size: 8pt; }
  .items th { font-weight: bold; color: #555; text-align: left; padding: 6pt 5pt;
              border-top: 1pt solid #8C8C8C; border-bottom: .8pt solid #D2D2D2; }
  .items th.r { text-align: right; }
  .items td { color: #666; padding: 7pt 5pt; border-bottom: .5pt solid #EEEEEE; }
  .code { width: 75pt; }
  .unit { width: 70pt; }
  .obs { margin-top: 14pt; font-size: 9pt; color: #707070; }
  .sign { margin-top: 60pt; font-size: 9pt; color: #555; }
  .sign td { width: 50%; text-align: center; padding: 0 20pt; }
  .line { border-top: .8pt solid #555; padding-top: 5pt; }
</style>
</head>
<body>

<table class="info">
  <tr>
    <td class="lab">Cliente:</td><td class="val"><strong>' . h($d['client_name']) . '</strong></td>
    <td class="lab2">Data de emissão:</td><td>' . h($issue) . '</td>
  </tr>
  <tr>
    <td class="lab">Contribuinte:</td><td class="val">' . h($d['client_contributor'] ?: '-') . '</td>
    <td class="lab2">Fatura associada:</td><td>' . h($invRef) . '</td>
  </tr>
  <tr>
    <td class="lab">Local de entrega:</td><td class="val" colspan="3">' . nl2br(h($address ?: '-')) . '</td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr><th class="code">Código</th><th>Descrição</th><th class="unit">Unidade</th><th class="r">Qtd.</th></tr>
  </thead>
  <tbody>' . $rows . '</tbody>
</table>'
  . ($d['notes']
    ? '<div class="obs"><strong>Observações:</strong><br>' . nl2br(h($d['notes'])) . '</div>'
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
bx_pdf_header($dompdf, [   // cabeçalho igual ao da fatura
  'logo' => $logo,
  'company_name' => $d['company_name'],
  'company_address' => bx_join_address([$d['company_address'], $d['company_city'], $d['company_country']]),
  'company_lines' => ['Tel: ' . ($d['company_phone'] ?: '-'), 'E-mail: ' . ($d['company_email'] ?: '-'), 'Contribuinte: ' . ($d['company_nif'] ?: '-')],
  'client_name' => $d['client_name'],
  'client_lines' => array_filter([bx_join_address([$d['client_address']]), $clientPhone !== '' ? 'Tel: ' . $clientPhone : '']),
  'title' => 'Nota de Entrega n.º ' . $d['serie'] . ' ' . $d['number'],
]);
bx_pdf_footer($dompdf);   // rodapé igual ao da fatura

$dompdf->stream(
  'NotaEntrega_' . $d['serie'] . '-' . $d['number'] . '.pdf',
  ['Attachment' => false]   // inline: a pré-visualização (iframe) mostra-o em vez de o descarregar
);
