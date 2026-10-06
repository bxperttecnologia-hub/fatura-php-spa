<?php

require_once '../../../app/config/db.php';

session_start();

header('Content-Type: application/json');

// DEBUG
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {

    // =========================================
    // VALIDAR SESSÃO
    // =========================================

    if (!isset($_SESSION['user']['company_id'])) {

        throw new Exception(
            'Empresa não encontrada na sessão.'
        );
    }

    $company_id = (int) $_SESSION['user']['company_id'];

    // =========================================
    // FILTRO
    // active     = 1
    // archived   = 0
    // =========================================

    $is_active = isset($_GET['archived'])
        ? (
            (int) $_GET['archived'] === 1
                ? 0
                : 1
        )
        : 1;

    // =========================================
    // QUERY
    // =========================================

    $sql = "
        SELECT
            id,
            name,
            email,
            telephone,
            country,
            city,
            is_active

        FROM contact

        WHERE company_id = :company_id
        AND is_active = :is_active

        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(
        ':company_id',
        $company_id,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':is_active',
        $is_active,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =========================================
    // RESPONSE
    // =========================================

    echo json_encode([
        'success' => true,
        'data' => $contacts
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>