<?php
require_once '../../../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {

    // =============================
    // MÉTODO
    // =============================
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        throw new Exception('Método não permitido.');
    }

    // =============================
    // VALIDAÇÃO
    // =============================
    $companyId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$companyId || $companyId <= 0) {
        http_response_code(400);
        throw new Exception('ID da empresa inválido.');
    }

    // =============================
    // QUERY (APENAS CAMPOS NECESSÁRIOS)
    // =============================
    $query = "SELECT * FROM companies WHERE id = :companyId LIMIT 1";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':companyId', $companyId, PDO::PARAM_INT);
    $stmt->execute();

    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        http_response_code(404);
        throw new Exception('Empresa não encontrada.');
    }
    // =============================
    // RESPOSTA
    // =============================
    echo json_encode([
        'success' => true,
        'data' => $company
    ]);
} catch (Exception $e) {

    if (http_response_code() === 200) {
        http_response_code(400);
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
