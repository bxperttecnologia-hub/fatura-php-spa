<?php
/**
 * Faturação eletrónica AGT — chave privada do contribuinte e séries de numeração.
 *
 * A comunicação com a AGT é feita pela API Node (agt-facturacao-api), que assina
 * softwareInfo com a identidade do Software Produtor. Aqui guardamos, por empresa,
 * a chave privada do contribuinte (cifrada com AES-256-GCM e AGT_KEY_SECRET) e as
 * séries que a AGT atribuiu.
 */

require_once __DIR__ . '/../config/config.php';

/** Tipos de documento aceites pela AGT (solicitarSerie / listarSeries). */
function agt_document_types(): array
{
    return [
        'FT' => 'Fatura',
        'FR' => 'Fatura/Recibo',
        'NC' => 'Nota de Crédito',
        'ND' => 'Nota de Débito',
        'RG' => 'Recibo',
        'RC' => 'Recibo de Caixa',
        'FA' => 'Fatura de Adiantamento',
        'FG' => 'Fatura Global',
        'GF' => 'Fatura Genérica',
        'AC' => 'Aviso de Cobrança',
        'AR' => 'Aviso de Cobrança/Recibo',
        'TV' => 'Talão de Venda',
        'RE' => 'Estorno ou Recibo de Estorno',
        'AF' => 'Fatura/Recibo de Autofaturação',
        'RP' => 'Recibo de Prémio',
        'RA' => 'Resseguro Aceite',
        'CS' => 'Imputação a Cosseguradoras',
        'LD' => 'Imputação a Cosseguradora Líder',
    ];
}

/** Séries criadas automaticamente quando o cliente carrega a chave: os documentos que o BXpert emite. */
function agt_default_document_types(): array
{
    return ['FT', 'FR', 'NC', 'ND', 'RG'];
}

function agt_environment(): string
{
    return defined('AGT_ENVIRONMENT') && AGT_ENVIRONMENT === 'production' ? 'production' : 'test';
}

/** O servidor da AGT não respondeu (rede/indisponibilidade). Não é uma recusa: nada deve ser alterado. */
class AgtUnavailableException extends RuntimeException
{
}

/** A API Node não conseguiu ligar-se à AGT (marca AGT_UNREACHABLE, ver agtUtils.httpPost). */
function agt_unreachable(array $data): bool
{
    return str_starts_with((string) ($data['resultado']['error'] ?? ''), 'AGT_UNREACHABLE');
}

function agt_unavailable_message(): string
{
    return 'O servidor ' . (agt_environment() === 'test' ? 'de testes (HML) ' : '')
        . 'da AGT não está a responder neste momento. Nada foi alterado; tente novamente dentro de alguns minutos.';
}

function agt_secret(): string
{
    $secret = defined('AGT_KEY_SECRET') ? base64_decode((string) AGT_KEY_SECRET, true) : false;
    if ($secret === false || strlen($secret) !== 32) {
        throw new RuntimeException('AGT_KEY_SECRET não está configurado (32 bytes em base64) em app/config/config.php.');
    }
    return $secret;
}

function agt_encrypt(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', agt_secret(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Não foi possível cifrar a chave privada.');
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function agt_decrypt(string $stored): string
{
    $raw = str_starts_with($stored, 'v1:') ? base64_decode(substr($stored, 3), true) : false;
    $plain = $raw === false || strlen($raw) < 29
        ? false
        : openssl_decrypt(substr($raw, 28), 'aes-256-gcm', agt_secret(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    if ($plain === false) {
        throw new RuntimeException('Não foi possível abrir a chave privada guardada. Volte a carregar a chave.');
    }
    return $plain;
}

/**
 * Valida o PEM de uma chave privada RSA (≥ 2048 bits) e devolve-o normalizado,
 * com a impressão digital (SHA-256 da chave pública) e o tamanho.
 */
function agt_parse_private_key(string $pem): array
{
    $pem = trim(str_replace(["\r\n", "\r"], "\n", $pem));
    if (!str_contains($pem, '-----BEGIN') || !str_contains($pem, 'PRIVATE KEY-----')) {
        throw new InvalidArgumentException('O ficheiro não contém uma chave privada PEM (-----BEGIN PRIVATE KEY-----).');
    }
    $key = openssl_pkey_get_private($pem);
    if ($key === false) {
        throw new InvalidArgumentException('Chave privada inválida ou protegida por palavra-passe.');
    }
    $details = openssl_pkey_get_details($key);
    if (($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
        throw new InvalidArgumentException('A AGT exige uma chave RSA.');
    }
    if (($details['bits'] ?? 0) < 2048) {
        throw new InvalidArgumentException('A AGT exige uma chave RSA de pelo menos 2048 bits.');
    }
    // Guarda o PEM tal como veio (PKCS#1 ou PKCS#8 — a API Node aceita ambos). Chaves com
    // palavra-passe já foram recusadas acima, por isso o PEM nunca vai cifrado para a API.
    return [
        'pem' => $pem . "\n",
        'bits' => (int) $details['bits'],
        'fingerprint' => hash('sha256', $details['key']),
    ];
}

function agt_settings(PDO $pdo, int $companyId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM agt_settings WHERE company_id = ?');
    $stmt->execute([$companyId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Dados para assinar pedidos à AGT em nome da empresa (NIF + chave já decifrada). */
function agt_credentials(PDO $pdo, int $companyId): array
{
    $settings = agt_settings($pdo, $companyId);
    if (!$settings) {
        throw new RuntimeException('Carregue primeiro a chave privada da empresa (Faturação Eletrónica AGT).');
    }
    $stmt = $pdo->prepare('SELECT name FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);

    return [
        'tax_id' => $settings['tax_id'],
        'private_key' => agt_decrypt($settings['private_key_enc']),
        'establishment' => $settings['establishment'] ?: 'SEDE',
        'test' => $settings['environment'] !== 'production',
        'nome_empresa' => (string) $stmt->fetchColumn(),
    ];
}

/**
 * POST JSON à API Node. Devolve ['status' => int, 'data' => array].
 * Erros de ligação lançam RuntimeException com mensagem para o utilizador.
 */
function agt_api_post(string $path, array $body, int $timeout = 60): array
{
    if (!defined('AGT_API_URL') || !defined('AGT_API_KEY') || AGT_API_KEY === '') {
        throw new RuntimeException('AGT_API_URL / AGT_API_KEY não estão configurados em app/config/config.php.');
    }
    $curl = curl_init(rtrim(AGT_API_URL, '/') . $path);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'x-api-key: ' . AGT_API_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        error_log('AGT API: ' . $error);
        throw new RuntimeException('O serviço de faturação eletrónica (API AGT) não está disponível.');
    }
    $data = json_decode((string) $response, true);
    return ['status' => $status, 'data' => is_array($data) ? $data : []];
}

/** Mensagem legível a partir da resposta da API Node (erros de validação ou da AGT). */
function agt_error_message(array $data): string
{
    if (agt_unreachable($data)) {
        return agt_unavailable_message();
    }
    $resultado = $data['resultado'] ?? [];
    if (!empty($resultado['error'])) {
        return (string) $resultado['error'];
    }
    if (!empty($data['error'])) {
        return is_string($data['error']) ? $data['error'] : json_encode($data['error'], JSON_UNESCAPED_UNICODE);
    }
    if (!empty($data['message'])) {
        return (string) $data['message'];
    }
    return 'A AGT não aceitou o pedido.';
}

/** Insere ou atualiza uma série vinda da AGT (solicitarSerie ou listarSeries). */
function agt_upsert_series(PDO $pdo, int $companyId, string $environment, array $s, ?int $userId = null): void
{
    $stmt = $pdo->prepare("
        INSERT INTO agt_series
            (company_id, environment, series_code, document_type, series_year, establishment, contingency, status,
             invoicing_method, first_document_no, last_document_no, last_document_created, authorized_quantity,
             created_by, synced_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = COALESCE(VALUES(status), status),
            invoicing_method = COALESCE(VALUES(invoicing_method), invoicing_method),
            first_document_no = COALESCE(VALUES(first_document_no), first_document_no),
            last_document_no = COALESCE(VALUES(last_document_no), last_document_no),
            last_document_created = COALESCE(VALUES(last_document_created), last_document_created),
            authorized_quantity = COALESCE(VALUES(authorized_quantity), authorized_quantity),
            synced_at = NOW()
    ");
    $stmt->execute([
        $companyId,
        $environment,
        $s['series_code'],
        $s['document_type'],
        (int) $s['series_year'],
        $s['establishment'] ?? 'SEDE',
        $s['contingency'] ?? 'N',
        $s['status'] ?? null,
        $s['invoicing_method'] ?? null,
        $s['first_document_no'] ?? null,
        $s['last_document_no'] ?? null,
        $s['last_document_created'] ?? null,
        $s['authorized_quantity'] ?? null,
        $userId,
    ]);

    // A primeira série de cada tipo/ano passa a ser a usada por omissão.
    $pdo->prepare("
        UPDATE agt_series s
        SET s.is_default = 1
        WHERE s.company_id = ? AND s.environment = ? AND s.series_code = ?
          AND NOT EXISTS (
              SELECT 1 FROM (SELECT id FROM agt_series
                             WHERE company_id = ? AND environment = ? AND document_type = ? AND series_year = ? AND is_default = 1) d
          )
    ")->execute([$companyId, $environment, $s['series_code'], $companyId, $environment, $s['document_type'], (int) $s['series_year']]);
}

function agt_list_local_series(PDO $pdo, int $companyId, string $environment): array
{
    $stmt = $pdo->prepare("
        SELECT id, series_code, document_type, series_year, establishment, contingency, status, invoicing_method,
               first_document_no, last_document_no, last_document_created, authorized_quantity, is_default,
               created_at, synced_at
        FROM agt_series
        WHERE company_id = ? AND environment = ?
        ORDER BY series_year DESC, FIELD(document_type, 'FT', 'FR', 'NC', 'ND', 'RG') = 0, FIELD(document_type, 'FT', 'FR', 'NC', 'ND', 'RG'), document_type, id
    ");
    $stmt->execute([$companyId, $environment]);
    $types = agt_document_types();
    return array_map(static function (array $row) use ($types): array {
        $row['document_label'] = $types[$row['document_type']] ?? $row['document_type'];
        $row['is_default'] = (bool) $row['is_default'];
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ======================================================================
 * Envio de faturas (registarFactura) e consulta do estado (obterEstado)
 * ====================================================================== */

/** Tipos de documento da tabela invoices que já seguem para a AGT. */
function agt_invoice_document_types(): array
{
    return ['FT', 'FR'];
}

/**
 * A empresa tem a faturação eletrónica ativa? Por agora só em HML: em produção
 * a emissão continua com a numeração interna até o envio ser validado.
 */
function agt_enabled(PDO $pdo, int $companyId): bool
{
    if (agt_environment() !== 'test') {
        return false;
    }
    $settings = agt_settings($pdo, $companyId);
    return $settings !== null && $settings['environment'] === 'test';
}

/** Série por omissão (aberta) da empresa para o tipo e ano. */
function agt_default_series(PDO $pdo, int $companyId, string $docType, int $year): ?array
{
    $stmt = $pdo->prepare("
        SELECT * FROM agt_series
        WHERE company_id = ? AND environment = ? AND document_type = ? AND series_year = ?
          AND contingency = 'N' AND (status IS NULL OR status <> 'F')
        ORDER BY is_default DESC, id
        LIMIT 1
    ");
    $stmt->execute([$companyId, agt_environment(), strtoupper($docType), $year]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Número do documento no formato SAF-T(AO) exigido pela AGT: "<tipo> <série>/<sequência>"
 * (ex.: "FT FT7826S1N/1"). Devolve null se a empresa não usar a AGT ou não tiver série.
 */
function agt_issue_number(PDO $pdo, int $companyId, string $docType): ?string
{
    $docType = strtoupper($docType);
    if (!in_array($docType, agt_invoice_document_types(), true) || !agt_enabled($pdo, $companyId)) {
        return null;
    }
    $series = agt_default_series($pdo, $companyId, $docType, (int) date('Y'));
    if (!$series) {
        return null;
    }
    $prefix = $docType . ' ' . $series['series_code'] . '/';
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(reference, '/', -1) AS UNSIGNED))
        FROM invoices
        WHERE company_id = ? AND reference LIKE ?
        FOR UPDATE
    ");
    $stmt->execute([$companyId, str_replace(['%', '_'], ['\%', '\_'], $prefix) . '%']);
    $first = max(1, (int) ($series['first_document_no'] ?? 1));
    $next = max((int) $stmt->fetchColumn() + 1, $first);
    if (!empty($series['last_document_no']) && $next > (int) $series['last_document_no']) {
        throw new RuntimeException('A série ' . $series['series_code'] . ' esgotou a numeração autorizada pela AGT. Crie uma nova série.');
    }
    return $prefix . $next;
}

/** "FT FT7826S1N/12" → ['FT', 'FT7826S1N', 12]; null se não for numeração AGT. */
function agt_parse_document_no(?string $reference): ?array
{
    if ($reference === null || !preg_match('~^([A-Z]{2}) ([A-Za-z0-9]{3,60})/(\d+)$~', $reference, $m)) {
        return null;
    }
    return [$m[1], $m[2], (int) $m[3]];
}

/** Arredonda por excesso ao cêntimo (regra da AGT para taxContribution). */
function agt_round_up_cents(float $value): float
{
    return ceil(round($value * 100, 6)) / 100;
}

function agt_country_code(?string $country): string
{
    $country = trim((string) $country);
    if (preg_match('/^[A-Za-z]{2}$/', $country)) {
        return strtoupper($country);
    }
    $map = [
        'angola' => 'AO', 'portugal' => 'PT', 'brasil' => 'BR', 'brazil' => 'BR', 'mocambique' => 'MZ',
        'moçambique' => 'MZ', 'cabo verde' => 'CV', 'sao tome e principe' => 'ST', 'são tomé e príncipe' => 'ST',
        'namibia' => 'NA', 'namíbia' => 'NA', 'africa do sul' => 'ZA', 'áfrica do sul' => 'ZA', 'espanha' => 'ES',
        'franca' => 'FR', 'frança' => 'FR', 'china' => 'CN', 'estados unidos' => 'US', 'reino unido' => 'GB',
        'andorra' => 'AD', 'congo' => 'CG', 'rd congo' => 'CD', 'zambia' => 'ZM', 'zâmbia' => 'ZM',
    ];
    return $map[mb_strtolower($country, 'UTF-8')] ?? 'AO';
}

function agt_unit_of_measure(?string $unit): string
{
    return [
        'unit' => 'UN', 'kg' => 'KG', 'g' => 'G', 'liter' => 'L', 'meter' => 'M',
        'box' => 'CX', 'pack' => 'PCT', 'service' => 'UN',
    ][(string) $unit] ?? 'UN';
}

/** Monta o pedido para POST /api/faturas a partir de uma fatura emitida. */
function agt_build_invoice_request(PDO $pdo, array $invoice, array $creds, string $exemptionCode): array
{
    require_once __DIR__ . '/document_tax.php';

    $stmt = $pdo->prepare('SELECT name, contributor, country FROM contact WHERE id = ?');
    $stmt->execute([(int) $invoice['contact_id']]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare('SELECT vat_regime FROM companies WHERE id = ?');
    $stmt->execute([(int) $invoice['company_id']]);
    $regime = (string) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT ii.quantity, ii.unit_price, ii.tax, ii.discount,
               i.code, i.name, i.unit_measure, i.item_type
        FROM invoice_items ii
        LEFT JOIN items i ON i.id = ii.item_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.id
    ");
    $stmt->execute([(int) $invoice['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) {
        throw new RuntimeException('A fatura não tem linhas.');
    }

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
            'operation_type' => $item['item_type'] === 'service' || $item['unit_measure'] === 'service' ? 'SG' : 'TB',
            'product_code' => (string) ($item['code'] ?: ('ITEM' . ($index + 1))),
            'product_description' => mb_substr((string) ($item['name'] ?: 'Artigo'), 0, 200),
            'quantity' => $qty,
            'unit_of_measure' => agt_unit_of_measure($item['unit_measure']),
            'unit_price_base' => $unitBase,
            'unit_price' => $unitPrice,
            'credit_amount' => $amount,
            'settlement_amount' => max(0.0, $settlement),
            'tax_type' => 'IVA',
            'tax_country_region' => 'AO',
            'tax_code' => $rate >= 14 ? 'NOR' : ($rate >= 7 ? 'INT' : ($rate > 0 ? 'RED' : 'ISE')),
            'tax_percentage' => $rate,
            'tax_contribution' => $tax,
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
    $body = $creds + [
        'document_type' => strtoupper($invoice['document_type'] ?: 'FT'),
        'document_no' => $invoice['reference'],
        'document_date' => substr((string) $invoice['issue_date'], 0, 10),
        'document_status' => 'N',
        'system_entry_date' => str_replace(' ', 'T', (string) $invoice['agt_system_entry_date']),
        'customer_tax_id' => $customerTaxId !== '' ? $customerTaxId : '999999999',
        'customer_name' => mb_substr((string) (($contact['name'] ?? '') ?: 'Consumidor final'), 0, 200),
        'customer_country' => agt_country_code($contact['country'] ?? ''),
        'net_total' => $net,
        'tax_payable' => $taxTotal,
        'gross_total' => round($net + $taxTotal, 2),
        'lines' => $lines,
    ];
    $retention = round((float) ($invoice['retention_value'] ?? 0), 2);
    if ($retention > 0) {
        $body['withholding_tax_list'] = [[
            'type' => 'II',
            'amount' => $retention,
            'description' => 'Retenção na fonte (Imposto Industrial)',
        ]];
    }
    return $body;
}

function agt_save_invoice_state(PDO $pdo, int $invoiceId, array $fields): void
{
    $sets = implode(', ', array_map(static fn ($k) => "`$k` = ?", array_keys($fields)));
    $pdo->prepare("UPDATE invoices SET $sets WHERE id = ?")->execute([...array_values($fields), $invoiceId]);
}

/**
 * Envia uma fatura já emitida à AGT. Nunca lança exceção: o resultado fica em
 * invoices.agt_* e é devolvido. Devolve null se o documento não for da AGT.
 */
function agt_submit_invoice(PDO $pdo, int $invoiceId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    $parsed = $invoice ? agt_parse_document_no($invoice['reference']) : null;
    if (!$invoice || !$parsed || !agt_enabled($pdo, (int) $invoice['company_id'])) {
        return null;
    }
    if (in_array($invoice['agt_status'], ['sent', 'valid'], true)) {
        return agt_invoice_state($invoice);
    }

    if (empty($invoice['agt_system_entry_date'])) {
        $invoice['agt_system_entry_date'] = date('Y-m-d H:i:s');
    }
    $fields = ['agt_system_entry_date' => $invoice['agt_system_entry_date'], 'agt_sent_at' => date('Y-m-d H:i:s'), 'agt_checked_at' => null];
    try {
        $stmt = $pdo->prepare('SELECT id FROM agt_series WHERE company_id = ? AND environment = ? AND series_code = ?');
        $stmt->execute([(int) $invoice['company_id'], agt_environment(), $parsed[1]]);
        $fields['agt_series_id'] = $stmt->fetchColumn() ?: null;

        $currency = strtoupper(trim((string) ($invoice['currency'] ?? '')));
        if ($currency !== '' && $currency !== 'AOA' && $currency !== 'KZ') {
            throw new RuntimeException('Faturas em moeda estrangeira ainda não são enviadas à AGT.');
        }

        $settings = agt_settings($pdo, (int) $invoice['company_id']);
        $creds = agt_credentials($pdo, (int) $invoice['company_id']);
        unset($creds['establishment']);
        $body = agt_build_invoice_request($pdo, $invoice, $creds, $settings['exemption_code'] ?: 'M00');
        $res = agt_api_post('/api/faturas', $body);
        $requestId = $res['data']['resultado']['requestId'] ?? null;

        if (!empty($res['data']['success']) && $requestId) {
            $fields += ['agt_status' => 'sent', 'agt_request_id' => (string) $requestId, 'agt_errors' => null];
        } else {
            $fields += ['agt_status' => 'error', 'agt_errors' => json_encode([agt_error_message($res['data'])], JSON_UNESCAPED_UNICODE)];
        }
    } catch (Throwable $e) {
        $fields += ['agt_status' => 'error', 'agt_errors' => json_encode([$e->getMessage()], JSON_UNESCAPED_UNICODE)];
    }
    agt_save_invoice_state($pdo, $invoiceId, $fields);
    return agt_invoice_state(array_merge($invoice, $fields));
}

/**
 * Consulta na AGT o resultado da validação (assíncrona) de uma fatura enviada.
 * resultCode: 0/1/2 concluído, 7 pedido prematuro, 8 em processamento, 9 cancelado.
 */
function agt_check_invoice(PDO $pdo, int $invoiceId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice || empty($invoice['agt_request_id']) || $invoice['agt_status'] !== 'sent') {
        return $invoice ? agt_invoice_state($invoice) : null;
    }

    $creds = agt_credentials($pdo, (int) $invoice['company_id']);
    unset($creds['establishment']);
    $res = agt_api_post('/api/faturas/estado', $creds + ['requestID' => $invoice['agt_request_id']]);
    $r = $res['data']['resultado'] ?? [];
    $fields = ['agt_checked_at' => date('Y-m-d H:i:s')];

    if (!empty($res['data']['success'])) {
        $doc = null;
        foreach ((array) ($r['documentStatusList'] ?? []) as $d) {
            if (is_array($d) && ($d['documentNo'] ?? null) === $invoice['reference']) {
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
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode($errors ?: ['Fatura inválida.'], JSON_UNESCAPED_UNICODE)];
        } elseif ((string) ($r['resultCode'] ?? '') === '9') {
            $fields += ['agt_status' => 'invalid', 'agt_errors' => json_encode(['Processamento cancelado pela AGT.'], JSON_UNESCAPED_UNICODE)];
        }
        // 7 (prematuro) / 8 (em processamento): continua "sent".
    } else {
        $message = agt_error_message($res['data']);
        // E96/E97: ainda em processamento / pedido prematuro — volta-se a consultar mais tarde.
        if (!preg_match('/\bE9[67]\b/', $message)) {
            $fields['agt_errors'] = json_encode([$message], JSON_UNESCAPED_UNICODE);
        }
    }
    agt_save_invoice_state($pdo, $invoiceId, $fields);
    return agt_invoice_state(array_merge($invoice, $fields));
}

/** Estado AGT de uma fatura, pronto para o frontend. */
function agt_invoice_state(array $invoice): array
{
    $labels = [
        'sent' => 'Enviada — a aguardar validação',
        'valid' => 'Validada pela AGT',
        'invalid' => 'Rejeitada pela AGT',
        'error' => 'Não foi enviada',
    ];
    $status = $invoice['agt_status'] ?? null;
    return [
        'status' => $status,
        'label' => $labels[$status] ?? 'Não enviada',
        'document_no' => $invoice['reference'] ?? null,
        'request_id' => $invoice['agt_request_id'] ?? null,
        'errors' => !empty($invoice['agt_errors']) ? (json_decode($invoice['agt_errors'], true) ?: [$invoice['agt_errors']]) : [],
        'sent_at' => $invoice['agt_sent_at'] ?? null,
        'checked_at' => $invoice['agt_checked_at'] ?? null,
    ];
}
