<?php
/**
 * Recibos (RC) na faturação eletrónica da AGT.
 *
 * Um recibo regulariza (total ou parcialmente) uma fatura FT já validada pela AGT.
 * Não tem linhas de artigos: leva o documento de origem em paymentReceipt.sourceDocuments.
 * A numeração segue o formato "RC <série RC>/<n>" e o envio/consulta é igual ao das faturas
 * (registarFactura / obterEstado na API Node), mas o estado fica em receipts.agt_*.
 */

require_once __DIR__ . '/agt.php';

/** A fatura foi emitida com numeração AGT (e portanto o recibo também tem de ir à AGT)? */
function agt_receipt_required(PDO $pdo, array $invoice): bool
{
    $parsed = agt_parse_document_no($invoice['reference'] ?? null);
    return $parsed !== null
        && $parsed[0] === 'FT'
        && agt_enabled($pdo, (int) $invoice['company_id']);
}

/**
 * Próximo número de recibo da série RC por omissão da empresa.
 * Devolve ['series_code' => 'RC…N', 'number' => 3, 'reference' => 'RC RC…N/3'].
 * Tem de correr dentro da transação que insere o recibo (FOR UPDATE).
 */
function agt_receipt_next_number(PDO $pdo, int $companyId): array
{
    $series = agt_default_series($pdo, $companyId, 'RC', (int) date('Y'));
    if (!$series) {
        throw new RuntimeException(
            'A empresa ainda não tem série de Recibo (RC) na AGT. Crie-a em Definições › Faturação eletrónica e volte a registar o pagamento.',
            422
        );
    }
    $prefix = 'RC ' . $series['series_code'] . '/';
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED))
        FROM receipts
        WHERE reference LIKE ?
        FOR UPDATE
    ");
    $stmt->execute([str_replace(['%', '_'], ['\%', '\_'], $prefix) . '%']);
    $next = max((int) $stmt->fetchColumn() + 1, max(1, (int) ($series['first_document_no'] ?? 1)));
    if (!empty($series['last_document_no']) && $next > (int) $series['last_document_no']) {
        throw new RuntimeException('A série ' . $series['series_code'] . ' esgotou a numeração autorizada pela AGT. Crie uma nova série RC.', 422);
    }
    return [
        'series_id' => (int) $series['id'],
        'series_code' => $series['series_code'],
        'number' => $next,
        'reference' => $prefix . $next,
    ];
}

/** Monta o pedido para POST /api/faturas (document_type RC). */
function agt_build_receipt_request(PDO $pdo, array $receipt, array $invoice, array $creds): array
{
    $stmt = $pdo->prepare('SELECT name, contributor, country FROM contact WHERE id = ?');
    $stmt->execute([(int) $invoice['contact_id']]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // O valor recebido reparte-se entre base e IVA na mesma proporção da fatura.
    $amount = round((float) $receipt['amount_paid'], 2);
    $invoiceTotal = (float) $invoice['final_total'];
    $share = $invoiceTotal > 0 ? min(1.0, $amount / $invoiceTotal) : 1.0;
    $tax = round((float) $invoice['total_tax'] * $share, 2);
    $net = round($amount - $tax, 2);

    $customerTaxId = preg_replace('/\s+/', '', (string) ($contact['contributor'] ?? ''));
    if ($customerTaxId === '') {
        $customerTaxId = agt_consumer_tax_id();
    }

    return $creds + [
        'document_type' => 'RC',
        'document_no' => $receipt['reference'],
        'document_date' => substr((string) $receipt['pay_date'], 0, 10),
        'document_status' => 'N',
        'system_entry_date' => str_replace(' ', 'T', (string) $receipt['agt_system_entry_date']),
        'customer_tax_id' => $customerTaxId,
        'customer_name' => mb_substr((string) (($contact['name'] ?? '') ?: 'Consumidor final'), 0, 200),
        'customer_country' => agt_country_code($contact['country'] ?? ''),
        'net_total' => $net,
        'tax_payable' => $tax,
        'gross_total' => $amount,
        'lines' => [],
        'payment_receipt' => [
            'source_documents' => [[
                'line_no' => '1',
                'source_document_id' => [
                    'originating_on' => $invoice['reference'],
                    'document_date' => substr((string) $invoice['issue_date'], 0, 10),
                ],
                'debit_amount' => '0',
                'credit_amount' => number_format($net, 2, '.', ''),
            ]],
        ],
    ];
}

function agt_save_receipt_state(PDO $pdo, int $receiptId, array $fields): void
{
    $sets = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($fields)));
    $pdo->prepare("UPDATE receipts SET $sets WHERE id = ?")->execute([...array_values($fields), $receiptId]);
}

/** Recibo + fatura de origem, ou null. */
function agt_receipt_load(PDO $pdo, int $receiptId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM receipts WHERE id = ?');
    $stmt->execute([$receiptId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([(int) $receipt['invoice_id']]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    return $invoice ? [$receipt, $invoice] : null;
}

/**
 * Envia um recibo à AGT. Nunca lança exceção: o resultado fica em receipts.agt_*
 * e é devolvido. Devolve null se o recibo não tiver numeração AGT.
 */
function agt_submit_receipt(PDO $pdo, int $receiptId): ?array
{
    $loaded = agt_receipt_load($pdo, $receiptId);
    if (!$loaded) {
        return null;
    }
    [$receipt, $invoice] = $loaded;
    $parsed = agt_parse_document_no($receipt['reference']);
    if (!$parsed || $parsed[0] !== 'RC' || !agt_enabled($pdo, (int) $invoice['company_id'])) {
        return null;
    }
    if (in_array($receipt['agt_status'], ['sent', 'valid'], true)) {
        return agt_receipt_state($receipt);
    }

    if (empty($receipt['agt_system_entry_date'])) {
        $receipt['agt_system_entry_date'] = date('Y-m-d H:i:s');
    }
    $fields = ['agt_system_entry_date' => $receipt['agt_system_entry_date'], 'agt_sent_at' => date('Y-m-d H:i:s'), 'agt_checked_at' => null];
    try {
        $creds = agt_credentials($pdo, (int) $invoice['company_id']);
        unset($creds['establishment']);
        $res = agt_api_post('/api/faturas', agt_build_receipt_request($pdo, $receipt, $invoice, $creds));
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
        $recovered = agt_recover_submission($pdo, (int) $invoice['company_id'], (string) $receipt['reference'], (string) (json_decode($fields['agt_errors'], true)[0] ?? ''));
        if ($recovered) {
            $fields = $recovered + $fields;
        }
    }
    agt_save_receipt_state($pdo, $receiptId, $fields);
    return agt_receipt_state(array_merge($receipt, $fields));
}

/** Consulta na AGT a validação de um recibo enviado (mesma lógica de agt_check_invoice). */
function agt_check_receipt(PDO $pdo, int $receiptId): ?array
{
    $loaded = agt_receipt_load($pdo, $receiptId);
    if (!$loaded) {
        return null;
    }
    [$receipt, $invoice] = $loaded;
    if (empty($receipt['agt_request_id']) || $receipt['agt_status'] !== 'sent') {
        return agt_receipt_state($receipt);
    }

    $creds = agt_credentials($pdo, (int) $invoice['company_id']);
    unset($creds['establishment']);
    $res = agt_api_post('/api/faturas/estado', $creds + ['requestID' => $receipt['agt_request_id']]);
    $r = $res['data']['resultado'] ?? [];
    $fields = ['agt_checked_at' => date('Y-m-d H:i:s')];

    if (!empty($res['data']['success'])) {
        $doc = null;
        foreach ((array) ($r['documentStatusList'] ?? []) as $d) {
            if (is_array($d) && ($d['documentNo'] ?? null) === $receipt['reference']) {
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
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode($errors ?: ['Recibo inválido.'], JSON_UNESCAPED_UNICODE)];
        } elseif ((string) ($r['resultCode'] ?? '') === '9') {
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode(['Processamento cancelado pela AGT.'], JSON_UNESCAPED_UNICODE)];
        }
    } else {
        $message = agt_error_message($res['data']);
        if (!preg_match('/\bE9[67]\b/', $message)) {
            $fields['agt_errors'] = json_encode([$message], JSON_UNESCAPED_UNICODE);
        }
    }
    agt_save_receipt_state($pdo, $receiptId, $fields);
    return agt_receipt_state(array_merge($receipt, $fields));
}

/** Estado AGT de um recibo, pronto para o frontend. */
function agt_receipt_state(array $receipt): array
{
    $labels = [
        'sent' => 'Enviado — a aguardar validação',
        'valid' => 'Validado pela AGT',
        'invalid' => 'Rejeitado pela AGT',
        'error' => 'Não foi enviado',
    ];
    $state = agt_invoice_state($receipt);
    $state['label'] = $labels[$state['status']] ?? 'Não enviado';
    return $state;
}
