<?php
header("Content-Type: application/json"); 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$selectedCountry = $_SESSION['user']['country']; 

echo json_encode(["selected_country" => $selectedCountry]);
?>
