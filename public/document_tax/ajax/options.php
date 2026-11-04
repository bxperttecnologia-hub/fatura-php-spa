<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
header('Content-Type: application/json; charset=utf-8');

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Data de referência inválida.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT tax_type, rate, base_type, enabled
     FROM company_document_tax_rates
     WHERE company_id = ?
       AND effective_from <= ?
       AND (effective_to IS NULL OR effective_to >= ?)
     ORDER BY effective_from DESC'
);
$stmt->execute([session_company_id(), $date, $date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$seen = [];
$options = [
    'withholding_6_5' => null,
    'ipu_rent' => null,
];
// Sem regra própria da empresa, ficam as taxas legais por omissão (activas).
foreach (array_keys($options) as $type) {
    $default = bx_document_tax_default_configuration($type);
    $options[$type] = [
        'rate' => (float)$default['rate'],
        'base_type' => $default['base_type'],
        'enabled' => true,
        'configured' => false,
    ];
}
foreach ($rows as $row) {
    $type = (string)$row['tax_type'];
    if (!array_key_exists($type, $options) || isset($seen[$type])) {
        continue;
    }
    $seen[$type] = true;
    $options[$type] = [
        'rate' => (float)$row['rate'],
        'base_type' => (string)$row['base_type'],
        'enabled' => (bool)$row['enabled'],
        'configured' => true,
    ];
}

echo json_encode(['success' => true, 'date' => $date, 'options' => $options]);
