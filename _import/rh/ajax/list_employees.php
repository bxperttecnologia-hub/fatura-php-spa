<?php

/**
 * IMPORTANTE: se este endpoint devolver "Invalid JSON response" no
 * DataTables, a causa mais comum é a Fase 1/Fase 2 ainda não terem sido
 * migradas na base de dados (faltam as colunas position_id, department_id,
 * manager_id, termination_type, termination_amount, termination_notes em
 * `employees`) — corre migrations/fase1_estrutura_dados.sql e
 * migrations/fase2_conformidade_ao.sql antes de usar esta página.
 * Este ficheiro agora nunca deixa um erro de PHP/SQL "vazar" para o
 * output e quebrar o JSON — captura qualquer erro e devolve sempre JSON
 * válido, com a mensagem real do erro para facilitar o diagnóstico.
 */

ob_start(); // segurança extra: qualquer warning/notice acidental do PHP fica
            // retido aqui em vez de se misturar com o JSON de saída.

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;

if (!$company_id) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['data' => [], 'error' => 'Sessão expirada ou inválida.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT e.id, e.name, e.bi, e.email, e.phone_ddi, e.phone, e.position, e.position_id, e.department_id, d.name AS department_name, e.manager_id, m.name AS manager_name, e.salary_base AS salary, e.status, e.document_type, e.birth_date, e.marital_status, e.academic_level, e.contract_type, e.contract_end_date, e.admission_date, e.end_date, e.termination_type, e.termination_amount, e.termination_notes, e.iban, e.photo_url, e.doc1_url, e.doc2_url,
        (SELECT COALESCE(SUM(p.net_salary), 0) FROM payroll p WHERE p.employee_id = e.id AND p.company_id = e.company_id AND p.status = 'Pago' AND YEAR(STR_TO_DATE(CONCAT(p.reference_month, '-01'), '%Y-%m-%d')) = YEAR(CURDATE())) AS total_pago_ano
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN employees m ON m.id = e.manager_id
    WHERE e.company_id = ?
    ORDER BY e.name ASC");
    $stmt->execute([$company_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode(['data' => $employees]);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    // A mensagem completa ajuda a diagnosticar (ex: "Unknown column 'e.position_id'"
    // indica que falta correr a migração da Fase 1). Loga também no servidor.
    error_log('list_employees.php: ' . $e->getMessage());
    echo json_encode([
        'data' => [],
        'error' => 'Erro ao carregar funcionários: ' . $e->getMessage()
    ]);
}
