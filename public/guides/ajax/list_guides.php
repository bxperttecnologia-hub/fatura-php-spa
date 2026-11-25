<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php'; // exige sessão iniciada (401/403 em JSON)
require_once '../../../app/config/db.php';

header('Content-Type: application/json');

$companyId = session_company_id();
$sql = "SELECT g.*, c.name AS client_name FROM guides g
        LEFT JOIN contact c ON c.id = g.contact_id
        WHERE g.company_id = :company
        ORDER BY g.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':company' => $companyId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach($rows as $g) {
    $data[] = [
        'id' => $g['id'],
        'document_number' => trim(($g['series'] ?? '') . ' ' . ($g['number'] ?? $g['id'])),
        'client_name' => htmlspecialchars($g['client_name'] ?? '-'),
        'vehicle_plate' => htmlspecialchars($g['vehicle_plate']),
        'cargo_date' => date('d/m/Y', strtotime($g['cargo_date'])),
        'cargo_date_iso' => $g['cargo_date'],
        'cargo_address' => $g['cargo_address'],
        'delivery_address' => $g['delivery_address'],
        'transport_status' => $g['transport_status'] ?? null,
        'status' => $g['status'] ?? null,
        'final_total' => '<b>' . number_format($g['final_total'],2,',','.') . ' ' . htmlspecialchars($g['currency']) . '</b>',
        'status' => '<span class="badge bg-secondary">' . htmlspecialchars(str_replace('_', ' ', strtoupper((string)($g['transport_status'] ?? $g['status'] ?? 'RASCUNHO')))) . '</span>',
        'created_at' => date('d/m/Y H:i', strtotime($g['created_at'])),
        'actions' => '<a href="view_guide.php?id='.$g['id'].'" class="btn btn-sm btn-outline-primary">Detalhes</a>'
    ];
}

echo json_encode($data);
exit;
