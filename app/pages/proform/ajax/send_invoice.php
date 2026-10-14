<?php
// invoices/ajax/send_invoice.php
//---------------------------------------------------------------
require_once '../../../app/config/db.php';   // $pdo
require_once '../../../app/helpers/authentication.php';
require_once '../../../app/helpers/document_pdf.php';

use PHPMailer\PHPMailer\PHPMailer;

//---------------------------------------------------------------
// 1) CAPTURA INPUT
header('Content-Type: application/json; charset=utf-8');

$to          = trim($_POST['to']          ?? '');
$cc          = trim($_POST['cc']          ?? '');
$subject     = trim($_POST['subject']     ?? '');
$bodyHtml    = trim($_POST['body']        ?? '');
$invoiceId   = intval($_POST['invoice_id'] ?? 0);
$attachPdf   = !empty($_POST['attach']);          // checkbox
$companyId   = (int)($_SESSION['user']['company_id'] ?? 0);

// validações mínimas
if(!$invoiceId || !filter_var($to, FILTER_VALIDATE_EMAIL)){
  http_response_code(422);
  echo json_encode(['error'=>'Destinatário ou fatura inválidos.']);
  exit;
}
if ($companyId <= 0) {
  http_response_code(403);
  echo json_encode(['error' => 'Empresa inválida para envio da proforma.']);
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
  SELECT concat(YEAR(p.issue_date), '/', p.id) AS codigo,
         comp.name AS company_name
  FROM proformas p
  JOIN companies comp ON comp.id = p.company_id
  WHERE p.id = :id AND p.company_id = :company_id");
$stmt->execute([':id' => $invoiceId, ':company_id' => $companyId]);
$invInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$invInfo){
  http_response_code(404);
  echo json_encode(['error'=>'Fatura não encontrada.']); exit;
}

// assunto default
if(!$subject){
  $subject = "Proforma {$invInfo['codigo']} – {$invInfo['company_name']}";
}

//---------------------------------------------------------------
// 3) GERA PDF (se solicitado)
$pdfPath = null;
try{
  if($attachPdf){
    $pdfPath = bx_generate_document_pdf(__DIR__ . '/proform_public.php', $invoiceId, 'proforma_', true);
  }
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error' => 'Falha ao gerar PDF.']); exit;
}

//---------------------------------------------------------------
// 4) ENVIA COM PHPMailer
$smtpUsername = trim((string)getenv('SMTP_USERNAME'));
$smtpPassword = (string)getenv('SMTP_PASSWORD');
$smtpFromName = trim((string)getenv('SMTP_FROM_NAME')) ?: 'BXpert';
if ($smtpUsername === '' || $smtpPassword === '' || !filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)) {
  if ($pdfPath && is_file($pdfPath)) {
    unlink($pdfPath);
  }
  http_response_code(500);
  echo json_encode(['error' => 'O serviço de email não está configurado.']);
  exit;
}

try{
  $mail = new PHPMailer(true);

  $mail->isSMTP();
  $mail->Host       = 'smtp.hostinger.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = $smtpUsername;
  $mail->Password   = $smtpPassword;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port       = 465;

  $mail->setFrom($smtpUsername, $smtpFromName);
  $mail->addAddress($to);
  if($cc) $mail->addCC($cc);

  // 4.3 ‑ anexo PDF ------------------------------------------------------------
  if($pdfPath){
    $mail->addAttachment($pdfPath, "Proforma_{$invInfo['codigo']}.pdf");
  }

  // 4.4 ‑ conteúdo -------------------------------------------------------------
  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $bodyHtml ?: '<p>Segue a fatura em anexo.</p>';
  $mail->AltBody = strip_tags($mail->Body);

  $mail->send();

  echo json_encode(['ok'=>1]);
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error' => 'Falha ao enviar o email.']);
}finally{
  if($pdfPath && is_file($pdfPath)){
    unlink($pdfPath);
  }
}
