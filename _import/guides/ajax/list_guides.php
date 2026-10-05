<?php
require_once '../../../app/config/db.php';

header('Content-Type: application/json');

$sql = "SELECT g.*, c.name AS client_name FROM guides g
        LEFT JOIN contact c ON c.id = g.contact_id
        ORDER BY g.id DESC";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach($rows as $g) {
    $data[] = [
        'id' => $g['id'],
        'client_name' => htmlspecialchars($g['client_name'] ?? '-'),
        'vehicle_plate' => htmlspecialchars($g['vehicle_plate']),
        'cargo_date' => date('d/m/Y', strtotime($g['cargo_date'])),
        'final_total' => '<b>' . number_format($g['final_total'],2,',','.') . ' ' . htmlspecialchars($g['currency']) . '</b>',
        'status' => '<span class="badge bg-success">Draft </span>',
        'created_at' => date('d/m/Y H:i', strtotime($g['created_at'])),
        'actions' => '<a href="view_guide.php?id='.$g['id'].'" class="btn btn-sm btn-outline-primary">Detalhes</a>'
    ];
}

echo json_encode($data);
exit;
