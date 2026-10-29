<html>
<?php
require_once '../app/views/layout_creation.php';
require_once '../app/config/db.php';
require_once '../app/helpers/subscription.php';

// =====================================================
// PLANO CONTRATADO — controla o que é mostrado no dashboard
// (ex.: BXPERT_BAZA/BXPERT_BASE só veem o relatório diário
// e não têm acesso ao separador de Recursos Humanos, no
// caso do BAZA).
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dashboardCompanyId = (int) ($_SESSION['user']['company_id'] ?? 0);
$dashboardPlan = subscription_get_plan($pdo, $dashboardCompanyId);
$dashboardReportsScope = subscription_reports_scope($dashboardPlan); // 'daily' | 'full'
// Planos BXPERT_BAZA / BXPERT_BASE (relatório diário): layout próprio e filtro mensal.
$isDailyPlan = ($dashboardReportsScope !== 'full');
$dashboardCanRH = subscription_feature_allowed($dashboardPlan, 'rh');
// Guardado para quando o separador de Stock for lançado; por agora ele
// continua sempre oculto no HTML (ver mais abaixo), independentemente do plano.
$dashboardCanStock = subscription_feature_allowed($dashboardPlan, 'stock');
?>

<!-- OWL CAROUSEL -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css">

<style>
    .tags {
        background: none;
        border-radius: 5px;
    }

    .tag-link {
        background: #fff;
        border: none;
        box-shadow: 1px 1px 1px 1px 10px #f5f5f5;
    }

    .tag-link:hover {
        background: #fff;
    }

    .tag-link.active {
        background: #007abd;
        color: #fff;
        font-weight: bold;
    }

    .tag-wrapper {
        position: relative;
    }

    .tag-content {
        position: absolute;
        width: 100%;
        top: 0;
        left: 0;

        opacity: 0;
        transform: translateY(15px);
        filter: blur(6px);

        transition:
            opacity 0.4s ease,
            transform 0.4s ease,
            filter 0.4s ease;

        pointer-events: none;
    }

    .tag-content.active {
        opacity: 1;
        transform: translateY(0);
        filter: blur(0);

        pointer-events: auto;
        position: relative;
    }

    .tag-content.exit {
        opacity: 0;
        transform: translateY(-10px);
        filter: blur(4px);
    }

    .icon-box {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }

    .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 12px;
        font-weight: 600;
    }

    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;

        padding: 10px;
        border-radius: 10px;

        transition: 0.2s;
    }

    .list-item:hover {
        background: #f8f9fa;
    }

    .card-custom {
        border-radius: 16px;
        border: 1px solid #eee;
        transition: 0.3s;
    }

    .card-custom:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    /* ---- Card do Assistente IA ---- */
    .ai-insights-card {
        border: 1px solid #e6def7;
        background: linear-gradient(135deg, #f8f5ff 0%, #ffffff 55%);
    }

    .ai-insights-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 12px;
        background: rgba(111, 66, 193, 0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #6f42c1;
    }

    .ai-insights-badge {
        background: #6f42c1;
        color: #fff;
        font-size: 11px;
        vertical-align: middle;
    }

    .ai-insight-chip {
        border: 1px solid #eee;
        border-left: 3px solid #6f42c1;
        border-radius: 10px;
        padding: 10px 12px;
        background: #fff;
    }

    .icon-box {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #007abd;
    }

    .icon-box i {
        color: #fff !important;
    }

    .small-text {
        font-size: 13px;
        color: #6c757d;
    }

    .hover-row:hover {
        background: #f8f9fa;
    }

    #clients {
        height: 500px !important;
        margin-top: 5px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        /* aqui pode continuar hidden, é só o wrapper externo */
    }

    #clients .d-flex.justify-content-between {
        flex-shrink: 0;
        /* cabeçalho nunca encolhe nem rola */
    }

    #topClients {
        flex: 1;
        overflow-y: auto;
        padding-right: 4px;
        /* espaço pra não colar a barra de scroll no texto */
    }

    #topClients::-webkit-scrollbar {
        width: 6px;
    }

    #topClients::-webkit-scrollbar-thumb {
        background-color: #ccc;
        border-radius: 10px;
    }

    .text-green {
        color: #28a745 !important;
    }

    .text-danger {
        color: red !important;
    }

    #low_stock_list,
    #purchase_list {
        max-height: 300px;
        overflow-y: auto;
    }

    .h-title {
        color: #007abd;
        border: 2px solid #007bbd41;
        border-radius: 8px;
        padding: 5px;
        font-weight: bolder;
        width: auto;
        margin: 0;
        font-size: 0.9rem;
    }

    .card-item {
        width: 20.1rem !important;
    }

    #trimestreSelect {
        width: 200px !important;
        float: right !important;
    }

    /* NOVO: texto neutro para "sem dados anteriores",
       usado quando não há histórico suficiente para
       calcular variação percentual */
    .text-muted-dif {
        color: #6c757d !important;
        font-weight: normal !important;
    }

    .top-cards {
        height: 200px !important;
    }

    /* Planos BXPERT_BAZA / BXPERT_BASE: card de Recebimentos acompanha a altura do Relatório Diário */
    .card.daily-fill.top-cards {
        height: auto !important;
        min-height: 200px;
    }

    /* ============================= */
    /* RESPONSIVIDADE EXTRA */
    /* ============================= */

    @media (max-width: 768px) {
        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
        }

        .tag-link {
            flex: 1;
            text-align: center;
            font-size: 13px;
            padding: 8px;
        }
    }

    @media (max-width: 1200px) {
        .card-item {
            width: 18rem !important;
        }
    }

    @media (max-width: 992px) {
        .card-item {
            width: 48% !important;
        }
    }

    @media (max-width: 576px) {
        .card-item {
            width: 100% !important;
        }

        .cards {
            flex-direction: column;
        }
    }

    @media (max-width: 992px) {
        #chart-card {
            margin-bottom: 20px;
        }
    }

    @media (max-width: 576px) {
        #trimestreSelect {
            width: 100% !important;
            float: none !important;
        }
    }

    @media (max-width: 768px) {
        #clients {
            height: auto !important;
        }

        #topClients {
            max-height: 250px;
        }
    }

    @media (max-width: 992px) {
        .col-md-3 {
            flex: 0 0 50%;
            max-width: 50%;
        }
    }

    @media (max-width: 576px) {
        .col-md-3 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    @media (max-width: 768px) {
        .row.g-3.mt-2 {
            flex-direction: column;
        }
    }

    @media (max-width: 576px) {
        .owl-carousel .card {
            margin: 0 auto;
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .h-title {
            font-size: 0.8rem;
            width: 100%;
            text-align: center;
        }
    }

    .dashboard-carousel .item {
        padding: 5px;
    }

    .dashboard-carousel .card {
        height: 100%;
    }

    .owl-stage {
        display: flex;
    }

    .owl-item {
        transition: all 0.3s ease;
    }
</style>

<body>
    <main style="background: #f7f7f7;">
        <div>
            <div class="container">
                <div class="row g-4 mt-5">
                    <div class="col-12">
                        <div class="d-flex justify-content-between col-12 col-sm-12">
                            <div class="tags">
                                <a href="#" data-tag="sell" class="tag-link active btn">Vendas</a>
                                <!-- Stock permanece oculto para todos os planos (módulo ainda não lançado) -->
                                <a href="#" data-tag="stock" class="tag-link btn d-none">Stock</a>
                                <a href="#" data-tag="rh" class="tag-link btn<?= $dashboardCanRH ? '' : ' d-none' ?>">Recursos Humanos</a>
                            </div>
                            <div class="mb-3">
                                <div style="width: 180px !important; margin-right: -80px;" name="export" id="exportData">
                                    <?php if ($isDailyPlan): ?>
                                        <select id="monthSelect" class="form-select" style="width: 100%;" aria-label="Mês"></select>
                                    <?php else: ?>
                                        <select id="yearSelect" class="form-select col-3 w-60" style="width: 100px;">
                                        </select>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="tag_contents p-0 col-12">

                        <!-- Sessao Gestao de Vendas -->
                        <div class="col-12 tag-content active" data-tag-content="sell">

                            <!-- ================== CARD: ASSISTENTE IA (INSIGHTS PROATIVOS) ==================
                            <div class="card card-custom p-3 mb-4 ai-insights-card" id="aiInsightsCard">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="ai-insights-icon">
                                            <i class="bi bi-robot"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 h-title">
                                                BXPERT Intelligence
                                                <span class="badge rounded-pill ai-insights-badge ms-1" id="aiInsightsBadge" hidden>0 novos</span>
                                            </h6>
                                            <div id="aiInsightsSummary" class="small text-muted">
                                                A analise os dados da empresa, obtendo insights valiosos para a tomada de decisão.
                                            </div>
                                        </div>
                                    </div>
                                    <a href="insights.php" class="btn btn-sm btn-outline-primary flex-shrink-0">Analisar</a>
                                </div>

                                <div id="aiInsightsBody" class="mt-3 d-none">
                                    <div class="row g-2" id="aiInsightsList"></div>
                                </div>
                            </div> -->
                            <!-- 
                            <script>
                                (function() {
                                    'use strict';

                                    const summaryEl = document.getElementById('aiInsightsSummary');
                                    const badgeEl = document.getElementById('aiInsightsBadge');
                                    const bodyEl = document.getElementById('aiInsightsBody');
                                    const listEl = document.getElementById('aiInsightsList');
                                    const companyIdEl = document.getElementById('company_id');

                                    if (!summaryEl || !companyIdEl) return;

                                    const companyId = companyIdEl.value;

                                    function escapeHtml(str) {
                                        const div = document.createElement('div');
                                        div.textContent = str ?? '';
                                        return div.innerHTML;
                                    }

                                    // Endpoint a criar no backend: agrega as sugestões geradas
                                    // pelo reportService (ex.: relatório mensal, clientes com
                                    // potencial de parceria) para a empresa atual.
                                    fetch(`index/ajax/get_ai_insights_dashboard.php?company_id=${encodeURIComponent(companyId)}`)
                                        .then(res => {
                                            if (!res.ok) throw new Error(`HTTP ${res.status}`);
                                            return res.json();
                                        })
                                        .then(data => {
                                            if (!data || data.success === false) {
                                                summaryEl.textContent = 'Sem sugestões disponíveis de momento.';
                                                return;
                                            }

                                            const insights = Array.isArray(data.insights) ? data.insights : [];

                                            if (insights.length === 0) {
                                                summaryEl.textContent = 'Tudo em dia — sem sugestões novas.';
                                                return;
                                            }

                                            summaryEl.textContent = data.summary ||
                                                `${insights.length} sugestão(ões) com base na análise recente da empresa.`;

                                            badgeEl.textContent = `${insights.length} novos`;
                                            badgeEl.hidden = false;

                                            listEl.innerHTML = '';
                                            insights.slice(0, 3).forEach(item => {
                                                const col = document.createElement('div');
                                                col.className = 'col-md-4 col-12';
                                                col.innerHTML = `
                                                    <div class="ai-insight-chip h-100">
                                                        <div class="fw-semibold small">${escapeHtml(item.title)}</div>
                                                        <div class="small text-muted mt-1">${escapeHtml(item.message)}</div>
                                                    </div>`;
                                                listEl.appendChild(col);
                                            });

                                            bodyEl.classList.remove('d-none');
                                        })
                                        .catch(err => {
                                            console.error('Erro ao carregar insights do agente IA:', err);
                                            summaryEl.textContent = 'Não foi possível carregar as sugestões do assistente.';
                                        });
                                })();
                            </script> -->

                            <div class="row g-4">

                                <!-- ================== COLUNA ESQUERDA: CARDS + GRÁFICO ================== -->
                                <div class="col-lg-8 col-12">

                                    <!-- ================== CARDS ================== -->
                                    <div class="row g-3 mb-4" id="top-cards">

                                        <?php if ($dashboardReportsScope === 'full'): ?>
                                            <!-- CARD 1: Vendas este Ano -->
                                            <div class="col-md-4 col-sm-6 col-12">
                                                <div class="card card-custom top-cards p-3 h-100">

                                                    <!-- Cabeçalho: ícone + média mensal -->
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="icon-box">
                                                            <i class="bi bi-coin text-primary"></i>
                                                        </div>

                                                        <div class="d-flex flex-column align-items-end">
                                                            <small class="fw-semibold text-success" id="month_average" aria-live="polite">0</small>
                                                            <span class="small-text text-muted">Média Mensal</span>
                                                        </div>
                                                    </div>

                                                    <!-- Valor principal -->
                                                    <h5 class="mt-3 mb-0 fw-semibold" id="trimestral_volume" aria-live="polite">0</h5>
                                                    <span class="small-text text-muted">Vendas este Ano</span>

                                                    <hr class="my-3 opacity-25">

                                                    <!-- Rodapé: crescimento + sparkline -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span id="trimestral_volume_dif"
                                                            class="badge rounded-pill small fw-semibold"
                                                            aria-live="polite">
                                                        </span>

                                                        <div style="width:80px;height:32px">
                                                            <canvas id="spark1"></canvas>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                            <!-- CARD 2: Vendas este Mês -->
                                            <div class="col-md-4 col-sm-6 col-12">
                                                <div class="card card-custom top-cards p-3 h-100">

                                                    <!-- Cabeçalho: ícone -->
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="icon-box">
                                                            <i class="bi bi-graph-up text-success"></i>
                                                        </div>
                                                    </div>

                                                    <!-- Valor principal -->
                                                    <h5 class="mt-3 mb-0 fw-semibold" id="month_sell" aria-live="polite">AOA 0</h5>
                                                    <span class="small-text text-muted">Vendas este Mês</span>

                                                    <hr class="my-3 opacity-25">

                                                    <!-- Rodapé: crescimento + sparkline -->
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span id="month_sell_dif"
                                                            class="badge rounded-pill small fw-semibold"
                                                            aria-live="polite">
                                                        </span>

                                                        <div style="width:80px;height:32px">
                                                            <canvas id="spark3"></canvas>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        <?php endif; // $dashboardReportsScope === 'full' 
                                        ?>

                                        <!-- CARD 3: Recebimentos este Mês (inclui "A Receber este Mês") -->
                                        <div class="<?= $isDailyPlan ? 'col-md-4 col-12' : 'col-md-4 col-sm-6 col-12' ?>">
                                            <div class="card card-custom top-cards p-3 h-100 position-relative<?= $isDailyPlan ? ' daily-fill' : '' ?>">

                                                <!-- Cabeçalho: ícone + a receber este mês -->
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="icon-box">
                                                        <i class="bi bi-file-earmark-text text-primary"></i>
                                                    </div>

                                                    <div class="d-flex flex-column align-items-end">
                                                        <span class="d-flex gap-1">
                                                            <span class="small-text text-muted" id="a_receber_label">Pendente: </span>
                                                            <small class="fw-semibold text-warning" id="a_receber_mensal" aria-live="polite">0</small>
                                                        </span>
                                                        <span class="small-text text-muted" id="a_receber_mensal_docs">&nbsp;</span>
                                                    </div>
                                                </div>

                                                <!-- Valor principal -->
                                                <h5 class="mt-3 mb-0 fw-semibold" id="recebimento_mensal" aria-live="polite">0</h5>
                                                <span class="small-text text-muted" id="recebimento_label">Recebimentos este Mês</span>

                                                <hr class="my-3 opacity-25">

                                                <!-- Rodapé: crescimento + sparkline -->
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span id="total_doc_dif"
                                                        class="badge rounded-pill small fw-semibold"
                                                        aria-live="polite">
                                                    </span>

                                                    <div style="width:80px;height:32px">
                                                        <canvas id="spark4"></canvas>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>


                                        <?php if ($isDailyPlan): ?>
                                            <!-- RELATÓRIO DIÁRIO: ao lado do card de Recebimentos -->
                                            <div class="col-md-8 col-12">
                                                <!-- ========= RELATÓRIO DIÁRIO (planos BXPERT_BAZA / BXPERT_BASE) ========= -->
                                                <div class="card card-custom p-3 h-100" id="daily-report-card">
                                                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                                        <h6 class="h-title mb-0"><i class="bi bi-calendar-day"></i> Relatório Diário</h6>
                                                        <span class="small text-muted" id="dailyReportDate"></span>
                                                    </div>

                                                    <div class="row g-3">
                                                        <div class="col-md-4 col-6">
                                                            <div class="p-3 border rounded-3 h-100">
                                                                <div class="small-text text-muted">Faturado Hoje</div>
                                                                <h5 class="mb-0 fw-semibold fs-6 text-break" id="daily_sales">0</h5>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-6">
                                                            <div class="p-3 border rounded-3 h-100">
                                                                <div class="small-text text-muted">Documentos Hoje</div>
                                                                <h5 class="mb-0 fw-semibold fs-6 text-break" id="daily_docs">0</h5>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4 col-6">
                                                            <div class="p-3 border rounded-3 h-100">
                                                                <div class="small-text text-muted">Recebido Hoje</div>
                                                                <h5 class="mb-0 fw-semibold fs-6 text-break" id="daily_received">0</h5>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- <div class="alert alert-light border small mt-3 mb-0">
                                                        <i class="bi bi-info-circle"></i>
                                                        O plano <strong><?= htmlspecialchars($dashboardPlan['name']) ?></strong> disponibiliza apenas o relatório diário.
                                                        Faça upgrade para aceder à evolução anual e aos relatórios comparativos.
                                                    </div> -->
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                    </div>

                                    <?php if ($dashboardReportsScope === 'full'): ?>
                                        <!-- ================== GRÁFICO ================== -->
                                        <div class="card card-custom p-4" id="chart-card">
                                            <div class="d-flex justify-content-between mb-3">
                                                <h6 class="h-title"><i class="bi bi-graph-up"></i> Evolução Anual</h6>
                                            </div>
                                            <div style="height: 400px;">
                                                <canvas id="chart"></canvas>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- ========= PRINCIPAIS CLIENTES (planos diários: por baixo do relatório) ========= -->
                                        <div class="card card-custom p-3" id="clients">
                                            <div class="d-flex justify-content-between mb-3">
                                                <h6 class="mb-3 h-title"><i class="bi bi-people"></i> Principais Clientes</h6>
                                            </div>

                                            <div class="col-12" id="topClients"></div>
                                        </div>
                                    <?php endif; // $dashboardReportsScope 
                                    ?>

                                </div>

                                <!-- ================== COLUNA DIREITA: FATURAS + CLIENTES ================== -->
                                <div class="col-lg-4 col-12" id="card-others">

                                    <div class="card card-custom top-cards p-3 mb-4">
                                        <div class="d-flex justify-content-between mb-3">
                                            <h6 class="h-title"><i class="bi bi-file-earmark-text"></i> Últimas Facturas</h6>
                                            <a href="list_invoices.php" class="small text-primary">Ver todas <i class="bi bi-chevron-right"></i></a>
                                        </div>

                                        <div class="row g-3">
                                            <div class="card p-3">
                                                <div class="owl-carousel invoice-carousel" id="invoiceCarousel"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!$isDailyPlan): ?>
                                        <div class="card card-custom p-3" id="clients">
                                            <div class="d-flex justify-content-between mb-3">
                                                <h6 class="mb-3 h-title"><i class="bi bi-people"></i> Principais Clientes</h6>
                                            </div>

                                            <div class="col-12" id="topClients"></div>
                                        </div>
                                    <?php endif; // clients: só nos planos completos 
                                    ?>

                                </div>

                            </div>
                        </div>


                        <!-- Sessao Gestao de RH -->
                        <?php if ($dashboardCanRH): ?>
                            <div class="col-12 tag-content" data-tag-content="rh">
                                <!-- CARDS -->
                                <div class="row g-3 mb-4">

                                    <!-- CARD 1: Funcionários Ativos -->
                                    <div class="col-12 col-md-3">
                                        <div class="card card-custom p-3 h-100">

                                            <!-- Cabeçalho: ícone -->
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-people"></i>
                                                </div>
                                            </div>

                                            <!-- Valor principal -->
                                            <h5 class="mt-3 mb-0 fw-semibold" id="rh_total_employees" aria-live="polite">0</h5>
                                            <span class="small-text text-muted">Total funcionários activos</span>

                                            <hr class="my-3 opacity-25">

                                            <!-- Rodapé: variação + sparkline -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span id="rh_total_employees_dif"
                                                    class="badge rounded-pill small fw-semibold"
                                                    aria-live="polite">
                                                </span>

                                                <div style="width:80px;height:32px">
                                                    <canvas id="spark5"></canvas>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- CARD 2: Custo Salarial Mensal -->
                                    <div class="col-12 col-md-3">
                                        <div class="card card-custom p-3 h-100">

                                            <!-- Cabeçalho: ícone -->
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-cash-stack"></i>
                                                </div>
                                            </div>

                                            <!-- Valor principal -->
                                            <h5 class="mt-3 mb-0 fw-semibold" id="rh_total_salary" aria-live="polite">AOA 0</h5>
                                            <span class="small-text text-muted">Custo salárial mensal</span>

                                            <hr class="my-3 opacity-25">

                                            <!-- Rodapé: variação + sparkline -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span id="rh_total_salary_dif"
                                                    class="badge rounded-pill small fw-semibold"
                                                    aria-live="polite">
                                                </span>

                                                <div style="width:80px;height:32px">
                                                    <canvas id="spark6"></canvas>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- CARD 3: Férias este Mês -->
                                    <div class="col-12 col-md-3">
                                        <div class="card card-custom p-3 h-100">

                                            <!-- Cabeçalho: ícone -->
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                            </div>

                                            <!-- Valor principal -->
                                            <h5 class="mt-3 mb-0 fw-semibold" id="rh_pending_vacations" aria-live="polite">0</h5>
                                            <span class="small-text text-muted">Férias este Mês</span>

                                            <hr class="my-3 opacity-25">

                                            <!-- Rodapé: variação + sparkline -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span id="rh_pending_vacations_dif"
                                                    class="badge rounded-pill small fw-semibold"
                                                    aria-live="polite">
                                                </span>

                                                <div style="width:80px;height:32px">
                                                    <canvas id="spark7"></canvas>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- CARD 4: Faltas no Mês -->
                                    <div class="col-12 col-md-3">
                                        <div class="card card-custom p-3 h-100">

                                            <!-- Cabeçalho: ícone -->
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-person-x"></i>
                                                </div>
                                            </div>

                                            <!-- Valor principal -->
                                            <h5 class="mt-3 mb-0 fw-semibold" id="rh_absences" aria-live="polite">0</h5>
                                            <span class="small-text text-muted">Faltas no mês</span>

                                            <hr class="my-3 opacity-25">

                                            <!-- Rodapé: variação + sparkline -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span id="rh_absences_month_dif"
                                                    class="badge rounded-pill small fw-semibold"
                                                    aria-live="polite">
                                                </span>

                                                <div style="width:80px;height:32px">
                                                    <canvas id="spark8"></canvas>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                </div>

                                <!-- NOVO: gráfico de faltas por mês, dando ao RH
                                 o mesmo peso visual que o módulo de Vendas -->
                                <div class="card card-custom p-4 mt-3">
                                    <div class="d-flex justify-content-between mb-3">
                                        <h6 class="h-title"><i class="bi bi-graph-up"></i> Custo Salarial mensal</h6>
                                    </div>
                                    <div style="height: 260px;">
                                        <canvas id="rhSalaryChart"></canvas>
                                    </div>
                                </div>

                                <!-- LISTAS -->
                                <div class="row g-3 mt-2">

                                    <!-- FÉRIAS -->
                                    <div class="col-12 col-lg-6">
                                        <div class="card card-custom p-3">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="fw-semibold mb-3 h-title"><i class="bi bi-list"></i>Férias Pendentes</h6>
                                                <a href="vacations.php" class="small text-green">Ver todos <i class="bi bi-chevron-right"></i></a>
                                            </div>

                                            <div class="col-12" id="rh_pending_vacations_list"></div>
                                        </div>
                                    </div>

                                    <!-- FALTAS -->
                                    <div class="col-12 col-lg-6">
                                        <div class="card card-custom p-3">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="fw-semibold mb-3 h-title"><i class="bi bi-list"></i>Funcionários com Mais Faltas</h6>
                                                <a href="ponto.php" class="small text-green">Ver todos <i class="bi bi-chevron-right"></i></a>
                                            </div>
                                            <div class="col-12" id="rh_recent_absences_list"></div>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        <?php endif; // $dashboardCanRH 
                        ?>

                        <!-- Sessao Gestao de Stock -->
                        <div class="col-12 tag-content" data-tag-content="stock">
                            <div class="d-flex gap-3 flex-wrap flex-lg-nowrap">
                                <div class="col-12 col-md-3">
                                    <div class="card card-custom p-3">
                                        <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <h4 class="fw-bold mt-3" id="kpi-depots">0</h4>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted ">Nº de depósitos</small>
                                            <span id="kpi-depots-growth" class="text-primary small"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <div class="card card-custom p-3">
                                        <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-box-seam"></i>
                                        </div>
                                        <h4 class="fw-bold mt-3" id="kpi-products">0</h4>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Quantidade de produtos em stock</small>
                                            <span id="kpi-products-growth" class="text-primary small"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <div class="card card-custom p-3">
                                        <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-cash-stack"></i>
                                        </div>
                                        <h4 class="fw-bold mt-3" id="kpi-total-value">AOA 0</h4>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Valor total dos produtos em stock</small>
                                            <span id="kpi-total-value-growth" class="text-primary small"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-3">
                                    <div class="card card-custom p-3">
                                        <div class="icon-box rounded-3 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-exclamation-triangle"></i>
                                        </div>
                                        <h4 class="fw-bold mt-3" id="kpi-low-stock">0</h4>
                                        <small class="text-muted">Produtos com stock baixo</small>
                                    </div>
                                </div>

                            </div>

                            <div class="card mt-4 card-custom p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-semibold h-title"><i class="bi bi-geo-alt"></i>Principais depósitos</h6>
                                </div>

                                <div class="row g-3" id="depots_list"></div>
                            </div>

                            <div class="row g-3 mt-2">

                                <div class="col-12 col-lg-6">
                                    <div class="card card-custom p-3">
                                        <div class="d-flex">
                                            <h6 class="fw-semibold mb-3 h-title"><i class="bi bi-exclamation-triangle"></i> Produtos com stock baixo</h6>
                                        </div>
                                        <div class="col-12" id="low_stock_list"></div>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-6">
                                    <div class="card card-custom p-3">
                                        <div class="d-flex justify-content-between mb-3">
                                            <h6 class="fw-semibold h-title"><i class="bi bi-list"></i> Lista de compras</h6>
                                            <small class="text-muted">Últimos 5 itens</small>
                                        </div>

                                        <div class="col-12" id="purchase_list"></div>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            </div>
    </main>
</body>

<?php
require_once '../app/views/footer.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../vendor/fontawesome-free-5.15.4-web/js/all.js"></script>
<script src="./assets/js/script.js"></script>

<script>
    /* =============================
   🔹 INIT VARS
============================= */

    const currentYear = new Date().getFullYear();
    let selectedYear = new Date().getFullYear();
    // Planos BXPERT_BAZA / BXPERT_BASE: o filtro do dashboard é mensal
    const isDailyPlan = <?= $isDailyPlan ? 'true' : 'false' ?>;
    let selectedMonth = new Date().getMonth() + 1;

    const userEl = document.getElementById("user_id");
    const companyEl = document.getElementById("company_id");

    const user_id = userEl?.value || null;
    const company_id = companyEl?.value || null;

    let chartInstance = null;
    let rhSalaryChartInstance = null;
    let carousel = null;
    let currentData = null;

    /* =============================
       🔹 HELPERS
    ============================= */
    function formatCurrency(value, currency = "AOA", locale = "pt-AO") {
        return new Intl.NumberFormat(locale, {
            style: "currency",
            currency
        }).format(Number(value) || 0);
    }

    function getBadge(status) {
        switch (status) {
            case 'pago':
                return '<span class="badge bg-success">Pago</span>';
            case 'parcial':
                return '<span class="badge bg-info">Parcial</span>';
            default:
                return '<span class="badge bg-warning text-dark">Pendente</span>';
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('pt-PT', {
            day: '2-digit',
            month: 'short'
        });
    }

    function getInitials(name = "") {
        if (!name) return "?";
        return name
            .split(' ')
            .map(n => n[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    }

    function formatDateRange(start, end) {
        if (!start || !end) return 'Datas não definidas';

        const s = new Date(start);
        const e = new Date(end);

        const opt = {
            day: '2-digit',
            month: 'short'
        };
        return `${s.toLocaleDateString('pt-PT', opt)}–${e.toLocaleDateString('pt-PT', opt)}`;
    }

    /* =============================
       🔹 NOVO: setDif genérico
       Trata valores null/undefined como "sem dados
       anteriores" em vez de forçar 0% ou -100%
       enganosos.
    ============================= */
    function setDif(id, value, opts = {}) {
        const el = document.getElementById(id);
        if (!el) return;

        const suffix = opts.suffix ?? '%';
        const noSuffixOnPositive = opts.noSuffixOnPositive ?? false;

        if (value === null || value === undefined) {
            el.innerHTML = `Sem dados anteriores`;
            el.className = "small text-muted-dif";
            return;
        }

        const numeric = Number(value);

        if (numeric > 0) {
            el.innerHTML = `<i class="bi bi-arrow-up-right"></i>${noSuffixOnPositive ? '+' : ''}${numeric}${noSuffixOnPositive ? '' : suffix}`;
            el.className = "small text-success";
        } else if (numeric < 0) {
            el.innerHTML = `<i class="bi bi-arrow-down-right"></i>${numeric}${suffix}`;
            el.className = "small text-danger";
        } else {
            el.innerHTML = `<i class="bi bi-dash"></i>0${suffix}`;
            el.className = "small text-muted-dif";
        }
    }

    /* =============================
       🔹 TABS
    ============================= */
    const tagsLink = document.querySelectorAll(".tag-link");
    const tagsContent = document.querySelectorAll(".tag-content");

    tagsLink.forEach(t => {
        t.addEventListener("click", () => {
            tagsLink.forEach(x => x.classList.remove("active"));
            t.classList.add("active");

            showAndHideTag(t.getAttribute("data-tag"));
        });
    });

    function showAndHideTag(tag) {
        tagsContent.forEach(tgc => {
            const attr = tgc.getAttribute("data-tag-content");

            if (attr === tag) {
                tgc.classList.add("active");
                tgc.classList.remove("exit");
            } else {
                if (tgc.classList.contains("active")) {
                    tgc.classList.add("exit");

                    setTimeout(() => {
                        tgc.classList.remove("active", "exit");
                    }, 300);
                }
            }
        });
    }

    /* =============================
       🔹 LOAD INVOICES
    ============================= */
    function loadInvoices() {
        $.ajax({
            url: 'invoices/ajax/get_invoices_feed.php',
            method: 'GET',
            dataType: 'json',
            data: {
                year: selectedYear,
                month: isDailyPlan ? selectedMonth : undefined
            },

            success: function(res) {

                if (!res?.success || !Array.isArray(res.data)) return;

                // Planos diários: mostra só as faturas do mês seleccionado
                const monthKey = `${selectedYear}-${String(selectedMonth).padStart(2, '0')}`;
                const invoices = isDailyPlan ?
                    res.data.filter(inv => String(inv.issue_date || '').slice(0, 7) === monthKey) :
                    res.data;

                let html = '';

                if (invoices.length === 0) {
                    html = `<div class="p-3 text-center text-muted small">Sem faturas neste período</div>`;
                }

                invoices.forEach(inv => {
                    // Trunca nomes longos com título completo no hover
                    const clientName = inv.client_name ?? '';
                    const displayName = clientName.length > 22 ?
                        clientName.slice(0, 22) + '…' :
                        clientName;

                    html += `
                    <a href="invoice.php?id=${inv.id}">
                        <div class="item">
                            <div class="card p-2 hover-row">
                                <div class="d-flex justify-content-between">
                                    <small class="text-primary">${inv.reference ?? ''}</small>
                                    ${getBadge(inv.status)}
                                </div>
                                <strong title="${clientName}">${displayName}</strong>
                                <div class="d-flex justify-content-between">
                                    <small>${formatDate(inv.issue_date)}</small>
                                    <span class="small text-black">
                                        ${formatCurrency(inv.final_total)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                    `;
                });

                if (carousel) {
                    try {
                        carousel.trigger('destroy.owl.carousel');
                    } catch (e) {}
                    $('#invoiceCarousel').html('');
                }

                $('#invoiceCarousel').html(html);

                carousel = $('#invoiceCarousel').owlCarousel({
                    items: 1.2,
                    margin: 10,
                    loop: invoices.length > 1,
                    autoplay: invoices.length > 1,
                    autoplayTimeout: 3000,
                    autoplayHoverPause: true,
                    dots: false,
                    nav: false
                });
            },
            error: function(xhr) {
                console.error("AJAX ERROR invoices:", xhr.responseText);
            }
        });

        $(document).ready(function() {
            $('.dashboard-carousel').owlCarousel({
                loop: true,
                margin: 4,
                nav: false,
                dots: false,
                autoplay: true,
                autoplayTimeout: 3000,
                autoplayHoverPause: true,
                smartSpeed: 800,

                responsive: {
                    0: {
                        items: 1.2
                    },
                    600: {
                        items: 2.2
                    },
                    1000: {
                        items: 3.2
                    }
                }
            });
        });
    }

    /* =============================
    🔹 KPI DATA (VENDAS)
    ============================= */
    function getInsightsNumber() {
        if (!company_id || !user_id) return;

        $.ajax({
            url: `index/ajax/get_sell_insights.php`,
            method: 'GET',
            data: {
                company_id,
                user_id,
                year: selectedYear,
                month: isDailyPlan ? selectedMonth : undefined
            },
            dataType: 'json',

            success: function(res) {
                if (res?.success) renderNumbers(res.data);
            },
            error: function(xhr) {
                console.error("AJAX ERROR KPI:", xhr.responseText);
            }
        });
    }

    // function renderNumbers(data) {
    //     const kpis = data?.kpis || {};

    //     $("#trimestral_volume").text(formatCurrency(kpis.volume_global));
    //     $("#month_average").text(formatCurrency(kpis?.media_mensal));
    //     $("#month_sell").text(formatCurrency(kpis.venda_periodo));
    //     $("#total_docs").text(formatCurrency(kpis.volume_liquid || 0));

    //     // CORRIGIDO: agora trata null como "sem dados anteriores"
    //     // em vez de mostrar -100% quando não há histórico real
    //     setDif("trimestral_volume_dif", kpis.crescimento);
    //     setDif("month_average_dif", kpis.media_mensal_dif);
    //     setDif("month_sell_dif", kpis.venda_periodo_growth);
    //     setDif("total_doc_dif", kpis.crescimento_documentos, {
    //         noSuffixOnPositive: true,
    //         suffix: ''
    //     });

    //     renderGraphics(data);
    //     renderTopClients(data);
    // }


    /* =============================
    🔹 SPARKLINES (mini-gráficos dos cards)
    ============================= */
    const sparkInstances = {};

    function renderSparkline(canvasId, data, color = "#007abd") {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        if (!Array.isArray(data) || data.length === 0) return;

        if (sparkInstances[canvasId]) {
            sparkInstances[canvasId].destroy();
        }

        sparkInstances[canvasId] = new Chart(canvas.getContext("2d"), {
            type: "line",
            data: {
                labels: data.map((_, i) => i),
                datasets: [{
                    data,
                    borderColor: color,
                    backgroundColor: color + "22",
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: false
                    }
                },
                scales: {
                    x: {
                        display: false
                    },
                    y: {
                        display: false
                    }
                },
                elements: {
                    line: {
                        borderJoinStyle: "round"
                    }
                }
            }
        });
    }

    // Escolhe a cor do sparkline com base no sinal do crescimento
    // (mesma leitura usada em setDif): positivo -> verde,
    // negativo -> vermelho, neutro/sem dados -> azul padrão.
    function sparkColorFromGrowth(value) {
        if (value === null || value === undefined) return "#007abd";

        const numeric = Number(value);

        if (numeric > 0) return "#28a745";
        if (numeric < 0) return "#dc3545";

        return "#007abd";
    }

    /* =============================
    🔹 KPI DATA (VENDAS)
    ============================= */
    function getInsightsNumber() {
        if (!company_id || !user_id) return;

        $.ajax({
            url: `index/ajax/get_sell_insights.php`,
            method: 'GET',
            data: {
                company_id,
                user_id,
                year: selectedYear,
                month: isDailyPlan ? selectedMonth : undefined
            },
            dataType: 'json',

            success: function(res) {
                if (res?.success) renderNumbers(res.data);
            },
            error: function(xhr) {
                console.error("AJAX ERROR KPI:", xhr.responseText);
            }
        });
    }

    function renderNumbers(data) {
        const kpis = data?.kpis || {};
        const sparks = data?.sparklines || {};

        $("#trimestral_volume").text(formatCurrency(kpis.volume_global));
        $("#month_average").text(formatCurrency(kpis?.media_mensal));
        $("#month_sell").text(formatCurrency(kpis.venda_periodo));
        $("#total_docs").text(formatCurrency(kpis.volume_liquid || 0));
        $("#recebimento_mensal").text(formatCurrency(kpis.volume_liquid_mensal || 0));
        $("#a_receber_mensal").text(formatCurrency(kpis.a_receber_mensal || 0));

        if (kpis.a_receber_documentos !== undefined) {
            const n = Number(kpis.a_receber_documentos) || 0;
            $("#a_receber_mensal_docs").text(
                n === 0 ?
                "Sem faturas por receber" :
                `${n} fatura${n === 1 ? "" : "s"} por receber`
            );
        }

        // CORRIGIDO: agora trata null como "sem dados anteriores"
        // em vez de mostrar -100% quando não há histórico real
        setDif("trimestral_volume_dif", kpis.crescimento);
        setDif("month_average_dif", kpis.media_mensal_dif);
        setDif("month_sell_dif", kpis.venda_periodo_growth);
        setDif("total_doc_dif", kpis.volume_liquid_mensal_crescimento.percentual, {
            noSuffixOnPositive: true,
            suffix: ''
        });

        // NOVO: sparklines dos 4 cards, cor dinâmica conforme
        // a tendência de cada KPI
        renderSparkline(
            "spark1",
            sparks.volume_global,
            sparkColorFromGrowth(kpis.crescimento)
        );

        renderSparkline(
            "spark2",
            sparks.media_mensal,
            sparkColorFromGrowth(kpis.media_mensal_dif)
        );

        renderSparkline(
            "spark3",
            sparks.venda_periodo,
            sparkColorFromGrowth(kpis.venda_periodo_growth)
        );

        renderSparkline(
            "spark4",
            sparks.total_docs,
            sparkColorFromGrowth(kpis.volume_liquid_mensal_crescimento.percentual)
        );

        renderGraphics(data);
        renderTopClients(data);
    }

    /* =============================
    🔹 RELATÓRIO DIÁRIO (planos com
       acesso apenas ao relatório do dia)
    ============================= */
    function getDailyReport() {
        // O card só existe no DOM para planos com "reports" = 'daily'.
        // Se não existir, não há nada a carregar.
        if (!document.getElementById("daily-report-card")) return;
        if (!company_id) return;

        $.ajax({
            url: `index/ajax/get_daily_report.php`,
            method: 'GET',
            data: {
                company_id
            },
            dataType: 'json',

            success: function(res) {
                if (!res?.success) return;

                const d = res.data || {};

                $("#daily_sales").text(formatCurrency(d.faturado_hoje || 0));
                $("#daily_docs").text(d.documentos_hoje ?? 0);
                $("#daily_received").text(formatCurrency(d.recebido_hoje || 0));

                if (d.data_referencia) {
                    $("#dailyReportDate").text(d.data_referencia);
                }
            },
            error: function(xhr) {
                console.error("AJAX ERROR RELATÓRIO DIÁRIO:", xhr.responseText);
            }
        });
    }

    /* =============================
    🔹 FILTRO MENSAL (planos BXPERT_BAZA / BXPERT_BASE)
    ============================= */
    const MONTH_NAMES = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];

    function updateMonthLabels() {
        if (!isDailyPlan) return;

        const now = new Date();
        const isCurrent = selectedYear === now.getFullYear() && selectedMonth === now.getMonth() + 1;
        const name = MONTH_NAMES[selectedMonth - 1];

        $("#recebimento_label").text(isCurrent ? "Recebimentos este Mês" : `Recebimentos em ${name}`);
        $("#a_receber_label").text("Pendente: ");
    }

    function renderMonthSelect(apiData) {
        const sel = document.getElementById("monthSelect");
        if (!sel) return;

        const now = new Date();
        const curY = now.getFullYear();
        const curM = now.getMonth() + 1;

        const years = (apiData?.yearsInvoices || []).map(y => Number(y.ano)).filter(Boolean);
        const firstYear = years.length ? Math.min(...years, curY) : curY;

        // do mês actual para trás, até ao primeiro ano com faturas
        const options = [];
        for (let y = curY; y >= firstYear; y--) {
            for (let m = (y === curY ? curM : 12); m >= 1; m--) {
                options.push({
                    value: `${y}-${String(m).padStart(2, '0')}`,
                    label: `${MONTH_NAMES[m - 1]} ${y}`
                });
            }
        }

        // só reconstrói se a lista mudou (o refresh de 30s não deve fechar o dropdown aberto)
        if (sel.options.length !== options.length) {
            sel.innerHTML = options.map(o => `<option value="${o.value}">${o.label}</option>`).join("");
        }

        sel.value = `${selectedYear}-${String(selectedMonth).padStart(2, '0')}`;

        sel.onchange = () => {
            const [y, m] = sel.value.split("-").map(Number);
            selectedYear = y;
            selectedMonth = m;

            updateMonthLabels();
            loadInvoices();
            getInsightsNumber();
            loadRHData();
            loadStockDashboard();
        };

        updateMonthLabels();
    }

    /* =============================
    🔹 CHART
    ============================= */

    function renderGraphics(apiData) {

        currentData = apiData;

        if (isDailyPlan) {
            renderMonthSelect(apiData);
            return;
        }

        const yearsInvoices = apiData?.yearsInvoices || [];

        const yearSelect = document.getElementById("yearSelect");

        const availableYears = yearsInvoices.map(y => Number(y.ano));

        if (!availableYears.includes(selectedYear)) {
            selectedYear =
                availableYears.includes(new Date().getFullYear()) ?
                new Date().getFullYear() :
                availableYears[0];
        }

        yearSelect.innerHTML = yearsInvoices.map(y => `
        <option
            value="${y.ano}"
            ${Number(y.ano) === selectedYear ? "selected" : ""}
        >
            ${y.ano}
        </option>
    `).join("");

        yearSelect.onchange = () => {

            selectedYear = Number(yearSelect.value);

            updateChart(selectedYear);

            setTimeout(() => {
                loadInvoices();
                getInsightsNumber();
                loadRHData();
                loadStockDashboard();
            }, 1000)
        };

        updateChart(selectedYear);
    }

    function updateChart(selectedYear) {

        const evolucao = currentData?.evolucao || [];
        const evolucaoAnterior = currentData?.evolucao_anterior || [];

        const nomesMeses = [
            'Jan', 'Fev', 'Mar',
            'Abr', 'Mai', 'Jun',
            'Jul', 'Ago', 'Set',
            'Out', 'Nov', 'Dez'
        ];

        const map = {};

        evolucao.forEach(item => {
            const [year] = item.mes.split("-");
            if (Number(year) === selectedYear) {
                map[item.mes] = Number(item.total || 0);
            }
        });

        // NOVO: mapa do ano anterior, para comparação lado a lado
        const mapAnterior = {};

        evolucaoAnterior.forEach(item => {
            mapAnterior[item.mes] = Number(item.total || 0);
        });

        const canvas = document.getElementById("chart");
        if (!canvas) return;

        const labels = [];
        const valores = [];
        const valoresAnteriores = [];

        for (let mes = 1; mes <= 12; mes++) {

            const mesFormatado =
                `${selectedYear}-${String(mes).padStart(2, '0')}`;

            const mesAnteriorFormatado =
                `${selectedYear - 1}-${String(mes).padStart(2, '0')}`;

            labels.push(nomesMeses[mes - 1]);
            valores.push(map[mesFormatado] || 0);
            valoresAnteriores.push(mapAnterior[mesAnteriorFormatado] || 0);
        }

        if (chartInstance) {
            chartInstance.destroy();
        }

        const ctx = canvas.getContext("2d");

        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, "#007abd");
        gradient.addColorStop(1, "#007bbd30");

        chartInstance = new Chart(ctx, {
            type: "bar",

            data: {
                labels,
                datasets: [{
                        label: `Receita ${selectedYear}`,
                        data: valores,
                        backgroundColor: gradient,
                        borderRadius: 8,
                        borderSkipped: false,
                        hoverBackgroundColor: "#007abd",
                        barThickness: 20
                    },
                    {
                        label: `Receita ${selectedYear - 1}`,
                        data: valoresAnteriores,
                        backgroundColor: "#c3c2b7",
                        borderRadius: 8,
                        borderSkipped: false,
                        hoverBackgroundColor: "#a3a29c",
                        barThickness: 20
                    }
                ]
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            boxWidth: 10,
                            usePointStyle: false
                        }
                    },

                    tooltip: {
                        callbacks: {
                            label: (context) =>
                                `${context.dataset.label}: ${context.raw.toLocaleString()}`
                        }
                    }
                },

                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: "#f1f5f9"
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /* =============================
    🔹 TOP CLIENTS
    ============================= */
    function renderTopClients(apiData) {
        const el = document.getElementById("topClients");
        if (!el) return;

        const list = apiData?.top_clients || [];

        if (list.length === 0) {
            el.innerHTML = `<div class="p-3 text-center text-muted small">Sem clientes registados</div>`;
            return;
        }

        el.innerHTML = list.map(c => {
            const nome = c.cliente ?? 'Cliente sem nome';
            const nomeCurto = nome.length > 28 ? nome.slice(0, 28) + '…' : nome;

            return `
        <div class="hover-row d-flex justify-content-between p-2 rounded">
            <div title="${nome}">
                <strong>${nomeCurto}</strong><br>
                <small class="text-muted">${c.total_faturas || 0} faturas</small>
            </div>
            <span class="text-black small">
                ${formatCurrency(c.total_faturado)}
            </span>
        </div>
        `;
        }).join('');
    }

    /* =============================
    🔹 RH MODULE
    ============================= */
    function loadRHData() {

        $.ajax({
            url: `index/ajax/get_hr_insights.php`,
            method: "GET",
            data: {
                company_id,
                year: selectedYear
            },
            dataType: "json",

            success: function(res) {
                if (!res?.success) return;

                const data = res.data || {};
                const k = data.kpis || {};
                const sparks = data.sparklines || {};

                $("#rh_total_employees").text(k.total_employes || 0);
                $("#rh_total_salary").text(formatCurrency(k.total_salary));
                $("#rh_pending_vacations").text(k.pending_vacations || 0);
                $("#rh_absences").text(k.absences || 0);

                setDif("rh_total_employees_dif", k.increase_employes, {
                    noSuffixOnPositive: true
                });
                setDif("rh_total_salary_dif", k.increase_salary);
                setDif("rh_pending_vacations_dif", k.increase_vacations, {
                    noSuffixOnPositive: true
                });
                setDif("rh_absences_month_dif", k.increase_absences, {
                    noSuffixOnPositive: true
                });

                // Sparklines dos 4 cards, cor dinâmica conforme a
                // tendência de cada KPI. Para pagamentos pendentes
                // e faltas, subir é "negativo" (mais pendências,
                // mais faltas), por isso invertemos o sinal só
                // para efeitos de cor.
                renderSparkline(
                    "spark5",
                    sparks.total_employes,
                    sparkColorFromGrowth(k.increase_employes)
                );

                renderSparkline(
                    "spark6",
                    sparks.total_salary,
                    sparkColorFromGrowth(k.increase_salary)
                );

                renderSparkline(
                    "spark7",
                    sparks.pending_vacations,
                    sparkColorFromGrowth(
                        k.increase_vacations != null ? -k.increase_vacations : null
                    )
                );

                renderSparkline(
                    "spark8",
                    sparks.absences,
                    sparkColorFromGrowth(
                        k.increase_absences != null ? -k.increase_absences : null
                    )
                );

                /* Férias pendentes */
                const vacationsList = data.pending_vacations_list || [];

                if (vacationsList.length === 0) {
                    $("#rh_pending_vacations_list").html(
                        `<div class="p-3 text-center text-muted small">Sem férias pendentes</div>`
                    );
                } else {

                    const statusBadge = (status) => {
                        switch (status) {
                            case 'Aprovado':
                                return {
                                    class: 'bg-success', label: 'Aprovado'
                                };
                            case 'Rejeitado':
                                return {
                                    class: 'bg-danger', label: 'Rejeitado'
                                };
                            default:
                                return {
                                    class: 'bg-warning text-dark', label: 'Pendente'
                                };
                        }
                    };

                    const formatDate = (value) => {
                        if (!value) return '';
                        const [y, m, d] = value.split('-');
                        return `${d}/${m}/${y}`;
                    };

                    // remove/reaproveita se já tiveres um helper de escape no projeto
                    const escapeHtml = (str) => String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');

                    const html = vacationsList.map(v => {
                        const nome = escapeHtml(v.name || 'Funcionário não identificado');
                        const tipo = escapeHtml(v.type || 'Férias');
                        const periodo = `${formatDate(v.start_date)} — ${formatDate(v.end_date)}`;
                        const badge = statusBadge(v.status);

                        return `
                            <div class="list-item d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar bg-warning-subtle text-warning">
                                        ${getInitials(nome)}
                                    </div>
                                    <div>
                                        <div class="fw-medium">${nome}</div>
                                        <small class="text-muted">${tipo} · ${periodo}</small>
                                    </div>
                                </div>
                                <span class="badge ${badge.class}">
                                    ${badge.label}
                                </span>
                            </div>`;
                    }).join('');

                    $("#rh_pending_vacations_list").html(html);
                }

                /* Últimas faltas registadas */
                const absencesList = data.rh_recent_absences_list || [];

                if (absencesList.length === 0) {
                    $("#rh_recent_absences_list").html(
                        `<div class="p-3 text-center text-muted small">Sem faltas registadas</div>`
                    );
                } else {
                    const html = absencesList.map(a => {
                        const nome = a.name || 'Funcionário não identificado';

                        return `
                            <div class="list-item d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar bg-danger-subtle text-danger">
                                        ${getInitials(nome)}
                                    </div>
                                    <div>
                                        <div class="fw-medium">${nome}</div>
                                        <small class="text-muted">${formatDate(a.date)}</small>
                                    </div>
                                </div>
                                <span class="badge ${a.justification ? 'bg-success' : 'bg-danger'}">
                                    ${a.justification ? 'Justificada' : 'Não justificada'}
                                </span>
                            </div>`;
                    }).join('');

                    $("#rh_recent_absences_list").html(html);
                }

                renderRHSalaryChart(data.salary_evolution || []);
            },
            error: function(xhr) {
                console.error("RH AJAX ERROR:", xhr.responseText);
            }
        });
    }

    /* =============================
    🔹 NOVO: GRÁFICO DE FALTAS (RH)
    ============================= */
    function renderRHSalaryChart(evolution) {
        const canvas = document.getElementById("rhSalaryChart");
        if (!canvas) return;

        const nomesMeses = [
            'Jan', 'Fev', 'Mar',
            'Abr', 'Mai', 'Jun',
            'Jul', 'Ago', 'Set',
            'Out', 'Nov', 'Dez'
        ];

        const map = {};
        evolution.forEach(item => {
            map[item.mes] = Number(item.total || 0);
        });

        const labels = [];
        const valores = [];

        for (let mes = 1; mes <= 12; mes++) {
            const mesFormatado = `${selectedYear}-${String(mes).padStart(2, '0')}`;
            labels.push(nomesMeses[mes - 1]);
            valores.push(map[mesFormatado] || 0);
        }

        if (rhSalaryChartInstance) {
            rhSalaryChartInstance.destroy();
        }

        const ctx = canvas.getContext("2d");

        rhSalaryChartInstance = new Chart(ctx, {
            type: "bar",
            data: {
                labels,
                datasets: [{
                    label: 'Custo Salarial',
                    data: valores,
                    backgroundColor: '#eda100',
                    borderRadius: 6,
                    barThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: "#f1f5f9"
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    /* ==========================================
    STOCK DASHBOARD
    ========================================== */

    function loadStockDashboard() {

        if (!company_id) return;

        $.ajax({
            url: "index/ajax/get_stock_dashboard.php",
            method: "GET",
            data: {
                company_id,
                year: currentYear
            },
            dataType: "json",

            success: function(res) {

                if (!res || !res.success) {
                    console.warn("Sem dados de stock");
                    return;
                }

                const data = res.data || {};
                const kpis = data.kpis || {};

                document.getElementById("kpi-depots").textContent = kpis.depots ?? 0;
                document.getElementById("kpi-products").textContent = kpis.products ?? 0;
                document.getElementById("kpi-total-value").textContent = formatCurrency(kpis.total_value);
                document.getElementById("kpi-low-stock").textContent = kpis.low_stock ?? 0;

                setDif('kpi-depots-growth', kpis.increase_depots, {
                    noSuffixOnPositive: true,
                    suffix: ''
                });
                setDif('kpi-products-growth', kpis.increase_products, {
                    noSuffixOnPositive: true,
                    suffix: ''
                });
                setDif('kpi-total-value-growth', kpis.increase_total_value != null ?
                    Number(kpis.increase_total_value).toFixed(2) :
                    null
                );

                /* DEPÓSITOS */
                const depositsContainer = document.getElementById("depots_list");

                if (depositsContainer) {
                    let html = "";

                    (data.depots || []).forEach(dep => {
                        html += `
                            <div class="col-12 col-sm-4">
                                <div class="p-3 border rounded-3 depo-item">
                                    <h6 class="fw-semibold">${dep.name}</h6>
                                    <small class="text-muted">${dep.city ?? ''}</small>

                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">${dep.total_items || 0} itens</small>
                                        <span class="fw-bold text-success">
                                            ${formatCurrency(dep.total_value)}
                                        </span>
                                    </div>

                                    <a href="stock-depot.php?depot_id=${dep.id}" class="stretched-link">
                                        <small class="text-primary d-block mt-2">
                                            Ver detalhes <i class="bi bi-chevron-right"></i>
                                        </small>
                                    </a>
                                </div>
                            </div>`;
                    });

                    depositsContainer.innerHTML = html || `<small class="text-muted">Sem depósitos</small>`;
                }

                /* STOCK BAIXO */
                const lowStockContainer = document.getElementById("low_stock_list");

                if (lowStockContainer) {
                    let html = "";

                    (data.low_stock || []).forEach(item => {
                        html += `
                            <div class="list-item">
                                <div>
                                    <div class="fw-medium">${item.name}</div>
                                    <small class="text-muted">
                                        ${item.quantity}/${item.min_quantity}
                                    </small>
                                </div>
                                <button class="btn btn-sm text-primary">
                                    <i class="bi bi-plus"></i> Lista
                                </button>
                            </div>`;
                    });

                    lowStockContainer.innerHTML = html || `<small class="text-muted">Sem alertas</small>`;
                }

                /* COMPRAS (SUGESTÃO) */
                const purchaseContainer = document.getElementById("purchase_list");

                if (purchaseContainer) {
                    let html = "";

                    (data.purchases || []).forEach(p => {
                        html += `
                            <div class="list-item">
                                <div>
                                    <div class="fw-medium">${p.name}</div>
                                    <small class="text-muted">
                                        Sugerido: ${p.suggested_qty}
                                    </small>
                                </div>
                                <small class="text-muted">Auto</small>
                            </div>`;
                    });

                    purchaseContainer.innerHTML = html || `<small class="text-muted">Sem sugestões</small>`;
                }
            },

            error: function(xhr) {
                console.error("Erro STOCK AJAX:", xhr.responseText);
            }
        });
    }

    /* =============================
    🔹 INIT
    ============================= */
    const REFRESH_INTERVAL = 30000;

    let dashboardTimer = null;
    let isRefreshing = false;

    async function refreshDashboard() {
        if (isRefreshing) return;

        try {
            isRefreshing = true;

            await Promise.all([
                getInsightsNumber(),
                loadInvoices(),
                loadRHData(),
                loadStockDashboard(),
                getDailyReport()
            ]);

        } catch (error) {
            console.error("Erro ao atualizar dashboard:", error);
        } finally {
            isRefreshing = false;
        }
    }

    function startDashboardRefresh() {

        refreshDashboard();

        dashboardTimer = setInterval(() => {

            if (document.hidden) return;

            refreshDashboard();

        }, REFRESH_INTERVAL);
    }

    function stopDashboardRefresh() {

        if (dashboardTimer) {
            clearInterval(dashboardTimer);
            dashboardTimer = null;
        }
    }

    $(document).ready(() => {

        if (isDailyPlan) renderMonthSelect(null);

        startDashboardRefresh();

        document.addEventListener("visibilitychange", () => {

            if (document.hidden) {
                stopDashboardRefresh();
            } else {
                startDashboardRefresh();
            }

        });
    });
</script>

</html>