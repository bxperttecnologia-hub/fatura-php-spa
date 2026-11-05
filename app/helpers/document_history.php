<?php

function bx_document_history_pdf_url(string $type, int $id): string
{
    $paths = [
        'invoice' => 'invoices',
        'proforma' => 'proformas',
        'receipt' => 'receipts',
        'credit-note' => 'credit-notes',
        'debit-note' => 'debit-notes',
        'delivery-note' => 'delivery-notes',
    ];

    return '/api/documents/' . $paths[$type] . '/' . $id . '/pdf';
}

function bx_document_history_add_invoice(array &$documents, int $id, int $companyId): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            i.id,
            i.document_type,
            i.issue_date,
            i.series,
            i.reference,
            i.numero_validacao,
            s.name AS status
        FROM invoices i
        INNER JOIN invoice_status s ON s.id = i.status
        WHERE i.id = ? AND i.company_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $companyId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        return false;
    }

    $typeLabels = [
        'FT' => 'Factura',
        'FR' => 'Factura-Recibo',
        'NC' => 'Nota de Crédito',
        'ND' => 'Nota de Débito',
    ];
    $type = (string)($invoice['document_type'] ?? 'FT');
    $fallbackNumber = trim((string)($invoice['series'] ?? '')) . '/' . $invoice['id'];
    $number = $invoice['status'] === 'Rascunho'
        ? $fallbackNumber
        : (trim((string)($invoice['numero_validacao'] ?? ''))
            ?: (trim((string)($invoice['reference'] ?? '')) ?: $fallbackNumber));

    $documents[] = [
        'type' => 'invoice',
        'label' => $typeLabels[$type] ?? 'Factura',
        'id' => (int)$invoice['id'],
        'number' => $number,
        'date' => (string)$invoice['issue_date'],
        'status' => (string)$invoice['status'],
        'pdf_url' => bx_document_history_pdf_url('invoice', (int)$invoice['id']),
        'order' => 1,
    ];

    return true;
}

function bx_document_history_add_proforma(array &$documents, int $id, int $companyId): bool
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT p.id, p.reference, p.series, p.issue_date, s.name AS status
        FROM proformas p
        INNER JOIN proforma_status s ON s.id = p.status
        WHERE p.id = ? AND p.company_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $companyId]);
    $proforma = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proforma) {
        return false;
    }

    $documents[] = [
        'type' => 'proforma',
        'label' => 'Proforma',
        'id' => (int)$proforma['id'],
        'number' => trim((string)($proforma['reference'] ?? ''))
            ?: ((string)$proforma['series'] . '/' . $proforma['id']),
        'date' => (string)$proforma['issue_date'],
        'status' => (string)$proforma['status'],
        'pdf_url' => bx_document_history_pdf_url('proforma', (int)$proforma['id']),
        'order' => 0,
    ];

    return true;
}

function bx_document_history_add_invoice_children(array &$documents, int $invoiceId, int $companyId): void
{
    global $pdo;

    $queries = [
        [
            'type' => 'credit-note',
            'label' => 'Nota de Crédito',
            'sql' => "SELECT id, issue_date, COALESCE(NULLIF(reference, ''), CONCAT(YEAR(issue_date), '/', id)) AS number
                FROM credit_notes WHERE invoice_id = ? AND company_id = ?",
            'date' => 'issue_date',
            'status' => 'Emitida',
        ],
        [
            'type' => 'debit-note',
            'label' => 'Nota de Débito',
            'sql' => "SELECT id, issue_date, CONCAT(serie, ' ', number) AS number
                FROM debit_notes WHERE invoice_id = ? AND company_id = ?",
            'date' => 'issue_date',
            'status' => 'Emitida',
        ],
        [
            'type' => 'delivery-note',
            'label' => 'Nota de Entrega',
            'sql' => "SELECT id, issue_date, CONCAT(serie, ' ', number) AS number
                FROM delivery_notes WHERE invoice_id = ? AND company_id = ?",
            'date' => 'issue_date',
            'status' => 'Emitida',
        ],
        [
            'type' => 'receipt',
            'label' => 'Recibo',
            'sql' => "SELECT r.id, r.pay_date AS issue_date, COALESCE(NULLIF(r.reference, ''), CONCAT(r.serie, ' ', r.number)) AS number
                FROM receipts r
                INNER JOIN invoices i ON i.id = r.invoice_id
                WHERE r.invoice_id = ? AND i.company_id = ?",
            'date' => 'issue_date',
            'status' => 'Emitido',
        ],
    ];

    foreach ($queries as $query) {
        $stmt = $pdo->prepare($query['sql']);
        $stmt->execute([$invoiceId, $companyId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $document) {
            $documents[] = [
                'type' => $query['type'],
                'label' => $query['label'],
                'id' => (int)$document['id'],
                'number' => (string)$document['number'],
                'date' => (string)$document[$query['date']],
                'status' => $query['status'],
                'pdf_url' => bx_document_history_pdf_url($query['type'], (int)$document['id']),
                'order' => 2,
            ];
        }
    }
}

function bx_document_history_respond(string $sourceType): void
{
    global $pdo;

    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
        return;
    }

    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if (!is_int($id)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Documento inválido.']);
        return;
    }

    $companyId = session_company_id();
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($companyId <= 0 || $userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão inválida.']);
        return;
    }

    try {
        $documents = [];
        $invoiceId = null;

        if ($sourceType === 'invoice') {
            $stmt = $pdo->prepare('SELECT id FROM invoices WHERE id = ? AND company_id = ? LIMIT 1');
            $stmt->execute([$id, $companyId]);
            if (!$stmt->fetchColumn()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Factura não encontrada.']);
                return;
            }

            bx_document_history_add_invoice($documents, $id, $companyId);
            $invoiceId = $id;

            if (Permission::hasAccess($pdo, $userId, $companyId, 'proformas')) {
                $stmt = $pdo->prepare("
                    SELECT id FROM proformas
                    WHERE converted_invoice_id = ? AND company_id = ?
                    ORDER BY issue_date, id
                ");
                $stmt->execute([$id, $companyId]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $proformaId) {
                    bx_document_history_add_proforma($documents, (int)$proformaId, $companyId);
                }
            }
        } elseif ($sourceType === 'proforma') {
            $stmt = $pdo->prepare('SELECT converted_invoice_id FROM proformas WHERE id = ? AND company_id = ? LIMIT 1');
            $stmt->execute([$id, $companyId]);
            $convertedInvoiceId = $stmt->fetchColumn();
            if ($convertedInvoiceId === false) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Proforma não encontrada.']);
                return;
            }

            bx_document_history_add_proforma($documents, $id, $companyId);
            if ((int)$convertedInvoiceId > 0
                && Permission::hasAccess($pdo, $userId, $companyId, 'invoices')
                && bx_document_history_add_invoice($documents, (int)$convertedInvoiceId, $companyId)
            ) {
                $invoiceId = (int)$convertedInvoiceId;
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tipo de documento inválido.']);
            return;
        }

        if ($invoiceId !== null) {
            bx_document_history_add_invoice_children($documents, $invoiceId, $companyId);
        }

        usort($documents, static function (array $a, array $b): int {
            return [$a['order'], $a['date'], $a['id']]
                <=> [$b['order'], $b['date'], $b['id']];
        });
        foreach ($documents as &$document) {
            unset($document['order']);
        }
        unset($document);

        echo json_encode(['success' => true, 'data' => $documents], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $error) {
        error_log('Erro ao carregar histórico documental: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Não foi possível carregar o histórico documental.']);
    }
}
