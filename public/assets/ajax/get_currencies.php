<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT iso_code, currency FROM currencies ORDER BY currency");
    $moedas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($moedas);
} catch (Exception $e) {
    echo json_encode([]);
}
