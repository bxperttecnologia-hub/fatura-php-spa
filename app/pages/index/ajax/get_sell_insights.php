<?php
require_once __DIR__ . '/../../../helpers/require_login.php'; // exige sessão iniciada (401/403 em JSON)

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
    // DEFINIÇÕES ÚNICAS (usadas por TODOS os números do dashboard)
    //
    // FACTURAÇÃO  = faturas FT/FR emitidas (sem rascunhos nem anuladas)
    //               + notas de débito - notas de crédito do período.
    //               Datas por issue_date; valores em AOA (converted_total
    //               quando existe, tal como na listagem de faturas).
    // RECEBIMENTOS = recibos (receipts.amount_paid) por data do pagamento
    //               + faturas-recibo (FR) pagas no acto da emissão, que
    //               não geram linha em receipts.
    //
    // Os estados são lidos pelo NOME em invoice_status (e não por IDs
    // fixos): os IDs 1/2/3/4/5 eram usados de forma contraditória no
    // código antigo (ex.: 4 = "Paga" num sítio e 5 = "Parcial" noutro).
    // =====================================================

    function dashAmountSql($alias = 'i')
    {
        return "(CASE WHEN {$alias}.converted_total > 0 THEN {$alias}.converted_total ELSE {$alias}.final_total END)";
    }

    // Fator de conversão fatura -> AOA para valores em moeda estrangeira
    // guardados em tabelas filhas (recibos, notas) na moeda da fatura.
    function dashFxSql($alias = 'i')
    {
        return "(CASE WHEN {$alias}.converted_total > 0 AND {$alias}.final_total > 0 THEN {$alias}.converted_total / {$alias}.final_total ELSE 1 END)";
    }

    // Requer: JOIN invoice_status ist ON ist.id = i.status
    function dashValidInvoiceSql($alias = 'i', $statusAlias = 'ist')
    {
        return "{$alias}.document_type IN ('FT','FR')
            AND {$alias}.status NOT IN (0, 1)
            AND LOWER(COALESCE({$statusAlias}.name, '')) NOT LIKE 'rascun%'
            AND LOWER(COALESCE({$statusAlias}.name, '')) NOT LIKE 'cancel%'
            AND LOWER(COALESCE({$statusAlias}.name, '')) NOT LIKE 'anul%'";
    }

    // =====================================================
    // FACTURAÇÃO DE UM INTERVALO (ano, mês, trimestre...)
    // =====================================================

    function getFaturacao($pdo, $start, $end, $company_id)
    {
        $valid = dashValidInvoiceSql('i', 'ist');

        // 1) Faturas FT/FR válidas
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(" . dashAmountSql('i') . "), 0)
            FROM invoices i
            LEFT JOIN invoice_status ist ON ist.id = i.status
            WHERE i.company_id = ?
              AND {$valid}
              AND i.issue_date BETWEEN ? AND ?
        ");
        $stmt->execute([$company_id, $start, $end]);
        $faturas = (float)$stmt->fetchColumn();

        // 2) Notas de crédito (reduzem) e de débito (aumentam), de faturas válidas
        $notas = [];
        foreach (['credit_notes' => -1, 'debit_notes' => 1] as $table => $sign) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(n.final_total * " . dashFxSql('i') . "), 0)
                FROM {$table} n
                JOIN invoices i ON i.id = n.invoice_id
                LEFT JOIN invoice_status ist ON ist.id = i.status
                WHERE n.company_id = ?
                  AND {$valid}
                  AND n.issue_date BETWEEN ? AND ?
            ");
            $stmt->execute([$company_id, $start, $end]);
            $notas[$table] = $sign * (float)$stmt->fetchColumn();
        }

        return $faturas + $notas['credit_notes'] + $notas['debit_notes'];
    }

    // Mantidos por compatibilidade com o resto do ficheiro
    function getTotal($pdo, $start, $end, $company_id)
    {
        return getFaturacao($pdo, $start, $end, $company_id);
    }

    function getTotalPeriod($pdo, $start, $end, $company_id)
    {
        return getFaturacao($pdo, $start, $end, $company_id);
    }

    // =====================================================
    // RECEBIMENTOS DE UM INTERVALO
    // =====================================================

    function getRecebimentos($pdo, $start, $end, $company_id)
    {
        $valid = "i.document_type IN ('FT','FR')
            AND i.status NOT IN (0, 1)
            AND LOWER(COALESCE(ist.name, '')) NOT LIKE 'rascun%'
            AND LOWER(COALESCE(ist.name, '')) NOT LIKE 'cancel%'
            AND LOWER(COALESCE(ist.name, '')) NOT LIKE 'anul%'";

        // 1) Recibos emitidos no período (data do pagamento)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(r.amount_paid * " . dashFxSql('i') . "), 0)
            FROM receipts r
            JOIN invoices i ON i.id = r.invoice_id
            LEFT JOIN invoice_status ist ON ist.id = i.status
            WHERE i.company_id = ?
              AND {$valid}
              AND r.pay_date BETWEEN ? AND ?
        ");
        $stmt->execute([$company_id, $start, $end]);
        $recibos = (float)$stmt->fetchColumn();

        // 2) Faturas-recibo (FR) pagas no acto: não têm linha em receipts
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(i.paid_total * " . dashFxSql('i') . "), 0)
            FROM invoices i
            LEFT JOIN invoice_status ist ON ist.id = i.status
            WHERE i.company_id = ?
              AND i.document_type = 'FR'
              AND {$valid}
              AND i.issue_date BETWEEN ? AND ?
              AND NOT EXISTS (SELECT 1 FROM receipts r2 WHERE r2.invoice_id = i.id)
        ");
        $stmt->execute([$company_id, $start, $end]);
        $faturasRecibo = (float)$stmt->fetchColumn();

        return $recibos + $faturasRecibo;
    }

    // Mantido por compatibilidade (card "Recebimentos global")
    function getTotalLiquid($pdo, $start, $end, $company_id)
    {
        return getRecebimentos($pdo, $start, $end, $company_id);
    }

    function getRecebimentosMesAtual($pdo, $company_id, $monthStart, $monthEnd)
    {
        return getRecebimentos($pdo, $monthStart, $monthEnd, $company_id);
    }

    $volumeLiquidMensal = getRecebimentosMesAtual($pdo, $company_id, $monthStart, $monthEnd);

    // =====================================================
    // A RECEBER ESTE MÊS
    // Saldo em aberto das faturas emitidas no mês em análise:
    // final_total + notas de débito - pago - notas de crédito
    // (mesma fórmula de registrar_pagamento.php). Exclui rascunhos,
    // anuladas e faturas já pagas.
    // =====================================================

    function getAReceberMesAtual($pdo, $company_id, $monthStart, $monthEnd)
    {
        $valid = dashValidInvoiceSql('i', 'ist');

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(t.pendente * t.fx), 0) AS total,
                COUNT(*) AS documentos
            FROM (
                SELECT
                    GREATEST(0,
                        i.final_total
                        + COALESCE((SELECT SUM(dn.final_total) FROM debit_notes dn WHERE dn.invoice_id = i.id), 0)
                        - i.paid_total
                        - COALESCE((SELECT SUM(cn.final_total) FROM credit_notes cn WHERE cn.invoice_id = i.id), 0)
                    ) AS pendente,
                    " . dashFxSql('i') . " AS fx
                FROM invoices i
                LEFT JOIN invoice_status ist ON ist.id = i.status
                WHERE i.company_id = ?
                  AND {$valid}
                  AND LOWER(COALESCE(ist.name, '')) NOT LIKE 'pag%'
                  AND i.issue_date BETWEEN ? AND ?
            ) t
            WHERE t.pendente > 0.005
        ");
        $stmt->execute([$company_id, $monthStart, $monthEnd]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (float)($row['total'] ?? 0),
            'documentos' => (int)($row['documentos'] ?? 0)
        ];
    }

    $aReceberMes = getAReceberMesAtual($pdo, $company_id, $monthStart, $monthEnd);

    // Variação dos recebimentos face ao mês anterior
    function getVariacaoRecebimentosMes(
        $pdo,
        $company_id,
        $monthStart,
        $monthEnd,
        $previousMonthStart,
        $previousMonthEnd
    ) {
        $atual    = getRecebimentos($pdo, $monthStart, $monthEnd, $company_id);
        $anterior = getRecebimentos($pdo, $previousMonthStart, $previousMonthEnd, $company_id);

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
    // SPARKLINE GENÉRICO (SOMA POR MÊS)
    // $kind:
    // - 'faturacao'    => getFaturacao  (cards Vendas ano / mês)
    // - 'recebimentos' => getRecebimentos (card Recebimentos)
    // Cada sparkline usa a MESMA função do KPI que representa.
    // =====================================================

    function getSparklineSum(
        $pdo,
        $company_id,
        $months = 6,
        $referenceDate = null,
        $kind = 'faturacao'
    ) {
        $referenceDate = $referenceDate ?? date('Y-m-d');
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $ref   = strtotime($referenceDate . " -{$i} months");
            $start = date('Y-m-01', $ref);
            $end   = date('Y-m-t', $ref);

            $series[] = round(
                $kind === 'recebimentos'
                    ? getRecebimentos($pdo, $start, $end, $company_id)
                    : getFaturacao($pdo, $start, $end, $company_id),
                2
            );
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
    // Sem mês escolhido: ano seleccionado (antes somava todos os anos)
    $topClientesPeriodSql = "AND DATE(i.issue_date) BETWEEN :start AND :end";
    $topClientesParams = [
        'company_id' => $company_id,
        'start' => ($month !== null) ? $monthStart : $yearStart,
        'end'   => ($month !== null) ? $monthEnd   : $today
    ];

    $topClientesSql = "
        SELECT
            c.id,
            c.name AS cliente,
            ROUND(SUM(" . dashAmountSql('i') . "), 2) AS total_faturado,
            COUNT(i.id) AS total_faturas
        FROM invoices i
        LEFT JOIN invoice_status ist ON ist.id = i.status
        INNER JOIN contact c
            ON c.id = i.contact_id
        WHERE i.company_id = :company_id
        AND " . dashValidInvoiceSql('i', 'ist') . "
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

    // Divide pelo nº de meses decorridos no intervalo analisado:
    // ano corrente -> mês actual; ano passado -> 12; mês escolhido -> esse mês.
    // (antes usava sempre o mês real de hoje, falseando anos anteriores)
    $currentMonthNumber = max(
        1,
        (int)date('n', strtotime($today))
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
        SELECT COUNT(DISTINCT i.contact_id) AS total
        FROM invoices i
        LEFT JOIN invoice_status ist ON ist.id = i.status
        WHERE i.company_id = :company_id
        AND " . dashValidInvoiceSql('i', 'ist') . "
        AND DATE(i.issue_date) BETWEEN :start AND :end
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
        FROM invoices i
        LEFT JOIN invoice_status ist ON ist.id = i.status
        WHERE i.company_id = :company_id
        AND " . dashValidInvoiceSql('i', 'ist') . "
        AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
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

            ROUND(SUM(" . dashAmountSql('i') . "), 2) AS total

        FROM invoices i
        LEFT JOIN invoice_status ist ON ist.id = i.status

        WHERE i.company_id = :company_id
        AND " . dashValidInvoiceSql('i', 'ist') . "
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
            ROUND(SUM(" . dashAmountSql('i') . "), 2) AS total
        FROM invoices i
        LEFT JOIN invoice_status ist ON ist.id = i.status
        WHERE i.company_id = :company_id
        AND " . dashValidInvoiceSql('i', 'ist') . "
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

    // Evolução diária para o mês seleccionado no gráfico.
    $dailyStart = sprintf('%04d-01-01', $previousYear);
    $dailySql = "
        SELECT
            DATE_FORMAT(i.issue_date, '%Y-%m-%d') AS dia,
            ROUND(SUM(" . dashAmountSql('i') . "), 2) AS total
        FROM invoices i
        LEFT JOIN invoice_status ist ON ist.id = i.status
        WHERE i.company_id = :company_id
          AND " . dashValidInvoiceSql('i', 'ist') . "
          AND i.issue_date >= :start
          AND i.issue_date < DATE_ADD(:end, INTERVAL 1 DAY)
        GROUP BY DATE(i.issue_date)
        ORDER BY dia ASC
    ";
    $stmtDaily = $pdo->prepare($dailySql);
    $stmtDaily->execute([
        'company_id' => $company_id,
        'start' => $dailyStart,
        'end' => $today
    ]);
    $evolucaoDiaria = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

    // =========================================================
    // ANOS DA FACTURAÇÃO
    // =========================================================

    $yearsSql = "
            SELECT YEAR(i.issue_date) AS ano,
            COUNT(*) AS total FROM invoices i
            WHERE i.company_id = :company_id
            GROUP BY YEAR(i.issue_date) ORDER BY ano;
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
            'faturacao'
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
            'faturacao'
        ),

        // O card de recebimentos usa a mesma função do KPI.
        'total_docs' => getSparklineSum(
            $pdo,
            $company_id,
            $sparkMonths,
            $today,
            'recebimentos'
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
            'evolucao_diaria' => $evolucaoDiaria,
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
