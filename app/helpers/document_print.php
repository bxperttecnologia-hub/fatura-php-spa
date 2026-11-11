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
    // Valores do formulário da empresa (select "Bens e serviços"), não texto livre.
    $codes = [
        'servicos' => 'Serviços prestados na data do documento.',
        'produtos' => 'Bens disponibilizados na data do documento.',
    ];
    if (isset($codes[mb_strtolower($custom)])) {
        return $codes[mb_strtolower($custom)];
    }
    return $custom !== '' ? $custom : 'Bens/serviços disponibilizados na data do documento.';
}

/** Junta partes de uma morada, sem vazios nem "-" repetidos. */
function bx_join_address(array $parts): string
{
    $clean = [];
    foreach ($parts as $part) {
        $part = trim((string) $part, " \t\n\r\0\x0B,-");
        if ($part === '') {
            continue;
        }
        // Não repete cidade/país que já vêm escritos na morada
        // (ex.: "Edifício Goya, Luanda Angola" + país "Angola" → "Angola" só uma vez).
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($part, '/') . '(?![\p{L}\p{N}])/iu';
        $repeated = false;
        foreach ($clean as $kept) {
            if (preg_match($pattern, $kept)) {
                $repeated = true;
                break;
            }
        }
        if (!$repeated) {
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

/**
 * Cabeçalho igual ao da fatura (invoice_public.php), desenhado em todas as páginas de um
 * documento Dompdf (recibo, nota de crédito, nota de débito, nota de entrega).
 * Mesmas coordenadas (pt) da fatura: logo na caixa 105x55 em (45,35), empresa em (45,100),
 * cliente em (435,100), via em y220, título em y238 e linha em y263.
 * O conteúdo do documento deve começar abaixo de BX_PDF_HEADER_BOTTOM (@page margin-top).
 *
 * $h: logo (data URI ou caminho), company_name, company_address, company_lines[] (Tel, E-mail,
 *     Contribuinte…), client_name, client_lines[] (morada, Tel…), copy_label, title.
 */
const BX_PDF_HEADER_BOTTOM = 275;   // pt: a tabela/corpo do documento começa aqui

function bx_pdf_header(\Dompdf\Dompdf $dompdf, array $h): void
{
    $logoFile = null;
    $logoW = $logoH = 0.0;
    $logo = (string) ($h['logo'] ?? '');
    if ($logo !== '' && preg_match('~^data:image/(png|jpe?g|gif);base64,(.+)$~i', $logo, $m)) {
        $logoFile = tempnam(sys_get_temp_dir(), 'bxlogo') . '.' . (strtolower($m[1]) === 'png' ? 'png' : (strtolower($m[1]) === 'gif' ? 'gif' : 'jpg'));
        file_put_contents($logoFile, base64_decode($m[2]));
        register_shutdown_function(static fn() => @unlink($logoFile));
    } elseif ($logo !== '' && is_file($logo)) {
        $logoFile = $logo;
    }
    if ($logoFile && ($size = @getimagesize($logoFile)) && $size[0] > 0 && $size[1] > 0) {
        $scale = min(105 / $size[0], 55 / $size[1]);   // cabe na caixa 105x55, sem deformar
        $logoW = $size[0] * $scale;
        $logoH = $size[1] * $scale;
    } else {
        $logoFile = null;
    }

    $grey = [0x70 / 255, 0x70 / 255, 0x70 / 255];
    $black = [0, 0, 0];
    $line9 = 9 * 1.156 + 3;    // linha de 9pt + margem de 3pt (como .blk>div)
    $line12 = 12 * 1.156 + 3;

    $companyName = mb_strtoupper(trim((string) ($h['company_name'] ?? '')) ?: '-');
    $companyAddress = trim((string) ($h['company_address'] ?? ''));
    $companyLines = array_values(array_filter(array_map('strval', $h['company_lines'] ?? []), 'strlen'));
    $clientName = trim((string) ($h['client_name'] ?? '')) ?: '-';
    $clientLines = array_values(array_filter(array_map('strval', $h['client_lines'] ?? []), 'strlen'));
    $copyLabel = (string) ($h['copy_label'] ?? 'Original');
    $title = (string) ($h['title'] ?? '');

    $dompdf->getCanvas()->page_script(static function (int $page, int $count, $canvas, $metrics) use (
        $logoFile, $logoW, $logoH, $grey, $black, $line9, $line12,
        $companyName, $companyAddress, $companyLines, $clientName, $clientLines, $copyLabel, $title
    ): void {
        $regular = $metrics->getFont('Helvetica', 'normal');
        $bold = $metrics->getFont('Helvetica', 'bold');

        // quebra por palavras (ou por vírgulas, como a morada da fatura) dentro de $width
        $wrap = static function (string $text, float $width, $font, float $size, bool $byComma = false) use ($metrics): array {
            if ($byComma) {
                // "Rua X, Bairro Y, Luanda": cada parte fica inteira; só quebra nas vírgulas
                $parts = array_values(array_filter(array_map('trim', explode(',', $text)), 'strlen'));
                $tokens = [];
                foreach ($parts as $i => $part) {
                    $tokens[] = $part . ($i < count($parts) - 1 ? ',' : '');
                }
            } else {
                $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
            }
            $lines = [];
            $cur = '';
            foreach ($tokens as $tok) {
                $try = $cur === '' ? $tok : $cur . ' ' . $tok;
                if ($cur !== '' && $metrics->getTextWidth($try, $font, $size) > $width) {
                    $lines[] = $cur;
                    $cur = $tok;
                } else {
                    $cur = $try;
                }
            }
            if ($cur !== '') {
                $lines[] = $cur;
            }
            return $lines ?: [''];
        };

        if ($logoFile) {
            $canvas->image($logoFile, 45, 35, $logoW, $logoH);
        }

        // EMPRESA (45,100)
        $y = 100.0;
        foreach ($wrap($companyName, 365, $bold, 12) as $l) {
            $canvas->text(45, $y, $l, $bold, 12, $black);
            $y += $line12;
        }
        if ($companyAddress !== '') {
            foreach ($wrap($companyAddress, 190, $regular, 9, true) as $l) {
                $canvas->text(45, $y, $l, $regular, 9, $grey);
                $y += 9 * 1.156;
            }
            $y += 3;
        }
        foreach ($companyLines as $l) {
            $canvas->text(45, $y, $l, $regular, 9, $grey);
            $y += $line9;
        }

        // CLIENTE (435,100)
        $cw = 115 - 0.278 * 9;
        $y = 100.0;
        foreach ($wrap($clientName, $cw, $bold, 12) as $l) {
            $canvas->text(435, $y, $l, $bold, 12, $black);
            $y += $line12;
        }
        foreach ($clientLines as $text) {
            foreach ($wrap($text, $cw, $regular, 9) as $l) {
                $canvas->text(435, $y, $l, $regular, 9, $grey);
                $y += 9 * 1.156;
            }
            $y += 3;
        }

        // VIA, TÍTULO E LINHA
        $canvas->text(45, 220, $copyLabel, $regular, 10, $grey);
        $canvas->text(45, 238, $title, $bold, 12, $black);
        $canvas->line(45, 263, 550, 263, [0x8C / 255, 0x8C / 255, 0x8C / 255], 1.5);
    });
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
