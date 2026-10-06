<?php
require_once '../../../app/config/db.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(0);

try {

    // Verifica se é POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Método inválido'
        ]);
        exit;
    }

    // Verifica se o código foi enviado
    $codigo = trim($_POST['codigo'] ?? '');

    if (empty($codigo)) {
        echo json_encode([
            'success' => false,
            'message' => 'Código não informado'
        ]);
        exit;
    }

    // Verifica sessão da empresa
    $companyId = $_SESSION['user']['company_id'] ?? null;

    if (!$companyId) {
        echo json_encode([
            'success' => false,
            'message' => 'Sessão inválida'
        ]);
        exit;
    }

    // Consulta
    $stmt = $pdo->prepare("
        SELECT id 
        FROM items 
        WHERE code = :codigo 
        AND id_company = :company_id
        LIMIT 1
    ");

    $stmt->execute([
        ':codigo' => $codigo,
        ':company_id' => $companyId
    ]);

    // Melhor que rowCount() para SELECT
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'exists' => !!$exists
    ]);
} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao validar código'
    ]);
}
