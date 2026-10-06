<?php
require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
$id = (int)($_POST['id'] ?? 0);

if (!$company_id || !$id) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$stmtUse = $pdo->prepare('SELECT COUNT(*) FROM evaluations WHERE template_id = ? AND company_id = ?');
$stmtUse->execute([$id, $company_id]);
if ((int)$stmtUse->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Não é possível eliminar: há avaliações já geradas com este modelo.']);
    exit;
}

$stmt = $pdo->prepare('DELETE FROM evaluation_templates WHERE id = ? AND company_id = ?');
$ok = $stmt->execute([$id, $company_id]);

echo json_encode(['success' => (bool)$ok]);
