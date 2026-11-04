<?php
/**
 * Converte uma proforma em Fatura (FT) ou Fatura/Recibo (FR) e emite-a:
 * numeração da série AGT, hash e envio à AGT (faturação eletrónica), tal como
 * uma fatura criada de raiz e finalizada em invoices/ajax/update_status.php.
 *
 * POST invoice_id   id da proforma
 *      document_type FT | FR
 */

require_once '../../../app/helpers/require_login.php';
require_once '../../../app/helpers/invoice_emit.php';

header('Content-Type: application/json; charset=utf-8');

function convert_respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    convert_respond(405, ['success' => false, 'error' => 'Método inválido.']);
}
if (
    empty($_SESSION['csrf'])
    || !hash_equals((string)$_SESSION['csrf'], (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
) {
    convert_respond(403, ['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
}

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
$userId = (int)($_SESSION['user']['id'] ?? 0);
$proformaId = (int)($_POST['invoice_id'] ?? 0);
$documentType = strtoupper(trim((string)($_POST['document_type'] ?? '')));

if ($companyId <= 0 || $userId <= 0) {
    convert_respond(401, ['success' => false, 'error' => 'Sessão inválida.']);
}
if ($proformaId <= 0) {
    convert_respond(422, ['success' => false, 'error' => 'Proforma inválida.']);
}
if (!in_array($documentType, ['FT', 'FR'], true)) {
    convert_respond(422, ['success' => false, 'error' => 'Escolha Fatura (FT) ou Fatura/Recibo (FR).']);
}

$isReceipt = $documentType === 'FR';
$statusName = $isReceipt ? 'Paga' : 'Finalizada';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM proformas WHERE id = ? AND company_id = ? FOR UPDATE');
    $stmt->execute([$proformaId, $companyId]);
    $proforma = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proforma) {
        throw new RuntimeException('Proforma não encontrada.', 404);
    }

    // Já convertida: devolve a fatura existente em vez de criar outra.
    if (!empty($proforma['converted_invoice_id'])) {
        $stmt = $pdo->prepare('SELECT id, reference, document_type FROM invoices WHERE id = ? AND company_id = ? LIMIT 1');
        $stmt->execute([(int)$proforma['converted_invoice_id'], $companyId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $pdo->commit();
            convert_respond(200, [
                'success' => true,
                'already_converted' => true,
                'new_invoice_id' => (int)$existing['id'],
                'reference' => $existing['reference'],
                'document_type' => $existing['document_type'],
            ]);
        }
        // A fatura associada já não existe: deixa converter de novo.
        $pdo->prepare('UPDATE proformas SET converted_invoice_id = NULL WHERE id = ?')->execute([$proformaId]);
    }

    $stmt = $pdo->prepare('SELECT item_id, quantity, unit_price, tax, discount, retention_rate FROM proforma_items WHERE proforma_id = ?');
    $stmt->execute([$proformaId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) {
        throw new RuntimeException('A proforma não tem itens.', 422);
    }

    // Série por omissão do tipo escolhido (faturação eletrónica). Sem AGT, a numeração é interna.
    $series = null;
    if (agt_enabled($pdo, $companyId)) {
        $agtSeries = agt_default_series($pdo, $companyId, $documentType, (int)date('Y'));
        if (!$agtSeries) {
            throw new RuntimeException(
                'Não há série ' . $documentType . ' aberta na AGT para ' . date('Y') . '. Crie-a em Faturação Eletrónica AGT.',
                422
            );
        }
        $series = $agtSeries['series_code'];
    }

    // Prazo de pagamento em dias. Proformas antigas gravaram o ano (ex.: 2026) por engano: trata como 0.
    $dueDays = (int)$proforma['due_date'];
    if ($dueDays < 0 || $dueDays > 365) {
        $dueDays = 0;
    }

    // Copia da proforma os totais e os dados fiscais (retenção, IVA, cativação) já calculados.
    $copy = [
        'contact_id', 'currency', 'manual_exchange_rate', 'observation',
        'retention', 'retention_value', 'total_sum', 'total_discount', 'subtotal_without_tax', 'total_tax',
        'tax_withholding_type', 'tax_withholding_rate', 'tax_withholding_base', 'tax_withholding_amount',
        'tax_withholding_description', 'tax_withholding_confirmed', 'gross_total', 'net_payable', 'final_total',
        'converted_total',
    ];
    $fields = array_intersect_key($proforma, array_flip($copy));
    $fields += [
        'company_id' => $companyId,
        'user_id' => $userId,
        'issue_date' => date('Y-m-d'),
        // FR é paga no acto: não tem vencimento.
        'due_date' => $isReceipt ? 0 : $dueDays,
        'reference' => null,
        'series' => $series,
        'document_type' => $documentType,
        'status' => invoice_status_map()['Rascunho'],
        'paid_total' => $isReceipt ? (float)$proforma['final_total'] : 0,
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO invoices (' . implode(',', array_keys($fields)) . ') VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')'
    );
    $stmt->execute(array_values($fields));
    $invoiceId = (int)$pdo->lastInsertId();

    $insertItem = $pdo->prepare('
        INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price, tax, discount, retention_rate)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($items as $item) {
        $insertItem->execute([
            $invoiceId,
            $item['item_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['tax'],
            $item['discount'],
            $item['retention_rate'],
        ]);
    }

    $emitted = bx_invoice_emit($pdo, $companyId, $invoiceId, $statusName, $documentType);

    $pdo->prepare('UPDATE proformas SET converted_invoice_id = ?, converted_at = NOW(), status = 2 WHERE id = ? AND company_id = ?')
        ->execute([$invoiceId, $proformaId, $companyId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('convert_proforma: ' . $e->getMessage());
    $code = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 500;
    convert_respond($code, ['success' => false, 'error' => $e->getMessage()]);
}

// Faturação eletrónica: envia à AGT depois do commit (a emissão não depende da resposta).
$agt = bx_invoice_send_agt($pdo, $invoiceId, $statusName);

convert_respond(200, [
    'success' => true,
    'already_converted' => false,
    'new_invoice_id' => $invoiceId,
    'reference' => $emitted['reference'],
    'document_type' => $documentType,
    'agt' => $agt,
]);
