<?php
require_once '../../../app/config/db.php';
session_start();

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.id, d.name, d.parent_department_id, p.name AS parent_name,
           (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.company_id = d.company_id) AS employee_count
    FROM departments d
    LEFT JOIN departments p ON p.id = d.parent_department_id
    WHERE d.company_id = ?
    ORDER BY d.name ASC
");
$stmt->execute([$company_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['data' => $data]);
