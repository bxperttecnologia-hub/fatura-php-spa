<?php
require_once '../../../app/config/db.php';
session_start();

$company_id = $_SESSION['user']['company_id'];

$sql = "SELECT p.*, e.name AS employee_name, e.iban
        FROM payroll p
        JOIN employees e ON e.id = p.employee_id";

$conditions = ["p.company_id = ?"];
$params = [$company_id];

if (!empty($_REQUEST['mes'])) {
    $conditions[] = "p.reference_month = ?";
    $params[] = $_REQUEST['mes'];
}

$sql .= " WHERE " . implode(' AND ', $conditions);
$sql .= " ORDER BY p.reference_month DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['data' => $dados]);

