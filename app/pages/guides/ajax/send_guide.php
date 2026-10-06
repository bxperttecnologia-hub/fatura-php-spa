<?php
// guides/ajax/send_guide.php
//---------------------------------------------------------------
require_once '../../../app/config/db.php';   // $pdo
require_once '../../../vendor/autoload.php'; // Composer

use Dompdf\{Dompdf, Options};
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

function generateGuidePdf(int $guideId): string{
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $url  = "https://{$host}/guides/ajax/guide_public.php?id={$guideId}";

  $opts = [
    "ssl" => [
      "verify_peer"      => false,
      "verify_peer_name" => false,
    ]
  ];
  $context = stream_context_create($opts);
  $html = @file_get_contents($url, false, $context);

  if(!$html){
    throw new Exception('Não foi possível obter o HTML da guia.');
  }

  $opt = new Options;
  $opt->set('isRemoteEnabled', true);
  $dompdf = new Dompdf($opt);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->render();

  $tmp = sys_get_temp_dir()."/guide_{$guideId}.pdf";
  file_put_contents($tmp, $dompdf->output());
  return $tmp;
}

$to        = trim($_POST['to'] ?? '');
$cc        = trim($_POST['cc'] ?? '');
$subject   = trim($_POST['subject'] ?? '');
$bodyHtml  = trim($_POST['body'] ?? '');
$guideId   = (int)($_POST['guide_id'] ?? 0);
$attachPdf = !empty($_POST['attach']);

if(!$guideId || !filter_var($to, FILTER_VALIDATE_EMAIL)){
  http_response_code(422);
  echo json_encode(['error'=>'Destinatário ou guia inválidos.']);
  exit;
}
if($cc && !filter_var($cc, FILTER_VALIDATE_EMAIL)){
  http_response_code(422);
  echo json_encode(['error'=>'Endereço CC inválido.']);
  exit;
}

// Dados básicos para fallback
$stmt = $pdo->prepare("SELECT series, id FROM guides WHERE id = :id");
$stmt->execute([':id' => $guideId]);
$g = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$g){
  http_response_code(404);
  echo json_encode(['error'=>'Guia não encontrada.']);
  exit;
}

if(!$subject){
  $subject = "Guia #{$g['series']}/{$g['id']}";
}
if(!$bodyHtml){
  $bodyHtml = '<p>Segue em anexo a guia.</p>';
}

$pdfPath = null;
try{
  if($attachPdf){
    $pdfPath = generateGuidePdf($guideId);
  }
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(['error'=>'Falha ao gerar PDF: '.$e->getMessage()]);
  exit;
}

try{
  $mail = new PHPMailer(true);

  $mail->isSMTP();
  $mail->Host       = 'smtp.hostinger.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'contato@israelsolucoesweb.com';
  $mail->Password   = '@Learsi99@';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
  $mail->Port       = 465;

  $mail->CharSet = 'UTF-8';
  $mail->setFrom('contato@israelsolucoesweb.com', 'BXpert');
  $mail->addAddress($to);
  if($cc) $mail->addCC($cc);

  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $bodyHtml;

  if($pdfPath){
    $mail->addAttachment($pdfPath, "Guia_{$g['series']}-{$g['id']}.pdf");
  }

  $mail->send();

  echo json_encode(['success'=>true]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error'=>'Falha ao enviar e-mail: '.$e->getMessage()]);
} finally {
  if($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);
}
