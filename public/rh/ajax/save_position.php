<?php
require_once __DIR__ . '/../lib/rh_guard.php'; // sessão + permissão do módulo RH
require_once '../../../app/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) exit;

// trim() é essencial aqui: o nome do cargo é comparado por texto noutros
// pontos do sistema (search_employees, folha, ponto), e um espaço a mais
// no fim faz o funcionário "desaparecer" desses selects.
$name = trim($_POST['name'] ?? '');
$suggested_salary = $_POST['suggested_salary'] ?? 0;
$food_allowance = $_POST['food_allowance'] ?? 0;
$transport_allowance = $_POST['transport_allowance'] ?? 0;
// Percentagem livre (0–100, até 2 casas decimais); aceita vírgula ou ponto.
$pct = static fn($v) => round(max(0, min(100, (float)str_replace(',', '.', (string)$v))), 2);
$vacation_subsidy_pct = $pct($_POST['vacation_subsidy_pct'] ?? 0);
$thirteenth_subsidy_pct = $pct($_POST['thirteenth_subsidy_pct'] ?? 0);
$rawDepartment = $_POST['department_id'] ?? '';
$department_id = ($rawDepartment !== '__other__' && !empty($rawDepartment)) ? (int)$rawDepartment : null;
$id = $_POST['id'] ?? null;

// Opção "Outros": cria (ou reutiliza, pelo nome) o departamento indicado.
if ($rawDepartment === '__other__') {
    $departmentOther = mb_substr(trim((string)($_POST['department_other'] ?? '')), 0, 150);
    if ($departmentOther === '') {
        http_response_code(400);
        echo 'Indique o nome do departamento.';
        exit;
    }
    $stmtDep = $pdo->prepare('SELECT id FROM departments WHERE company_id = ? AND TRIM(name) = TRIM(?) LIMIT 1');
    $stmtDep->execute([$company_id, $departmentOther]);
    $foundDepartment = $stmtDep->fetchColumn();
    if ($foundDepartment) {
        $department_id = (int)$foundDepartment;
    } else {
        $pdo->prepare('INSERT INTO departments (company_id, name) VALUES (?, ?)')->execute([$company_id, $departmentOther]);
        $department_id = (int)$pdo->lastInsertId();
    }
}

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
