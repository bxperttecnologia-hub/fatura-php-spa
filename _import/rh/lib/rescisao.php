<?php

/**
 * Fase 2, item 5 — Fluxo de rescisão/desligamento.
 *
 * ⚠️ APOIO AO CÁLCULO, NÃO DECISÃO AUTOMÁTICA/DEFINITIVA. Esta função
 * implementa uma leitura das regras gerais da Lei n.º 12/23, de 27 de
 * dezembro de 2023 (Lei Geral do Trabalho de Angola), mas HÁ EXCEÇÕES E
 * NUANCES que ela NÃO cobre — proteção especial a grávidas, trabalhadores
 * com deficiência, representantes sindicais, entre outras. ANTES de usar
 * o valor calculado para pagar alguém, confirma com um advogado laboral
 * ou contabilista. É por isso que esta função é isolada (não está embutida
 * numa query nem é chamada automaticamente ao mudar o status do
 * funcionário) — para poderes rever/ajustar os parâmetros com calma.
 *
 * Tipos de cessação suportados ($tipoCessacao):
 *   - 'periodo_experimental'      : sem aviso prévio nem indemnização
 *   - 'termo_certo_nao_renovado'  : aviso prévio de 30 dias; se não for dado
 *                                   a tempo, indemnização = 30 dias de salário base
 *   - 'causas_objetivas'          : indemnização = salário base × anos de
 *                                   antiguidade, teto de 5 anos
 *   - 'sem_justa_causa'           : indemnização = salário base × anos de
 *                                   antiguidade, mínimo de 3 meses de salário base
 *   - 'demissao_trabalhador'      : trabalhador demite-se sem justa causa —
 *                                   deve cumprir aviso prévio (dias configuráveis
 *                                   em rh_settings.aviso_previo_demissao_dias,
 *                                   por defeito 30 — CONFIRMAR com o contabilista
 *                                   se no teu quadro de pessoal é 15 ou 30 dias)
 */

require_once __DIR__ . '/rh_helpers.php';

/**
 * @param array $employee   Linha da tabela employees (precisa de: salary_base,
 *                           admission_date, contract_type, contract_end_date).
 * @param string $tipoCessacao Um dos valores listados acima.
 * @param string $dataFim   Data efetiva de cessação (Y-m-d).
 * @param array $opcoes     Opcionais:
 *   - 'aviso_previo_dado' (bool): se o aviso prévio de 30 dias foi dado a
 *     tempo (só relevante para 'termo_certo_nao_renovado').
 *   - 'aviso_previo_dias_config' (int): dias de aviso prévio configurados
 *     para demissão do trabalhador (rh_settings.aviso_previo_demissao_dias).
 *
 * @return array{
 *   tipo_cessacao: string,
 *   anos_antiguidade: int,
 *   aviso_previo_dias: int,
 *   indemnizacao: float,
 *   detalhe: string,
 *   avisos: string[]
 * }
 */
function calcularRescisao(array $employee, string $tipoCessacao, string $dataFim, array $opcoes = []): array
{
    $salarioBase = (float)($employee['salary_base'] ?? 0);
    $admissionDate = $employee['admission_date'] ?? null;

    $avisos = [
        'Cálculo de apoio — confirma sempre com um advogado laboral ou contabilista antes de pagar. '
        . 'Não cobre proteção especial a grávidas, trabalhadores com deficiência, representantes '
        . 'sindicais, nem outras exceções da Lei n.º 12/23.'
    ];

    $anosAntiguidade = $admissionDate ? rh_anos_completos($admissionDate, $dataFim) : 0;

    $resultado = [
        'tipo_cessacao'     => $tipoCessacao,
        'anos_antiguidade'  => $anosAntiguidade,
        'aviso_previo_dias' => 0,
        'indemnizacao'      => 0.0,
        'detalhe'           => '',
        'avisos'            => $avisos,
    ];

    switch ($tipoCessacao) {

        case 'periodo_experimental':
            // Cessação sem aviso prévio nem indemnização — só o trabalho já prestado.
            $resultado['indemnizacao'] = 0.0;
            $resultado['detalhe'] = 'Período experimental: sem aviso prévio nem indemnização. Paga-se apenas o trabalho já prestado até à data de cessação.';
            break;

        case 'termo_certo_nao_renovado':
            $avisoDado = (bool)($opcoes['aviso_previo_dado'] ?? false);
            $resultado['aviso_previo_dias'] = 30;
            if ($avisoDado) {
                $resultado['indemnizacao'] = 0.0;
                $resultado['detalhe'] = 'Contrato a termo certo não renovado, com aviso prévio de 30 dias dado a tempo: sem compensação adicional.';
            } else {
                $resultado['indemnizacao'] = $salarioBase; // 30 dias de salário base = 1x salário base mensal
                $resultado['detalhe'] = 'Contrato a termo certo não renovado, SEM aviso prévio de 30 dias dado a tempo: compensação de 30 dias de salário base.';
            }
            break;

        case 'causas_objetivas':
            // Despedimento por causas objetivas / coletivo — teto de 5 anos.
            $anosParaCalculo = min($anosAntiguidade, 5);
            $resultado['indemnizacao'] = round($salarioBase * $anosParaCalculo, 2);
            $resultado['detalhe'] = "Despedimento por causas objetivas/coletivo: {$anosAntiguidade} ano(s) de antiguidade, "
                . "limitado ao teto legal de 5 anos ⇒ indemnização = {$anosParaCalculo}× salário base.";
            if ($anosAntiguidade > 5) {
                $resultado['avisos'][] = "Antiguidade real ({$anosAntiguidade} anos) excede o teto de 5 anos — teto aplicado.";
            }
            break;

        case 'sem_justa_causa':
            // Rescisão com justa causa pelo trabalhador, ou despedimento sem
            // justa causa (sem reintegração) — mínimo de 3 meses de salário base.
            $porAntiguidade = round($salarioBase * $anosAntiguidade, 2);
            $minimoLegal = round($salarioBase * 3, 2);
            $resultado['indemnizacao'] = max($porAntiguidade, $minimoLegal);
            $resultado['detalhe'] = "Rescisão com justa causa (trabalhador) ou despedimento sem justa causa: "
                . "{$anosAntiguidade} ano(s) × salário base = Kz " . number_format($porAntiguidade, 2, ',', '.')
                . ", com mínimo legal de 3 meses (Kz " . number_format($minimoLegal, 2, ',', '.') . ") ⇒ "
                . "aplica-se o maior dos dois.";
            break;

        case 'demissao_trabalhador':
            // Trabalhador demite-se sem justa causa — deve cumprir aviso prévio.
            $diasAviso = (int)($opcoes['aviso_previo_dias_config'] ?? 30);
            $resultado['aviso_previo_dias'] = $diasAviso;
            $resultado['indemnizacao'] = 0.0;
            $resultado['detalhe'] = "Demissão pelo trabalhador, sem justa causa: deve cumprir {$diasAviso} dias de aviso prévio "
                . "(valor configurado em rh_settings — CONFIRMA com o contabilista se é 15 ou 30 dias para o teu quadro de pessoal).";
            $resultado['avisos'][] = 'A lei prevê 15 ou 30 dias de aviso prévio conforme o caso — o valor usado aqui vem de uma configuração, não de uma regra fixa no código.';
            break;

        default:
            $resultado['detalhe'] = 'Tipo de cessação desconhecido — nenhum cálculo aplicado.';
            $resultado['avisos'][] = "Tipo de cessação '{$tipoCessacao}' não reconhecido.";
    }

    return $resultado;
}
