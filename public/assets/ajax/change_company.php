<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    $_SESSION['user']['company_id'] = $_POST['company_id'];
    $_SESSION['user']['name_company'] = $_POST['name_company'];
    $_SESSION['user']['registration_number'] = $_POST['registration_number'];
    $_SESSION['user']['email_company'] = $_POST['email_company'];
    
    echo json_encode(['success' => true, 'company' => $_SESSION['user']['name_company']]);
    exit;
}

echo json_encode(['success' => false]);
?>
