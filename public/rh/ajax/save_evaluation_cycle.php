<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$period_start = $_POST['period_start'] ?? '';
$period_end = $_POST['period_end'] ?? '';

if ($name === '' || !$period_start || !$period_end) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preenche nome, início e fim do ciclo.']);
    exit;
}

if (strtotime($period_end) < strtotime($period_start)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'O fim do ciclo não pode ser anterior ao início.']);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO evaluation_cycles (company_id, name, period_start, period_end) VALUES (?, ?, ?, ?)');
$stmt->execute([$company_id, $name, $period_start, $period_end]);

echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
