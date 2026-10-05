<?php

/**
 * Fase 2, item 5 — Ação "Desligar funcionário".
 *
 * Marca status = 'inativo', preenche end_date, e regista o tipo de
 * cessação + valor de indemnização. O valor pode vir tal como calculado
 * por calcularRescisao(), ou AJUSTADO manualmente pelo RH depois de rever
 * com o contabilista (por isso aceita `indemnizacao_final` do POST em vez
 * de recalcular por conta própria — o cálculo é só uma sugestão, ver
 * rh/lib/rescisao.php).
 *
 * POST: employee_id, tipo_cessacao, data_fim, indemnizacao_final, notas (opcional), aviso_previo_dado (0/1, opcional)
 */

require_once '../../../app/config/db.php';
require_once __DIR__ . '/../lib/rescisao.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada ou inválida.']);
    exit;
}

$employee_id = (int)($_POST['employee_id'] ?? 0);
$tipoCessacao = $_POST['tipo_cessacao'] ?? '';
$dataFim = $_POST['data_fim'] ?? date('Y-m-d');
$indemnizacaoFinal = isset($_POST['indemnizacao_final']) && $_POST['indemnizacao_final'] !== ''
    ? (float)$_POST['indemnizacao_final']
    : null;
$notas = trim($_POST['notas'] ?? '');

$tiposValidos = ['periodo_experimental', 'termo_certo_nao_renovado', 'causas_objetivas', 'sem_justa_causa', 'demissao_trabalhador'];

if (!$employee_id || !in_array($tipoCessacao, $tiposValidos, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM employees WHERE id = ? AND company_id = ? FOR UPDATE');
    $stmt->execute([$employee_id, $company_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception('Funcionário não encontrado.');
    }

    if ($employee['status'] === 'inativo') {
        throw new Exception('Este funcionário já está inativo.');
    }

    // Se não veio um valor final ajustado, calcula pela regra padrão
    // (o frontend normalmente já mostrou este valor via simular_rescisao.php
    // antes do utilizador confirmar).
    if ($indemnizacaoFinal === null) {
        $stmtSettings = $pdo->prepare('SELECT aviso_previo_demissao_dias FROM rh_settings WHERE company_id = ?');
        $stmtSettings->execute([$company_id]);
        $avisoPrevioDiasConfig = (int)($stmtSettings->fetchColumn() ?: 30);

        $opcoes = [
            'aviso_previo_dado' => !empty($_POST['aviso_previo_dado']),
            'aviso_previo_dias_config' => $avisoPrevioDiasConfig,
        ];
        $calculo = calcularRescisao($employee, $tipoCessacao, $dataFim, $opcoes);
        $indemnizacaoFinal = $calculo['indemnizacao'];
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE employees
        SET status = 'inativo',
            end_date = ?,
            termination_type = ?,
            termination_amount = ?,
            termination_notes = ?
        WHERE id = ? AND company_id = ?
    ");
    $stmtUpdate->execute([$dataFim, $tipoCessacao, $indemnizacaoFinal, $notas, $employee_id, $company_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Funcionário desligado com sucesso.',
        'indemnizacao_registada' => $indemnizacaoFinal,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
