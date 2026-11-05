<?php
/**
 * Textos comuns aos documentos impressos (fatura, fatura-recibo, proforma, recibo, NC, ND).
 */

require_once __DIR__ . '/env.php';

/** Nº de certificação do software mostrado no rodapé — vem do .env (AGT_SOFTWARE_CERTIFICATE). */
function bx_software_certificate(): string
{
    return trim((string) bx_env('AGT_SOFTWARE_CERTIFICATE')) ?: '-';
}

/** Descrição gravada com HTML do editor ("<p>Serviço&nbsp;</p>") → texto simples. */
function bx_plain_text(?string $value): string
{
    $text = (string) $value;
    $text = preg_replace('~<\s*br\s*/?>|</\s*(p|div|li)\s*>~i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\u{00A0}", ' ', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/\s*\n\s*/u', "\n", $text);
    return trim($text);
}

/** Nome do regime de IVA da empresa ('geral', 'simplificado', 'isento'…). */
function bx_vat_regime_label(?string $regime): string
{
    $r = mb_strtolower(trim((string) $regime), 'UTF-8');
    return match (true) {
        $r === '' => '-',
        str_contains($r, 'geral') => 'Regime Geral',
        str_contains($r, 'simplif') => 'Regime Simplificado',
        str_contains($r, 'isent') || str_contains($r, 'exclus') => 'Regime de Exclusão',
        default => mb_convert_case($r, MB_CASE_TITLE, 'UTF-8'),
    };
}

/** Frase "bens e serviços": a da empresa, ou a curta por omissão. */
function bx_goods_services_text(?string $custom): string
{
    $custom = trim((string) $custom);
    return $custom !== '' ? $custom : 'Bens/serviços disponibilizados na data do documento.';
}

/** Junta partes de uma morada, sem vazios nem "-" repetidos. */
function bx_join_address(array $parts): string
{
    $clean = [];
    foreach ($parts as $part) {
        $part = trim((string) $part, " \t\n\r\0\x0B,-");
        if ($part !== '' && !in_array(mb_strtolower($part), array_map('mb_strtolower', $clean), true)) {
            $clean[] = $part;
        }
    }
    return implode(', ', $clean);
}

/**
 * Rodapé dos PDF (Dompdf) igual ao da fatura, desenhado depois do render em posições fixas da
 * folha A4 (as mesmas da fatura): QR à esquerda, "Software certificado pela AGT | Nº …",
 * "Powered by BXPERT" e "Página n de N". O modelo deve deixar livres os últimos ~70pt da página.
 * $qrSrc: data URI PNG do QR (ou null).
 */
function bx_pdf_footer(\Dompdf\Dompdf $dompdf, ?string $qrSrc = null): void
{
    $qrFile = null;
    if ($qrSrc && preg_match('~^data:image/png;base64,(.+)$~', $qrSrc, $m)) {
        $qrFile = tempnam(sys_get_temp_dir(), 'bxqr') . '.png';
        file_put_contents($qrFile, base64_decode($m[1]));
    }
    $cert = 'Software certificado pela AGT | Nº ' . bx_software_certificate();
    $dompdf->getCanvas()->page_script(static function (int $page, int $count, $canvas, $metrics) use ($qrFile, $cert): void {
        $font = $metrics->getFont('Helvetica', 'normal');
        if ($qrFile) {
            $canvas->image($qrFile, 49.2, 776.7, 45, 45);
        }
        $canvas->text(112.3, 795.5, $cert, $font, 8, [0, 0, 0]);
        $canvas->text(112.3, 804.75, 'Powered by BXPERT', $font, 8, [0, 0, 0]);
        $label = "Página {$page} de {$count}";
        $canvas->text(550 - $metrics->getTextWidth($label, $font, 8), 795.5, $label, $font, 8, [0, 0, 0]);
    });
    if ($qrFile) {
        register_shutdown_function(static function () use ($qrFile): void {
            @unlink($qrFile);
        });
    }
}

/** IBAN em grupos de 4 ("AO06 0040 0000 …"), sem cortar dígitos. */
function bx_format_iban(?string $iban): string
{
    $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $iban));
    return $raw === '' ? '' : trim(chunk_split($raw, 4, ' '));
}

/** "Banco · IBAN" para os documentos (usa o IBAN principal ou, se vazio, o secundário). */
function bx_bank_details_text(array $company): string
{
    $iban = bx_format_iban(($company['iban'] ?? '') ?: ($company['iban_optional'] ?? ''));
    $bank = trim((string) (($company['iban'] ?? '') ? ($company['bank_name'] ?? '') : (($company['bank_name_optional'] ?? '') ?: ($company['bank_name'] ?? ''))));
    if ($iban === '') {
        return '-';
    }
    return $bank !== '' ? $bank . "\nIBAN: " . $iban : 'IBAN: ' . $iban;
}
