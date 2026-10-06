<?php
require_once '../../../app/config/db.php';
session_start();

$id = $_GET['id'] ?? null;
$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$id || !$company_id) {
    echo json_encode(['error' => 'Parâmetros inválidos']);
    exit;
}

$sql = "SELECT 
    p.*, 
    e.id AS employee_id,
    e.name AS employee_name,
    e.document_type,
    e.birth_date,
    e.position,
    e.contract_type,
    e.admission_date,
    e.iban,
    e.bi,
    comp.name AS company_name,
    comp.logo_url,
    comp.phone AS company_phone,
    comp.email AS company_email,
    comp.registration_number,
    comp.address AS company_address
FROM payroll p
JOIN employees e ON e.id = p.employee_id
JOIN companies comp ON comp.id = p.company_id
WHERE p.id = ? AND p.company_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $company_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo json_encode(['error' => 'Folha não encontrada']);
    exit;
}

// Buscar faltas no mês da folha
$reference = $data['reference_month'];
$yearMonth = explode('-', $reference);
$firstDay = $yearMonth[0] . '-' . $yearMonth[1] . '-01';
$lastDay = date('Y-m-t', strtotime($firstDay));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance 
                       WHERE employee_id = ? AND company_id = ? 
                       AND type = 'falta' 
                       AND date BETWEEN ? AND ?
                       AND date NOT IN (SELECT date FROM holidays WHERE company_id = ?)");
$stmt->execute([
    $data['employee_id'],
    $company_id,
    $firstDay,
    $lastDay,
    $company_id
]);
$data['absences'] = $stmt->fetchColumn();

echo json_encode($data);
