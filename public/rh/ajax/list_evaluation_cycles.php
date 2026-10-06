<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.period_start, c.period_end, c.status,
           (SELECT COUNT(*) FROM evaluations e WHERE e.cycle_id = c.id) AS total_evaluations,
           (SELECT COUNT(*) FROM evaluations e WHERE e.cycle_id = c.id AND e.status = 'concluida') AS concluidas
    FROM evaluation_cycles c
    WHERE c.company_id = ?
    ORDER BY c.period_start DESC
");
$stmt->execute([$company_id]);

echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
