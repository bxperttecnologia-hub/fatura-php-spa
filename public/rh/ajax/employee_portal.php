<?php
/**
 * Acesso do funcionário ao portal (/portal.php?t=...).
 *  POST action=generate employee_id → gera um novo link (o anterior deixa de funcionar).
 *  POST action=revoke   employee_id → desactiva o acesso.
 * Só se guarda o SHA-256 do token: o link completo só é mostrado no momento em que é gerado.
 */
require_once __DIR__ . '/../lib/rh_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rh_json(['success' => false, 'message' => 'Método não permitido.'], 405);
}
$employeeId = (int)($_POST['employee_id'] ?? 0);
$stmt = $pdo->prepare("SELECT id, name, email, status FROM employees WHERE id = ? AND company_id = ?");
$stmt->execute([$employeeId, $company_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    rh_json(['success' => false, 'message' => 'Funcionário não encontrado.'], 404);
}
$action = (string)($_POST['action'] ?? '');
if ($action === 'revoke') {
    $pdo->prepare('UPDATE employees SET portal_token = NULL WHERE id = ? AND company_id = ?')->execute([$employeeId, $company_id]);
    rh_json(['success' => true, 'message' => "Acesso ao portal de {$emp['name']} desactivado."]);
}
if ($action !== 'generate') {
    rh_json(['success' => false, 'message' => 'Acção inválida.'], 422);
}
if ($emp['status'] !== 'ativo') {
    rh_json(['success' => false, 'message' => 'Só funcionários activos podem ter acesso ao portal.'], 422);
}
$token = bin2hex(random_bytes(24));
$pdo->prepare('UPDATE employees SET portal_token = ? WHERE id = ? AND company_id = ?')->execute([hash('sha256', $token), $employeeId, $company_id]);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
rh_json([
    'success' => true,
    'message' => 'Link do portal gerado. Qualquer link anterior deixou de funcionar.',
    'url'     => ($https ? 'https' : 'http') . "://$host/portal.php?t=$token",
    'email'   => $emp['email'],
]);
