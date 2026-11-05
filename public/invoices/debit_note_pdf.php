<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/helpers/company_logo.php';
require_once __DIR__ . '/../../app/helpers/agt.php';
require_once __DIR__ . '/../../app/helpers/agt_qr.php';
require_once __DIR__ . '/../../app/helpers/document_print.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    nd.id, nd.serie, nd.number, nd.reference, nd.agt_status, nd.issue_date, nd.reason, nd.currency,
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
    comp.vat_regime,

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
  SELECT description, quantity, unit_price, discount, tax
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

$logo = company_logo_pdf_src($d['logo_url'] ?? '') ?: company_logo_src($d['logo_url'] ?? '', $BASE_URL);   // data URI: o Dompdf não pede o logo ao próprio servidor

/* ---------------- HTML ---------------- */

// ND da faturação eletrónica: "ND <série>/<n>" e QR de consulta da AGT.
$agtNumber = agt_parse_document_no($d['reference'] ?? null) ? (string)$d['reference'] : null;
$number   = h($agtNumber ?? ($d['serie'] . ' ' . $d['number']));
$agtQrUrl = $agtNumber ? agt_qr_url((string)$d['company_nif'], $agtNumber) : null;
$qrSrc    = $agtQrUrl ? agt_qr_image($agtQrUrl) : null;   // vai para o rodapé comum (bx_pdf_footer)
$issue    = $d['issue_date'] ? date('d/m/Y', strtotime($d['issue_date'])) : '-';
$invRef   = $d['invoice_reference'] ?: $d['invoice_id'];
$currency = h($d['currency']);

$rows = '';
foreach ($items as $n => $it) {
  $gross = round((float)$it['quantity'] * (float)$it['unit_price'], 2);
  $discount = round($gross * (float)($it['discount'] ?? 0) / 100, 2);
  $lineBase = round($gross - $discount, 2);
  $lineTotal = round($lineBase * (1 + (float)$it['tax'] / 100), 2);
  $rows .= '<tr>'
    . '<td>' . ($n + 1) . '</td>'
    . '<td>' . h(bx_plain_text($it['description'])) . '</td>'
    . '<td class="r">' . money($it['unit_price']) . ' Kz</td>'
    . '<td class="ce">' . qty($it['quantity']) . '</td>'
    . '<td class="ce">' . rtrim(rtrim(number_format((float)$it['tax'], 2, ',', '.'), '0'), ',') . '%</td>'
    . '<td class="ce">' . qty($it['discount'] ?? 0) . '%</td>'
    . '<td class="r">' . money($lineTotal) . ' Kz</td>'
    . '</tr>';
}

$html = '
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
  /* cabeçalho e rodapé iguais aos da fatura (bx_pdf_header / bx_pdf_footer): o corpo fica entre eles */
  @page { margin: ' . BX_PDF_HEADER_BOTTOM . 'pt 45pt 80pt 45pt; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; }
  .info td { font-size: 9pt; color: #707070; padding: 0 0 6pt 0; }
  .info .lab { width: 100pt; }
  .info .lab2 { width: 78pt; }
  .info .val { width: 220pt; color: #444; }
  .info td:last-child { white-space: nowrap; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
  .info td { font-size: 9pt; color: #707070; padding: 0 0 6pt 0; }
  .info .lab { width: 100pt; }
  .info .lab2 { width: 78pt; }
  .info .val { width: 220pt; color: #444; }
  .info td:last-child { white-space: nowrap; }
  .r { text-align: right; }
  .ce { text-align: center; }
  .items { margin-top: 10pt; font-size: 8pt; }
  .items th { font-weight: bold; color: #555; text-align: left; padding: 6pt 5pt;
              border-top: 1pt solid #8C8C8C; border-bottom: .8pt solid #D2D2D2; }
  .items th.r { text-align: right; } .items th.ce { text-align: center; }
  .items td { color: #666; padding: 7pt 5pt; border-bottom: .5pt solid #EEEEEE; }
  .bottom { margin-top: 20pt; }
  .sec { font-size: 10pt; font-weight: bold; color: #555; padding-bottom: 5pt;
         border-top: 1.5pt solid #8C8C8C; padding-top: 6pt; border-bottom: .8pt solid #C4C4C4; margin-bottom: 6pt; }
  .kv td { font-size: 8.5pt; color: #707070; padding: 3pt 0; }
  .kv .k { width: 100pt; }
  .sum .grand td { font-size: 12pt; font-weight: bold; color: #000; padding-top: 8pt;
                   border-top: 1pt solid #000; border-bottom: 2pt solid #555; }
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
    <td class="lab">Motivo:</td><td class="val" colspan="3">' . nl2br(h($d['reason'])) . '</td>
  </tr>
</table>

<!-- tabela e totais com o mesmo aspecto da fatura / nota de crédito -->
<table class="items">
  <thead>
    <tr>
      <th style="width:30pt">#</th>
      <th>Descrição</th>
      <th class="r" style="width:85pt">Preço Uni.</th>
      <th class="ce" style="width:32pt">Qtd.</th>
      <th class="ce" style="width:45pt">Taxa/IVA</th>
      <th class="ce" style="width:38pt">Desc.</th>
      <th class="r" style="width:95pt">Total</th>
    </tr>
  </thead>
  <tbody>' . $rows . '</tbody>
</table>

<table class="bottom">
  <tr>
    <td style="width:58%;padding-right:25pt">
      <div class="sec">Dados fiscais</div>
      <table class="kv">
        <tr><td class="k">Regime de IVA:</td><td>' . h(bx_vat_regime_label($d['vat_regime'] ?? '')) . '</td></tr>
        <tr><td class="k">Fatura associada:</td><td>' . h($invRef) . '</td></tr>
      </table>
    </td>
    <td style="width:42%">
      <div class="sec">Sumário</div>
      <table class="kv sum">
        <tr><td class="k">Sem Imposto/IVA:</td><td class="r">' . money($d['subtotal_without_tax']) . ' Kz</td></tr>
        <tr><td class="k">Imposto/IVA:</td><td class="r">' . money($d['total_tax']) . ' Kz</td></tr>
        <tr class="grand"><td>Total a débito:</td><td class="r">' . money($d['final_total']) . ' Kz</td></tr>
      </table>
    </td>
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
  'client_lines' => [bx_join_address([$d['client_address']])],
  'title' => 'Nota de Débito n.º ' . ($agtNumber ?? ($d['serie'] . ' ' . $d['number'])),
]);
bx_pdf_footer($dompdf, $qrSrc);   // rodapé igual ao da fatura

$dompdf->stream(
  'NotaDebito_' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $agtNumber ?? ($d['serie'] . '-' . $d['number'])) . '.pdf',
  ['Attachment' => false]   // inline: a pré-visualização (iframe) mostra-o em vez de o descarregar
);
