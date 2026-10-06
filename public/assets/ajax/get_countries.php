<?php

require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();

$selectedCountry = $_SESSION['user']['country'];  

try {
    $stmt = $pdo->prepare("SELECT code, name, phone FROM countries ORDER BY (name = ?) DESC, name");
    $stmt->execute([$selectedCountry]);
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($countries);
} catch (PDOException $e) {
    echo json_encode(["error" => "Erro ao buscar países"]);
}
?>
