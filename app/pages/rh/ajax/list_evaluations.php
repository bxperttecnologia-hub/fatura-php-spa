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

$sql = "SELECT ev.id, ev.status, ev.final_score, ev.employee_id, e.name AS employee_name,
               ev.evaluator_id, m.name AS evaluator_name, ev.template_id, t.name AS template_name,
               c.name AS cycle_name
        FROM evaluations ev
        JOIN employees e ON e.id = ev.employee_id
        LEFT JOIN employees m ON m.id = ev.evaluator_id
        JOIN evaluation_templates t ON t.id = ev.template_id
        JOIN evaluation_cycles c ON c.id = ev.cycle_id
        WHERE ev.company_id = ?";
$params = [$company_id];

if (!empty($_REQUEST['cycle_id'])) {
    $sql .= " AND ev.cycle_id = ?";
    $params[] = (int)$_REQUEST['cycle_id'];
}

// "minhas": só as avaliações onde o utilizador logado é o avaliador
// (assume que $_SESSION['user']['employee_id'] existe quando o utilizador
// também é um funcionário do RH — ajusta este mapeamento à tua sessão real
// se o campo tiver outro nome).
if (!empty($_REQUEST['minhas']) && !empty($_SESSION['user']['employee_id'])) {
    $sql .= " AND ev.evaluator_id = ?";
    $params[] = (int)$_SESSION['user']['employee_id'];
}

if (!empty($_REQUEST['employee_id'])) {
    $sql .= " AND ev.employee_id = ?";
    $params[] = (int)$_REQUEST['employee_id'];
}

$sql .= " ORDER BY ev.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
