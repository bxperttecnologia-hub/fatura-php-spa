<?php

/**
 * Fase 5, item 2 — Alertas automáticos.
 *
 * NOTA DE DESENHO: o prompt original pedia para reaproveitar o motor de
 * `alert_rules`/`alert_logs` "já existente no sistema, se aplicável". Esse
 * motor não fazia parte dos ficheiros/dump fornecidos nesta entrega, por
 * isso não dá para saber o esquema real dele sem arriscar quebrar algo.
 * Em vez de adivinhar, este endpoint calcula os alertas DIRETAMENTE a
 * partir dos dados (sem gravar em lado nenhum) e devolve-os prontos a
 * mostrar no dashboard (Fase 5, item 4). Se o teu `alert_rules`/`alert_logs`
 * já existir, dá para adaptar isto facilmente: troca o `echo json_encode`
 * final por um INSERT nesse motor, usando os mesmos dados já calculados
 * aqui.
 *
 * Tipos de alerta devolvidos:
 *  - contrato_terminando   : contract_end_date a <= X dias (default 30)
 *  - fim_experimental      : contract_type = período experimental, perto do fim
 *    (assume 90 dias de experimental a partir de admission_date — Lei Geral
 *    do Trabalho prevê prazos que variam por tipo de contrato/função;
 *    CONFIRMA o prazo aplicável ao teu quadro de pessoal antes de confiar
 *    cegamente neste número)
 *  - ferias_vencidas       : mais de 12 meses desde admission_date (ou desde
 *    o fim do último período de férias aprovado) sem novo período aprovado
 *  - aniversario_admissao  : aniversário de admissão nos próximos X dias
 *  - aniversario_natalicio : aniversário de nascimento nos próximos X dias
 */

require_once '../../../app/config/db.php';
session_start();
header('Content-Type: application/json');

$company_id = $_SESSION['user']['company_id'] ?? null;
if (!$company_id) {
    http_response_code(401);
    echo json_encode(['data' => []]);
    exit;
}

$janelaDias = (int)($_GET['dias'] ?? 30);
$hoje = new DateTime();
$alerts = [];

// --- Contratos a termo perto do fim ---
$stmt = $pdo->prepare("
    SELECT id, name, contract_end_date
    FROM employees
    WHERE company_id = ? AND status = 'ativo' AND contract_end_date IS NOT NULL
    AND contract_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
");
$stmt->execute([$company_id, $janelaDias]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $dias = (int)$hoje->diff(new DateTime($row['contract_end_date']))->format('%r%a');
    $alerts[] = [
        'tipo' => 'contrato_terminando',
        'employee_id' => $row['id'],
        'employee_name' => $row['name'],
        'data' => $row['contract_end_date'],
        'dias_restantes' => $dias,
        'mensagem' => "Contrato de {$row['name']} termina em {$dias} dia(s) ({$row['contract_end_date']}).",
    ];
}

// --- Fim de período experimental (assume 90 dias — CONFIRMAR) ---
$diasExperimental = 90;
$stmt = $pdo->prepare("
    SELECT id, name, admission_date
    FROM employees
    WHERE company_id = ? AND status = 'ativo'
    AND contract_type LIKE '%experimental%'
    AND admission_date IS NOT NULL
    AND DATE_ADD(admission_date, INTERVAL ? DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
");
$stmt->execute([$company_id, $diasExperimental, $janelaDias]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $fimExperimental = (new DateTime($row['admission_date']))->modify("+{$diasExperimental} days");
    $dias = (int)$hoje->diff($fimExperimental)->format('%r%a');
    $alerts[] = [
        'tipo' => 'fim_experimental',
        'employee_id' => $row['id'],
        'employee_name' => $row['name'],
        'data' => $fimExperimental->format('Y-m-d'),
        'dias_restantes' => $dias,
        'mensagem' => "Período experimental de {$row['name']} termina em {$dias} dia(s) (estimativa de {$diasExperimental} dias — confirmar prazo real aplicável).",
    ];
}

// --- Férias vencidas: mais de 12 meses desde a admissão (ou desde o fim das
//     últimas férias aprovadas) sem novo período de férias aprovado ---
$stmt = $pdo->prepare("
    SELECT e.id, e.name, e.admission_date,
           (SELECT MAX(v.end_date) FROM vacations v WHERE v.employee_id = e.id AND v.company_id = e.company_id AND v.status = 'Aprovado') AS ultimas_ferias_fim
    FROM employees e
    WHERE e.company_id = ? AND e.status = 'ativo' AND e.admission_date IS NOT NULL
");
$stmt->execute([$company_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $baseDate = $row['ultimas_ferias_fim'] ? new DateTime($row['ultimas_ferias_fim']) : new DateTime($row['admission_date']);
    $mesesDesde = $baseDate->diff($hoje)->y * 12 + $baseDate->diff($hoje)->m;
    if ($mesesDesde >= 12) {
        $alerts[] = [
            'tipo' => 'ferias_vencidas',
            'employee_id' => $row['id'],
            'employee_name' => $row['name'],
            'data' => $baseDate->format('Y-m-d'),
            'meses_sem_ferias' => $mesesDesde,
            'mensagem' => "{$row['name']} está há {$mesesDesde} mês(es) sem férias aprovadas — verificar direito a férias vencidas.",
        ];
    }
}

// --- Aniversário de admissão / aniversário natalício, nos próximos X dias ---
function proximoAniversario(string $dataOriginal, DateTime $hoje): DateTime
{
    $original = new DateTime($dataOriginal);
    $proximo = new DateTime($hoje->format('Y') . '-' . $original->format('m-d'));
    if ($proximo < $hoje) {
        $proximo->modify('+1 year');
    }
    return $proximo;
}

$stmt = $pdo->prepare("SELECT id, name, admission_date, birth_date FROM employees WHERE company_id = ? AND status = 'ativo'");
$stmt->execute([$company_id]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['admission_date']) {
        $prox = proximoAniversario($row['admission_date'], $hoje);
        $dias = (int)$hoje->diff($prox)->format('%a');
        if ($dias <= $janelaDias) {
            $anos = (int)(new DateTime($row['admission_date']))->diff($prox)->y;
            $alerts[] = [
                'tipo' => 'aniversario_admissao',
                'employee_id' => $row['id'],
                'employee_name' => $row['name'],
                'data' => $prox->format('Y-m-d'),
                'dias_restantes' => $dias,
                'mensagem' => "{$row['name']} completa {$anos} ano(s) de casa em {$dias} dia(s).",
            ];
        }
    }
    if ($row['birth_date']) {
        $prox = proximoAniversario($row['birth_date'], $hoje);
        $dias = (int)$hoje->diff($prox)->format('%a');
        if ($dias <= $janelaDias) {
            $alerts[] = [
                'tipo' => 'aniversario_natalicio',
                'employee_id' => $row['id'],
                'employee_name' => $row['name'],
                'data' => $prox->format('Y-m-d'),
                'dias_restantes' => $dias,
                'mensagem' => "Aniversário de {$row['name']} em {$dias} dia(s).",
            ];
        }
    }
}

// Ordena por urgência (menos dias primeiro); alertas sem 'dias_restantes' (ex: férias vencidas) ficam no topo
usort($alerts, function ($a, $b) {
    return ($a['dias_restantes'] ?? -1) <=> ($b['dias_restantes'] ?? -1);
});

echo json_encode(['data' => $alerts]);
