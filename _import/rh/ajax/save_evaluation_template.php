<?php

/**
 * Recebe: name, id (opcional), criteria (JSON array de {label, weight}, id opcional por critério).
 * Substitui sempre TODO o conjunto de critérios do modelo pelo enviado
 * (mais simples de raciocinar do que fazer diffs) — se o modelo já tiver
 * avaliações concluídas associadas a critérios antigos, essas respostas
 * ficam registadas (evaluation_answers guarda o criterion_id na altura),
 * mas o modelo passa a refletir só os critérios atuais.
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

$id = $_POST['id'] ?? null;
$name = trim($_POST['name'] ?? '');
$criteriaJson = $_POST['criteria'] ?? '[]';
$criteria = json_decode($criteriaJson, true);

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nome do modelo é obrigatório.']);
    exit;
}

if (!is_array($criteria) || count($criteria) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Adiciona pelo menos um critério de avaliação.']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($id) {
        $stmtChk = $pdo->prepare('SELECT COUNT(*) FROM evaluation_templates WHERE id = ? AND company_id = ?');
        $stmtChk->execute([$id, $company_id]);
        if (!$stmtChk->fetchColumn()) {
            throw new Exception('Modelo não encontrado.');
        }
        $stmt = $pdo->prepare('UPDATE evaluation_templates SET name = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([$name, $id, $company_id]);
        $templateId = $id;

        // Substitui os critérios (ver nota no topo do ficheiro)
        $pdo->prepare('DELETE FROM evaluation_criteria WHERE template_id = ?')->execute([$templateId]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO evaluation_templates (company_id, name) VALUES (?, ?)');
        $stmt->execute([$company_id, $name]);
        $templateId = $pdo->lastInsertId();
    }

    $insertCrit = $pdo->prepare('INSERT INTO evaluation_criteria (template_id, label, weight) VALUES (?, ?, ?)');
    foreach ($criteria as $c) {
        $label = trim($c['label'] ?? '');
        $weight = (float)($c['weight'] ?? 1);
        if ($label === '') continue;
        $insertCrit->execute([$templateId, $label, $weight]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $templateId]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
