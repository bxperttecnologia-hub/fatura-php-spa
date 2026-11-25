<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../app/helpers/require_login.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$companyId = session_company_id();
$query = trim((string)($_GET['q'] ?? $_GET['reference'] ?? ''));
$type = strtolower(trim((string)($_GET['type'] ?? 'all')));
$limit = min(max((int)($_GET['limit'] ?? 20), 1), 50);
$results = [];

try {
    if (in_array($type, ['all', 'invoice', 'factura', 'ft', 'fr'], true)) {
        $term = '%' . str_replace(['%', '_'], ['\%%', '\_'], $query) . '%';
        $sql = "
            SELECT i.id, i.document_type, i.series, i.reference, i.issue_date,
                   c.id AS contact_id, c.name AS contact_name,
                   s.name AS status_name
            FROM invoices i
            JOIN contact c ON c.id = i.contact_id
            LEFT JOIN invoice_status s ON s.id = i.status
            WHERE i.company_id = :company
              AND i.document_type IN ('FT', 'FR')
              AND COALESCE(s.name, '') NOT IN ('Rascunho', 'Cancelada', 'Cancelado')
              AND (
                :query = '%%'
                OR i.reference LIKE :term
                OR i.series LIKE :term
                OR CAST(i.id AS CHAR) LIKE :term
                OR c.name LIKE :term
              )
            ORDER BY i.issue_date DESC, i.id DESC
            LIMIT :limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':term', $term);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id' => (int)$row['id'],
                'type' => 'factura',
                'document_type' => (string)$row['document_type'],
                'series' => (string)($row['series'] ?? ''),
                'number' => (string)($row['reference'] ?? ''),
                'issue_date' => (string)$row['issue_date'],
                'status' => (string)($row['status_name'] ?? ''),
                'contact_id' => (int)$row['contact_id'],
                'contact_name' => (string)$row['contact_name'],
                'label' => trim(($row['document_type'] ?? '') . ' ' . ($row['series'] ?? '') . ' ' . ($row['reference'] ?? '') . ' — ' . ($row['contact_name'] ?? '')),
            ];
        }
    }

    if (in_array($type, ['all', 'proforma', 'pf'], true)) {
        $term = '%' . str_replace(['%', '_'], ['\%%', '\_'], $query) . '%';
        $sql = "
            SELECT p.id, p.series, p.reference, p.issue_date,
                   c.id AS contact_id, c.name AS contact_name
            FROM proformas p
            JOIN contact c ON c.id = p.contact_id
            WHERE p.company_id = :company
              AND (
                :query = '%%'
                OR p.reference LIKE :term
                OR CAST(p.series AS CHAR) LIKE :term
                OR CAST(p.id AS CHAR) LIKE :term
                OR c.name LIKE :term
              )
            ORDER BY p.issue_date DESC, p.id DESC
            LIMIT :limit
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':term', $term);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'id' => (int)$row['id'],
                'type' => 'proforma',
                'document_type' => 'PF',
                'series' => (string)$row['series'],
                'number' => (string)$row['reference'],
                'issue_date' => (string)$row['issue_date'],
                'status' => 'Elegível',
                'contact_id' => (int)$row['contact_id'],
                'contact_name' => (string)$row['contact_name'],
                'label' => trim('PF ' . ($row['series'] ?? '') . ' ' . ($row['reference'] ?? '') . ' — ' . ($row['contact_name'] ?? '')),
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => array_slice($results, 0, $limit)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível pesquisar documentos.'], JSON_UNESCAPED_UNICODE);
}
