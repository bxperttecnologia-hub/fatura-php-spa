<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();

$userId = $_SESSION['user']['id'];

$query = "SELECT c.id, c.name, c.registration_number, c.email, c.logo_url, c.is_active, c.plan_code, c.plan_expires_at FROM users u 
          JOIN company_has_user chu ON chu.user_id = u.id 
          JOIN companies c ON c.id = chu.company_id 
          WHERE u.id = :userId";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
$stmt->execute();
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($empresas);
?>
