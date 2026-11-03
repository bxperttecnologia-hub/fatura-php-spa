<?php

/**
 * QR code da faturação eletrónica, obtido no gerador externo.
 * Devolve uma data URI PNG ou null para documentos sem numeração AGT
 * e quando o gerador não responde.
 */
function agt_fetch_qr_image(string $issuerNif, ?string $reference): ?string
{
    if (!preg_match('~^[A-Z]{2} [A-Za-z0-9]{3,60}/\d+$~', (string) $reference)
        || trim($issuerNif) === '') {
        return null;
    }

    $url = 'https://fe.silicaerp.com/api/qrcode?nif=' . rawurlencode(trim($issuerNif))
        . '&documento=' . rawurlencode((string) $reference);
    $ch = curl_init($url);
    if ($ch === false) {
        error_log("QR AGT: não foi possível iniciar o pedido para {$reference}.");
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
    ]);
    $png = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($png === false || $status !== 200 || stripos($type, 'image/png') !== 0) {
        error_log("QR AGT: gerador sem resposta válida para {$reference} (HTTP {$status}; {$error}).");
        return null;
    }

    return 'data:image/png;base64,' . base64_encode($png);
}
