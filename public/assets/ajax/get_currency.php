<?php

require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();

if (!isset($_SESSION['user']['currency'])) {
    echo json_encode(["error" => "Moeda não definida na sessão"]);
    exit;
}

$currency_code = $_SESSION['user']['currency'];

try {
    $stmt = $pdo->prepare("SELECT symbol, position FROM currencies WHERE iso_code = :currency_code");
    $stmt->bindParam(':currency_code', $currency_code, PDO::PARAM_STR);
    $stmt->execute();
    $currency = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currency) {
        echo json_encode(["currency" => $currency_code, "symbol" => $currency['symbol'], "position" => $currency['position']]);
    } else {
        echo json_encode(["error" => "Moeda não encontrada"]);
    }
} catch (PDOException $e) {
    echo json_encode(["error" => "Erro no banco de dados"]);
}
?>
