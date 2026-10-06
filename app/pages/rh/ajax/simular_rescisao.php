<?php

/**
 * Fase 2, item 5 — Simula o cálculo de rescisão SEM gravar nada.
 * Usado pelo frontend para mostrar a estimativa antes de confirmar o
 * desligamento (o contabilista/RH revê o valor antes de seguir em frente).
 *
 * GET employee_id, tipo_cessacao, data_fim (Y-m-d), aviso_previo_dado (0/1, opcional)
 */

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../lib/rescisao.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
$employee_id = (int)($_GET['employee_id'] ?? 0);
$tipoCessacao = $_GET['tipo_cessacao'] ?? '';
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');

if (!$company_id || !$employee_id || !$tipoCessacao) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros insuficientes.']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ? AND company_id = ?');
$stmt->execute([$employee_id, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Funcionário não encontrado.']);
    exit;
}

$stmtSettings = $pdo->prepare('SELECT aviso_previo_demissao_dias FROM rh_settings WHERE company_id = ?');
$stmtSettings->execute([$company_id]);
$avisoPrevioDiasConfig = (int)($stmtSettings->fetchColumn() ?: 30);

$opcoes = [
    'aviso_previo_dado' => !empty($_GET['aviso_previo_dado']),
    'aviso_previo_dias_config' => $avisoPrevioDiasConfig,
];

$resultado = calcularRescisao($employee, $tipoCessacao, $dataFim, $opcoes);

echo json_encode(['success' => true] + $resultado);
