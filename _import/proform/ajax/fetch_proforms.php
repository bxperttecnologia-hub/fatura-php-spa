<?php
require_once '../../../app/config/db.php';
header('Content-Type: application/json'); 
session_start();

try { 
    $sql = "SELECT p.id, p.company_id, ps.name as status_invoice, ps.color, ps.text_color,
     concat(YEAR(p.issue_date),'/',p.id) as codigo, c.name as cliente, p.issue_date, 
     p.due_date, case when p.converted_total > 0 then round(p.converted_total,2) else p.final_total end as final_total,
      cr.symbol, cr.position, concat(cr.currency, ' (',cr.iso_code,')') as currency 
    FROM proformas p
    join contact c on c.id = p.contact_id
    join currencies cr on cr.iso_code = 'AOA'
    join proforma_status ps on ps.id = p.status
    where p.company_id = :company_id
    order by p.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(":company_id", $_SESSION['user']['company_id'], PDO::PARAM_INT);
    $stmt->execute();

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    $formattedInvoices = array_map(function ($invoice) {
        $issueDate = new DateTime($invoice['issue_date']);
        $dueDate = clone $issueDate;
        $dueDate->modify('+' . intval($invoice['due_date']) . ' days');
        $invoice['due_date'] = $dueDate->format('Y-m-d');
        return $invoice;
    }, $invoices);
 
    echo json_encode($formattedInvoices);
} catch (Exception $e) { 
    echo json_encode(['error' => 'Erro ao buscar dados: ' . $e->getMessage()]);
}
