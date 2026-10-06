<?php

/**
 * Exportação de proformas/faturas para Excel (.xlsx) ou CSV.
 *
 * Correções em relação à versão anterior:
 *  - CSV estava completamente partido: $sqlItems só era definido dentro
 *    do bloco "excel", por isso o export CSV rebentava com um erro fatal.
 *  - Faltava filtrar por empresa/utilizador autenticado: qualquer pessoa
 *    conseguia exportar faturas de TODAS as empresas (fuga de dados grave
 *    num sistema multi-empresa).
 *  - N+1 queries: uma query por fatura para buscar os itens. Passa a ser
 *    uma única query com "WHERE proforma_id IN (...)" para todas as
 *    faturas, agrupada depois em PHP.
 *  - O progresso em $_SESSION nunca funcionava de facto: session_start()
 *    bloqueia o ficheiro de sessão durante toda a execução do script, por
 *    isso o pedido que consulta ?status= ficava à espera do export acabar
 *    para poder ler o progresso. Corrigido com session_write_close().
 *  - Divisão por zero quando não há faturas para exportar.
 *  - catch (Exception) não apanha Error/TypeError/DivisionByZeroError no
 *    PHP 7+. Passa a apanhar Throwable.
 *  - Depois de já ter enviado o header binário, um erro era devolvido como
 *    JSON dentro de uma resposta "application/octet-stream" — o browser
 *    tentava fazer download de um ficheiro inválido em vez de mostrar o
 *    erro. Agora só define os headers de download quando a geração é
 *    garantida.
 *  - Números eram sempre convertidos para texto (number_format), o que no
 *    Excel os torna texto em vez de números (quebra somas/ordenação). No
 *    Excel os valores passam a ser numéricos reais, com formatação de
 *    célula aplicada.
 *  - CSV sem BOM UTF-8: acentos (ç, ã, é) ficavam ilegíveis ao abrir no
 *    Excel. Adicionado BOM.
 *  - Validação do parâmetro `formato` (evita comportamento indefinido com
 *    valores inesperados).
 */

session_start();

// Ligado desde já: protege TODOS os header() seguintes contra qualquer
// aviso/output acidental de ficheiros incluídos (db.php, autoload.php, etc.).
ob_start();

require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../../../vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pedido de consulta de progresso (feito por outra aba/polling em JS).
 * Não deve exigir toda a app carregada, mas mantemos igual ao original.
 */
if (isset($_GET['status'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['progress' => $_SESSION['export_progress'] ?? 0]);
    exit;
}

/**
 * TODO: ajustar à tua camada de autenticação real.
 * Isto assume que o login guarda o id da empresa ativa na sessão.
 */
$companyId = $_SESSION['company_id'] ?? null;

if (!$companyId) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Sessão inválida ou empresa não identificada.']);
    exit;
}

/**
 * Atualiza o progresso e liberta imediatamente o lock do ficheiro de
 * sessão, para que o pedido de polling (?status=) consiga ler o valor
 * atual sem ficar bloqueado até este script terminar.
 */
function atualizarProgresso(int $percentagem): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['export_progress'] = $percentagem;
    session_write_close();
}

atualizarProgresso(0);

// Formato solicitado
$formatosPermitidos = ['excel', 'csv'];
$formato = $_GET['formato'] ?? 'excel';

if (!in_array($formato, $formatosPermitidos, true)) {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Formato inválido. Use "excel" ou "csv".']);
    exit;
}

// Evita que o script morra a meio de exportações grandes.
set_time_limit(0);
ini_set('memory_limit', '512M');

try {
    atualizarProgresso(10);

    /**
     * Filtros opcionais de período (?data_inicio=YYYY-MM-DD&data_fim=YYYY-MM-DD).
     * Evita exportações gigantes por omissão e permite ao utilizador
     * pedir só o intervalo que precisa.
     */
    $dataInicio = $_GET['data_inicio'] ?? null;
    $dataFim = $_GET['data_fim'] ?? null;

    $condicoes = ['p.company_id = :companyId'];
    $parametros = [':companyId' => $companyId];

    if ($dataInicio) {
        $condicoes[] = 'p.issue_date >= :dataInicio';
        $parametros[':dataInicio'] = $dataInicio;
    }

    if ($dataFim) {
        $condicoes[] = 'p.issue_date <= :dataFim';
        $parametros[':dataFim'] = $dataFim;
    }

    $whereSql = implode(' AND ', $condicoes);

    $sql = "
        SELECT
            p.id AS proforma_id,
            CONCAT(YEAR(p.issue_date), '/', p.id) AS codigo,
            p.issue_date,
            p.due_date,
            p.final_total,
            p.total_tax,
            p.subtotal_without_tax,
            c.name AS client_name,
            comp.name AS company_name
        FROM proformas p
        JOIN companies comp ON comp.id = p.company_id
        JOIN contact c ON c.id = p.contact_id
        WHERE $whereSql
        ORDER BY p.issue_date DESC, p.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    atualizarProgresso(30);

    /**
     * Uma única query para os itens de TODAS as faturas encontradas
     * (em vez de uma query de itens por cada fatura, que era o
     * problema de N+1 na versão anterior). Os itens são depois
     * agrupados em PHP por proforma_id.
     */
    $itemsPorFatura = [];

    if (count($invoices) > 0) {
        $proformaIds = array_column($invoices, 'proforma_id');
        $placeholders = implode(',', array_fill(0, count($proformaIds), '?'));

        $sqlItems = "
            SELECT
                ii.proforma_id,
                it.description,
                ii.quantity,
                ii.unit_price,
                ii.tax
            FROM proforma_items ii
            JOIN items it ON it.id = ii.item_id
            WHERE ii.proforma_id IN ($placeholders)
        ";

        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute($proformaIds);

        while ($item = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
            $descricaoItem = sprintf(
                '%s (%sx %s - %s%% IVA)',
                $item['description'],
                $item['quantity'],
                number_format((float) $item['unit_price'], 2, ',', '.'),
                $item['tax']
            );

            $itemsPorFatura[$item['proforma_id']][] = $descricaoItem;
        }
    }

    $cabecalho = [
        'Número da Fatura',
        'Data Emissão',
        'Data Vencimento',
        'Cliente',
        'Empresa',
        'Total Líquido',
        'Total Imposto',
        'Total Final',
        'Produtos/Serviços da Fatura',
    ];

    $totalFaturas = count($invoices);

    if ($totalFaturas === 0) {
        atualizarProgresso(100);
        ob_end_clean();
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Não há faturas para exportar no período indicado.']);
        exit;
    }

    if ($formato === 'excel') {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Faturas');
        $sheet->fromArray($cabecalho, null, 'A1');

        $row = 2;
        $faturaAtual = 0;

        foreach ($invoices as $invoice) {
            $faturaAtual++;
            $itemsString = implode(', ', $itemsPorFatura[$invoice['proforma_id']] ?? []);

            $sheet->fromArray([
                $invoice['codigo'],
                $invoice['issue_date'],
                $invoice['due_date'],
                $invoice['client_name'],
                $invoice['company_name'],
                (float) $invoice['subtotal_without_tax'],
                (float) $invoice['total_tax'],
                (float) $invoice['final_total'],
                $itemsString,
            ], null, "A$row");

            // Colunas F, G, H ficam como números reais formatados
            // como moeda, em vez de texto.
            $sheet->getStyle("F$row:H$row")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');

            $row++;

            if ($faturaAtual % 25 === 0 || $faturaAtual === $totalFaturas) {
                atualizarProgresso(30 + (int) (($faturaAtual / $totalFaturas) * 50));
            }
        }

        // Larguras automáticas para leitura mais confortável
        foreach (range('A', 'I') as $coluna) {
            $sheet->getColumnDimension($coluna)->setAutoSize(true);
        }

        atualizarProgresso(90);

        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="faturas.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        atualizarProgresso(100);
    } else { // csv
        atualizarProgresso(50);

        ob_end_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="faturas.csv"');

        $output = fopen('php://output', 'w');

        // BOM UTF-8: sem isto o Excel mostra acentos trocados.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $cabecalho, ';');

        $faturaAtual = 0;

        foreach ($invoices as $invoice) {
            $faturaAtual++;
            $itemsString = implode(', ', $itemsPorFatura[$invoice['proforma_id']] ?? []);

            fputcsv($output, [
                $invoice['codigo'],
                $invoice['issue_date'],
                $invoice['due_date'],
                $invoice['client_name'],
                $invoice['company_name'],
                number_format((float) $invoice['subtotal_without_tax'], 2, ',', '.'),
                number_format((float) $invoice['total_tax'], 2, ',', '.'),
                number_format((float) $invoice['final_total'], 2, ',', '.'),
                $itemsString,
            ], ';');

            if ($faturaAtual % 25 === 0 || $faturaAtual === $totalFaturas) {
                atualizarProgresso(50 + (int) (($faturaAtual / $totalFaturas) * 50));
            }
        }

        fclose($output);
        atualizarProgresso(100);
    }

    exit;
} catch (\Throwable $e) {
    // Garante que nenhum header binário foi enviado antes do erro.
    if (ob_get_length() !== false) {
        ob_end_clean();
    }

    error_log('Erro ao exportar faturas: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erro ao gerar o arquivo. Tente novamente ou contacte o suporte.']);
    atualizarProgresso(0);
    exit;
}
