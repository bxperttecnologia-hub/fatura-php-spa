<?php

require_once '../../../app/config/db.php';

/**
 * ==================================================
 * DOCUMENT HELPER - SISTEMA FISCAL AGT
 * ==================================================
 */

/**
 * STATUS PADRÃO (INT -> invoice_status.id)
 */
function invoice_status_map(): array
{
    return [
        'Rascunho'     => 1,
        'Emitida' => 2,
        'Finalizada' => 3,
        'Paga'      => 4,
        'Cancelada'   => 5,
        'Pendente' => 6,
        'Expirada'   => 7
    ];
}

/**
 * PREFIXO DO DOCUMENTO
 */
function document_prefix(string $type): string
{
    return match (strtoupper(trim($type))) {
        'PF' => 'PF',
        'FT' => 'FT',
        'FR' => 'FR',
        'NC' => 'NC',
        'ND' => 'ND',
        default => 'FT'
    };
}

/**
 * VERIFICA SE DEVE GERAR NUMERAÇÃO
 */
function should_generate_number(?string $reference): bool
{
    return empty($reference);
}

/**
 * VERIFICA EXISTÊNCIA DE REFERÊNCIA
 */
function reference_exists(PDO $pdo, string $reference): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM invoices 
        WHERE reference = ?
    ");

    $stmt->execute([$reference]);

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * GERADOR SEGURO DE NUMERAÇÃO POR DOCUMENTO
 */
function generate_document_number(
    PDO $pdo,
    int $companyId,
    string $documentType
): string {

    $year = date('Y');
    $prefix = document_prefix($documentType);

    $like = "{$prefix}{$year}/%";

    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED)) 
        FROM invoices
        WHERE company_id = ?
          AND document_type = ?
          AND reference LIKE ?
        FOR UPDATE
    ");

    $stmt->execute([$companyId, $documentType, $like]);

    $last = (int)$stmt->fetchColumn();
    $next = $last + 1;

    do {
        $reference = sprintf("%s%s/%04d", $prefix, $year, $next);

        if (!reference_exists($pdo, $reference)) {
            break;
        }

        $next++;
    } while (true);

    return $reference;
}

/**
 * REGRAS PRINCIPAIS DE NUMERAÇÃO
 */
function apply_invoice_number_rules(
    PDO $pdo,
    int $companyId,
    string $status,
    ?string $currentReference,
    string $documentType
): array {

    $documentType = strtoupper(trim($documentType));

    $statusMap = invoice_status_map();
    $statusId = $statusMap[$status] ?? 1;

    /**
     * ❌ CANCELADO NUNCA ALTERA
     */
    if ($statusId === 2) {
        return [
            'reference' => $currentReference,
            'status' => $statusId
        ];
    }

    /**
     * ✔ GERAR SE NÃO EXISTE REFERÊNCIA
     */
    if (should_generate_number($currentReference)) {

        $reference = generate_document_number(
            $pdo,
            $companyId,
            $documentType
        );

        return [
            'reference' => $reference,
            'status' => $statusId
        ];
    }

    /**
     * ✔ CASO NORMAL
     */
    return [
        'reference' => $currentReference,
        'status' => $statusId
    ];
}

/**
 * VERIFICAR SE DEVE LIMPAR NUMERO (AGT)
 */
function should_clear_number(string $status): bool
{
    return false;
}
