<?php
require_once __DIR__ . '/../../../app/config/db.php';

require_once __DIR__ .'/../../../vendor/autoload.php'; // Dompdf ou outra lib

use Dompdf\Dompdf;
use Dompdf\Options;

// Pega o ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    echo "ID inválido";
    exit;
}

// Pega o conteúdo da guia renderizada
ob_start();
include "guide_public.php"; // precisa aceitar ?id=...
$html = ob_get_clean();

// Carrega o CSS e injeta diretamente no HTML (Inline) para garantir que o Dompdf aplique o estilo
$cssPath = __DIR__ . '/../guides_public.css';
$customCss = file_exists($cssPath) ? file_get_contents($cssPath) : '';

// Tenta carregar o Bootstrap via PHP para injetar inline (evita bloqueios de rede do Dompdf)
$bootstrapUrl = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
$context = stream_context_create([
    "ssl" => ["verify_peer" => false, "verify_peer_name" => false],
    "http" => ["timeout" => 5]
]);
$bootstrapCss = @file_get_contents($bootstrapUrl, false, $context);

// CSS de correção para Layout (Dompdf não suporta Flexbox do Bootstrap)
$layoutFix = "
    .d-flex { display: block; }
    .justify-content-between::after { content: ''; display: table; clear: both; }
    .inv-left { float: left; width: 48%; }
    .inv-right { float: right; width: 48%; text-align: right; }
    .text-end { text-align: right; }
    .text-start { text-align: left; }
    /* Garante que tabelas ocupem largura total */
    table { width: 100%; border-collapse: collapse; }
";

// Remove links de CSS antigos do HTML capturado para evitar erros
$html = str_replace('<link rel="stylesheet" href="guides/guides_public.css">', '', $html);

// Monta o HTML final com todos os estilos inline
$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . ($bootstrapCss ?: '') . '</style><style>' . $customCss . '</style><style>' . $layoutFix . '</style></head><body>' . $html . '</body></html>';

// Gera o PDF
$options = new Options();
$options->set('isRemoteEnabled', true); // Permite carregar imagens externas (QR Code) e CSS
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Baixa como arquivo
$dompdf->stream("guia-{$id}.pdf", ["Attachment" => true]);
