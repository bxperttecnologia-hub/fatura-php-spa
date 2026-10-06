<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
$id = (int)($_POST['id'] ?? 0);

if (!$company_id || !$id) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

// Bloqueia se houver funcionários ou departamentos-filho vinculados.
$stmtEmp = $pdo->prepare('SELECT COUNT(*) FROM employees WHERE department_id = ? AND company_id = ?');
$stmtEmp->execute([$id, $company_id]);
if ((int)$stmtEmp->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Não é possível eliminar: há funcionários vinculados a este departamento.']);
    exit;
}

$stmtChild = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE parent_department_id = ? AND company_id = ?');
$stmtChild->execute([$id, $company_id]);
if ((int)$stmtChild->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Não é possível eliminar: existem sub-departamentos ligados a este.']);
    exit;
}

$stmtPos = $pdo->prepare('SELECT COUNT(*) FROM positions WHERE department_id = ? AND company_id = ?');
$stmtPos->execute([$id, $company_id]);
if ((int)$stmtPos->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Não é possível eliminar: há cargos vinculados a este departamento.']);
    exit;
}

$stmt = $pdo->prepare('DELETE FROM departments WHERE id = ? AND company_id = ?');
$ok = $stmt->execute([$id, $company_id]);

echo json_encode(['success' => (bool)$ok]);
