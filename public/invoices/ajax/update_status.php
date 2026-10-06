<?php
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../../../app/config/db.php';
require_once '../../../app/helpers/invoice_helper.php';

// 🔐 FUNÇÃO HASH
function gerarHash($invoiceDate, $systemEntryDate, $invoiceNo, $grossTotal, $previousHash = "")
{
    $string = $invoiceDate . ";" .
        $systemEntryDate . ";" .
        $invoiceNo . ";" .
        number_format($grossTotal, 2, '.', '') . ";" .
        $previousHash;

    return strtoupper(sha1($string));
}

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido");
    }

    $invoiceId = $_POST['invoice_id'] ?? null;
    $newStatusName = $_POST['new_status'] ?? 'Pendente';
    $documentType = $_POST['document_type'] ?? 'FT';

    if (!$invoiceId) {
        throw new Exception("ID inválido");
    }

    $pdo->beginTransaction();

    //  LOCK DA FATURA
    $stmt = $pdo->prepare("
        SELECT *
        FROM invoices
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $invoiceId]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Fatura não encontrada");
    }

    // ❌ NÃO PERMITIR DUPLA EMISSÃO
    if (!empty($invoice['hash'])) {
        throw new Exception("Fatura já finalizada (hash existente)");
    }

    $docType = !empty($invoice['document_type'])
        ? $invoice['document_type']
        : $documentType;

    $docType = strtoupper(trim($docType));

    $statusMap = invoice_status_map();

    if (!isset($statusMap[$newStatusName])) {
        throw new Exception("Status inválido");
    }

    $statusId = $statusMap[$newStatusName];

    //  GERAR NUMERAÇÃO
    $result = apply_invoice_number_rules(
        $pdo,
        (int)$invoice['company_id'],
        $newStatusName,
        $invoice['reference'],
        $docType
    );

    $newReference = $result['reference'];

    // 🔹 ATUALIZAR NUMERO E STATUS
    $update = $pdo->prepare("
        UPDATE invoices 
        SET status = ?, reference = ?, document_type = ?
        WHERE id = ?
    ");
    $update->execute([
        $statusId,
        $newReference,
        $docType,
        $invoiceId
    ]);

    // =========================
    // 🔗 HASH ENCADEADO
    // =========================

    // 🔹 buscar último hash da mesma série
    $stmtHash = $pdo->prepare("
        SELECT hash 
        FROM invoices
        WHERE company_id = ?
        AND document_type = ?
        AND hash IS NOT NULL
        ORDER BY issue_date DESC, id DESC
        LIMIT 1
    ");

    $stmtHash->execute([
        $invoice['company_id'],
        $docType
    ]);

    $previousHash = $stmtHash->fetchColumn() ?? "";

    // 🔹 gerar hash
    // 🔹 gerar hash
    $systemEntryDate = date('Y-m-d\TH:i:s');

    $hash = gerarHash(
        $invoice['issue_date'],
        $systemEntryDate,
        $newReference,
        $invoice['final_total'],
        $previousHash
    );

    // 🔹 guardar hash
    $updateHash = $pdo->prepare("
        UPDATE invoices 
        SET hash = ?, hash_control = '1'
        WHERE id = ?
    ");
    $updateHash->execute([$hash, $invoiceId]);

    //  OPCIONAL: guardar system_entry_date
    $updateDate = $pdo->prepare("
        UPDATE invoices 
        SET created_at = ?
        WHERE id = ?
    ");
    $updateDate->execute([$systemEntryDate, $invoiceId]);

    $pdo->commit();

    $output = ob_get_clean();

    echo json_encode([
        'success' => true,
        'reference' => $newReference,
        'hash' => $hash,
        'debug' => $output
    ]);
} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $output = ob_get_clean();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $output
    ]);
}
