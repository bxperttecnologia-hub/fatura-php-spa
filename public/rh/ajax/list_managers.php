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

// exclude_id: ao editar um funcionário, não o mostra na própria lista de possíveis chefias
$excludeId = !empty($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;

$sql = "SELECT id, name, position FROM employees WHERE company_id = ? AND status = 'ativo'";
$params = [$company_id];

if ($excludeId) {
    $sql .= " AND id <> ?";
    $params[] = $excludeId;
}

$sql .= " ORDER BY name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
