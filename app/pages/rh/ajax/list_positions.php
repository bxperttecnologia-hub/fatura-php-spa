<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');
$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$company_id) {
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare("SELECT p.id, p.name, p.suggested_salary, p.food_allowance, p.transport_allowance, p.vacation_subsidy_pct, p.thirteenth_subsidy_pct, p.department_id, d.name AS department_name
                       FROM positions p
                       LEFT JOIN departments d ON d.id = p.department_id
                       WHERE p.company_id = ?");
$stmt->execute([$company_id]);

$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['data' => $positions]);
