<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
$id = (int)($_GET['id'] ?? 0);

if (!$company_id || !$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT ev.id, ev.status, ev.final_score, ev.template_id, ev.employee_id, e.name AS employee_name, e.position
    FROM evaluations ev
    JOIN employees e ON e.id = ev.employee_id
    WHERE ev.id = ? AND ev.company_id = ?
");
$stmt->execute([$id, $company_id]);
$evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluation) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Avaliação não encontrada.']);
    exit;
}

$stmtCrit = $pdo->prepare('SELECT id, label, weight FROM evaluation_criteria WHERE template_id = ? ORDER BY id ASC');
$stmtCrit->execute([$evaluation['template_id']]);
$criteria = $stmtCrit->fetchAll(PDO::FETCH_ASSOC);

$stmtAns = $pdo->prepare('SELECT criterion_id, score, comment FROM evaluation_answers WHERE evaluation_id = ?');
$stmtAns->execute([$id]);
$answersByCriterion = [];
foreach ($stmtAns->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $answersByCriterion[$a['criterion_id']] = $a;
}

foreach ($criteria as &$c) {
    $c['score'] = $answersByCriterion[$c['id']]['score'] ?? null;
    $c['comment'] = $answersByCriterion[$c['id']]['comment'] ?? '';
}
unset($c);

$evaluation['criteria'] = $criteria;

echo json_encode(['success' => true, 'data' => $evaluation]);
