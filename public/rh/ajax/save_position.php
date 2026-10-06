<?php
require_once '../../../app/config/db.php';
session_start();

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) exit;

// trim() é essencial aqui: o nome do cargo é comparado por texto noutros
// pontos do sistema (search_employees, folha, ponto), e um espaço a mais
// no fim faz o funcionário "desaparecer" desses selects.
$name = trim($_POST['name'] ?? '');
$suggested_salary = $_POST['suggested_salary'] ?? 0;
$food_allowance = $_POST['food_allowance'] ?? 0;
$transport_allowance = $_POST['transport_allowance'] ?? 0;
$vacation_subsidy_pct = (int)($_POST['vacation_subsidy_pct'] ?? 0);
$thirteenth_subsidy_pct = (int)($_POST['thirteenth_subsidy_pct'] ?? 0);
$department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
$id = $_POST['id'] ?? null;

if ($name === '') {
    http_response_code(400);
    echo 'Nome do cargo é obrigatório.';
    exit;
}

if ($id) {
    $stmt = $pdo->prepare("UPDATE positions SET name = ?, suggested_salary = ?, food_allowance = ?, transport_allowance = ?, vacation_subsidy_pct = ?, thirteenth_subsidy_pct = ?, department_id = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$name, $suggested_salary, $food_allowance, $transport_allowance, $vacation_subsidy_pct, $thirteenth_subsidy_pct, $department_id, $id, $company_id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO positions (company_id, name, suggested_salary, food_allowance, transport_allowance, vacation_subsidy_pct, thirteenth_subsidy_pct, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$company_id, $name, $suggested_salary, $food_allowance, $transport_allowance, $vacation_subsidy_pct, $thirteenth_subsidy_pct, $department_id]);
}

echo 'ok';
