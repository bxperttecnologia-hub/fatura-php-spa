<?php

declare(strict_types=1);

/*
 * document_history.php
 * ---------------------------------------------------------------------------
 * Histórico documental de UMA fatura (ou proforma).
 *
 *   GET ?kind=invoice|proforma&id=123
 *
 * Resposta:
 *   {
 *     success: true,
 *     root:  { ...documento principal (tem layout na página)... },
 *     items: [ ...documentos derivados: RC, NC, ND, GT (SEM layout; só PDF via API)... ]
 *   }
 *
 * Regras:
 *  - O tipo é sempre explícito (invoices.id e proformas.id colidem).
 *  - Tudo é filtrado pela empresa da sessão.
 *  - Documentos derivados têm has_layout=false: o front nunca os desenha, só
 *    abre/descarrega o PDF a partir de pdf_url (API de documentos).
 *  - Falha numa tabela secundária não derruba o histórico inteiro.
 */

require_once '../../../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function bx_fail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user']['company_id'])) {
    bx_fail(401, 'Sessão expirada. Inicie sessão novamente.');
}
$companyId = (int)$_SESSION['user']['company_id'];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    bx_fail(400, 'Documento inválido.');
}

$kind = strtolower((string)($_GET['kind'] ?? 'invoice'));
if (!in_array($kind, ['invoice', 'proforma'], true)) {
    bx_fail(400, 'Tipo de documento inválido.');
}

/** "FT 2026/12" > "FT 2026/12"; senão "SERIE NUMERO"; senão "CODIGO #id". */
function bx_doc_number(array $row, string $code): string
{
    $ref = trim((string)($row['reference'] ?? ''));
    if ($ref !== '') {
        return $ref;
    }
    $serie  = trim((string)($row['serie'] ?? ''));
    $number = trim((string)($row['number'] ?? ''));
    if ($serie !== '' && $number !== '') {
        return "{$serie} {$number}";
    }
    return "{$code} #" . (int)($row['id'] ?? 0);
}

function bx_node(
    string $type,
    string $code,
    string $label,
    array $row,
    ?string $date,
    ?float $total,
    string $pdfSegment,
    string $filePrefix
): array {
    $id = (int)$row['id'];
    return [
        'type'          => $type,
        'document_code' => $code,
        'label'         => $label,
        'id'            => $id,
        'number'        => bx_doc_number($row, $code),
        'date'          => $date,
        'total'         => $total,
        'has_layout'    => false,
        'pdf_url'       => "/api/documents/{$pdfSegment}/" . rawurlencode((string)$id) . '/pdf',
        'filename'      => "{$filePrefix}_{$id}.pdf",
    ];
}

try {
    // ───────── documento raiz (valida posse) ─────────
    if ($kind === 'proforma') {
        $stmt = $pdo->prepare("
            SELECT d.id, d.reference, d.series, d.issue_date, d.final_total, d.currency,
                   ps.name AS status_name, c.name AS client_name
            FROM proformas d
            JOIN proforma_status ps ON ps.id = d.status
            JOIN contact c          ON c.id  = d.contact_id
            WHERE d.id = :id AND d.company_id = :company
            LIMIT 1
        ");
        $code  = 'PF';
        $label = 'Proforma';
    } else {
        $stmt = $pdo->prepare("
            SELECT d.id, d.reference, d.series, d.issue_date, d.final_total, d.currency,
                   d.document_type, ivs.name AS status_name, c.name AS client_name
            FROM invoices d
            JOIN invoice_status ivs ON ivs.id = d.status
            JOIN contact c          ON c.id   = d.contact_id
            WHERE d.id = :id AND d.company_id = :company
            LIMIT 1
        ");
        $code  = 'FT';
        $label = 'Factura';
    }
    $stmt->execute([':id' => $id, ':company' => $companyId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        bx_fail(404, $kind === 'proforma' ? 'Proforma não encontrada.' : 'Factura não encontrada.');
    }

    if ($kind === 'invoice') {
        $code  = strtoupper((string)($doc['document_type'] ?: 'FT'));
        $label = $code === 'FR' ? 'Factura-Recibo' : 'Factura';
    }

    $isDraft = strcasecmp(trim((string)$doc['status_name']), 'Rascunho') === 0;
    $rootNumber = $isDraft
        ? trim((string)$doc['series']) . '/' . (int)$doc['id']
        : (trim((string)$doc['reference']) !== '' ? (string)$doc['reference'] : trim((string)$doc['series']) . '/' . (int)$doc['id']);

    $root = [
        'type'          => $kind,
        'document_code' => $code,
        'label'         => $label,
        'id'            => (int)$doc['id'],
        'number'        => $rootNumber,
        'date'          => $doc['issue_date'],
        'total'         => (float)$doc['final_total'],
        'status'        => $doc['status_name'],
        'client'        => $doc['client_name'],
        'currency'      => $doc['currency'],
        'has_layout'    => true,
        'is_root'       => true,
    ];

    $items = [];

    // Proforma não tem documentos derivados.
    if ($kind === 'invoice') {
        // helper: executa uma consulta secundária sem derrubar o histórico
        $safe = static function (string $name, callable $fn) use (&$items): void {
            try {
                foreach ($fn() as $node) {
                    $items[] = $node;
                }
            } catch (Throwable $e) {
                error_log("[document_history] {$name}: " . $e->getMessage());
            }
        };

        // ───────── recibos ─────────
        $safe('receipts', function () use ($pdo, $id): array {
            $q = $pdo->prepare("
                SELECT r.id, r.serie, r.reference, r.number, r.pay_date, r.created_at, r.amount_paid
                FROM receipts r
                WHERE r.invoice_id = :id AND r.reference IS NOT NULL
            ");
            $q->execute([':id' => $id]);
            $out = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = bx_node(
                    'receipt',
                    'RC',
                    'Recibo',
                    $r,
                    $r['pay_date'] ?: $r['created_at'],
                    (float)$r['amount_paid'],
                    'receipts',
                    'Recibo'
                );
            }
            return $out;
        });

        // ───────── notas de crédito ─────────
        $safe('credit_notes', function () use ($pdo, $id, $companyId): array {
            // SELECT * de propósito: o esquema de credit_notes não está versionado no projecto.
            $q = $pdo->prepare("SELECT * FROM credit_notes WHERE invoice_id = :id AND company_id = :company");
            $q->execute([':id' => $id, ':company' => $companyId]);
            $out = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = bx_node(
                    'credit_note',
                    'NC',
                    'Nota de crédito',
                    $r,
                    $r['issue_date'] ?? $r['created_at'] ?? null,
                    isset($r['final_total']) ? (float)$r['final_total'] : null,
                    'credit-notes',
                    'NotaCredito'
                );
            }
            return $out;
        });

        // ───────── notas de débito ─────────
        $safe('debit_notes', function () use ($pdo, $id, $companyId): array {
            $q = $pdo->prepare("
                SELECT id, serie, number, issue_date, final_total
                FROM debit_notes
                WHERE invoice_id = :id AND company_id = :company
            ");
            $q->execute([':id' => $id, ':company' => $companyId]);
            $out = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = bx_node(
                    'debit_note',
                    'ND',
                    'Nota de débito',
                    $r,
                    $r['issue_date'],
                    (float)$r['final_total'],
                    'debit-notes',
                    'NotaDebito'
                );
            }
            return $out;
        });

        // ───────── notas de entrega ─────────
        $safe('delivery_notes', function () use ($pdo, $id, $companyId): array {
            $q = $pdo->prepare("
                SELECT id, serie, number, issue_date
                FROM delivery_notes
                WHERE invoice_id = :id AND company_id = :company
            ");
            $q->execute([':id' => $id, ':company' => $companyId]);
            $out = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = bx_node(
                    'delivery_note',
                    'GT',
                    'Nota de entrega',
                    $r,
                    $r['issue_date'],
                    null,
                    'delivery-notes',
                    'NotaEntrega'
                );
            }
            return $out;
        });

        // cronológico (mais antigo primeiro); desempate estável por tipo e id
        usort($items, static function (array $a, array $b): int {
            return [(string)$a['date'], $a['document_code'], $a['id']]
                <=> [(string)$b['date'], $b['document_code'], $b['id']];
        });
    }

    echo json_encode(
        ['success' => true, 'kind' => $kind, 'root' => $root, 'items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );
} catch (Throwable $e) {
    error_log('[document_history] ' . $e->getMessage());
    bx_fail(500, 'Não foi possível carregar o histórico.');
}
