<?php
header("Content-Type: application/json"); 
session_start();
$selectedCountry = $_SESSION['user']['country']; 

echo json_encode(["selected_country" => $selectedCountry]);
?>
