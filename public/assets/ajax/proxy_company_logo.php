<?php
header('Access-Control-Allow-Origin: *');

$file = basename((string) ($_GET['file'] ?? ''));
if ($file === '' || $file === '.' || $file === '..') {
    http_response_code(400);
    exit('Logo inválido.');
}

$remoteUrl = 'https://app.bxpert.co.ao/assets/img/companies/' . rawurlencode($file);
$imageData = false;
$contentType = 'application/octet-stream';

if (function_exists('curl_init')) {
    $curl = curl_init($remoteUrl);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Bxpert invoice logo proxy',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $imageData = curl_exec($curl);
    $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: $contentType;
    $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($statusCode < 200 || $statusCode >= 300) {
        $imageData = false;
    }
} else {
    $imageData = @file_get_contents($remoteUrl);
}

if ($imageData === false || $imageData === '') {
    http_response_code(404);
    exit('Logo não encontrado.');
}

if (strpos($contentType, 'image/') !== 0) {
    $detectedType = function_exists('finfo_open')
        ? (new finfo(FILEINFO_MIME_TYPE))->buffer($imageData)
        : 'image/png';
    $contentType = strpos((string) $detectedType, 'image/') === 0
        ? $detectedType
        : 'image/png';
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=86400');
echo $imageData;
