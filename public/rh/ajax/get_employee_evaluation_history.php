<?php

/**
 * Histórico de avaliações de um funcionário — pensado para ser chamado a
 * partir da ficha do funcionário (employees.php, não incluído neste
 * pacote): basta um `$.getJSON('rh/ajax/get_employee_evaluation_history.php?employee_id=' + id, ...)`
 * e renderizar `data` numa tabela/timeline na aba de RH da ficha.
 */

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
$employee_id = (int)($_GET['employee_id'] ?? 0);

if (!$company_id || !$employee_id) {
    http_response_code(400);
    echo json_encode(['data' => []]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT ev.id, ev.status, ev.final_score, c.name AS cycle_name, c.period_start, c.period_end,
           t.name AS template_name, m.name AS evaluator_name
    FROM evaluations ev
    JOIN evaluation_cycles c ON c.id = ev.cycle_id
    JOIN evaluation_templates t ON t.id = ev.template_id
    LEFT JOIN employees m ON m.id = ev.evaluator_id
    WHERE ev.employee_id = ? AND ev.company_id = ?
    ORDER BY c.period_start DESC
");
$stmt->execute([$employee_id, $company_id]);

echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
