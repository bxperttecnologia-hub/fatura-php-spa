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

$stmt = $pdo->prepare('SELECT id, name FROM evaluation_templates WHERE company_id = ? ORDER BY name ASC');
$stmt->execute([$company_id]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtCrit = $pdo->prepare('SELECT id, label, weight FROM evaluation_criteria WHERE template_id = ? ORDER BY id ASC');
foreach ($templates as &$tpl) {
    $stmtCrit->execute([$tpl['id']]);
    $tpl['criteria'] = $stmtCrit->fetchAll(PDO::FETCH_ASSOC);
}
unset($tpl);

echo json_encode(['data' => $templates]);
