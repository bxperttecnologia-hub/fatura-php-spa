<?php

/**
 * Fase 4 — Cria automaticamente uma `evaluation` pendente para cada
 * funcionário ATIVO de um departamento (ou de todos, se department_id
 * vier vazio), com evaluator_id = manager_id (usa o organograma da Fase 3).
 *
 * POST: cycle_id, template_id, department_id (opcional, vazio = todos os departamentos)
 *
 * Idempotente por natureza do request: não gera duplicado para quem já
 * tem uma avaliação neste ciclo com este template.
 */

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
    exit;
}

$cycle_id = (int)($_POST['cycle_id'] ?? 0);
$template_id = (int)($_POST['template_id'] ?? 0);
$department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

if (!$cycle_id || !$template_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Escolhe o ciclo e o modelo de avaliação.']);
    exit;
}

// Confirma que ciclo e modelo pertencem a esta empresa
$stmtChk = $pdo->prepare('SELECT COUNT(*) FROM evaluation_cycles WHERE id = ? AND company_id = ?');
$stmtChk->execute([$cycle_id, $company_id]);
if (!$stmtChk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ciclo inválido.']);
    exit;
}
$stmtChk = $pdo->prepare('SELECT COUNT(*) FROM evaluation_templates WHERE id = ? AND company_id = ?');
$stmtChk->execute([$template_id, $company_id]);
if (!$stmtChk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Modelo inválido.']);
    exit;
}

$sql = "SELECT id, manager_id FROM employees WHERE company_id = ? AND status = 'ativo'";
$params = [$company_id];
if ($department_id) {
    $sql .= " AND department_id = ?";
    $params[] = $department_id;
}

$stmtEmp = $pdo->prepare($sql);
$stmtEmp->execute($params);
$employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

$stmtExists = $pdo->prepare('SELECT COUNT(*) FROM evaluations WHERE cycle_id = ? AND employee_id = ? AND template_id = ?');
$stmtInsert = $pdo->prepare("
    INSERT INTO evaluations (company_id, cycle_id, employee_id, evaluator_id, template_id, status)
    VALUES (?, ?, ?, ?, ?, 'pendente')
");

$created = 0;
$skippedNoManager = 0;
$skippedExists = 0;

foreach ($employees as $emp) {
    if (!$emp['manager_id']) {
        // Sem chefia direta definida — não há quem avalie. Fica de fora
        // até o organograma (Fase 3) ter esse funcionário associado a um manager.
        $skippedNoManager++;
        continue;
    }

    $stmtExists->execute([$cycle_id, $emp['id'], $template_id]);
    if ((int)$stmtExists->fetchColumn() > 0) {
        $skippedExists++;
        continue;
    }

    $stmtInsert->execute([$company_id, $cycle_id, $emp['id'], $emp['manager_id'], $template_id]);
    $created++;
}

echo json_encode([
    'success' => true,
    'created' => $created,
    'skipped_no_manager' => $skippedNoManager,
    'skipped_already_exists' => $skippedExists,
]);
