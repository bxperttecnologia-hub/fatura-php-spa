<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/agt_debit_note.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Empresa não encontrada na sessão.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$companyId = (int)$_SESSION['user']['company_id'];

$invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);

if (!$invoiceId || $invoiceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da fatura inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Notas de débito já emitidas para esta fatura (mais recente primeiro)
    $stmt = $pdo->prepare("
        SELECT id, serie, number, reference, issue_date, final_total, currency,
               agt_status, agt_request_id, agt_errors, agt_sent_at, agt_checked_at
        FROM debit_notes
        WHERE invoice_id = :invoice_id
          AND company_id = :company_id
        ORDER BY id DESC
    ");
    $stmt->execute([':invoice_id' => $invoiceId, ':company_id' => $companyId]);

    $rows = array_map(static function (array $row): array {
        $isAgt = agt_parse_document_no($row['reference'] ?? null) !== null;
        $row['display_number'] = $isAgt ? $row['reference'] : $row['serie'] . ' ' . $row['number'];
        $row['agt'] = $isAgt ? agt_debit_note_state($row) : null;
        unset($row['agt_request_id'], $row['agt_errors'], $row['agt_sent_at'], $row['agt_checked_at']);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno no servidor.', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
