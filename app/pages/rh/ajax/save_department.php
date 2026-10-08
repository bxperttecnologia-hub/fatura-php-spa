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
$id = $id !== null && $id !== '' ? (int)$id : null;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome do departamento é obrigatório.']);
    exit;
}

try {
    if ($id !== null) {
        $current = $pdo->prepare('SELECT id FROM departments WHERE id = ? AND company_id = ?');
        $current->execute([$id, $company_id]);
        if (!$current->fetchColumn()) {
            throw new Exception('Departamento não encontrado.');
        }
    }

    if ($parent_department_id !== null) {
        $parent = $pdo->prepare('SELECT id, parent_department_id FROM departments WHERE id = ? AND company_id = ?');
        $parent->execute([$parent_department_id, $company_id]);
        $ancestor = $parent->fetch(PDO::FETCH_ASSOC);
        if (!$ancestor) {
            throw new Exception('Departamento-pai inválido.');
        }

        $visited = [];
        while ($ancestor) {
            $ancestorId = (int)$ancestor['id'];
            if ($id !== null && $ancestorId === $id) {
                throw new Exception('Não é possível mover um departamento para dentro de si ou de um dos seus subdepartamentos.');
            }
            if (isset($visited[$ancestorId])) {
                throw new Exception('A hierarquia existente contém um ciclo; corrija-a antes de continuar.');
            }
            $visited[$ancestorId] = true;
            $nextParentId = (int)($ancestor['parent_department_id'] ?? 0);
            if (!$nextParentId) {
                break;
            }
            $parent->execute([$nextParentId, $company_id]);
            $ancestor = $parent->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($id !== null) {
        $stmt = $pdo->prepare('UPDATE departments SET name = ?, parent_department_id = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([$name, $parent_department_id, $id, $company_id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO departments (company_id, name, parent_department_id) VALUES (?, ?, ?)');
        $stmt->execute([$company_id, $name, $parent_department_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
