<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    // CÓDIGO CURTO: "SRV-0014" (antes "15-SRV-000013")
    // O código só tem de ser único dentro da empresa (índice unique_company_code),
    // por isso o ID da empresa já não entra no código. Produto de um stock: "PROD01-0001".
    // A numeração continua a partir dos códigos antigos do mesmo tipo.
    // =========================================
    $stockPart = '';
    if ($typePrefix === 'PROD' && $stock_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM stocks WHERE id = :id AND company_id = :company_id");
        $stmt->execute([':id' => $stock_id, ':company_id' => $company_id]);
        if ($stmt->fetch()) {
            $stockPart = str_pad((string) $stock_id, 2, '0', STR_PAD_LEFT);
        }
    }

    $baseCode = $typePrefix . $stockPart;                       // novo: SRV / PROD01
    $legacyBase = $stockPart !== ''
        ? 'STK' . $stockPart . '-' . $typePrefix                // antigo: STK01-PROD
        : $company_id . '-' . $typePrefix;                      // antigo: 15-SRV

    $stmt = $pdo->prepare("
        SELECT code
        FROM items
        WHERE company_id = :company_id
          AND (code LIKE :new_prefix OR code LIKE :legacy_prefix)
    ");
    $stmt->execute([
        ':company_id'    => $company_id,
        ':new_prefix'    => $baseCode . '-%',
        ':legacy_prefix' => $legacyBase . '-%',
    ]);

    $pattern = '/^(?:' . preg_quote($baseCode, '/') . '|' . preg_quote($legacyBase, '/') . ')-(\d+)$/i';
    $existing = [];
    $lastNumber = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $code) {
        $existing[strtoupper((string) $code)] = true;
        if (preg_match($pattern, (string) $code, $m)) {
            $lastNumber = max($lastNumber, (int) $m[1]);
        }
    }

    $nextNumber = $lastNumber + 1;
    do {
        $sequence = str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        $generated_code = $baseCode . '-' . $sequence;
        $nextNumber++;
    } while (isset($existing[strtoupper($generated_code)]));

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
        'generated_code' => 'ITM-0001'
    ]);
}
