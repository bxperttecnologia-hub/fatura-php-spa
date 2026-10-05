<?php

require_once '../../../app/config/db.php';

header('Content-Type: application/json');

try {

    // =====================================================
    // VALIDAR PARÂMETROS
    // =====================================================

    $company_id = isset($_GET['company_id'])
        ? (int)$_GET['company_id']
        : 0;

    $user_id = isset($_GET['user_id'])
        ? (int)$_GET['user_id']
        : 0;

    $year = isset($_GET['year']) && is_numeric($_GET['year'])
        ? (int)$_GET['year']
        : (int)date('Y');

    // Filtro mensal opcional (planos BXPERT_BAZA / BXPERT_BASE).
    // Sem ?month= o comportamento é exactamente o anterior.
    $month = (isset($_GET['month']) && is_numeric($_GET['month'])
        && (int)$_GET['month'] >= 1 && (int)$_GET['month'] <= 12)
        ? (int)$_GET['month']
        : null;

    if ($company_id <= 0 || $user_id <= 0) {

        echo json_encode([
            'success' => false,
            'error' => 'company_id e user_id são obrigatórios'
        ]);

        exit;
    }

    // =====================================================
    // DATAS
    // =====================================================

    $currentYear = (int)date('Y');

    // Intervalo do ano selecionado
    $yearStart = "{$year}-01-01";
    $yearEnd   = "{$year}-12-31";

    // Se for o ano atual, usa a data atual.
    // Caso contrário, considera o ano completo.
    if ($year === $currentYear) {

        $today = date('Y-m-d');

        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $previousMonthStart = date(
            'Y-m-01',
            strtotime('first day of last month')
        );

        $previousMonthEnd = date(
            'Y-m-t',
            strtotime('last day of last month')
        );
    } else {

        $today = $yearEnd;

        // último mês do ano selecionado
        $monthStart = "{$year}-12-01";
        $monthEnd   = "{$year}-12-31";

        // novembro do ano selecionado
        $previousMonthStart = "{$year}-11-01";
        $previousMonthEnd   = "{$year}-11-30";
    }


    // Mês escolhido: sobrepõe o mês em análise (e o mês anterior, para os crescimentos)
    if ($month !== null) {

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $previousMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
        $previousMonthEnd   = date('Y-m-t', strtotime($previousMonthStart));

        // sparklines/janelas terminam no fim do mês escolhido (ou hoje, se for o mês actual)
        $today = min($monthEnd, date('Y-m-d'));
    }

    // =====================================================
    // ÚLTIMOS 3 MESES
    // =====================================================

    $current = [
        'start' => date(
            'Y-m-01',
            strtotime($today . ' -2 months')
        ),
        'end' => $today
    ];

    // =====================================================
    // 3 MESES ANTERIORES
    // =====================================================

    $previous = [
        'start' => date(
            'Y-m-01',
            strtotime($today . ' -5 months')
        ),
        'end' => date(
            'Y-m-t',
            strtotime($today . ' -3 months')
        )
    ];

    // =====================================================
    // TOTAL GLOBAL (STATUS 1 E 2)
    // =====================================================

    function getTotal($pdo, $start, $end, $company_id)
    {
        $sql = "
        SELECT COALESCE(SUM(final_total), 0)
        FROM invoices
        WHERE company_id = :company_id
        AND status IN (1, 2)
        AND DATE(issue_date) BETWEEN :start AND :end
    ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'start' => $start,
            'end' => $end,
            'company_id' => $company_id
        ]);

        return (float) $stmt->fetchColumn();
    }

    function getTotalPeriod($pdo, $start, $end, $company_id)
    {
        $sql = "
        SELECT COALESCE(SUM(final_total), 0)
        FROM invoices
        WHERE company_id = :company_id
        AND DATE(issue_date) BETWEEN :start AND :end
    ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'start' => $start,
            'end' => $end,
            'company_id' => $company_id
        ]);

        return (float) $stmt->fetchColumn();
    }

    // =====================================================
    // TOTAL LÍQUIDO (STATUS 3 E 4)
    // =====================================================

    function getTotalLiquid($pdo, $start, $end, $company_id)
    {
        $sql = "
        SELECT COALESCE(SUM(final_total), 0)
        FROM invoices
        WHERE company_id = :company_id
        AND status IN (3, 4)
        AND DATE(issue_date) BETWEEN :start AND :end
    ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'start' => $start,
            'end' => $end,
            'company_id' => $company_id
        ]);

        return (float) $stmt->fetchColumn();
    }
    function getRecebimentosMesAtual($pdo, $company_id, $monthStart, $monthEnd)
    {
        $sql = "
        SELECT COALESCE(SUM(final_total), 0)
        FROM invoices
        WHERE company_id = :company_id
          AND status IN (3,4)
          AND DATE(issue_date) BETWEEN :start AND :end
    ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_id' => $company_id,
            'start' => $monthStart,
            'end' => $monthEnd
        ]);

        return (float)$stmt->fetchColumn();
    }

    // CORRIGIDO: antes usava sempre CURDATE(), ignorando o ?year=
    // escolhido pelo utilizador. Agora usa $monthStart/$monthEnd,
    // que já respeitam o ano selecionado.
    $volumeLiquidMensal = getRecebimentosMesAtual($pdo, $company_id, $monthStart, $monthEnd);

    // =====================================================
    // A RECEBER ESTE MÊS
    // Saldo em aberto (final_total - paid_total) das faturas
    // emitidas no mês em análise. Não depende do status,
    // porque este usa diretamente o valor já pago em cada
    // fatura (paid_total), evitando presumir o significado
    // de cada código de status.
    // =====================================================

    function getAReceberMesAtual($pdo, $company_id, $monthStart, $monthEnd)
    {
        $sql = "
        SELECT
            COALESCE(SUM(final_total - paid_total), 0) AS total,
            COUNT(*) AS documentos
        FROM invoices
        WHERE company_id = :company_id
          AND DATE(issue_date) BETWEEN :start AND :end
          AND (final_total - paid_total) > 0
    ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_id' => $company_id,
            'start' => $monthStart,
            'end' => $monthEnd
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (float)($row['total'] ?? 0),
            'documentos' => (int)($row['documentos'] ?? 0)
        ];
    }

    $aReceberMes = getAReceberMesAtual($pdo, $company_id, $monthStart, $monthEnd);


    // recebimentos mensais crescimento
    function getVariacaoRecebimentosMes(
        $pdo,
        $company_id,
        $monthStart,
        $monthEnd,
        $previousMonthStart,
        $previousMonthEnd
    ) {
        $sql = "
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN DATE(issue_date) BETWEEN :monthStart AND :monthEnd
                    THEN final_total
                END
            ), 0) AS mes_atual,

            COALESCE(SUM(
                CASE
                    WHEN DATE(issue_date) BETWEEN :prevStart AND :prevEnd
                    THEN final_total
                END
            ), 0) AS mes_anterior

        FROM invoices
        WHERE company_id = :company_id
          AND status IN (3,4)
    ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_id' => $company_id,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'prevStart' => $previousMonthStart,
            'prevEnd' => $previousMonthEnd
        ]);

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        $atual = (float) $dados['mes_atual'];
        $anterior = (float) $dados['mes_anterior'];

        if ($anterior > 0) {
            $percentual = (($atual - $anterior) / $anterior) * 100;
        } elseif ($atual > 0) {
            // Não houve recebimentos no mês anterior
            $percentual = 100;
        } else {
            // Ambos os meses sem recebimentos
            $percentual = 0;
        }

        return [
            'mes_atual'    => $atual,
            'mes_anterior' => $anterior,
            'percentual'   => round($percentual, 2)
        ];
    }

    // CORRIGIDO: mesma correção do KPI anterior - passa a respeitar
    // o ?year= selecionado, em vez de assumir sempre o mês atual real.
    $volumeLiquidMensalCrescimento = getVariacaoRecebimentosMes(
        $pdo,
        $company_id,
        $monthStart,
        $monthEnd,
        $previousMonthStart,
        $previousMonthEnd
    );


    // =====================================================
    // FUNÇÃO CRESCIMENTO
    // =====================================================

    function calcGrowth($current, $previous)
    {
        $current = (float)$current;
        $previous = (float)$previous;

        if ($previous <= 0) {

            if ($current > 0) {
                return 100;
            }

            return 0;
        }

        return round(
            (($current - $previous) / $previous) * 100,
            1
        );
    }

    // =====================================================
    // NOVO: VERIFICA SE HÁ HISTÓRICO ANTES DE UMA DATA
    // Evita mostrar "-100%" quando na verdade não há
    // dado anterior suficiente para comparar (ex: empresa
    // nova, poucos meses de uso).
    // =====================================================

    function hasSufficientHistory($pdo, $beforeDate, $company_id)
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM invoices
            WHERE company_id = :company_id
            AND issue_date < :beforeDate
        ");

        $stmt->execute([
            'company_id' => $company_id,
            'beforeDate' => $beforeDate
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    // =====================================================
    // CORRIGIDO: SPARKLINE GENÉRICO (SOMA POR MÊS)
    // Recebe $statusList para que cada sparkline use
    // EXATAMENTE o mesmo filtro de status do KPI a que
    // pertence — evita mostrar uma tendência que não bate
    // certo com o número apresentado no card.
    //
    // $statusList:
    // - [1, 2] => mesmo filtro de getTotal (volume_global,
    //             venda_periodo)
    // - [3, 4] => mesmo filtro de getTotalLiquid (volume_liquid
    //             / card "Recebimentos Global")
    // =====================================================

    function getSparklineSum(
        $pdo,
        $company_id,
        $months = 6,
        $referenceDate = null,
        $statusList = null
    ) {
        $referenceDate = $referenceDate ?? date('Y-m-d');

        $start = date(
            'Y-m-01',
            strtotime($referenceDate . " -" . ($months - 1) . " months")
        );

        $statusFilter = '';
        $params = [
            'company_id' => $company_id,
            'start' => $start,
            'end' => $referenceDate
        ];

        if (is_array($statusList) && count($statusList) > 0) {

            $placeholders = [];

            foreach ($statusList as $i => $status) {
                $key = "status{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $status;
            }

            $statusFilter = "AND status IN (" . implode(',', $placeholders) . ")";
        }

        $sql = "
            SELECT
                DATE_FORMAT(issue_date, '%Y-%m') AS mes,
                COALESCE(SUM(final_total), 0) AS total
            FROM invoices
            WHERE company_id = :company_id
            {$statusFilter}
            AND issue_date >= :start
            AND issue_date <= :end
            GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
            ORDER BY mes ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) {
            $map[$r['mes']] = (float)$r['total'];
        }

        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime($referenceDate . " -{$i} months"));
            $series[] = round($map[$key] ?? 0, 2);
        }

        return $series;
    }

    // =====================================================
    // NOVO: SPARKLINE DE MÉDIA ACUMULADA
    // Reconstrói, mês a mês, o mesmo cálculo do KPI
    // "média mensal de vendas" (volume acumulado desde o
    // início do ano até esse mês, dividido pelo nº do mês).
    // Assim o sparkline reflecte de facto a tendência da
    // média, e não apenas repete o volume bruto.
    // =====================================================

    function getSparklineAverage(
        $pdo,
        $company_id,
        $year,
        $months = 6,
        $referenceDate = null
    ) {
        $referenceDate = $referenceDate ?? date('Y-m-d');

        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {

            $monthDate = strtotime($referenceDate . " -{$i} months");
            $monthEnd = date('Y-m-t', $monthDate);
            $monthNumber = max(1, (int)date('n', $monthDate));
            $monthYearStart = date('Y', $monthDate) . '-01-01';

            $acumulado = getTotal(
                $pdo,
                $monthYearStart,
                $monthEnd,
                $company_id
            );

            $series[] = round($acumulado / $monthNumber, 2);
        }

        return $series;
    }

    // =====================================================
    // TOP CLIENTES
    // =====================================================

    // Com filtro mensal, os principais clientes são os do mês escolhido
    $topClientesPeriodSql = ($month !== null)
        ? "AND DATE(i.issue_date) BETWEEN :start AND :end"
        : "";
    $topClientesParams = ['company_id' => $company_id];
    if ($month !== null) {
        $topClientesParams['start'] = $monthStart;
        $topClientesParams['end']   = $monthEnd;
    }

    $topClientesSql = "
        SELECT
            c.id,
            c.name AS cliente,
            ROUND(SUM(i.final_total), 2) AS total_faturado,
            COUNT(i.id) AS total_faturas
        FROM invoices i
        INNER JOIN contact c
            ON c.id = i.contact_id
        WHERE i.company_id = :company_id
        AND i.status NOT IN (1,2)
        {$topClientesPeriodSql}
        GROUP BY c.id, c.name
        ORDER BY total_faturado DESC
        LIMIT 10
    ";

    $stmtTop = $pdo->prepare($topClientesSql);

    $stmtTop->execute($topClientesParams);

    $topClientes = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // VOLUME GLOBAL
    // =====================================================

    $volumeGlobal = getTotal(
        $pdo,
        $yearStart,
        $today,
        $company_id
    );

    // =====================================================
    // VOLUME Liquido
    // =====================================================

    $volumeLiquid = getTotalLiquid(
        $pdo,
        $yearStart,
        $today,
        $company_id
    );

    // =====================================================
    // VENDA PERÍODO
    // =====================================================

    $vendaPeriodo = getTotalPeriod(
        $pdo,
        $monthStart,
        $monthEnd,
        $company_id
    );

    // =====================================================
    // VENDA PERÍODO ANTERIOR
    // =====================================================

    $vendaPeriodoAnterior = getTotal(
        $pdo,
        $previousMonthStart,
        $previousMonthEnd,
        $company_id
    );

    // =====================================================
    // HÁ HISTÓRICO SUFICIENTE?
    // =====================================================

    $temHistoricoMensal = hasSufficientHistory(
        $pdo,
        $previousMonthStart,
        $company_id
    );

    $temHistorico3Meses = hasSufficientHistory(
        $pdo,
        $previous['start'],
        $company_id
    );

    // =====================================================
    // CRESCIMENTO PERÍODO
    // =====================================================

    $vendaPeriodoGrowth = calcGrowth(
        $vendaPeriodo,
        $vendaPeriodoAnterior
    );

    // =====================================================
    // MÉDIA MENSAL
    // =====================================================

    $currentMonthNumber = max(
        1,
        (int)date('n')
    );

    $mediaMensal = round(
        $volumeGlobal / $currentMonthNumber,
        2
    );

    // =====================================================
    // COMPARAÇÃO 3 MESES
    // =====================================================

    $volumeAtual = getTotal(
        $pdo,
        $current['start'],
        $current['end'],
        $company_id
    );

    $volumeAnterior = getTotal(
        $pdo,
        $previous['start'],
        $previous['end'],
        $company_id
    );

    $mediaAtual = $volumeAtual / 3;
    $mediaAnterior = $volumeAnterior / 3;

    $mediaMensal_dif = calcGrowth(
        $mediaAtual,
        $mediaAnterior
    );

    // =====================================================
    // CRESCIMENTO GERAL
    // =====================================================

    $crescimento = calcGrowth(
        $volumeAtual,
        $volumeAnterior
    );

    // =====================================================
    // CLIENTES ATIVOS
    // =====================================================

    $clientesAtivosStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT contact_id) AS total
        FROM invoices
        WHERE company_id = :company_id
        AND status NOT IN (1,2)
        AND DATE(issue_date) BETWEEN :start AND :end
    ");

    $clientesAtivosStmt->execute([
        'company_id' => $company_id,
        'start' => $monthStart,
        'end' => $monthEnd
    ]);

    $clientesAtivos = (int)$clientesAtivosStmt->fetchColumn();

    // =====================================================
    // TOTAL CLIENTES
    // =====================================================

    $clientesStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM contact
        WHERE company_id = :company_id
    ");

    $clientesStmt->execute([
        'company_id' => $company_id
    ]);

    $clientes = (int)$clientesStmt->fetchColumn();

    // =====================================================
    // DOCUMENTOS
    // =====================================================

    $documentosStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT i.id) AS total_i,

        COUNT(DISTINCT r.id) AS total_r,

        COUNT(DISTINCT c.id) AS total_c,

        (
            COUNT(DISTINCT i.id) +
            COUNT(DISTINCT r.id) +
            COUNT(DISTINCT c.id)
        ) AS total

    FROM invoices i

    LEFT JOIN receipts r
        ON r.invoice_id = i.id

    LEFT JOIN credit_notes c
        ON c.invoice_id = i.id

    WHERE i.company_id = :company_id
      AND i.issue_date >= :start
      AND i.issue_date < DATE_ADD(:end, INTERVAL 1 DAY)
");

    $documentosStmt->execute([
        ':company_id' => $company_id,
        ':start'      => $monthStart,
        ':end'        => $monthEnd
    ]);

    // CORRIGIDO: fetchColumn() sem argumento devolve a 1ª coluna
    // da query (total_i = só faturas). O valor pretendido é a
    // 4ª coluna (índice 3), que soma invoices + receipts + credit_notes.
    $documentos = (int)$documentosStmt->fetchColumn(3);

    // =====================================================
    // NOVOS CLIENTES
    // =====================================================

    $novosClientesStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM contact
        WHERE company_id = :company_id
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    ");

    $novosClientesStmt->execute([
        'company_id' => $company_id
    ]);

    $novosClientes = (int)$novosClientesStmt->fetchColumn();

    // =====================================================
    // NOVOS DOCUMENTOS
    // =====================================================

    $novosDocumentosStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM invoices
        WHERE company_id = :company_id
        AND status NOT IN (1,2)
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
    ");

    $novosDocumentosStmt->execute([
        'company_id' => $company_id
    ]);

    $novosDocumentos = (int)$novosDocumentosStmt->fetchColumn();

    // =====================================================
    // EVOLUÇÃO MENSAL
    // =====================================================

    $evolucaoSql = "
        SELECT
            DATE_FORMAT(i.issue_date, '%Y-%m') AS mes,

            ROUND(SUM(i.final_total), 2) AS total

        FROM invoices i

        WHERE i.company_id = :company_id
        AND i.status NOT IN (1, 2)
        AND i.issue_date >= :start
        AND i.issue_date < DATE_ADD(:end, INTERVAL 1 DAY)

        GROUP BY DATE_FORMAT(i.issue_date, '%Y-%m')

        ORDER BY mes ASC
    ";

    $stmtEvolucao = $pdo->prepare($evolucaoSql);

    $stmtEvolucao->execute([
        'start' => $yearStart,
        'end' => $today,
        'company_id' => $company_id
    ]);

    $evolucao = $stmtEvolucao->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // NOVO: EVOLUÇÃO DO ANO ANTERIOR (para comparação
    // lado a lado no gráfico, em vez de uma barra isolada)
    // =====================================================

    $previousYear = $year - 1;

    $evolucaoAnteriorSql = "
        SELECT
            DATE_FORMAT(i.issue_date, '%Y-%m') AS mes,
            ROUND(SUM(i.final_total), 2) AS total
        FROM invoices i
        WHERE i.company_id = :company_id
        AND i.status NOT IN (1, 2)
        AND YEAR(i.issue_date) = :previousYear
        GROUP BY DATE_FORMAT(i.issue_date, '%Y-%m')
        ORDER BY mes ASC
    ";

    $stmtEvolucaoAnterior = $pdo->prepare($evolucaoAnteriorSql);

    $stmtEvolucaoAnterior->execute([
        'company_id' => $company_id,
        'previousYear' => $previousYear
    ]);

    $evolucaoAnterior = $stmtEvolucaoAnterior->fetchAll(PDO::FETCH_ASSOC);

    // =========================================================
    // ANOS DA FACTURAÇÃO
    // =========================================================

    $yearsSql = "
            SELECT YEAR(i.created_at) AS ano, 
            COUNT(*) AS total FROM invoices i 
            WHERE i.company_id = :company_id
            GROUP BY YEAR(i.created_at) ORDER BY ano;
        ";

    $stmtYears = $pdo->prepare($yearsSql);

    $stmtYears->execute([
        'company_id' => $company_id
    ]);

    $yearsInvoices = $stmtYears->fetchAll(PDO::FETCH_ASSOC);

    // =====================================================
    // CORRIGIDO: SPARKLINES (últimos 6 meses até $today)
    // Uma série por card, cada uma usando o MESMO filtro de
    // status do KPI que representa:
    //
    // - trimestral_volume (Volume global)   -> status IN(1,2)
    // - month_average    (Média mensal)     -> média acumulada
    //                                          derivada do volume
    //                                          global (mesmo
    //                                          filtro IN(1,2))
    // - month_sell       (Venda período)    -> status IN(1,2),
    //                                          série mensal
    // - total_docs       (na verdade exibe
    //                      kpis.volume_liquid)-> status IN(3,4)
    // =====================================================

    $sparkMonths = 6;

    $sparklines = [

        'volume_global' => getSparklineSum(
            $pdo,
            $company_id,
            $sparkMonths,
            $today,
            [1, 2]
        ),

        'media_mensal' => getSparklineAverage(
            $pdo,
            $company_id,
            $year,
            $sparkMonths,
            $today
        ),

        'venda_periodo' => getSparklineSum(
            $pdo,
            $company_id,
            $sparkMonths,
            $today,
            [1, 2]
        ),

        // CORRIGIDO: o card "total_docs" exibe kpis.volume_liquid
        // no front (status IN(3,4)), por isso o sparkline usa
        // o mesmo filtro — não o de documentos/contagem.
        'total_docs' => getSparklineSum(
            $pdo,
            $company_id,
            $sparkMonths,
            $today,
            [3, 4]
        ),
    ];

    // =====================================================
    // RESPONSE
    // =====================================================

    echo json_encode([
        'success' => true,

        'data' => [

            'top_clients' => $topClientes,

            'kpis' => [

                'volume_global' => round($volumeGlobal, 2),
                'volume_liquid' => round($volumeLiquid, 2),
                'volume_liquid_mensal' => round($volumeLiquidMensal, 2),
                'volume_liquid_mensal_crescimento' => $volumeLiquidMensalCrescimento,

                'a_receber_mensal' => round($aReceberMes['total'], 2),
                'a_receber_documentos' => $aReceberMes['documentos'],

                'media_mensal' => round($mediaMensal, 2),

                // null quando não há histórico suficiente -
                // o front mostra "Sem dados anteriores" em vez
                // de um -100% enganoso
                'media_mensal_dif' => $temHistorico3Meses
                    ? round($mediaMensal_dif, 1)
                    : null,

                'venda_periodo' => round(
                    $vendaPeriodo,
                    2
                ),

                'venda_periodo_growth' => $temHistoricoMensal
                    ? round($vendaPeriodoGrowth, 1)
                    : null,

                'clientes' => $clientes,

                'clientes_ativos' => $clientesAtivos,

                'crescimento_clientes' => $novosClientes,

                'documentos' => $documentos,

                'crescimento_documentos' => $novosDocumentos,

                'crescimento' => $temHistorico3Meses
                    ? round($crescimento, 1)
                    : null
            ],

            'comparacao' => [

                'atual' => round(
                    $volumeAtual,
                    2
                ),

                'anterior' => round(
                    $volumeAnterior,
                    2
                )
            ],

            'evolucao' => $evolucao,
            'evolucao_anterior' => $evolucaoAnterior,
            'yearsInvoices' => $yearsInvoices,

            // CORRIGIDO: sparklines dos 4 cards, consistentes
            // com os filtros de status usados em cada KPI
            'sparklines' => $sparklines
        ]
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
