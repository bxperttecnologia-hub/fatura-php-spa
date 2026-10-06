<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$guideId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$guideId){
  http_response_code(422);
  echo json_encode(['error' => 'Guia inválida.']);
  exit;
}

$sql = "
SELECT
  g.id,
  g.series,
  g.document_date,
  g.cargo_date,
  g.final_total,
  g.currency,
  c.name AS client_name,
  c.email AS client_email,
  comp.name AS company_name,
  comp.address AS company_address,
  comp.city AS company_city,
  comp.country AS company_country,
  comp.phone AS company_phone,
  cr.symbol AS money_symbol,
  cr.position AS money_position
FROM guides g
JOIN contact c      ON c.id = g.contact_id
JOIN companies comp ON comp.id = c.company_id
LEFT JOIN currencies cr ON cr.iso_code COLLATE utf8mb4_general_ci = g.currency COLLATE utf8mb4_general_ci
WHERE g.id = :id
LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $guideId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$row){
  http_response_code(404);
  echo json_encode(['error' => 'Guia não encontrada.']);
  exit;
}

echo json_encode(['success' => true, 'data' => $row]);
