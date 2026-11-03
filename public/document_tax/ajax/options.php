<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/config/db.php';
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
$options = [
    'withholding_6_5' => ['rate' => 6.5, 'base_type' => null, 'enabled' => false, 'configured' => false],
    'ipu_rent' => ['rate' => 15.0, 'base_type' => null, 'enabled' => false, 'configured' => false],
];
foreach ($rows as $row) {
    $type = (string)$row['tax_type'];
    if (!isset($options[$type])) {
        continue;
    }
    $options[$type] = [
        'rate' => (float)$row['rate'],
        'base_type' => (string)$row['base_type'],
        'enabled' => (bool)$row['enabled'],
        'configured' => true,
    ];
}

echo json_encode(['success' => true, 'date' => $date, 'options' => $options]);
