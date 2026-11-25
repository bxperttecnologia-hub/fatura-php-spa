<?php
/** Aprova / rejeita / repõe como pendente um período de férias ou licença. POST id, status */
require_once __DIR__ . '/../lib/rh_guard.php';
require_once __DIR__ . '/../lib/vacation_lib.php';

$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '');
if (!$id || !in_array($status, ['Aprovado', 'Pendente', 'Rejeitado'], true)) {
    rh_json(['success' => false, 'message' => 'Dados insuficientes para actualizar o estado.'], 422);
}
$stmt = $pdo->prepare('SELECT * FROM vacations WHERE id = ? AND company_id = ?');
$stmt->execute([$id, $company_id]);
$v = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$v) {
    rh_json(['success' => false, 'message' => 'Período não encontrado.'], 404);
}
if ($status === 'Aprovado' && rh_vacation_overlaps($pdo, $company_id, (int)$v['employee_id'], $v['start_date'], $v['end_date'], $id)) {
    rh_json(['success' => false, 'message' => 'Não é possível aprovar: sobrepõe-se a outro período do mesmo funcionário.'], 422);
}
$pdo->prepare('UPDATE vacations SET status = ?, decided_at = ?, decided_by = ?, working_days = COALESCE(working_days, ?) WHERE id = ? AND company_id = ?')
    ->execute([$status, $status === 'Pendente' ? null : date('Y-m-d H:i:s'), $status === 'Pendente' ? null : ($rh_user_id ?: null),
               rh_working_days($pdo, $company_id, $v['start_date'], $v['end_date']), $id, $company_id]);
rh_json(['success' => true, 'message' => ['Aprovado' => 'Período aprovado.', 'Rejeitado' => 'Período rejeitado.', 'Pendente' => 'Período reposto como pendente.'][$status]]);
