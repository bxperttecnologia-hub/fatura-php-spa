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

$id = $_POST['id'] ?? null;
$name = trim($_POST['name'] ?? '');
$parent_department_id = !empty($_POST['parent_department_id']) ? (int)$_POST['parent_department_id'] : null;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome do departamento é obrigatório.']);
    exit;
}

// Um departamento não pode ser pai de si mesmo, nem criar um ciclo direto (pai = filho imediato).
if ($id && $parent_department_id && (int)$id === $parent_department_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Um departamento não pode ser o seu próprio departamento-pai.']);
    exit;
}

try {
    if ($id) {
        // Confirma que o pai escolhido pertence à mesma empresa (evita ligar a departamento de outra empresa)
        if ($parent_department_id) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE id = ? AND company_id = ?');
            $chk->execute([$parent_department_id, $company_id]);
            if (!$chk->fetchColumn()) {
                throw new Exception('Departamento-pai inválido.');
            }
        }
        $stmt = $pdo->prepare('UPDATE departments SET name = ?, parent_department_id = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([$name, $parent_department_id, $id, $company_id]);
    } else {
        if ($parent_department_id) {
            $chk = $pdo->prepare('SELECT COUNT(*) FROM departments WHERE id = ? AND company_id = ?');
            $chk->execute([$parent_department_id, $company_id]);
            if (!$chk->fetchColumn()) {
                throw new Exception('Departamento-pai inválido.');
            }
        }
        $stmt = $pdo->prepare('INSERT INTO departments (company_id, name, parent_department_id) VALUES (?, ?, ?)');
        $stmt->execute([$company_id, $name, $parent_department_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
