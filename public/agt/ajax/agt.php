<?php

/**
 * Faturação eletrónica AGT — chave privada da empresa e séries de numeração.
 *
 *   GET  ?action=status        configuração + séries guardadas
 *   POST ?action=save_key      carrega a chave privada (ficheiro "key" ou campo "key_text") e valida-a na AGT
 *   POST ?action=create_series cria séries na AGT (types[] ou "defaults=1" para as séries base)
 *   POST ?action=sync          atualiza as séries a partir da AGT (listarSeries)
 *   POST ?action=set_default   série usada por omissão para o tipo/ano (id)
 *   POST ?action=save_options  código de isenção por omissão (exemption_code)
 *   GET  ?action=invoice&id=   estado AGT de uma fatura (consulta a AGT se ainda estiver pendente)
 *   POST ?action=invoice_send  (re)envia uma fatura que não chegou à AGT (id)
 *   GET  ?action=receipt&id=   estado AGT de um recibo (RC)
 *   POST ?action=receipt_send  (re)envia um recibo que não chegou à AGT (id)
 *   GET  ?action=credit_note&id=   estado AGT de uma nota de crédito (NC)
 *   POST ?action=credit_note_send  (re)envia uma NC que não chegou à AGT (id)
 *   GET  ?action=debit_note&id=    estado AGT de uma nota de débito (ND)
 *   POST ?action=debit_note_send   (re)envia uma ND que não chegou à AGT (id)
 *   GET  ?action=documents     últimas faturas com numeração AGT
 *   POST ?action=check_pending consulta o estado das faturas pendentes
 */
require_once __DIR__ . '/../../../app/helpers/require_login.php';
require_once __DIR__ . '/../../../app/helpers/agt.php';
require_once __DIR__ . '/../../../app/helpers/agt_receipt.php';
require_once __DIR__ . '/../../../app/helpers/agt_credit_note.php';
require_once __DIR__ . '/../../../app/helpers/agt_debit_note.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function agt_respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

$companyId = session_company_id();
$userId = (int) ($_SESSION['user']['id'] ?? 0);
$environment = agt_environment();
$action = (string) ($_GET['action'] ?? '');
$allowedMethods = [
    'status' => 'GET',
    'invoice' => 'GET',
    'documents' => 'GET',
    'receipt' => 'GET',
    'receipt_send' => 'POST',
    'credit_note' => 'GET',
    'credit_note_send' => 'POST',
    'debit_note' => 'GET',
    'debit_note_send' => 'POST',
    'save_key' => 'POST',
    'create_series' => 'POST',
    'sync' => 'POST',
    'set_default' => 'POST',
    'save_options' => 'POST',
    'invoice_send' => 'POST',
    'check_pending' => 'POST',
];

if (!isset($allowedMethods[$action]) || $_SERVER['REQUEST_METHOD'] !== $allowedMethods[$action]) {
    agt_respond(405, ['success' => false, 'message' => 'Método ou ação inválida.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expected = (string) ($_SESSION['csrf'] ?? '');
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || !hash_equals($expected, $provided)) {
        agt_respond(403, ['success' => false, 'message' => 'Sessão expirada. Atualize a página.']);
    }
}

/** Pede uma série à AGT e guarda-a. Devolve o resultado por tipo. */
function agt_request_series(PDO $pdo, int $companyId, ?int $userId, string $environment, array $creds, string $type, int $year, string $establishment, string $contingency): array
{
    $res = agt_api_post('/api/series', [
        'tax_id' => $creds['tax_id'],
        'private_key' => $creds['private_key'],
        'nome_empresa' => $creds['nome_empresa'],
        'test' => $creds['test'],
        'document_type' => $type,
        'year' => (string) $year,
        'establishment' => $establishment,
        'contingency_indicator' => $contingency,
    ]);
    if (agt_unreachable($res['data'])) {
        throw new AgtUnavailableException(agt_unavailable_message());
    }
    $r = $res['data']['resultado'] ?? [];
    if (empty($res['data']['success']) || empty($r['seriesCode'])) {
        return ['document_type' => $type, 'success' => false, 'message' => agt_error_message($res['data'])];
    }
    agt_upsert_series($pdo, $companyId, $environment, [
        'series_code' => $r['seriesCode'],
        'document_type' => $type,
        'series_year' => $year,
        'establishment' => $establishment,
        'contingency' => $contingency,
        'status' => 'A',
        'invoicing_method' => 'FESF',
        'first_document_no' => $r['firstDocumentNo'] ?? null,
        'last_document_no' => $r['lastDocumentNo'] ?? null,
        'authorized_quantity' => isset($r['authorizedQuantity']) ? (string) $r['authorizedQuantity'] : null,
    ], $userId);

    return ['document_type' => $type, 'success' => true, 'series_code' => $r['seriesCode']];
}

/** listarSeries na AGT → guarda localmente. Devolve o nº de séries recebidas. */
function agt_sync_series(PDO $pdo, int $companyId, string $environment, array $creds): int
{
    $res = agt_api_post('/api/series/listar', [
        'tax_id' => $creds['tax_id'],
        'private_key' => $creds['private_key'],
        'nome_empresa' => $creds['nome_empresa'],
        'test' => $creds['test'],
    ]);
    if (agt_unreachable($res['data'])) {
        throw new AgtUnavailableException(agt_unavailable_message());
    }
    if (empty($res['data']['success'])) {
        throw new RuntimeException(agt_error_message($res['data']));
    }
    $series = $res['data']['resultado']['series'] ?? [];
    // Só atualiza as séries que este software já tem. A AGT só aceita faturas do software que
    // criou a série (E36), por isso séries criadas noutro software não são importadas.
    $known = array_flip(array_column(agt_list_local_series($pdo, $companyId, $environment), 'series_code'));
    $updated = 0;
    foreach ($series as $s) {
        if (empty($s['seriesCode']) || empty($s['documentType']) || !isset($known[$s['seriesCode']])) {
            continue;
        }
        $updated++;
        agt_upsert_series($pdo, $companyId, $environment, [
            'series_code' => $s['seriesCode'],
            'document_type' => strtoupper($s['documentType']),
            'series_year' => (int) ($s['seriesYear'] ?? date('Y')),
            'establishment' => $creds['establishment'],
            'contingency' => $s['seriesContingencyIndicator'] ?? 'N',
            'status' => $s['seriesStatus'] ?? null,
            'invoicing_method' => $s['invoicingMethod'] ?? null,
            'first_document_no' => $s['firstDocumentApproved'] ?? null,
            'last_document_no' => $s['lastDocumentApproved'] ?? null,
            'last_document_created' => $s['lastDocumentCreated'] ?? null,
        ]);
    }
    return $updated;
}

/** Tipos base que ainda não têm série normal ativa (não fechada) no ano indicado. */
function agt_missing_default_types(PDO $pdo, int $companyId, string $environment, int $year): array
{
    $existing = array_column(array_filter(
        agt_list_local_series($pdo, $companyId, $environment),
        static fn ($s) => (int) $s['series_year'] === $year && $s['contingency'] === 'N' && $s['status'] !== 'F'
    ), 'document_type');
    return array_values(array_diff(agt_default_document_types(), $existing));
}

function agt_status_payload(PDO $pdo, int $companyId, string $environment): array
{
    $settings = agt_settings($pdo, $companyId);
    $stmt = $pdo->prepare('SELECT name, registration_number FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'success' => true,
        'environment' => $environment,
        'company' => ['name' => $company['name'] ?? '', 'nif' => $company['registration_number'] ?? ''],
        'settings' => $settings ? [
            'tax_id' => $settings['tax_id'],
            'establishment' => $settings['establishment'],
            'exemption_code' => $settings['exemption_code'],
            'key_fingerprint' => substr($settings['key_fingerprint'], 0, 16),
            'key_bits' => (int) $settings['key_bits'],
            'environment' => $settings['environment'],
            'updated_at' => $settings['updated_at'],
        ] : null,
        'document_types' => agt_document_types(),
        'default_types' => agt_default_document_types(),
        'series' => agt_list_local_series($pdo, $companyId, $environment),
        'year' => (int) date('Y'),
        // Depois de 15/12 a AGT aceita também séries do ano seguinte.
        'years' => (int) date('md') > 1215 ? [(int) date('Y'), (int) date('Y') + 1] : [(int) date('Y')],
    ];
}

try {
    switch ($action) {
        case 'status':
            agt_respond(200, agt_status_payload($pdo, $companyId, $environment));

        case 'save_key':
            $taxId = preg_replace('/\s+/', '', (string) ($_POST['tax_id'] ?? ''));
            $establishment = trim((string) ($_POST['establishment'] ?? '')) ?: 'SEDE';
            if (!preg_match('/^[A-Za-z0-9]{6,15}$/', $taxId)) {
                agt_respond(422, ['success' => false, 'message' => 'Indique o NIF da empresa (até 15 caracteres).']);
            }
            $pem = '';
            if (!empty($_FILES['key']['tmp_name']) && is_uploaded_file($_FILES['key']['tmp_name'])) {
                if ((int) $_FILES['key']['size'] > 64 * 1024) {
                    agt_respond(422, ['success' => false, 'message' => 'Ficheiro demasiado grande para uma chave privada.']);
                }
                $pem = (string) file_get_contents($_FILES['key']['tmp_name']);
            } else {
                $pem = (string) ($_POST['key_text'] ?? '');
            }
            try {
                $key = agt_parse_private_key($pem);
            } catch (InvalidArgumentException $e) {
                agt_respond(422, ['success' => false, 'message' => $e->getMessage()]);
            }

            $previous = agt_settings($pdo, $companyId);
            $pdo->prepare("
                INSERT INTO agt_settings (company_id, environment, tax_id, establishment, private_key_enc, key_fingerprint, key_bits, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE environment = VALUES(environment), tax_id = VALUES(tax_id),
                    establishment = VALUES(establishment), private_key_enc = VALUES(private_key_enc),
                    key_fingerprint = VALUES(key_fingerprint), key_bits = VALUES(key_bits), updated_by = VALUES(updated_by)
            ")->execute([$companyId, $environment, $taxId, $establishment, agt_encrypt($key['pem']), $key['fingerprint'], $key['bits'], $userId]);

            // Repõe a chave anterior (ou nenhuma) quando a AGT não valida a nova.
            $restorePrevious = static function () use ($pdo, $previous, $companyId): void {
                if ($previous) {
                    $pdo->prepare('UPDATE agt_settings SET environment = ?, tax_id = ?, establishment = ?, private_key_enc = ?, key_fingerprint = ?, key_bits = ? WHERE company_id = ?')
                        ->execute([$previous['environment'], $previous['tax_id'], $previous['establishment'], $previous['private_key_enc'], $previous['key_fingerprint'], $previous['key_bits'], $companyId]);
                } else {
                    $pdo->prepare('DELETE FROM agt_settings WHERE company_id = ?')->execute([$companyId]);
                }
            };

            $syncError = null;
            $created = [];
            try {
                $creds = agt_credentials($pdo, $companyId);

                // Séries que a empresa já tenha na AGT (ex.: criadas noutro software ou numa carga anterior).
                try {
                    agt_sync_series($pdo, $companyId, $environment, $creds);
                } catch (AgtUnavailableException | AgtApiConfigException $e) {
                    throw $e;
                } catch (RuntimeException $e) {
                    $syncError = $e->getMessage();
                }

                // Cria as séries base que ainda não existam para o ano corrente.
                $year = (int) date('Y');
                foreach (agt_missing_default_types($pdo, $companyId, $environment, $year) as $type) {
                    $created[] = agt_request_series($pdo, $companyId, $userId, $environment, $creds, $type, $year, $establishment, 'N');
                }
            } catch (AgtUnavailableException $e) {
                // A AGT não respondeu: não se sabe se a chave é válida, por isso não fica guardada.
                $restorePrevious();
                agt_respond(503, ['success' => false, 'unavailable' => true, 'message' => $e->getMessage()]);
            } catch (AgtApiConfigException $e) {
                // A API adaptadora recusou a AGT_API_KEY: problema de configuração, a chave não foi sequer avaliada.
                $restorePrevious();
                agt_respond(502, ['success' => false, 'config_error' => true, 'message' => $e->getMessage()]);
            } catch (Throwable $e) {
                // Qualquer outra falha inesperada: não deixa a chave guardada sem validação.
                $restorePrevious();
                throw $e;
            }

            // A chave só fica guardada se a AGT aceitar pelo menos uma operação assinada com ela
            // (a AGT valida a adesão (E06) antes da assinatura, por isso um erro não prova que a chave é boa).
            $accepted = $syncError === null || count(array_filter($created, static fn($c) => $c['success'])) > 0;
            if (!$accepted) {
                $restorePrevious();
                $errors = array_values(array_unique(array_filter(array_merge(
                    [$syncError],
                    array_map(static fn($c) => $c['message'] ?? null, $created)
                ))));
                // E40 = a jwsSignature não confere: a chave não é a registada na AGT para este NIF.
                $signatureRejected = count(array_filter($errors, static fn($m) => str_contains($m, 'E40'))) > 0;
                agt_respond(422, [
                    'success' => false,
                    'message' => $signatureRejected
                        ? 'A AGT não reconheceu esta chave para o NIF ' . $taxId . '. Confirme que é a chave privada da empresa obtida no '
                        . 'Portal do Contribuinte' . ($environment === 'test' ? ' (ambiente de testes/HML)' : '') . '.'
                        : 'A AGT recusou os pedidos, por isso a chave não foi guardada.',
                    'errors' => $errors,
                ]);
            }

            agt_respond(200, agt_status_payload($pdo, $companyId, $environment) + [
                'message' => 'Chave guardada.',
                'sync_error' => $syncError,
                'created' => $created,
            ]);

        case 'create_series':
            $creds = agt_credentials($pdo, $companyId);
            $year = (int) ($_POST['year'] ?? date('Y'));
            $allowedYears = (int) date('md') > 1215 ? [(int) date('Y'), (int) date('Y') + 1] : [(int) date('Y')];
            if (!in_array($year, $allowedYears, true)) {
                agt_respond(422, ['success' => false, 'message' => 'A AGT só aceita séries do ano corrente (e do seguinte, depois de 15 de dezembro).']);
            }
            $defaults = ($_POST['defaults'] ?? '') === '1';
            if ($defaults) {
                $types = agt_missing_default_types($pdo, $companyId, $environment, $year);
                if ($types === []) {
                    agt_respond(200, agt_status_payload($pdo, $companyId, $environment) + [
                        'created' => [],
                        'message' => 'Todos os tipos de documento já têm série em ' . $year . '.',
                    ]);
                }
            } else {
                $types = array_values(array_unique(array_map('strtoupper', (array) ($_POST['types'] ?? []))));
                $valid = array_keys(agt_document_types());
                if ($types === [] || array_diff($types, $valid)) {
                    agt_respond(422, [
                        'success' => false,
                        'message' => 'Escolha pelo menos um tipo de documento válido (' . implode(', ', $valid) . ').',
                    ]);
                }
            }
            $establishment = trim((string) ($_POST['establishment'] ?? '')) ?: $creds['establishment'];
            $contingency = !$defaults && ($_POST['contingency'] ?? 'N') === 'C' ? 'C' : 'N';

            $created = [];
            foreach ($types as $type) {
                $created[] = agt_request_series($pdo, $companyId, $userId, $environment, $creds, $type, $year, $establishment, $contingency);
            }
            $ok = count(array_filter($created, static fn($c) => $c['success']));
            agt_respond(200, agt_status_payload($pdo, $companyId, $environment) + [
                'created' => $created,
                'message' => $ok === count($created) ? 'Séries criadas na AGT.' : ($ok ? 'Algumas séries não foram criadas.' : 'A AGT não criou as séries.'),
            ]);

        case 'sync':
            $count = agt_sync_series($pdo, $companyId, $environment, agt_credentials($pdo, $companyId));
            agt_respond(200, agt_status_payload($pdo, $companyId, $environment) + [
                'message' => $count . ' série(s) atualizada(s) a partir da AGT.',
            ]);

        case 'set_default':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT document_type, series_year FROM agt_series WHERE id = ? AND company_id = ? AND environment = ?');
            $stmt->execute([$id, $companyId, $environment]);
            $series = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$series) {
                agt_respond(404, ['success' => false, 'message' => 'Série não encontrada.']);
            }
            $pdo->prepare('UPDATE agt_series SET is_default = (id = ?) WHERE company_id = ? AND environment = ? AND document_type = ? AND series_year = ?')
                ->execute([$id, $companyId, $environment, $series['document_type'], $series['series_year']]);
            agt_respond(200, agt_status_payload($pdo, $companyId, $environment) + ['message' => 'Série padrão atualizada.']);

        case 'save_options':
            $code = strtoupper(trim((string) ($_POST['exemption_code'] ?? '')));
            if (!preg_match('/^M\d{2}$/', $code)) {
                agt_respond(422, ['success' => false, 'message' => 'Código de isenção inválido (formato M00).']);
            }
            if (!agt_settings($pdo, $companyId)) {
                agt_respond(422, ['success' => false, 'message' => 'Carregue primeiro a chave privada da empresa.']);
            }
            $pdo->prepare('UPDATE agt_settings SET exemption_code = ? WHERE company_id = ?')->execute([$code, $companyId]);
            agt_respond(200, agt_status_payload($pdo, $companyId, $environment) + ['message' => 'Opções guardadas.']);

        case 'invoice':
        case 'invoice_send':
            $id = (int) ($_REQUEST['id'] ?? 0);
            require_owned('invoices', $id);
            $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
            $stmt->execute([$id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!agt_parse_document_no($invoice['reference'] ?? null)) {
                agt_respond(200, ['success' => true, 'enabled' => agt_enabled($pdo, $companyId), 'agt' => null]);
            }
            if ($action === 'invoice_send') {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    agt_respond(405, ['success' => false, 'message' => 'Use POST.']);
                }
                if (!in_array($invoice['agt_status'], [null, 'error'], true)) {
                    agt_respond(422, ['success' => false, 'message' => 'Esta fatura já foi recebida pela AGT.']);
                }
                $state = agt_submit_invoice($pdo, $id);
            } elseif (
                $invoice['agt_status'] === 'sent'
                && (empty($invoice['agt_checked_at']) || strtotime($invoice['agt_checked_at']) < time() - 20)
            ) {
                // A validação na AGT é assíncrona: consulta, no máximo, a cada 20 s.
                $state = agt_check_invoice($pdo, $id);
            } else {
                $state = agt_invoice_state($invoice);
            }
            if ($state !== null) {
                $state['consumer_final'] = agt_invoice_is_consumer_final($pdo, $invoice);
                $state['consumer_tax_id'] = agt_consumer_tax_id();
            }
            agt_respond(200, ['success' => true, 'enabled' => true, 'agt' => $state]);

        case 'receipt':
        case 'receipt_send':
            $id = (int) ($_REQUEST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT r.* FROM receipts r JOIN invoices i ON i.id = r.invoice_id WHERE r.id = ? AND i.company_id = ?');
            $stmt->execute([$id, $companyId]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$receipt) {
                agt_respond(404, ['success' => false, 'message' => 'Recibo não encontrado.']);
            }
            if (!agt_parse_document_no($receipt['reference'] ?? null)) {
                agt_respond(200, ['success' => true, 'enabled' => agt_enabled($pdo, $companyId), 'agt' => null]);
            }
            if ($action === 'receipt_send') {
                if (!in_array($receipt['agt_status'], [null, 'error'], true)) {
                    agt_respond(422, ['success' => false, 'message' => 'Este recibo já foi recebido pela AGT.']);
                }
                $state = agt_submit_receipt($pdo, $id);
            } elseif (
                $receipt['agt_status'] === 'sent'
                && (empty($receipt['agt_checked_at']) || strtotime($receipt['agt_checked_at']) < time() - 20)
            ) {
                $state = agt_check_receipt($pdo, $id);
            } else {
                $state = agt_receipt_state($receipt);
            }
            agt_respond(200, ['success' => true, 'enabled' => true, 'agt' => $state]);

        case 'credit_note':
        case 'credit_note_send':
            $id = (int) ($_REQUEST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM credit_notes WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$note) {
                agt_respond(404, ['success' => false, 'message' => 'Nota de crédito não encontrada.']);
            }
            if (!agt_parse_document_no($note['reference'] ?? null)) {
                agt_respond(200, ['success' => true, 'enabled' => agt_enabled($pdo, $companyId), 'agt' => null]);
            }
            if ($action === 'credit_note_send') {
                if (!in_array($note['agt_status'], [null, 'error'], true)) {
                    agt_respond(422, ['success' => false, 'message' => 'Esta nota de crédito já foi recebida pela AGT.']);
                }
                $state = agt_submit_credit_note($pdo, $id);
            } elseif (
                $note['agt_status'] === 'sent'
                && (empty($note['agt_checked_at']) || strtotime($note['agt_checked_at']) < time() - 20)
            ) {
                $state = agt_check_credit_note($pdo, $id);
            } else {
                $state = agt_credit_note_state($note);
            }
            agt_respond(200, ['success' => true, 'enabled' => true, 'agt' => $state]);

        case 'debit_note':
        case 'debit_note_send':
            $id = (int) ($_REQUEST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM debit_notes WHERE id = ? AND company_id = ?');
            $stmt->execute([$id, $companyId]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$note) {
                agt_respond(404, ['success' => false, 'message' => 'Nota de débito não encontrada.']);
            }
            if (!agt_parse_document_no($note['reference'] ?? null)) {
                agt_respond(200, ['success' => true, 'enabled' => agt_enabled($pdo, $companyId), 'agt' => null]);
            }
            if ($action === 'debit_note_send') {
                if (!in_array($note['agt_status'], [null, 'error'], true)) {
                    agt_respond(422, ['success' => false, 'message' => 'Esta nota de débito já foi recebida pela AGT.']);
                }
                $state = agt_submit_debit_note($pdo, $id);
            } elseif (
                $note['agt_status'] === 'sent'
                && (empty($note['agt_checked_at']) || strtotime($note['agt_checked_at']) < time() - 20)
            ) {
                $state = agt_check_debit_note($pdo, $id);
            } else {
                $state = agt_debit_note_state($note);
            }
            agt_respond(200, ['success' => true, 'enabled' => true, 'agt' => $state]);

        case 'documents':
            $stmt = $pdo->prepare("
                SELECT i.id, i.reference, i.document_type, i.issue_date, i.final_total, c.name AS customer,
                       i.agt_status, i.agt_request_id, i.agt_errors, i.agt_sent_at, i.agt_checked_at
                FROM invoices i
                LEFT JOIN contact c ON c.id = i.contact_id
                WHERE i.company_id = ? AND i.agt_status IS NOT NULL
                ORDER BY i.agt_sent_at DESC, i.id DESC
                LIMIT 50
            ");
            $stmt->execute([$companyId]);
            $docs = array_map(static fn($row) => [
                'id' => (int) $row['id'],
                'customer' => $row['customer'],
                'issue_date' => $row['issue_date'],
                'final_total' => (float) $row['final_total'],
            ] + agt_invoice_state($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
            agt_respond(200, ['success' => true, 'documents' => $docs]);

        case 'check_pending':
            $stmt = $pdo->prepare("SELECT id FROM invoices WHERE company_id = ? AND agt_status = 'sent' ORDER BY agt_sent_at LIMIT 20");
            $stmt->execute([$companyId]);
            $checked = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                agt_check_invoice($pdo, (int) $id);
                $checked++;
            }
            $stmt = $pdo->prepare("SELECT r.id FROM receipts r JOIN invoices i ON i.id = r.invoice_id WHERE i.company_id = ? AND r.agt_status = 'sent' ORDER BY r.agt_sent_at LIMIT 20");
            $stmt->execute([$companyId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                agt_check_receipt($pdo, (int) $id);
                $checked++;
            }
            $stmt = $pdo->prepare("SELECT id FROM credit_notes WHERE company_id = ? AND agt_status = 'sent' ORDER BY agt_sent_at LIMIT 20");
            $stmt->execute([$companyId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                agt_check_credit_note($pdo, (int) $id);
                $checked++;
            }
            $stmt = $pdo->prepare("SELECT id FROM debit_notes WHERE company_id = ? AND agt_status = 'sent' ORDER BY agt_sent_at LIMIT 20");
            $stmt->execute([$companyId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                agt_check_debit_note($pdo, (int) $id);
                $checked++;
            }
            agt_respond(200, ['success' => true, 'checked' => $checked]);
    }

    agt_respond(404, ['success' => false, 'message' => 'Ação desconhecida.']);
} catch (AgtUnavailableException $e) {
    agt_respond(503, ['success' => false, 'unavailable' => true, 'message' => $e->getMessage()]);
} catch (AgtApiConfigException $e) {
    agt_respond(502, ['success' => false, 'config_error' => true, 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    agt_respond(502, ['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('AGT endpoint: ' . $e->getMessage());
    agt_respond(500, ['success' => false, 'message' => 'Erro interno ao comunicar com a AGT.']);
}
