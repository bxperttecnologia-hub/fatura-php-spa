<?php
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../app/helpers/document_tax.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$expectedToken = (string)($_SESSION['csrf'] ?? '');
$sentToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($expectedToken === '' || !hash_equals($expectedToken, $sentToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
    exit;
}

$type = trim((string)($_POST['tax_type'] ?? ''));
$rateRaw = str_replace(',', '.', trim((string)($_POST['rate'] ?? '')));
$baseType = trim((string)($_POST['base_type'] ?? ''));
$effectiveFrom = trim((string)($_POST['effective_from'] ?? ''));
$enabled = isset($_POST['enabled']) && (string)$_POST['enabled'] === '1' ? 1 : 0;
$validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom);

if (!bx_document_tax_is_type($type)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Tipo de imposto inválido.']);
    exit;
}
if (!is_numeric($rateRaw) || !is_finite((float)$rateRaw) || (float)$rateRaw <= 0 || (float)$rateRaw > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A taxa deve ser superior a 0% e não exceder 100%.']);
    exit;
}
if (!in_array($baseType, ['net_subtotal', 'gross_including_vat'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Seleccione uma base de incidência.']);
    exit;
}
if (!$validDate || $validDate->format('Y-m-d') !== $effectiveFrom) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Data de vigência inválida.']);
    exit;
}
if ($enabled !== 0 && trim((string)($_POST['legal_validation_note'] ?? '')) === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Registe a referência da validação legal antes de activar esta regra.']);
    exit;
}
if (!in_array((string)($_SESSION['user']['role'] ?? ''), ['owner', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Apenas o proprietário ou um administrador pode alterar a configuração fiscal.']);
    exit;
}

try {
    $companyId = session_company_id();
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($companyId <= 0 || $userId <= 0) {
        throw new RuntimeException('Sessão inválida.', 401);
    }

    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id FROM companies WHERE id = ? FOR UPDATE');
    $lock->execute([$companyId]);
    if (!$lock->fetchColumn()) {
        throw new RuntimeException('Empresa não encontrada.', 404);
    }

    $previousStmt = $pdo->prepare(
        'SELECT id, effective_from, effective_to
         FROM company_document_tax_rates
         WHERE company_id = ? AND tax_type = ?
         ORDER BY effective_from DESC
         LIMIT 1
         FOR UPDATE'
    );
    $previousStmt->execute([$companyId, $type]);
    $previous = $previousStmt->fetch(PDO::FETCH_ASSOC);
    if ($previous && $effectiveFrom <= (string)$previous['effective_from']) {
        throw new RuntimeException('A nova vigência deve começar depois da última configuração. As versões anteriores são imutáveis.', 409);
    }

    if ($previous && $previous['effective_to'] === null) {
        $close = $pdo->prepare(
            'UPDATE company_document_tax_rates
             SET effective_to = DATE_SUB(?, INTERVAL 1 DAY)
             WHERE id = ? AND company_id = ?'
        );
        $close->execute([$effectiveFrom, (int)$previous['id'], $companyId]);
    }

    $insert = $pdo->prepare(
        'INSERT INTO company_document_tax_rates
         (company_id, tax_type, rate, base_type, enabled, effective_from, legal_validation_note, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
    );
    $insert->execute([
        $companyId,
        $type,
        round((float)$rateRaw, 4, PHP_ROUND_HALF_UP),
        $baseType,
        $enabled,
        $effectiveFrom,
        trim((string)($_POST['legal_validation_note'] ?? '')) ?: null,
        $userId,
    ]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = in_array($e->getCode(), [401, 404, 409], true) ? $e->getCode() : 500;
    http_response_code($status);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
