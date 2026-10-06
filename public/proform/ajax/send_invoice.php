<?php
// invoices/ajax/send_invoice.php
//---------------------------------------------------------------
require_once '../../../app/config/db.php';   // $pdo
require_once '../../../vendor/autoload.php'; // Composer

use Dompdf\{Dompdf, Options};
use PHPMailer\PHPMailer\PHPMailer;

//---------------------------------------------------------------
// util ‑ gera PDF da fatura e devolve caminho tmp
function generateInvoicePdf(int $invId): string{

  // -------- 1) captura HTML já pronto ------------------------
  // chama o mesmo endpoint público que você carrega no <iframe>
  $url  = "https://{$_SERVER['HTTP_HOST']}/sistema_fatura/public/invoices/ajax/invoice_public.php?id=$invId";
$opts = [
    "ssl" => [
        "verify_peer"      => false,
        "verify_peer_name" => false,
    ]
];
$context = stream_context_create($opts);
$html = file_get_contents($url, false, $context);


  if(!$html){
    throw new Exception('Não foi possível obter o HTML da fatura.');
  }

  // -------- 2) Dompdf ---------------------------------------
  $opt = new Options;
  $opt->set('isRemoteEnabled', true);        // permite <img src="http">
  $opt->set('defaultMediaType', 'print');    // aplica @media print e @page em todas as páginas
  $opt->set('isPhpEnabled', true);           // habilita <script type="text/php"> (numeração de páginas)
  $dompdf = new Dompdf($opt);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->render();

  // -------- 3) salva em /tmp e devolve caminho --------------
  $tmp = sys_get_temp_dir()."/invoice_$invId.pdf";
  file_put_contents($tmp, $dompdf->output());
  return $tmp;
}

//---------------------------------------------------------------
// 1) CAPTURA INPUT
header('Content-Type: application/json; charset=utf-8');

$to          = trim($_POST['to']          ?? '');
$cc          = trim($_POST['cc']          ?? '');
$subject     = trim($_POST['subject']     ?? '');
$bodyHtml    = trim($_POST['body']        ?? '');
$invoiceId   = intval($_POST['invoice_id'] ?? 0);
$attachPdf   = !empty($_POST['attach']);          // checkbox

// validações mínimas
if(!$invoiceId || !filter_var($to, FILTER_VALIDATE_EMAIL)){
  http_response_code(422);
  echo json_encode(['error'=>'Destinatário ou fatura inválidos.']);
  exit;
}
if($cc && !filter_var($cc, FILTER_VALIDATE_EMAIL)){
  http_response_code(422);
  echo json_encode(['error'=>'Endereço CC inválido.']);
  exit;
}

//---------------------------------------------------------------
// 2) CARREGA ALGUNS DADOS DA FATURA PARA usar no e‑mail
$stmt = $pdo->prepare("
  SELECT concat(YEAR(issue_date), '/', i.id) AS codigo,
         comp.name AS company_name
  FROM invoices i
  JOIN companies comp ON comp.id = i.company_id
  WHERE i.id = :id");
$stmt->execute([':id'=>$invoiceId]);
$invInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$invInfo){
  http_response_code(404);
  echo json_encode(['error'=>'Fatura não encontrada.']); exit;
}

// assunto default
if(!$subject){
  $subject = "Fatura {$invInfo['codigo']} – {$invInfo['company_name']}";
}

//---------------------------------------------------------------
// 3) GERA PDF (se solicitado)
$pdfPath = null;
try{
  if($attachPdf){
    $pdfPath = generateInvoicePdf($invoiceId);
  }
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error'=>'Falha ao gerar PDF: '.$e->getMessage()]); exit;
}

//---------------------------------------------------------------
// 4) ENVIA COM PHPMailer
try{
  $mail = new PHPMailer(true);

  // 4.1 ‑ SMTP ----------------------------------------------------------------
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'contato@israelsolucoesweb.com';
    $mail->Password   = '@Learsi99@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // <- ATENÇÃO AQUI!
    $mail->Port       = 465;
    

  // 4.2 ‑ remetente e destinatários -------------------------------------------
  $mail->setFrom('contato@israelsolucoesweb.com', 'Israel');
  $mail->addAddress($to);
  if($cc) $mail->addCC($cc);

  // 4.3 ‑ anexo PDF ------------------------------------------------------------
  if($pdfPath){
    $mail->addAttachment($pdfPath, "Fatura_{$invInfo['codigo']}.pdf");
  }

  // 4.4 ‑ conteúdo -------------------------------------------------------------
  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $bodyHtml ?: '<p>Segue a fatura em anexo.</p>';
  $mail->AltBody = strip_tags($mail->Body);

  $mail->send();

  // remove o temp se gerado
  if($pdfPath && file_exists($pdfPath)) unlink($pdfPath);

  echo json_encode(['ok'=>1]);
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error'=>'Mailer Error: '.$mail->ErrorInfo]);
}
