<?php
require_once '../../../app/config/db.php';

// ==============================
// 🔹 PARÂMETROS
// ==============================
$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? null;

$where = "WHERE YEAR(i.issue_date) = :ano";
$params = [':ano' => $ano];

if ($mes) {
    $where .= " AND MONTH(i.issue_date) = :mes";
    $params[':mes'] = $mes;
}

// ==============================
// 📄 INICIAR XML
// ==============================
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$auditFile = $xml->createElement("AuditFile");
$xml->appendChild($auditFile);

// ==============================
// 📌 HEADER
// ==============================
$header = $xml->createElement("Header");

$header->appendChild($xml->createElement("AuditFileVersion", "1.01"));
$header->appendChild($xml->createElement("CompanyID", "123456789"));
$header->appendChild($xml->createElement("TaxRegistrationNumber", "123456789"));
$header->appendChild($xml->createElement("CompanyName", "Minha Empresa"));
$header->appendChild($xml->createElement("FiscalYear", $ano));
$header->appendChild($xml->createElement("StartDate", "$ano-01-01"));
$header->appendChild($xml->createElement("EndDate", "$ano-12-31"));
$header->appendChild($xml->createElement("CurrencyCode", "AOA"));
$header->appendChild($xml->createElement("DateCreated", date('Y-m-d')));
$header->appendChild($xml->createElement("SoftwareCertificateNumber", "0000"));

$auditFile->appendChild($header);

// ==============================
// 📌 MASTER FILES
// ==============================
$masterFiles = $xml->createElement("MasterFiles");

// 🔹 CLIENTES
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.name, c.contributor
    FROM contact c
    JOIN invoices i ON i.contact_id = c.id
    $where
");
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($clientes as $c) {
    $customer = $xml->createElement("Customer");

    $customer->appendChild($xml->createElement("CustomerID", $c['id']));
    $customer->appendChild($xml->createElement("CustomerTaxID", $c['tax_number'] ?? "999999999"));
    $customer->appendChild($xml->createElement("CompanyName", $c['name']));

    $masterFiles->appendChild($customer);
}

// 🔹 PRODUTOS
$produtos = $pdo->query("SELECT DISTINCT item_id FROM invoice_items")->fetchAll(PDO::FETCH_ASSOC);

foreach ($produtos as $p) {
    $product = $xml->createElement("Product");

    $product->appendChild($xml->createElement("ProductCode", $p['item_id']));
    $product->appendChild($xml->createElement("ProductDescription", "Produto ".$p['item_id']));
    $product->appendChild($xml->createElement("ProductType", "P"));

    $masterFiles->appendChild($product);
}

// 🔹 TAX TABLE
$taxTable = $xml->createElement("TaxTable");

$taxEntry = $xml->createElement("TaxTableEntry");
$taxEntry->appendChild($xml->createElement("TaxType", "IVA"));
$taxEntry->appendChild($xml->createElement("TaxCountryRegion", "AO"));
$taxEntry->appendChild($xml->createElement("TaxCode", "NOR"));
$taxEntry->appendChild($xml->createElement("Description", "IVA 14%"));
$taxEntry->appendChild($xml->createElement("TaxPercentage", "14"));

$taxTable->appendChild($taxEntry);
$masterFiles->appendChild($taxTable);

$auditFile->appendChild($masterFiles);

// ==============================
// 📌 SOURCE DOCUMENTS
// ==============================
$sourceDocuments = $xml->createElement("SourceDocuments");
$salesInvoices = $xml->createElement("SalesInvoices");

// 🔹 FATURAS
$stmt = $pdo->prepare("
    SELECT * FROM invoices i
    $where AND i.status = 5 OR i.status = 4 OR i.status = 3 
");
$stmt->execute($params);
$faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($faturas as $f) {

    $invoice = $xml->createElement("Invoice");

    $invoice->appendChild($xml->createElement("InvoiceNo", $f['reference']));
    $invoice->appendChild($xml->createElement("InvoiceDate", $f['issue_date']));
    $invoice->appendChild($xml->createElement("InvoiceType", $f['document_type']));
    $invoice->appendChild($xml->createElement("CustomerID", $f['contact_id']));
    $invoice->appendChild($xml->createElement("SystemEntryDate", $f['created_at']));
    $invoice->appendChild($xml->createElement("SourceID", $f['user_id']));
    $invoice->appendChild($xml->createElement("Hash", $f['hash'] ?? ""));
    $invoice->appendChild($xml->createElement("HashControl", "1"));

    // 🔹 LINHAS
    $stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $stmtItems->execute([$f['id']]);
    $itens = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    foreach ($itens as $index => $i) {
        $line = $xml->createElement("Line");

        $line->appendChild($xml->createElement("LineNumber", $index + 1));
        $line->appendChild($xml->createElement("ProductCode", $i['item_id']));
        $line->appendChild($xml->createElement("Quantity", $i['quantity']));
        $line->appendChild($xml->createElement("UnitPrice", $i['unit_price']));
        $line->appendChild($xml->createElement("LineExtensionAmount", $i['quantity'] * $i['unit_price']));

        // 🔹 TAX
        $tax = $xml->createElement("Tax");
        $tax->appendChild($xml->createElement("TaxType", "IVA"));
        $tax->appendChild($xml->createElement("TaxCountryRegion", "AO"));
        $tax->appendChild($xml->createElement("TaxCode", "NOR"));
        $tax->appendChild($xml->createElement("TaxPercentage", $i['tax'] ?? 0));

        $line->appendChild($tax);

        $invoice->appendChild($line);
    }

    // 🔹 TOTAIS
    $totals = $xml->createElement("DocumentTotals");

    $totals->appendChild($xml->createElement("TaxPayable", $f['total_tax']));
    $totals->appendChild($xml->createElement("NetTotal", $f['subtotal_without_tax']));
    $totals->appendChild($xml->createElement("GrossTotal", $f['final_total']));

    $invoice->appendChild($totals);

    $salesInvoices->appendChild($invoice);
}

// ==============================
// 🔹 NOTAS DE CRÉDITO (como Invoice NC)
// ==============================
$stmt = $pdo->prepare("
    SELECT * FROM credit_notes i
    $where
");
$stmt->execute($params);
$creditNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($creditNotes as $cn) {

    $invoice = $xml->createElement("Invoice");

    $invoice->appendChild($xml->createElement("InvoiceNo", "NC".$cn['id']));
    $invoice->appendChild($xml->createElement("InvoiceDate", $cn['issue_date']));
    $invoice->appendChild($xml->createElement("InvoiceType", "NC"));
    $invoice->appendChild($xml->createElement("CustomerID", $cn['contact_id']));
    $invoice->appendChild($xml->createElement("SystemEntryDate", $cn['created_at']));
    $invoice->appendChild($xml->createElement("SourceID", $cn['user_id']));
    $invoice->appendChild($xml->createElement("Hash", ""));
    $invoice->appendChild($xml->createElement("HashControl", "1"));

    $totals = $xml->createElement("DocumentTotals");
    $totals->appendChild($xml->createElement("TaxPayable", $cn['total_tax']));
    $totals->appendChild($xml->createElement("NetTotal", $cn['subtotal_without_tax']));
    $totals->appendChild($xml->createElement("GrossTotal", $cn['final_total']));

    $invoice->appendChild($totals);

    $salesInvoices->appendChild($invoice);
}

$sourceDocuments->appendChild($salesInvoices);
$auditFile->appendChild($sourceDocuments);

// ==============================
// 💾 EXPORTAR
// ==============================
$fileName = "SAFT_AO_{$ano}".($mes ? "_$mes" : "").".xml";

header('Content-Type: application/xml');
header("Content-Disposition: attachment;filename=\"$fileName\"");

echo $xml->saveXML();
exit;