<?php

/**
 * Fase 3, item 1 — Organograma (v1, apenas leitura).
 *
 * Devolve, em JSON hierárquico, todos os funcionários ATIVOS da empresa,
 * organizados por manager_id (raiz = funcionários sem manager_id).
 * É gerado a partir dos dados de employees.manager_id / department_id —
 * atualiza-se sozinho sempre que esses campos mudam, porque não há estado
 * guardado aqui: cada pedido recalcula a árvore.
 */

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada ou inválida.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.id, e.name, e.position, e.manager_id, e.department_id,
           d.name AS department_name, e.photo_url, e.phone, e.email
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    WHERE e.company_id = ? AND e.status = 'ativo'
    ORDER BY e.name ASC
");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Indexa por manager_id para montar a árvore em memória (evita N+1 queries)
$byManager = [];
$byId = [];
foreach ($employees as $emp) {
    $emp['children'] = [];
    $byId[$emp['id']] = $emp;
    $managerKey = $emp['manager_id'] ?: 0;
    $byManager[$managerKey][] = $emp['id'];
}

function buildNode(int $id, array &$byId, array &$byManager, array &$visited): array
{
    // Guarda de segurança: nunca deve acontecer com dados válidos (o
    // formulário de funcionário bloqueia auto-referência), mas evita que um
    // ciclo manager_id -> manager_id acidental derrube o endpoint.
    if (isset($visited[$id])) {
        $node = $byId[$id];
        $node['children'] = [];
        $node['_ciclo_detectado'] = true;
        return $node;
    }
    $visited[$id] = true;

    $node = $byId[$id];
    $childIds = $byManager[$id] ?? [];
    foreach ($childIds as $childId) {
        $node['children'][] = buildNode($childId, $byId, $byManager, $visited);
    }
    return $node;
}

// Raízes: funcionários sem manager_id, ou cujo manager_id aponta para
// alguém inativo/inexistente (evita "perder" o nó por causa de dados
// inconsistentes — melhor mostrar como raiz do que não mostrar).
$rootIds = [];
foreach ($employees as $emp) {
    $managerId = $emp['manager_id'];
    if (!$managerId || !isset($byId[$managerId])) {
        $rootIds[] = $emp['id'];
    }
}

$tree = [];
foreach ($rootIds as $rootId) {
    $visited = [];
    $tree[] = buildNode($rootId, $byId, $byManager, $visited);
}

echo json_encode(['success' => true, 'data' => $tree]);
