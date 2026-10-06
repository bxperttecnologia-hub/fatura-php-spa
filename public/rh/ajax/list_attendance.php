<?php
session_start();

require_once '../../../app/config/db.php';

$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$company_id) {
    echo json_encode([
        'data' => [],
        'error' => 'Empresa não definida.'
    ]);
    exit;
}

$query = "
    SELECT 
        a.*, 
        e.name AS employee_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.company_id = ?
";

$params = [$company_id];

if (!empty($_REQUEST['mes'])) {
    $query .= " AND DATE_FORMAT(a.date, '%Y-%m') = ?";
    $params[] = $_REQUEST['mes'];
}

if (!empty($_REQUEST['funcionario'])) {
    $query .= " AND a.employee_id = ?";
    $params[] = $_REQUEST['funcionario'];
}

$query .= " ORDER BY a.date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'data' => $data
]);
