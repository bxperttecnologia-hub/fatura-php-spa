<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

$username = 'israelsouza';
$geonameId = isset($_GET['geonameId']) ? (int)$_GET['geonameId'] : 0;
if (!$geonameId) {
  echo json_encode(['error' => 'geonameId é obrigatório']);
  exit;
}

$url = "https://api.geonames.org/childrenJSON?geonameId={$geonameId}&username={$username}";

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
