<?php

/**
 * QR code da faturação eletrónica (especificação da AGT):
 * https://portaldoparceiro.minfin.gov.ao/doc-agt/faturacao-electronica/1/qrcode.html
 *
 * O QR leva à consulta do documento no Portal do Contribuinte:
 *   <AGT_QR_BASE_URL_*>?emissor=<NIF do emissor>&document=<documentNo>
 * com cada espaço do documentNo substituído por %20, correção de erros M e PNG.
 * A URL base vem do .env: AGT_QR_BASE_URL_TEST (homologação) ou AGT_QR_BASE_URL_PRODUCTION.
 */

require_once __DIR__ . '/env.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** URL de consulta na AGT, ou null se o documento não tiver numeração AGT ("FT <série>/<n>"). */
function agt_qr_url(string $issuerNif, ?string $documentNo): ?string
{
    $issuerNif = preg_replace('/\s+/', '', $issuerNif);
    if ($issuerNif === '' || !preg_match('~^[A-Z]{2} [A-Za-z0-9]{3,60}/\d+$~', (string) $documentNo)) {
        return null;
    }

    $production = function_exists('agt_environment') && agt_environment() === 'production';
    $key = $production ? 'AGT_QR_BASE_URL_PRODUCTION' : 'AGT_QR_BASE_URL_TEST';
    $base = trim((string) bx_env($key));
    if ($base === '') {
        error_log("QR AGT: {$key} não está configurado no .env.");
        return null;
    }

    return $base . (str_contains($base, '?') ? '&' : '?')
        . 'emissor=' . rawurlencode($issuerNif)
        . '&document=' . str_replace(' ', '%20', (string) $documentNo);
}

/** PNG (data URI) do QR, segundo a especificação da AGT (byte/UTF-8, correção de erros M). */
function agt_qr_image(string $url): string
{
    $options = new QROptions([
        'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'     => QRCode::ECC_M,
        'scale'        => 7,
        'addQuietzone' => true,
        'quietzoneSize' => 2,
        'imageBase64'  => true,
    ]);

    return (new QRCode($options))->render($url);
}
