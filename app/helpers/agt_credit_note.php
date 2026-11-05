<?php
/**
 * Notas de crédito (NC) na faturação eletrónica da AGT.
 *
 * A NC credita, total ou parcialmente, uma FT/FR já validada pela AGT: leva as linhas
 * creditadas (com a quantidade creditada), com debitAmount (regra E16) e referenceInfo
 * para a linha correspondente da fatura de origem.
 * Numeração "NC <série NC>/<n>"; envio por POST /api/faturas/nota-credito na API Node
 * e consulta por /api/faturas/estado. O estado fica em credit_notes.agt_*.
 */

require_once __DIR__ . '/agt.php';

/** A fatura tem numeração AGT (e portanto a NC também tem de ir à AGT)? */
function agt_credit_note_required(PDO $pdo, array $invoice): bool
{
    $parsed = agt_parse_document_no($invoice['reference'] ?? null);
    return $parsed !== null
        && in_array($parsed[0], agt_invoice_document_types(), true)
        && agt_enabled($pdo, (int) $invoice['company_id']);
}

/** Próximo número da série NC por omissão da empresa (dentro da transação, FOR UPDATE). */
function agt_credit_note_next_number(PDO $pdo, int $companyId): array
{
    $series = agt_default_series($pdo, $companyId, 'NC', (int) date('Y'));
    if (!$series) {
        throw new RuntimeException(
            'A empresa ainda não tem série de Nota de Crédito (NC) na AGT. Crie-a em Definições › Faturação eletrónica.',
            422
        );
    }
    $prefix = 'NC ' . $series['series_code'] . '/';
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED))
        FROM credit_notes
        WHERE company_id = ? AND reference LIKE ?
        FOR UPDATE
    ");
    $stmt->execute([$companyId, str_replace(['%', '_'], ['\%', '\_'], $prefix) . '%']);
    $next = max((int) $stmt->fetchColumn() + 1, max(1, (int) ($series['first_document_no'] ?? 1)));
    if (!empty($series['last_document_no']) && $next > (int) $series['last_document_no']) {
        throw new RuntimeException('A série ' . $series['series_code'] . ' esgotou a numeração autorizada pela AGT. Crie uma nova série NC.', 422);
    }
    return ['series_id' => (int) $series['id'], 'reference' => $prefix . $next];
}

/**
 * Pedido da NC: parte do pedido da fatura de origem (mesmas linhas e totais,
 * já com as regras de IVA/isenção) e troca creditAmount por debitAmount + referenceInfo.
 */
function agt_build_credit_note_request(PDO $pdo, array $note, array $invoice, array $creds, string $exemptionCode): array
{
    $reason = mb_substr(trim((string) ($note['reason'] ?? '')) ?: 'Anulação da fatura', 0, 200);
    $invoiceForLines = $invoice;
    $invoiceForLines['agt_system_entry_date'] = $note['agt_system_entry_date'];
    $stmt = $pdo->prepare('SELECT invoice_item_id, quantity FROM credit_note_items WHERE credit_note_id = ? ORDER BY id');
    $stmt->execute([(int) $note['id']]);
    $quantities = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (empty($row['invoice_item_id'])) {
            throw new RuntimeException('Esta nota de crédito não indica as linhas da fatura que credita.');
        }
        $quantities[(int) $row['invoice_item_id']] = (float) $row['quantity'];
    }
    if (!$quantities) {
        throw new RuntimeException('A nota de crédito não tem linhas.');
    }
    $body = agt_build_invoice_request($pdo, $invoiceForLines, $creds, $exemptionCode, $quantities);

    foreach ($body['lines'] as $i => $line) {
        $line['debit_amount'] = $line['credit_amount'];
        unset($line['credit_amount']);
        $line['reference_info'] = [
            'reference' => $invoice['reference'],
            'reference_item_line_no' => (int) ($line['source_line_no'] ?? $i + 1),
            'reason' => $reason,
        ];
        unset($line['source_line_no']);
        $body['lines'][$i] = $line;
    }
    unset($body['document_type']);
    $body['document_no'] = $note['reference'];
    $body['document_date'] = substr((string) $note['issue_date'], 0, 10);
    $body['reference'] = $invoice['reference'];
    $body['reason'] = $reason;
    return $body;
}

function agt_save_credit_note_state(PDO $pdo, int $noteId, array $fields): void
{
    $sets = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($fields)));
    $pdo->prepare("UPDATE credit_notes SET $sets WHERE id = ?")->execute([...array_values($fields), $noteId]);
}

/** NC + fatura de origem, ou null. */
function agt_credit_note_load(PDO $pdo, int $noteId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM credit_notes WHERE id = ?');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([(int) $note['invoice_id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    return $invoice ? [$note, $invoice] : null;
}

/** Envia uma NC à AGT. Nunca lança exceção; devolve null se a NC não tiver numeração AGT. */
function agt_submit_credit_note(PDO $pdo, int $noteId): ?array
{
    $loaded = agt_credit_note_load($pdo, $noteId);
    if (!$loaded) {
        return null;
    }
    [$note, $invoice] = $loaded;
    $parsed = agt_parse_document_no($note['reference'] ?? null);
    if (!$parsed || $parsed[0] !== 'NC' || !agt_enabled($pdo, (int) $note['company_id'])) {
        return null;
    }
    if (in_array($note['agt_status'], ['sent', 'valid'], true)) {
        return agt_credit_note_state($note);
    }

    if (empty($note['agt_system_entry_date'])) {
        $note['agt_system_entry_date'] = date('Y-m-d H:i:s');
    }
    $fields = ['agt_system_entry_date' => $note['agt_system_entry_date'], 'agt_sent_at' => date('Y-m-d H:i:s'), 'agt_checked_at' => null];
    try {
        $settings = agt_settings($pdo, (int) $note['company_id']);
        $creds = agt_credentials($pdo, (int) $note['company_id']);
        unset($creds['establishment']);
        $body = agt_build_credit_note_request($pdo, $note, $invoice, $creds, $settings['exemption_code'] ?: 'M00');
        $res = agt_api_post('/api/faturas/nota-credito', $body);
        $requestId = $res['data']['resultado']['requestId'] ?? null;
        if (!empty($res['data']['success']) && $requestId) {
            $fields += ['agt_status' => 'sent', 'agt_request_id' => (string) $requestId, 'agt_errors' => null];
        } else {
            $fields += ['agt_status' => 'error', 'agt_errors' => json_encode([agt_error_message($res['data'])], JSON_UNESCAPED_UNICODE)];
        }
    } catch (Throwable $e) {
        $fields += ['agt_status' => 'error', 'agt_errors' => json_encode([$e->getMessage()], JSON_UNESCAPED_UNICODE)];
    }
    if ($fields['agt_status'] === 'error') {
        $recovered = agt_recover_submission($pdo, (int) $note['company_id'], (string) $note['reference'], (string) (json_decode($fields['agt_errors'], true)[0] ?? ''));
        if ($recovered) {
            $fields = $recovered + $fields;
        }
    }
    agt_save_credit_note_state($pdo, $noteId, $fields);
    return agt_credit_note_state(array_merge($note, $fields));
}

/** Consulta na AGT a validação (assíncrona) de uma NC enviada. */
function agt_check_credit_note(PDO $pdo, int $noteId): ?array
{
    $loaded = agt_credit_note_load($pdo, $noteId);
    if (!$loaded) {
        return null;
    }
    [$note] = $loaded;
    if (empty($note['agt_request_id']) || $note['agt_status'] !== 'sent') {
        return agt_credit_note_state($note);
    }

    $creds = agt_credentials($pdo, (int) $note['company_id']);
    unset($creds['establishment']);
    $res = agt_api_post('/api/faturas/estado', $creds + ['requestID' => $note['agt_request_id']]);
    $r = $res['data']['resultado'] ?? [];
    $fields = ['agt_checked_at' => date('Y-m-d H:i:s')];

    if (!empty($res['data']['success'])) {
        $doc = null;
        foreach ((array) ($r['documentStatusList'] ?? []) as $d) {
            if (is_array($d) && ($d['documentNo'] ?? null) === $note['reference']) {
                $doc = $d;
            }
        }
        if ($doc && ($doc['documentStatus'] ?? '') === 'V') {
            $fields += ['agt_status' => 'valid', 'agt_errors' => null];
        } elseif ($doc && ($doc['documentStatus'] ?? '') === 'I') {
            $errors = array_map(
                static fn ($e) => trim(($e['errorCode'] ?? $e['idError'] ?? '') . ': ' . ($e['errorDescription'] ?? $e['descriptionError'] ?? ''), ': '),
                array_filter((array) ($doc['errorList'] ?? []), 'is_array')
            );
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode($errors ?: ['Nota de crédito inválida.'], JSON_UNESCAPED_UNICODE)];
        } elseif ((string) ($r['resultCode'] ?? '') === '9') {
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode(['Processamento cancelado pela AGT.'], JSON_UNESCAPED_UNICODE)];
        }
    } else {
        $message = agt_error_message($res['data']);
        if (!preg_match('/\bE9[67]\b/', $message)) {
            $fields['agt_errors'] = json_encode([$message], JSON_UNESCAPED_UNICODE);
        }
    }
    agt_save_credit_note_state($pdo, $noteId, $fields);
    return agt_credit_note_state(array_merge($note, $fields));
}

/** Estado AGT de uma NC, pronto para o frontend. */
function agt_credit_note_state(array $note): array
{
    $state = agt_invoice_state($note);
    $state['label'] = [
        'sent' => 'Enviada — a aguardar validação',
        'valid' => 'Validada pela AGT',
        'invalid' => 'Rejeitada pela AGT',
        'error' => 'Não foi enviada',
    ][$state['status']] ?? 'Não enviada';
    return $state;
}
