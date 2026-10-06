<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');
session_start();

try {

    if (!isset($_SESSION['user']['company_id'])) {
        throw new Exception('Sessão inválida.');
    }

    $company_id = (int) $_SESSION['user']['company_id'];

    // =========================================
    // INPUTS (TOTALMENTE TOLERANTE A ERROS)
    // =========================================
    $stock_id  = isset($_GET['stock_id']) ? (int) $_GET['stock_id'] : 0;
    $item_type = $_GET['item_type'] ?? '';

    // =========================================
    // TYPE PREFIX (COM FALLBACK TOTAL)
    // =========================================
    $typeMap = [
        'product'       => 'PROD',
        'service'       => 'SRV',
        'raw_material'  => 'RAW',
        'finished_good' => 'FIN',
        'consumable'    => 'CON'
    ];

    // se item_type for inválido → fallback automático
    $typePrefix = $typeMap[$item_type] ?? 'ITM';

    // =========================================
    // BASE PREFIX (SEM DEPENDÊNCIAS OBRIGATÓRIAS)
    // =========================================
    if ($typePrefix === 'PROD' && $stock_id > 0) {

        $stmt = $pdo->prepare("
            SELECT id 
            FROM stocks 
            WHERE id = :id 
            AND company_id = :company_id
        ");

        $stmt->execute([
            ':id' => $stock_id,
            ':company_id' => $company_id
        ]);

        if ($stmt->fetch()) {
            $baseCode = 'STK' . str_pad($stock_id, 2, '0', STR_PAD_LEFT) . '-' . $typePrefix;
        } else {
            $baseCode = $company_id . '-' . $typePrefix;
        }
    } else {
        $baseCode = $company_id . '-' . $typePrefix;
    }

    $prefixLike = $baseCode . '-%';

    // =========================================
    // BUSCAR ÚLTIMO CÓDIGO (SEGURO)
    // =========================================
    $stmt = $pdo->prepare("
        SELECT code
        FROM items
        WHERE company_id = :company_id
        AND code LIKE :prefix
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        ':company_id' => $company_id,
        ':prefix' => $prefixLike
    ]);

    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    // =========================================
    // SEQUÊNCIA SEGURA
    // =========================================
    $nextNumber = 1;

    if (!empty($last['code'])) {
        $parts = explode('-', $last['code']);
        $lastNumber = (int) end($parts);

        $nextNumber = $lastNumber + 1;
    }

    $sequence = str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

    // =========================================
    // CÓDIGO FINAL
    // =========================================
    $generated_code = $baseCode . '-' . $sequence;

    echo json_encode([
        'success' => true,
        'generated_code' => $generated_code,
        'type_used' => $typePrefix,
        'sequence' => $sequence
    ]);
} catch (Exception $e) {

    // NUNCA bloquear frontend
    http_response_code(200);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'generated_code' => $company_id . '-ITM-000001'
    ]);
}
