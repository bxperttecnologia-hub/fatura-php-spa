<?php

/**
 * O avaliador pontua cada critério (0 a 100, por exemplo — a escala fica
 * ao critério da empresa) e o sistema calcula final_score ponderado pelos
 * pesos definidos no modelo.
 *
 * POST: evaluation_id, answers (JSON array de {criterion_id, score, comment}), finalizar (0/1)
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

$evaluation_id = (int)($_POST['evaluation_id'] ?? 0);
$answers = json_decode($_POST['answers'] ?? '[]', true);
$finalizar = !empty($_POST['finalizar']);

if (!$evaluation_id || !is_array($answers)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, template_id FROM evaluations WHERE id = ? AND company_id = ? FOR UPDATE');
    $stmt->execute([$evaluation_id, $company_id]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evaluation) {
        throw new Exception('Avaliação não encontrada.');
    }

    $stmtCrit = $pdo->prepare('SELECT id, weight FROM evaluation_criteria WHERE template_id = ?');
    $stmtCrit->execute([$evaluation['template_id']]);
    $weightsByCriterion = [];
    foreach ($stmtCrit->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $weightsByCriterion[$c['id']] = (float)$c['weight'];
    }

    // evaluation_answers não garante UNIQUE(evaluation_id, criterion_id) no
    // schema original da Fase 4 — por isso o upsert é feito manualmente
    // (SELECT + UPDATE/INSERT) em vez de confiar num ON DUPLICATE KEY.
    $weightedSum = 0;
    $totalWeight = 0;

    foreach ($answers as $a) {
        $criterionId = (int)($a['criterion_id'] ?? 0);
        $score = isset($a['score']) ? (float)$a['score'] : null;
        $comment = trim($a['comment'] ?? '');

        if (!$criterionId || $score === null || !isset($weightsByCriterion[$criterionId])) {
            continue;
        }

        // Sem UNIQUE key garantida, faz upsert manual (mais lento, mas seguro
        // com o schema tal como foi definido na Fase 4).
        $stmtCheck = $pdo->prepare('SELECT id FROM evaluation_answers WHERE evaluation_id = ? AND criterion_id = ?');
        $stmtCheck->execute([$evaluation_id, $criterionId]);
        $existingAnswerId = $stmtCheck->fetchColumn();

        if ($existingAnswerId) {
            $pdo->prepare('UPDATE evaluation_answers SET score = ?, comment = ? WHERE id = ?')
                ->execute([$score, $comment, $existingAnswerId]);
        } else {
            $pdo->prepare('INSERT INTO evaluation_answers (evaluation_id, criterion_id, score, comment) VALUES (?, ?, ?, ?)')
                ->execute([$evaluation_id, $criterionId, $score, $comment]);
        }

        $weight = $weightsByCriterion[$criterionId];
        $weightedSum += $score * $weight;
        $totalWeight += $weight;
    }

    $finalScore = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;

    $newStatus = $finalizar ? 'concluida' : 'em_curso';
    $pdo->prepare('UPDATE evaluations SET status = ?, final_score = ? WHERE id = ?')
        ->execute([$newStatus, $finalScore, $evaluation_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'final_score' => $finalScore, 'status' => $newStatus]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
