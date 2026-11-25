<?php
/** Elimina um registo de ponto (da empresa da sessão; mês não fechado). */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/payroll_engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rh_json(['success' => false, 'message' => 'Método não permitido.'], 405);
}
$stmt = $pdo->prepare('SELECT date FROM attendance WHERE id = ? AND company_id = ?');
$stmt->execute([(int)($_POST['id'] ?? 0), $company_id]);
$date = $stmt->fetchColumn();
if (!$date) {
    rh_json(['success' => false, 'message' => 'Registo não encontrado.'], 404);
}
if (rh_period_is_closed($pdo, $company_id, substr($date, 0, 7))) {
    rh_json(['success' => false, 'message' => 'O mês está fechado. Reabra o período para alterar o ponto.'], 422);
}
$pdo->prepare('DELETE FROM attendance WHERE id = ? AND company_id = ?')->execute([(int)$_POST['id'], $company_id]);
rh_json(['success' => true, 'message' => 'Registo eliminado.']);
