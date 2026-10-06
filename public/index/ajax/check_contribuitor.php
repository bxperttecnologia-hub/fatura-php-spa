<?php
require_once '../../../app/config/db.php';

header('Content-Type: application/json');
session_start();

/**
 * Verifica se o NIF já existe
 */
function nifExists($pdo, $registration_number)
{
    $sql = "SELECT COUNT(*) FROM companies WHERE registration_number = :registration_number";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':registration_number', $registration_number, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $registration_number = trim($_POST['registration_number'] ?? '');

    // Validação básica
    if (empty($registration_number)) {
        echo json_encode([
            'success' => false,
            'message' => 'NIF não informado.'
        ]);
        exit;
    }

    $exists = nifExists($pdo, $registration_number);

    echo json_encode([
        'success' => true,
        'exists' => $exists
    ]);
}
