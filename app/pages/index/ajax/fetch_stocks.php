<?php

header('Content-Type: application/json');
require_once '../../../app/config/db.php';
session_start();

try {

    if (
        !isset($_SESSION['user']['company_id'])
    ) {
        echo json_encode([
            "status" => false,
            "message" => "Sessão inválida."
        ]);
        exit;
    }

    $companyId = (int) $_SESSION['user']['company_id'];

    $query = "SELECT * FROM stocks WHERE company_id = :company_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['company_id' => $companyId]);

    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($stocks),
        'data' => $stocks
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
