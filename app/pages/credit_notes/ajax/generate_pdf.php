<?php

session_start();

require_once '../../../app/config/db.php';
require_once '../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  http_response_code(422);
  exit('Nota de crédito inválida.');
}

/*
|--------------------------------------------------------------------------
| Buscar nota
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT 
        id,
        issue_date,
        company_id
    FROM credit_notes
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
  ':id' => $id
]);

$cn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cn) {
  http_response_code(404);
  exit('Nota de crédito não encontrada.');
}

/*
|--------------------------------------------------------------------------
| Segurança
|--------------------------------------------------------------------------
*/

$sessionCompanyId = (int)($_SESSION['user']['company_id'] ?? 0);

if (
  $sessionCompanyId > 0 &&
  (int)$cn['company_id'] !== $sessionCompanyId
) {
  http_response_code(403);
  exit('Acesso negado.');
}

/*
|--------------------------------------------------------------------------
| Capturar HTML diretamente
|--------------------------------------------------------------------------
*/

ob_start();

$_GET['id'] = $id;

require __DIR__ . '/credit_note_public.php';

$html = ob_get_clean();

if (empty(trim($html))) {
  http_response_code(500);
  exit('HTML vazio.');
}

/*
|--------------------------------------------------------------------------
| DOMPDF
|--------------------------------------------------------------------------
*/

$options = new Options();

$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');

$dompdf->loadHtml($html, 'UTF-8');

$dompdf->render();

/*
|--------------------------------------------------------------------------
| Nome arquivo
|--------------------------------------------------------------------------
*/

$year = !empty($cn['issue_date'])
  ? date('Y', strtotime($cn['issue_date']))
  : date('Y');

$filename = "Nota_Credito_{$year}_{$id}.pdf";

/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
*/

$dompdf->stream($filename, [
  'Attachment' => true
]);

exit;
