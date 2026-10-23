<?php
/**
 * GET /api/documents/<tipo>/<id>/pdf  (encaminhado pelo router.php / .htaccess)
 *
 * Gera os PDFs de documentos no próprio PHP (Dompdf), usando os geradores que já existiam
 * em public/**. Substitui a dependência do serviço Node externo (bxintelligence-api-rest)
 * para estes documentos; o frontend (assets/js/document-api.js) não muda.
 */
require_once __DIR__ . '/../app/helpers/require_login.php';

ini_set('display_errors', '0');   // um aviso no corpo da resposta corrompe o PDF (fica no log)

function document_pdf_fail(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message, 'message' => $message]);
    exit;
}

/** O registo tem de pertencer à empresa ativa da sessão. */
function document_pdf_owned(string $sql, int $id): void
{
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, session_company_id()]);
    if (!$stmt->fetchColumn()) {
        document_pdf_fail(404, 'Documento não encontrado.');
    }
}

/** Corre um gerador legado (que faz $dompdf->stream()) como se fosse chamado diretamente. */
function document_pdf_run(string $script, array $query): void
{
    global $pdo;   // os geradores fazem require_once do db.php (já carregado) e esperam $pdo no seu âmbito
    foreach ($query as $k => $v) {
        $_GET[$k] = $v;
        $_REQUEST[$k] = $v;
    }
    chdir(dirname($script));   // os geradores usam caminhos relativos ao próprio ficheiro
    require $script;
    exit;
}

$path = trim((string)($_GET['path'] ?? ''), '/');

if (preg_match('~^(invoices|proformas|receipts|debit-notes|delivery-notes|credit-notes|payroll)/(\d+)/pdf$~', $path, $m)) {
    [, $type, $id] = $m;
    $id = (int)$id;

    switch ($type) {
        case 'invoices':   // ?copies=1..3 (lido pelo próprio modelo)
            document_pdf_owned('SELECT 1 FROM invoices WHERE id = ? AND company_id = ?', $id);
            require_once __DIR__ . '/../app/helpers/document_pdf.php';
            $file = bx_generate_document_pdf(__DIR__ . '/invoices/ajax/invoice_public.php', $id, 'FT');   // o modelo já pagina
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="fatura-' . $id . '.pdf"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            unlink($file);
            exit;

        case 'proformas':
            document_pdf_owned('SELECT 1 FROM proformas WHERE id = ? AND company_id = ?', $id);
            require_once __DIR__ . '/../app/helpers/document_pdf.php';
            $file = bx_generate_document_pdf(__DIR__ . '/proform/ajax/proform_public.php', $id, 'PF');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="proforma-' . $id . '.pdf"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            unlink($file);
            exit;

        case 'receipts':
            document_pdf_owned('SELECT 1 FROM receipts r JOIN invoices i ON i.id = r.invoice_id WHERE r.id = ? AND i.company_id = ?', $id);
            document_pdf_run(__DIR__ . '/invoices/recibo_pdf.php', ['id' => $id]);

        case 'debit-notes':
            document_pdf_owned('SELECT 1 FROM debit_notes WHERE id = ? AND company_id = ?', $id);
            document_pdf_run(__DIR__ . '/invoices/debit_note_pdf.php', ['id' => $id]);

        case 'delivery-notes':
            document_pdf_owned('SELECT 1 FROM delivery_notes WHERE id = ? AND company_id = ?', $id);
            document_pdf_run(__DIR__ . '/invoices/delivery_note_pdf.php', ['id' => $id]);

        case 'payroll':
            document_pdf_owned('SELECT 1 FROM payroll p JOIN employees e ON e.id = p.employee_id WHERE p.id = ? AND e.company_id = ?', $id);
            document_pdf_run(__DIR__ . '/rh/pdf/recibo.php', ['id' => $id]);

        case 'credit-notes':
            document_pdf_owned('SELECT 1 FROM credit_notes WHERE id = ? AND company_id = ?', $id);
            require_once __DIR__ . '/../app/helpers/document_pdf.php';
            $file = bx_generate_document_pdf(__DIR__ . '/credit_notes/ajax/credit_note_public.php', $id, 'NC');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="nota-credito-' . $id . '.pdf"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            unlink($file);
            exit;
    }
}

if ($path === 'payroll/export.pdf') {
    $month = (string)($_GET['reference_month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        document_pdf_fail(422, 'Mês de referência inválido.');
    }
    document_pdf_run(__DIR__ . '/rh/ajax/export_all_payroll_pdf.php', ['mes' => $month]);
}

document_pdf_fail(404, 'Documento não suportado: ' . $path);
