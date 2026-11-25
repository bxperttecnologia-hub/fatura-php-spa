<?php
declare(strict_types=1);

function delivery_workflow_fail(PDO $pdo, int $status, string $message): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function delivery_workflow_event(PDO $pdo, int $companyId, string $documentType, int $documentId,
                                 string $eventType, ?string $previousStatus, ?string $newStatus,
                                 array $payload = [], ?int $userId = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO delivery_document_events
         (company_id, document_type, document_id, event_type, previous_status, new_status, payload, user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $companyId,
        $documentType,
        $documentId,
        $eventType,
        $previousStatus,
        $newStatus,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $userId,
    ]);
}

function delivery_workflow_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}
