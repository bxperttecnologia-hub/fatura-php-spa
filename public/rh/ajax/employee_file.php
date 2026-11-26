<?php
/**
 * Serve um documento de funcionário (doc1/doc2: BI, contrato...) só a quem tem acesso ao módulo RH
 * da empresa a que o funcionário pertence. A pasta public/assets/docs/employees não é servida diretamente.
 *   GET /rh/ajax/employee_file.php?file=doc1_xxx.pdf
 */
require_once __DIR__ . '/../lib/rh_guard.php';   // sessão + permissão RH; define $company_id

$file = basename((string)($_GET['file'] ?? ''));
if (!preg_match('/^doc[12]_[A-Za-z0-9._-]+\.(pdf|png|jpe?g|webp)$/i', $file)) {
    rh_json(['success' => false, 'message' => 'Documento inválido.'], 404);
}

$stmt = $pdo->prepare('SELECT 1 FROM employees WHERE company_id = ? AND (doc1_url = ? OR doc2_url = ?) LIMIT 1');
$stmt->execute([$company_id, $file, $file]);
$path = dirname(__DIR__, 2) . '/assets/docs/employees/' . $file;
if (!$stmt->fetchColumn() || !is_file($path)) {
    rh_json(['success' => false, 'message' => 'Documento não encontrado.'], 404);
}

$types = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $file . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
