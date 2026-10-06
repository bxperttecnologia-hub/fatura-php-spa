<?php
require_once '../../../app/config/db.php';
session_start();

$company_id = $_SESSION['user']['company_id'];
$term = $_GET['term'] ?? '';

// Fase 1: já usa employees.position_id como fonte de verdade (mais rápido,
// não depende de o texto do cargo bater certo). Mantém o fallback por TRIM()
// só para o caso raro de um funcionário ainda não migrado (position_id nulo) —
// ver migrations/fase1_migrate_positions.php.
$sql = "SELECT e.id, e.name, e.salary_base AS salary, e.position, p.name as position_name, p.suggested_salary, p.food_allowance, p.transport_allowance, p.vacation_subsidy_pct, p.thirteenth_subsidy_pct FROM employees as e
        LEFT JOIN positions as p ON (
            (e.position_id IS NOT NULL AND p.id = e.position_id)
            OR (e.position_id IS NULL AND p.company_id = e.company_id AND TRIM(p.name) = TRIM(e.position))
        )
        WHERE e.company_id = ? AND e.status = 'ativo' AND e.name LIKE ? 
        ORDER BY e.name ASC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute([$company_id, "%$term%"]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        'id' => $row['id'],
        'text' => $row['name'],
        'salary' => $row['salary'],
        'position' => $row['position'],
        // Com LEFT JOIN, um funcionário sem cargo correspondente em `positions`
        // vem com estes campos a NULL — normaliza para 0 para não quebrar o JS.
        'sub_suge' => $row['suggested_salary'] ?? 0,
        'sub_alim' => $row['food_allowance'] ?? 0,
        'sub_trans' => $row['transport_allowance'] ?? 0,
        'sub_ferias' => $row['vacation_subsidy_pct'] ?? 0,
        'sub_decimo' => $row['thirteenth_subsidy_pct'] ?? 0
    ];
}

echo json_encode($results);
