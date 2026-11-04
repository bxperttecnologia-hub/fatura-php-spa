<?php

/**
 * Emissão de faturas (FT/FR): numeração (AGT ou interna), hash encadeado e envio à AGT.
 * Usado por invoices/ajax/update_status.php e pela conversão de proformas, para que
 * os dois caminhos emitam exatamente da mesma forma.
 */

require_once __DIR__ . '/invoice_helper.php';

function bx_invoice_hash(string $invoiceDate, string $systemEntryDate, string $invoiceNo, $grossTotal, string $previousHash = ''): string
{
    $string = $invoiceDate . ';' .
        $systemEntryDate . ';' .
        $invoiceNo . ';' .
        number_format((float)$grossTotal, 2, '.', '') . ';' .
        $previousHash;

    return strtoupper(sha1($string));
}

/**
 * Emite uma fatura em rascunho: atribui o número, muda o estado e grava o hash.
 * Tem de correr dentro de uma transação aberta pelo chamador; o envio à AGT
 * (bx_invoice_send_agt) só deve ser feito depois do commit.
 *
 * @return array{reference: string, hash: string, status: int, document_type: string}
 */
function bx_invoice_emit(PDO $pdo, int $companyId, int $invoiceId, string $newStatusName, string $fallbackDocType = 'FT'): array
{
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? AND company_id = ? FOR UPDATE');
    $stmt->execute([$invoiceId, $companyId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new RuntimeException('Fatura não encontrada');
    }
    if (!empty($invoice['hash'])) {
        throw new RuntimeException('Fatura já finalizada (hash existente)');
    }

    $docType = strtoupper(trim((string)($invoice['document_type'] ?: $fallbackDocType)));
    if (!in_array($docType, ['FT', 'FR'], true)) {
        throw new RuntimeException('Tipo de documento inválido');
    }

    // A Fatura/Recibo é paga no acto: ao emitir fica sempre "Paga", sem vencimento.
    $isReceipt = $docType === 'FR' && stripos($newStatusName, 'cancel') !== 0 && $newStatusName !== 'Rascunho';
    if ($isReceipt) {
        $newStatusName = 'Paga';
    }

    $statusMap = invoice_status_map();
    if (!isset($statusMap[$newStatusName])) {
        throw new RuntimeException('Status inválido');
    }
    $statusId = $statusMap[$newStatusName];

    $currentReference = $invoice['reference'];
    $hadDuplicateReference = $currentReference !== null
        && $currentReference !== ''
        && reference_exists($pdo, $companyId, (string)$currentReference, $invoiceId);
    if ($hadDuplicateReference) {
        $currentReference = null;
    }

    // Numeração: usa a série AGT escolhida na fatura (se for válida para o tipo), senão a por omissão.
    $series = (string)($invoice['series'] ?? '');
    $result = apply_invoice_number_rules(
        $pdo,
        $companyId,
        $newStatusName,
        $currentReference,
        $docType,
        $series !== '' ? $series : null,
        $invoiceId
    );
    $newReference = (string)$result['reference'];

    if (
        $statusId !== 1
        && (preg_match('/^RASCUNHO-(?:FT|FR)-\d{4}$/', $series) || $hadDuplicateReference || $series === '')
    ) {
        $agtDocumentNumber = agt_parse_document_no($newReference);
        $series = $agtDocumentNumber[1] ?? date('Y', strtotime((string)$invoice['issue_date']));
    }

    $pdo->prepare('UPDATE invoices SET status = ?, reference = ?, series = ?, document_type = ? WHERE id = ? AND company_id = ?')
        ->execute([$statusId, $newReference, $series, $docType, $invoiceId, $companyId]);
    if ($isReceipt) {
        $pdo->prepare('UPDATE invoices SET paid_total = final_total, due_date = 0 WHERE id = ? AND company_id = ?')
            ->execute([$invoiceId, $companyId]);
    }

    // Hash encadeado com o último documento do mesmo tipo.
    $stmtHash = $pdo->prepare("
        SELECT hash
        FROM invoices
        WHERE company_id = ? AND document_type = ? AND hash IS NOT NULL
        ORDER BY issue_date DESC, id DESC
        LIMIT 1
    ");
    $stmtHash->execute([$companyId, $docType]);
    $previousHash = (string)($stmtHash->fetchColumn() ?: '');

    $systemEntryDate = date('Y-m-d\TH:i:s');
    $hash = bx_invoice_hash((string)$invoice['issue_date'], $systemEntryDate, $newReference, $invoice['final_total'], $previousHash);

    $pdo->prepare("UPDATE invoices SET hash = ?, hash_control = '1', created_at = ? WHERE id = ?")
        ->execute([$hash, $systemEntryDate, $invoiceId]);

    return ['reference' => $newReference, 'hash' => $hash, 'status' => $statusId, 'document_type' => $docType];
}

/**
 * Faturação eletrónica: envia à AGT uma fatura já emitida. Não bloqueia a emissão:
 * se falhar, o erro fica na fatura e pode ser reenviada a partir da página da fatura.
 */
function bx_invoice_send_agt(PDO $pdo, int $invoiceId, string $statusName = ''): ?array
{
    if (stripos($statusName, 'cancel') === 0) {
        return null;
    }
    try {
        $state = agt_submit_invoice($pdo, $invoiceId);
        if ($state !== null) {
            $stmt = $pdo->prepare('SELECT contact_id FROM invoices WHERE id = ?');
            $stmt->execute([$invoiceId]);
            $state['consumer_final'] = agt_invoice_is_consumer_final($pdo, ['contact_id' => $stmt->fetchColumn()]);
            $state['consumer_tax_id'] = agt_consumer_tax_id();
        }
        return $state;
    } catch (Throwable $e) {
        error_log('AGT envio fatura ' . $invoiceId . ': ' . $e->getMessage());
        return ['status' => 'error', 'label' => 'Não foi enviada', 'errors' => [$e->getMessage()]];
    }
}
