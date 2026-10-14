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

$toRaw       = trim($_POST['to']          ?? '');
$ccRaw       = trim($_POST['cc']          ?? '');
$subject     = trim($_POST['subject']     ?? '');
$bodyHtml    = trim($_POST['body']        ?? '');
$invoiceId   = intval($_POST['invoice_id'] ?? 0);
$attachPdf   = !empty($_POST['attach']);          // checkbox
$companyId   = (int)($_SESSION['user']['company_id'] ?? 0);

// Keep accepting legacy single-address requests while supporting comma/semicolon lists.
$parseRecipients = static function (string $raw): array {
  if ($raw === '') {
    return [];
  }
  return array_values(array_filter(array_map('trim', preg_split('/[;,\r\n]+/', $raw))));
};
$to = $parseRecipients($toRaw);
$cc = $parseRecipients($ccRaw);

if (!$invoiceId || !$to) {
  http_response_code(422);
  echo json_encode(['error' => 'Adicione pelo menos um destinatário válido.']);
  exit;
}
if ($companyId <= 0) {
  http_response_code(403);
  echo json_encode(['error' => 'Empresa inválida para envio da fatura.']);
  exit;
}
$seenRecipients = [];
foreach (array_merge($to, $cc) as $address) {
  $normalizedAddress = strtolower($address);
  if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => "Endereço de e-mail inválido: {$address}."]);
    exit;
  }
  if (isset($seenRecipients[$normalizedAddress])) {
    http_response_code(422);
    echo json_encode(['error' => "O endereço {$address} está repetido em Para/Cc."]);
    exit;
  }
  $seenRecipients[$normalizedAddress] = true;
}

//---------------------------------------------------------------
// 2) CARREGA ALGUNS DADOS DA FATURA PARA usar no e‑mail
$stmt = $pdo->prepare("
  SELECT concat(YEAR(issue_date), '/', i.id) AS codigo,
         comp.name AS company_name
  FROM invoices i
  JOIN companies comp ON comp.id = i.company_id
  WHERE i.id = :id AND i.company_id = :company_id");
$stmt->execute([':id' => $invoiceId, ':company_id' => $companyId]);
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
    $pdfPath = bx_generate_document_pdf(__DIR__ . '/invoice_public.php', $invoiceId, 'invoice_');
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
  foreach ($to as $address) {
    $mail->addAddress($address);
  }
  foreach ($cc as $address) {
    $mail->addCC($address);
  }

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

  echo json_encode(['ok'=>1]);
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error' => 'Falha ao enviar o email.']);
}finally{
  if($pdfPath && is_file($pdfPath)){
    unlink($pdfPath);
  }
}
