<?php
require_once '../../../app/config/db.php';
require_once __DIR__ . '/../lib/rh_helpers.php';
session_start();
$company_id = $_SESSION['user']['company_id'] ?? null;
$id = $_POST['id'] ?? null;
$employee_id = $_POST['employee_id'];

$date = $_POST['date'];
$type = $_POST['type'];
$justification = $_POST['justification'];

// Fase 2: um feriado nacional nunca deve ser lançado como 'falta' — o
// funcionário não é obrigado a trabalhar nesse dia, logo não é uma ausência.
if ($type === 'falta' && $company_id && rh_is_holiday($pdo, (int)$company_id, $date)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Esta data é um feriado nacional — não pode ser lançada como falta.']);
    exit;
}

if ($id) {
    $stmt = $pdo->prepare("
        UPDATE attendance SET employee_id=?, company_id=?, date=?, type=?, justification=? WHERE id=?
    ");
    $stmt->execute([$employee_id, $company_id, $date, $type, $justification, $id]);
} else {
    $stmt = $pdo->prepare("
        INSERT INTO attendance (employee_id, company_id, date, type, justification)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$employee_id, $company_id, $date, $type, $justification]);
}

echo json_encode(['success' => true]);
