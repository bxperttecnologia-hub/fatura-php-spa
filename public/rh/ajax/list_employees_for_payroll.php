<?php
require_once '../../../app/config/db.php';
session_start();

$company_id       = (int)($_SESSION['user']['company_id'] ?? 0);
$reference_month  = $_GET['mes'] ?? '';

if (!$company_id || !$reference_month) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mês de referência obrigatório.']);
    exit;
}

// Colaboradores ativos, com cargo associado (necessário para calcular a folha)
$stmt = $pdo->prepare("
    SELECT e.id, e.name, e.position, e.salary_base
    FROM employees e
    JOIN positions p ON p.name = e.position
    WHERE e.company_id = ? AND e.status = 'ativo'
    ORDER BY e.name ASC
");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Quem já tem folha lançada neste mês (para avisar e não duplicar por engano)
$stmtP = $pdo->prepare("
    SELECT employee_id, status FROM payroll
    WHERE company_id = ? AND reference_month = ?
");
$stmtP->execute([$company_id, $reference_month]);
$already = [];
foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $already[$row['employee_id']] = $row['status'];
}

$data = array_map(function ($e) use ($already) {
    return [
        'id'                => (int)$e['id'],
        'name'              => $e['name'],
        'position'          => $e['position'],
        'salary_base'       => (float)$e['salary_base'],
        'already_issued'    => isset($already[$e['id']]),
        'existing_status'   => $already[$e['id']] ?? null,
    ];
}, $employees);

echo json_encode(['success' => true, 'data' => $data]);
