<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

// Proxy para GeoNames (evita erro de certificado/mixed content no browser)

$username = 'israelsouza';
$url = "https://api.geonames.org/countryInfoJSON?username={$username}";

$opts = [
  'ssl' => [
    'verify_peer' => false,
    'verify_peer_name' => false,
  ],
];

try {
  $context = stream_context_create($opts);
  $raw = file_get_contents($url, false, $context);
  if ($raw === false) {
    throw new Exception('Falha ao consultar GeoNames.');
  }
  echo $raw;
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
