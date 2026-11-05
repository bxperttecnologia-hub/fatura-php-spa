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
