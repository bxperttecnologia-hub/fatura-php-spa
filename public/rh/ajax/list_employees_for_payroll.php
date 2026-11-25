<?php
/** Funcionários activos para lançar folha num mês, com indicação de quem já tem folha. GET mes=YYYY-MM */
require_once __DIR__ . '/../lib/rh_guard.php';

$month = (string)($_GET['mes'] ?? '');
if (!rh_valid_month($month)) {
    rh_json(['success' => false, 'message' => 'Mês de referência obrigatório.'], 400);
}
// Cargo por position_id (o nome do cargo em texto pode estar vazio: o cargo já não é obrigatório)
$stmt = $pdo->prepare("SELECT e.id, e.name, COALESCE(p.name, NULLIF(e.position, '')) AS position, e.salary_base, d.name AS department
                       FROM employees e
                       LEFT JOIN positions p ON p.id = e.position_id AND p.company_id = e.company_id
                       LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
                       WHERE e.company_id = ? AND e.status = 'ativo' AND (e.admission_date IS NULL OR e.admission_date <= LAST_DAY(?))
                       ORDER BY e.name");
$stmt->execute([$company_id, "$month-01"]);
$stmtP = $pdo->prepare('SELECT employee_id, status FROM payroll WHERE company_id = ? AND reference_month = ?');
$stmtP->execute([$company_id, $month]);
$already = $stmtP->fetchAll(PDO::FETCH_KEY_PAIR);

rh_json(['success' => true, 'data' => array_map(fn($e) => [
    'id'              => (int)$e['id'],
    'name'            => $e['name'],
    'position'        => $e['position'],
    'department'      => $e['department'],
    'salary_base'     => (float)$e['salary_base'],
    'already_issued'  => isset($already[$e['id']]),
    'existing_status' => $already[$e['id']] ?? null,
], $stmt->fetchAll(PDO::FETCH_ASSOC))]);
