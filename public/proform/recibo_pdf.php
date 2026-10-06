<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/config/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$receiptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$receiptId){
  die('Recibo não encontrado.');
}

$sql = "
SELECT 
  r.id AS receipt_id, r.serie, r.number, r.pay_date, r.amount_paid, r.payment_method, r.notes,
  i.id AS invoice_id, i.issue_date, i.reference, i.final_total, i.currency AS invoice_currency,
  cr.symbol AS moneySymbol, cr.position AS moneyPos,
  comp.name AS company_name, comp.address AS company_address, comp.city AS company_city, comp.country AS company_country,
  comp.phone AS company_phone, comp.email AS company_email, comp.registration_number AS company_nif,
  comp.logo_url,
  c.name AS client_name, c.email AS client_email, c.contributor AS client_contributor
FROM receipts r
JOIN invoices i ON i.id = r.invoice_id
JOIN companies comp ON comp.id = i.company_id
JOIN contact c ON c.id = i.contact_id
JOIN currencies cr ON cr.iso_code = i.currency
WHERE r.id = :rid
";
$st = $pdo->prepare($sql);
$st->execute(['rid'=>$receiptId]);
$d = $st->fetch(PDO::FETCH_ASSOC);
if(!$d){
  die('Recibo não encontrado.');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function formatMoney(float $value, string $symbol, string $pos): string{
  $formatted = number_format($value, 2, ',', '.');
  return ($pos === 'left') ? ($symbol.' '.$formatted) : ($formatted.' '.$symbol);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? ($scheme.'://'.$host) : '';

$logo = ($d['logo_url'] && $baseUrl) ? ($baseUrl.'/assets/img/companies/'.$d['logo_url']) : '';

$html = '<!doctype html><html><head><meta charset="utf-8">
<style>
  body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#111;margin:18px;}
  .row{display:flex;justify-content:space-between;gap:16px;}
  .card{border:1px solid #e5e7eb;border-radius:10px;padding:12px;}
  .title{font-size:16px;font-weight:700;margin:0 0 6px 0;}
  .muted{color:#6b7280;}
  .kv{margin-top:8px;}
  .kv table{width:100%;border-collapse:collapse;}
  .kv td{padding:7px 0;border-bottom:1px dashed #ddd;vertical-align:top;}
  .kv tr:last-child td{border-bottom:0;}
  .kv td:first-child{color:#374151;width:52%;}
  .kv td:last-child{text-align:right;font-weight:700;}
  .logo{max-width:160px;max-height:70px;object-fit:contain;}
  .footer{position:fixed;left:18px;right:18px;bottom:12px;border-top:1px solid #111;padding-top:6px;font-size:10px;color:#6b7280;text-align:center;}
</style>
</head><body>';

$html .= '<div class="row">';
$html .= '<div style="flex:1" class="card">';
$html .= '<div class="row" style="align-items:center">';
$html .= '<div>';
$html .= '<div class="title">RECIBO</div>';
$html .= '<div class="muted">Nº '.$d['serie'].'/'.$d['number'].'</div>';
$html .= '</div>';
$html .= $logo ? ('<div><img class="logo" src="'.h($logo).'" alt="Logo"></div>') : '<div></div>';
$html .= '</div>';

$html .= '<div class="kv">';
$html .= '<table>';
$html .= '<tr><td>Data do pagamento</td><td>'.h($d['pay_date']).'</td></tr>';
$html .= '<tr><td>Valor pago</td><td>'.h(formatMoney((float)$d['amount_paid'], (string)$d['moneySymbol'], (string)$d['moneyPos'])).'</td></tr>';
$html .= '<tr><td>Meio de pagamento</td><td>'.h($d['payment_method']).'</td></tr>';
$html .= '<tr><td>Fatura referência</td><td>'.h(date('Y', strtotime($d['issue_date'])).'/'.$d['invoice_id']).'</td></tr>';
$html .= '</table>';
$html .= '</div>';

if(!empty($d['notes'])){
  $html .= '<p class="muted" style="margin:10px 0 0 0">Obs: '.h($d['notes']).'</p>';
}

$html .= '</div>';

$html .= '<div style="width:260px" class="card">';
$html .= '<div style="font-weight:700;margin-bottom:6px">Emitente</div>';
$html .= '<div>'.h($d['company_name']).'</div>';
$html .= '<div class="muted">'.h($d['company_address']).'</div>';
$html .= '<div class="muted">'.h($d['company_city'].' - '.$d['company_country']).'</div>';
$html .= '<div class="muted">Tel: '.h($d['company_phone']).'</div>';
$html .= '<div class="muted">Email: '.h($d['company_email']).'</div>';
$html .= '<div class="muted">NIF: '.h($d['company_nif']).'</div>';
$html .= '<hr style="border:0;border-top:1px solid #eee;margin:10px 0">';
$html .= '<div style="font-weight:700;margin-bottom:6px">Cliente</div>';
$html .= '<div>'.h($d['client_name']).'</div>';
$html .= '<div class="muted">Email: '.h($d['client_email']).'</div>';
$html .= '<div class="muted">Contribuinte: '.h($d['client_contributor']).'</div>';
$html .= '</div>';

$html .= '</div>';

$html .= '<div class="footer">'.h($d['company_name']).' | '.h($d['company_city'].' - '.$d['company_country']).' | Recibo gerado pelo Sistema Fatura</div>';

$html .= '</body></html>';

$opt = new Options();
$opt->set('isRemoteEnabled', true);
$dompdf = new Dompdf($opt);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$dompdf->stream('Recibo_'.$d['serie'].'-'.$d['number'].'.pdf', ['Attachment' => 1]);
