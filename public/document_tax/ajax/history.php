<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$stmt = $pdo->prepare(
    'SELECT tax_type, rate, base_type, enabled, effective_from, effective_to, legal_validation_note, created_at
     FROM company_document_tax_rates
     WHERE company_id = ?
     ORDER BY tax_type, effective_from DESC
     LIMIT 40'
);
$stmt->execute([session_company_id()]);
echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
