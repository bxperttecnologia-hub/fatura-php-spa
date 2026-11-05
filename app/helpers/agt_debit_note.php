<?php
/**
 * Notas de débito (ND) na faturação eletrónica da AGT.
 *
 * A ND cobra um valor adicional sobre uma FT/FR já validada (juros, portes, correção de
 * preço para cima…). Segue a estrutura de uma fatura (linhas com creditAmount) e leva
 * referenceInfo para a fatura de origem + motivo. Numeração "ND <série ND>/<n>";
 * envio por POST /api/faturas/nota-debito e consulta por /api/faturas/estado.
 * O estado fica em debit_notes.agt_*.
 *
 * (Este ficheiro substitui uma cópia antiga e não usada de agt.php.)
 */

require_once __DIR__ . '/agt.php';
require_once __DIR__ . '/document_tax.php';

/** A fatura tem numeração AGT (e portanto a ND também tem de ir à AGT)? */
function agt_debit_note_required(PDO $pdo, array $invoice): bool
{
    $parsed = agt_parse_document_no($invoice['reference'] ?? null);
    return $parsed !== null
        && in_array($parsed[0], agt_invoice_document_types(), true)
        && agt_enabled($pdo, (int) $invoice['company_id']);
}

/** Próximo número da série ND por omissão da empresa (dentro da transação, FOR UPDATE). */
function agt_debit_note_next_number(PDO $pdo, int $companyId): array
{
    $series = agt_default_series($pdo, $companyId, 'ND', (int) date('Y'));
    if (!$series) {
        throw new RuntimeException(
            'A empresa ainda não tem série de Nota de Débito (ND) na AGT. Crie-a em Definições › Faturação eletrónica.',
            422
        );
    }
    $prefix = 'ND ' . $series['series_code'] . '/';
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED))
        FROM debit_notes
        WHERE company_id = ? AND reference LIKE ?
        FOR UPDATE
    ");
    $stmt->execute([$companyId, str_replace(['%', '_'], ['\%', '\_'], $prefix) . '%']);
    $next = max((int) $stmt->fetchColumn() + 1, max(1, (int) ($series['first_document_no'] ?? 1)));
    if (!empty($series['last_document_no']) && $next > (int) $series['last_document_no']) {
        throw new RuntimeException('A série ' . $series['series_code'] . ' esgotou a numeração autorizada pela AGT. Crie uma nova série ND.', 422);
    }
    return ['series_id' => (int) $series['id'], 'series_code' => $series['series_code'], 'number' => $next, 'reference' => $prefix . $next];
}

/** Pedido para POST /api/faturas/nota-debito a partir da ND gravada. */
function agt_build_debit_note_request(PDO $pdo, array $note, array $invoice, array $creds, string $exemptionCode): array
{
    $stmt = $pdo->prepare('SELECT name, contributor, country FROM contact WHERE id = ?');
    $stmt->execute([(int) $note['contact_id']]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT description, quantity, unit_price, discount, tax FROM debit_note_items WHERE debit_note_id = ? ORDER BY id');
    $stmt->execute([(int) $note['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) {
        throw new RuntimeException('A nota de débito não tem linhas.');
    }

    $stmt = $pdo->prepare('SELECT vat_regime FROM companies WHERE id = ?');
    $stmt->execute([(int) $note['company_id']]);
    $regime = (string) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?');
    $stmt->execute([(int) $invoice['id']]);
    $invoiceLineCount = max(1, (int) $stmt->fetchColumn());

    $reason = mb_substr(trim((string) ($note['reason'] ?? '')) ?: 'Débito adicional', 0, 200);
    $lines = [];
    $net = 0.0;
    $taxTotal = 0.0;
    foreach ($items as $index => $item) {
        $qty = (float) $item['quantity'];
        $unitBase = round((float) $item['unit_price'], 4);
        $discountPct = min(100.0, max(0.0, (float) $item['discount']));
        $unitPrice = round($unitBase * (1 - $discountPct / 100), 4);
        $amount = round($qty * $unitPrice, 2);
        $settlement = round($qty * $unitBase - $amount, 2);
        $rate = bx_document_effective_tax_rate($item['tax'], $regime);
        $tax = agt_round_up_cents($amount * $rate / 100);

        $line = [
            'operation_type' => 'SG',
            'product_code' => 'ND' . ($index + 1),
            'product_description' => mb_substr((string) ($item['description'] ?: 'Débito adicional'), 0, 200),
            'quantity' => $qty,
            'unit_of_measure' => 'UN',
            'unit_price_base' => $unitBase,
            'unit_price' => $unitPrice,
            'credit_amount' => $amount,
            'settlement_amount' => max(0.0, $settlement),
            'tax_type' => 'IVA',
            'tax_country_region' => 'AO',
            'tax_code' => $rate >= 14 ? 'NOR' : ($rate >= 7 ? 'INT' : ($rate > 0 ? 'RED' : 'ISE')),
            'tax_percentage' => $rate,
            'tax_contribution' => $tax,
            // Linha da fatura de origem a que o débito se refere: a ND não tem linhas ligadas,
            // por isso aponta para a linha correspondente (ou a última) da fatura.
            'reference_info' => [
                'reference' => $invoice['reference'],
                'reference_item_line_no' => min($index + 1, $invoiceLineCount),
                'reason' => $reason,
            ],
        ];
        if ($rate <= 0) {
            $line['tax_exemption_code'] = $exemptionCode;
        }
        $lines[] = $line;
        $net += $amount;
        $taxTotal += $tax;
    }
    $net = round($net, 2);
    $taxTotal = round($taxTotal, 2);

    $customerTaxId = preg_replace('/\s+/', '', (string) ($contact['contributor'] ?? ''));
    if ($customerTaxId === '') {
        $customerTaxId = agt_consumer_tax_id();
    }

    return $creds + [
        'document_no' => $note['reference'],
        'document_date' => substr((string) $note['issue_date'], 0, 10),
        'document_status' => 'N',
        'system_entry_date' => str_replace(' ', 'T', (string) $note['agt_system_entry_date']),
        'customer_tax_id' => $customerTaxId,
        'customer_name' => mb_substr((string) (($contact['name'] ?? '') ?: 'Consumidor final'), 0, 200),
        'customer_country' => agt_country_code($contact['country'] ?? ''),
        'net_total' => $net,
        'tax_payable' => $taxTotal,
        'gross_total' => round($net + $taxTotal, 2),
        'reference' => $invoice['reference'],
        'reason' => $reason,
        'lines' => $lines,
    ];
}

function agt_save_debit_note_state(PDO $pdo, int $noteId, array $fields): void
{
    $sets = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($fields)));
    $pdo->prepare("UPDATE debit_notes SET $sets WHERE id = ?")->execute([...array_values($fields), $noteId]);
}

/** ND + fatura de origem, ou null. */
function agt_debit_note_load(PDO $pdo, int $noteId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM debit_notes WHERE id = ?');
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

/** Envia uma ND à AGT. Nunca lança exceção; devolve null se a ND não tiver numeração AGT. */
function agt_submit_debit_note(PDO $pdo, int $noteId): ?array
{
    $loaded = agt_debit_note_load($pdo, $noteId);
    if (!$loaded) {
        return null;
    }
    [$note, $invoice] = $loaded;
    $parsed = agt_parse_document_no($note['reference'] ?? null);
    if (!$parsed || $parsed[0] !== 'ND' || !agt_enabled($pdo, (int) $note['company_id'])) {
        return null;
    }
    if (in_array($note['agt_status'], ['sent', 'valid'], true)) {
        return agt_debit_note_state($note);
    }

    if (empty($note['agt_system_entry_date'])) {
        $note['agt_system_entry_date'] = date('Y-m-d H:i:s');
    }
    $fields = ['agt_system_entry_date' => $note['agt_system_entry_date'], 'agt_sent_at' => date('Y-m-d H:i:s'), 'agt_checked_at' => null];
    try {
        $settings = agt_settings($pdo, (int) $note['company_id']);
        $creds = agt_credentials($pdo, (int) $note['company_id']);
        unset($creds['establishment']);
        $body = agt_build_debit_note_request($pdo, $note, $invoice, $creds, $settings['exemption_code'] ?: 'M00');
        $res = agt_api_post('/api/faturas/nota-debito', $body);
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
    agt_save_debit_note_state($pdo, $noteId, $fields);
    return agt_debit_note_state(array_merge($note, $fields));
}

/** Consulta na AGT a validação (assíncrona) de uma ND enviada. */
function agt_check_debit_note(PDO $pdo, int $noteId): ?array
{
    $loaded = agt_debit_note_load($pdo, $noteId);
    if (!$loaded) {
        return null;
    }
    [$note] = $loaded;
    if (empty($note['agt_request_id']) || $note['agt_status'] !== 'sent') {
        return agt_debit_note_state($note);
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
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode($errors ?: ['Nota de débito inválida.'], JSON_UNESCAPED_UNICODE)];
        } elseif ((string) ($r['resultCode'] ?? '') === '9') {
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode(['Processamento cancelado pela AGT.'], JSON_UNESCAPED_UNICODE)];
        }
    } else {
        $message = agt_error_message($res['data']);
        if (!preg_match('/\bE9[67]\b/', $message)) {
            $fields['agt_errors'] = json_encode([$message], JSON_UNESCAPED_UNICODE);
        }
    }
    agt_save_debit_note_state($pdo, $noteId, $fields);
    return agt_debit_note_state(array_merge($note, $fields));
}

/** Estado AGT de uma ND, pronto para o frontend. */
function agt_debit_note_state(array $note): array
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

/** Total das ND de uma fatura (soma ao valor em dívida). */
function bx_debit_notes_total(PDO $pdo, int $invoiceId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(final_total), 0) FROM debit_notes WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    return round((float) $stmt->fetchColumn(), 2);
}
