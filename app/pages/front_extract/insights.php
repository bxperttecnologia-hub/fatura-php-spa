<html>
<?php
require_once '../app/views/layout_creation.php';

// --- Sessão / defaults ---
$companyId    = (int)($_SESSION['user']['company_id'] ?? 0);
$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');
$currentQuarter = (int)ceil($currentMonth / 3);

$currentPlanCode = strtoupper((string)($_SESSION['user']['plan_code'] ?? ''));
if ($companyId > 0 && $currentPlanCode === '') {
    $planStmt = $pdo->prepare('SELECT plan_code FROM companies WHERE id = ? LIMIT 1');
    $planStmt->execute([$companyId]);
    $currentPlanCode = strtoupper((string)($planStmt->fetchColumn() ?: ''));
}

$intelligenceAvailable = in_array($currentPlanCode, ['XPERT', 'ENTERPRISE'], true);
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">

<style>
    :root {
        --ai-purple: #6f42c1;
        --ai-purple-soft: #f8f5ff;
        --ai-green: #1aa053;
        --ai-red: #e0453f;
        --ai-amber: #f0a83a;
    }

    .insights-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
    }

    .insights-title-icon {
        width: 46px;
        height: 46px;
        min-width: 46px;
        border-radius: 14px;
        background: rgba(111, 66, 193, 0.12);
        color: var(--ai-purple);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    .insights-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .insights-controls .form-select {
        min-width: 130px;
    }

    .btn-ai-refresh {
        background: var(--ai-purple);
        border-color: var(--ai-purple);
        color: #fff;
    }

    .btn-ai-refresh:hover {
        background: #5c359e;
        border-color: #5c359e;
        color: #fff;
    }

    .btn-ai-refresh.loading i {
        animation: spin 0.9s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    .kpi-card {
        border-radius: 16px;
        border: 1px solid #eee;
        background: #fff;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        height: 100%;
        transition: 0.25s;
    }

    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
    }

    .kpi-icon {
        width: 46px;
        height: 46px;
        min-width: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .kpi-icon.blue {
        background: rgba(0, 122, 189, 0.12);
        color: #007abd;
    }

    .kpi-icon.purple {
        background: rgba(111, 66, 193, 0.12);
        color: var(--ai-purple);
    }

    .kpi-icon.green {
        background: rgba(26, 160, 83, 0.12);
        color: var(--ai-green);
    }

    .kpi-icon.red {
        background: rgba(224, 69, 63, 0.12);
        color: var(--ai-red);
    }

    .kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
        margin: 0;
    }

    .kpi-label {
        font-size: 0.8rem;
        color: #888;
        margin: 0;
    }

    .kpi-trend {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.72rem;
        font-weight: 700;
        margin-top: 4px;
        padding: 1px 7px;
        border-radius: 20px;
    }

    .kpi-trend.up-good,
    .kpi-trend.down-good {
        background: rgba(26, 160, 83, 0.12);
        color: var(--ai-green);
    }

    .kpi-trend.up-bad,
    .kpi-trend.down-bad {
        background: rgba(224, 69, 63, 0.12);
        color: var(--ai-red);
    }

    .kpi-trend.neutral {
        background: #f0f0f0;
        color: #888;
    }

    .card-custom {
        border-radius: 16px;
        border: 1px solid #eee;
        background: #fff;
    }

    .chart-card-title {
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 4px;
    }

    .chart-card-subtitle {
        font-size: 0.8rem;
        color: #888;
        margin-bottom: 14px;
    }

    .payment-rate-bar {
        height: 6px;
        border-radius: 6px;
        background: #eee;
        overflow: hidden;
        min-width: 90px;
    }

    .payment-rate-bar>span {
        display: block;
        height: 100%;
        border-radius: 6px;
    }

    .rate-good {
        background: var(--ai-green);
    }

    .rate-mid {
        background: var(--ai-amber);
    }

    .rate-bad {
        background: var(--ai-red);
    }

    .badge-rate-good {
        background: rgba(26, 160, 83, 0.12);
        color: var(--ai-green);
    }

    .badge-rate-mid {
        background: rgba(240, 168, 58, 0.15);
        color: #ad7716;
    }

    .badge-rate-bad {
        background: rgba(224, 69, 63, 0.12);
        color: var(--ai-red);
    }

    #clientsTable tbody tr:hover {
        background: #f8f9fa;
    }

    .ai-suggestions-content {
        font-size: 0.92rem;
        line-height: 1.65;
    }

    .ai-suggestions-content h1,
    .ai-suggestions-content h2,
    .ai-suggestions-content h3 {
        font-size: 1rem;
        font-weight: 700;
        margin-top: 18px;
        margin-bottom: 8px;
        color: #333;
    }

    .ai-suggestions-content ul {
        padding-left: 1.1rem;
        margin-bottom: 0.6rem;
    }

    .ai-suggestions-content li {
        margin-bottom: 8px;
    }

    .ai-suggestions-content strong {
        color: var(--ai-purple);
    }

    .export-btn {
        border-radius: 10px;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .insights-empty,
    .insights-error {
        border-radius: 14px;
        padding: 30px;
        text-align: center;
        color: #888;
    }

    .insights-skeleton {
        border-radius: 16px;
        background: linear-gradient(90deg, #f2f2f2 25%, #e9e9e9 37%, #f2f2f2 63%);
        background-size: 400% 100%;
        animation: shimmer 1.4s ease infinite;
    }

    @keyframes shimmer {
        0% {
            background-position: 100% 50%;
        }

        100% {
            background-position: 0 50%;
        }
    }

    .generated-for-pill {
        background: var(--ai-purple-soft);
        border: 1px solid #e6def7;
        color: var(--ai-purple);
        border-radius: 30px;
        padding: 4px 14px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
</style>

<body>
    <?php if (!$intelligenceAvailable): ?>
        <div class="container-fluid py-4">
            <div class="alert alert-warning border-0 shadow-sm rounded-4 px-4 py-3 mb-4" role="alert" style="max-width: 1200px; margin: 24px auto 0; background: linear-gradient(135deg, rgba(255, 193, 7, 0.16), rgba(255, 255, 255, 0.9)); color: #7a5300;">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-rocket-takeoff me-2"></i>Upgrade para explorar Agent IA e Intelligence</h4>
                        <div class="mb-0">O período de testes gratuito de 30 dias não inclui acesso ao Intelligence e ao Agent IA. Atualize para um plano XPERT ou ENTERPRISE e desbloqueie recomendações, previsões e ações automáticas.</div>
                    </div>
                    <a href="subscription.php" class="btn btn-warning fw-bold rounded-pill px-4">
                        Ver planos
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
    <main style="background: #f7f7f7;">
        <div>
            <div class="container">
                <div class="row g-4 mt-5">
                    <div class="col-12">

                        <input type="hidden" id="companyIdInsights" value="<?= $companyId ?>">

                        <!-- ================== CABEÇALHO ================== -->
                        <div class="insights-header">
                            <div class="d-flex align-items-start gap-3">
                                <div class="insights-title-icon">
                                    <i class="bi bi-robot"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1 fw-bold" id="insightsTitle">Assistente IA — Relatório</h5>
                                    <div class="text-muted small">
                                        Análise automática de faturação, clientes e recomendações geradas pelo agente IA.
                                    </div>
                                    <span class="generated-for-pill mt-2" id="generatedForPill">
                                        <i class="bi bi-calendar-event"></i>
                                        <span id="generatedForText">a carregar…</span>
                                    </span>
                                </div>
                            </div>

                            <div class="insights-controls">
                                <select id="periodTypeSelect" class="form-select form-select-sm">
                                    <option value="month">Mensal</option>
                                    <option value="quarter">Trimestral</option>
                                    <option value="year">Anual</option>
                                </select>
                                <select id="monthSelect" class="form-select form-select-sm"></select>
                                <select id="quarterSelect" class="form-select form-select-sm d-none"></select>
                                <select id="yearSelectInsights" class="form-select form-select-sm"></select>
                                <button type="button" class="btn btn-sm btn-ai-refresh" id="refreshBtn">
                                    <i class="bi bi-arrow-clockwise"></i> Atualizar
                                </button>
                            </div>
                        </div>

                        <!-- ================== ESTADO: LOADING ================== -->
                        <div id="insightsLoading">
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-lg-3">
                                    <div class="insights-skeleton" style="height:86px;"></div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="insights-skeleton" style="height:86px;"></div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="insights-skeleton" style="height:86px;"></div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="insights-skeleton" style="height:86px;"></div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <div class="insights-skeleton" style="height:280px;"></div>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <div class="insights-skeleton" style="height:280px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- ================== ESTADO: ERRO ================== -->
                        <div id="insightsError" class="insights-error card-custom d-none">
                            <i class="bi bi-wifi-off fs-2 d-block mb-2"></i>
                            <div id="insightsErrorMsg">Não foi possível obter o relatório do serviço de IA.</div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-3" id="retryBtn">Tentar novamente</button>
                        </div>

                        <!-- ================== CONTEÚDO ================== -->
                        <div id="insightsContent" class="d-none">

                            <!-- KPIs -->
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-lg-3">
                                    <div class="kpi-card">
                                        <div class="kpi-icon blue"><i class="bi bi-receipt"></i></div>
                                        <div>
                                            <p class="kpi-value" id="kpiFaturas">–</p>
                                            <p class="kpi-label">Faturas emitidas</p>
                                            <span class="kpi-trend d-none" id="trendFaturas"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="kpi-card">
                                        <div class="kpi-icon purple"><i class="bi bi-cash-stack"></i></div>
                                        <div>
                                            <p class="kpi-value" id="kpiReceitaTotal">–</p>
                                            <p class="kpi-label">Receita total</p>
                                            <span class="kpi-trend d-none" id="trendReceitaTotal"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="kpi-card">
                                        <div class="kpi-icon green"><i class="bi bi-check-circle"></i></div>
                                        <div>
                                            <p class="kpi-value" id="kpiReceitaPaga">–</p>
                                            <p class="kpi-label">Receita paga (<span id="kpiPagaPct">0%</span>)</p>
                                            <span class="kpi-trend d-none" id="trendReceitaPaga"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-lg-3">
                                    <div class="kpi-card">
                                        <div class="kpi-icon red"><i class="bi bi-exclamation-triangle"></i></div>
                                        <div>
                                            <p class="kpi-value" id="kpiEmAtraso">–</p>
                                            <p class="kpi-label">Valor em atraso</p>
                                            <span class="kpi-trend d-none" id="trendValorEmAtraso"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Gráficos -->
                            <div class="row g-3 mb-4">
                                <div class="col-12 col-lg-5">
                                    <div class="card-custom p-3 h-100">
                                        <div class="chart-card-title">Receita paga vs. em atraso</div>
                                        <div class="chart-card-subtitle">Distribuição da receita total do período</div>
                                        <div style="position:relative; height:230px;">
                                            <canvas id="revenueStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-7">
                                    <div class="card-custom p-3 h-100">
                                        <div class="chart-card-title">Top clientes por volume comprado</div>
                                        <div class="chart-card-subtitle">Valor total faturado por cliente no período</div>
                                        <div style="position:relative; height:230px;">
                                            <canvas id="topClientsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tabela de clientes -->
                            <div class="card-custom p-3 mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="chart-card-title mb-0">Detalhe por cliente</div>
                                    <span class="text-muted small" id="clientsCountLabel"></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-borderless align-middle mb-0" id="clientsTable">
                                        <thead>
                                            <tr class="text-muted small text-uppercase">
                                                <th>Cliente</th>
                                                <th class="text-end">Nº faturas</th>
                                                <th class="text-end">Total comprado</th>
                                                <th>Pagamento pontual</th>
                                            </tr>
                                        </thead>
                                        <tbody id="clientsTableBody"></tbody>
                                    </table>
                                </div>
                                <div class="insights-empty d-none" id="clientsEmpty">Sem dados de clientes para este período.</div>
                            </div>

                            <!-- Sugestões da IA -->
                            <div class="card-custom p-4 mb-4" style="background: linear-gradient(135deg, #f8f5ff 0%, #ffffff 55%); border-color:#e6def7;">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <div class="insights-title-icon" style="width:36px;height:36px;font-size:1rem;">
                                        <i class="bi bi-lightbulb"></i>
                                    </div>
                                    <div class="fw-bold">Sugestões e recomendações do Assistente IA</div>
                                </div>
                                <div id="aiSuggestionsContent" class="ai-suggestions-content"></div>
                            </div>

                            <!-- Exportações -->
                            <div class="card-custom p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-2 text-muted small">
                                    <i class="bi bi-download"></i> Exportar este relatório:
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-success export-btn" id="exportXlsx" href="#" target="_blank" rel="noopener">
                                        <i class="bi bi-file-earmark-excel"></i> Excel
                                    </a>
                                    <a class="btn btn-sm btn-outline-danger export-btn" id="exportPdf" href="#" target="_blank" rel="noopener">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
                                    </a>
                                    <a class="btn btn-sm btn-outline-primary export-btn" id="exportPptx" href="#" target="_blank" rel="noopener">
                                        <i class="bi bi-file-earmark-slides"></i> PowerPoint
                                    </a>
                                </div>
                            </div>

                        </div>
                        <!-- /insightsContent -->

                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/12.0.2/marked.min.js"></script>
    <script src="assets/js/lucide.js"></script>
    <script>
        (function() {
            'use strict';

            // URL base da API Node (IA Agent). Ajuste host/porta se mudar em produção.
            const AI_API_BASE = 'http://localhost:3000/api' || 'https://api-sandibox.bxpert.co.ao/api';

            const MONTH_NAMES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];

            const companyId = document.getElementById('companyIdInsights').value;
            const periodTypeSelect = document.getElementById('periodTypeSelect');
            const monthSelect = document.getElementById('monthSelect');
            const quarterSelect = document.getElementById('quarterSelect');
            const yearSelect = document.getElementById('yearSelectInsights');
            const refreshBtn = document.getElementById('refreshBtn');
            const retryBtn = document.getElementById('retryBtn');

            const loadingEl = document.getElementById('insightsLoading');
            const errorEl = document.getElementById('insightsError');
            const errorMsgEl = document.getElementById('insightsErrorMsg');
            const contentEl = document.getElementById('insightsContent');

            const CURRENT_YEAR = <?= $currentYear ?>;
            const CURRENT_MONTH = <?= $currentMonth ?>;
            const CURRENT_QUARTER = <?= $currentQuarter ?>;

            let revenueChart = null;
            let clientsChart = null;

            // ---------- Setup dos selects ----------
            MONTH_NAMES.forEach((name, i) => {
                const opt = document.createElement('option');
                opt.value = i + 1;
                opt.textContent = name;
                if (i + 1 === CURRENT_MONTH) opt.selected = true;
                monthSelect.appendChild(opt);
            });

            for (let q = 1; q <= 4; q++) {
                const opt = document.createElement('option');
                opt.value = q;
                opt.textContent = `${q}º Trimestre`;
                if (q === CURRENT_QUARTER) opt.selected = true;
                quarterSelect.appendChild(opt);
            }

            for (let y = CURRENT_YEAR; y >= CURRENT_YEAR - 4; y--) {
                const opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                if (y === CURRENT_YEAR) opt.selected = true;
                yearSelect.appendChild(opt);
            }

            // ---------- Helpers ----------
            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str ?? '';
                return div.innerHTML;
            }

            function formatKz(value, decimals) {
                const num = Number(value) || 0;
                const formatted = new Intl.NumberFormat('pt-PT', {
                    minimumFractionDigits: decimals ?? 0,
                    maximumFractionDigits: decimals ?? 2,
                }).format(num).replace(/\./g, ' ');
                return `${formatted} Kz`;
            }

            function formatInt(value) {
                return new Intl.NumberFormat('pt-PT').format(Number(value) || 0).replace(/\./g, ' ');
            }

            function rateClass(rate) {
                if (rate >= 0.5) return 'good';
                if (rate >= 0.3) return 'mid';
                return 'bad';
            }

            function setLoading(isLoading) {
                refreshBtn.classList.toggle('loading', isLoading);
                refreshBtn.disabled = isLoading;
                if (isLoading) {
                    loadingEl.classList.remove('d-none');
                    errorEl.classList.add('d-none');
                    contentEl.classList.add('d-none');
                }
            }

            // ---------- Controlo de visibilidade dos selects por tipo de período ----------
            function updateControlsVisibility() {
                const type = periodTypeSelect.value;
                monthSelect.classList.toggle('d-none', type !== 'month');
                quarterSelect.classList.toggle('d-none', type !== 'quarter');

                const titles = {
                    month: 'Assistente IA — Relatório Mensal',
                    quarter: 'Assistente IA — Relatório Trimestral',
                    year: 'Assistente IA — Relatório Anual',
                };
                document.getElementById('insightsTitle').textContent = titles[type];
            }

            // ---------- Renderização ----------
            function renderSummary(summary) {
                const total = Number(summary?.receita_total) || 0;
                const paga = Number(summary?.receita_paga) || 0;
                const atraso = Number(summary?.valor_em_atraso) || 0;
                const pct = total > 0 ? Math.round((paga / total) * 100) : 0;

                document.getElementById('kpiFaturas').textContent = formatInt(summary?.total_faturas);
                document.getElementById('kpiReceitaTotal').textContent = formatKz(total, 0);
                document.getElementById('kpiReceitaPaga').textContent = formatKz(paga, 0);
                document.getElementById('kpiPagaPct').textContent = `${pct}%`;
                document.getElementById('kpiEmAtraso').textContent = formatKz(atraso, 0);

                renderRevenueChart(paga, atraso);
            }

            // goodDirection: 'up' significa que crescer é bom (receita);
            // para valor_em_atraso, crescer é mau, por isso é 'down'.
            function renderTrend(elId, variacaoPct, goodDirection) {
                const el = document.getElementById(elId);
                if (variacaoPct === null || variacaoPct === undefined) {
                    el.classList.add('d-none');
                    return;
                }

                const isUp = variacaoPct >= 0;
                const isGood = goodDirection === 'up' ? isUp : !isUp;
                const arrow = isUp ? '▲' : '▼';
                const cls = variacaoPct === 0 ? 'neutral' : (isGood ? `${isUp ? 'up' : 'down'}-good` : `${isUp ? 'up' : 'down'}-bad`);

                el.className = `kpi-trend ${cls}`;
                el.textContent = `${arrow} ${Math.abs(variacaoPct).toFixed(1)}% vs período anterior`;
            }

            function renderComparison(comparison) {
                const trends = ['trendFaturas', 'trendReceitaTotal', 'trendReceitaPaga', 'trendValorEmAtraso'];

                if (!comparison) {
                    trends.forEach(id => document.getElementById(id).classList.add('d-none'));
                    return;
                }

                renderTrend('trendFaturas', comparison.total_faturas?.variacao_pct, 'up');
                renderTrend('trendReceitaTotal', comparison.receita_total?.variacao_pct, 'up');
                renderTrend('trendReceitaPaga', comparison.receita_paga?.variacao_pct, 'up');
                renderTrend('trendValorEmAtraso', comparison.valor_em_atraso?.variacao_pct, 'down');
            }

            function renderRevenueChart(paga, atraso) {
                const canvas = document.getElementById('revenueStatusChart');
                if (!canvas) return;
                if (revenueChart) revenueChart.destroy();

                const hasData = (paga + atraso) > 0;

                revenueChart = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Paga', 'Em atraso'],
                        datasets: [{
                            data: hasData ? [paga, atraso] : [1, 0],
                            backgroundColor: ['#1aa053', '#e0453f'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 10,
                                    boxHeight: 10,
                                    padding: 16,
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ` ${ctx.label}: ${formatKz(ctx.parsed, 0)}`,
                                },
                            },
                        },
                    },
                });
            }

            function renderClients(clients) {
                const list = Array.isArray(clients) ? clients : [];
                const tbody = document.getElementById('clientsTableBody');
                const emptyEl = document.getElementById('clientsEmpty');
                const countLabel = document.getElementById('clientsCountLabel');

                tbody.innerHTML = '';
                countLabel.textContent = list.length ? `${list.length} cliente(s)` : '';

                if (list.length === 0) {
                    emptyEl.classList.remove('d-none');
                } else {
                    emptyEl.classList.add('d-none');
                }

                const fragment = document.createDocumentFragment();

                list.forEach((c) => {
                    const rate = parseFloat(c.taxa_pagamento_pontual) || 0;
                    const pct = Math.round(rate * 100);
                    const cls = rateClass(rate);

                    const tr = document.createElement('tr');

                    const tdCliente = document.createElement('td');
                    tdCliente.className = 'fw-semibold';
                    tdCliente.textContent = c.cliente ?? '—';

                    const tdFaturas = document.createElement('td');
                    tdFaturas.className = 'text-end';
                    tdFaturas.textContent = formatInt(c.num_faturas);

                    const tdTotal = document.createElement('td');
                    tdTotal.className = 'text-end';
                    tdTotal.textContent = formatKz(c.total_comprado, 2);

                    const tdRate = document.createElement('td');
                    tdRate.innerHTML = `
                    <div class="d-flex align-items-center gap-2">
                        <div class="payment-rate-bar"><span class="rate-${cls}" style="width:${pct}%"></span></div>
                        <span class="badge rounded-pill badge-rate-${cls}">${pct}%</span>
                    </div>`;

                    tr.append(tdCliente, tdFaturas, tdTotal, tdRate);
                    fragment.appendChild(tr);
                });

                tbody.appendChild(fragment);
                renderClientsChart(list);
            }

            function renderClientsChart(clients) {
                const canvas = document.getElementById('topClientsChart');
                if (!canvas) return;
                if (clientsChart) clientsChart.destroy();

                const top = clients.slice(0, 6);
                const labels = top.map(c => (c.cliente && c.cliente.length > 18) ? c.cliente.slice(0, 18) + '…' : (c.cliente ?? '—'));
                const values = top.map(c => Number(c.total_comprado) || 0);

                clientsChart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Total comprado',
                            data: values,
                            backgroundColor: '#6f42c1',
                            borderRadius: 6,
                            maxBarThickness: 28,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ` ${formatKz(ctx.parsed.x, 2)}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                ticks: {
                                    callback: (val) => formatKz(val, 0),
                                },
                            },
                        },
                    },
                });
            }

            function renderSuggestions(markdown) {
                const el = document.getElementById('aiSuggestionsContent');
                const text = (markdown ?? '').trim();

                if (!text) {
                    el.innerHTML = '<span class="text-muted">Sem sugestões geradas para este período.</span>';
                    return;
                }

                if (window.marked) {
                    el.innerHTML = marked.parse(text);
                } else {
                    el.textContent = text;
                }
            }

            function renderExports(actions) {
                const map = {
                    exportXlsx: 'exportXlsx',
                    exportPdf: 'exportPdf',
                    exportPptx: 'exportPptx',
                };

                const origin = AI_API_BASE.replace(/\/api$/, '');

                Object.entries(map).forEach(([field, elId]) => {
                    const link = document.getElementById(elId);
                    const path = actions?.[field];
                    if (path) {
                        const href = /^https?:\/\//i.test(path) ? path : origin + path;
                        link.href = href;
                        link.classList.remove('disabled');
                    } else {
                        link.href = '#';
                        link.classList.add('disabled');
                    }
                });
            }

            function renderGeneratedFor(periodLabel) {
                document.getElementById('generatedForText').textContent = `Relatório de ${periodLabel}`;
            }

            // ---------- Monta a URL certa conforme o tipo de período ----------
            function buildReportUrl() {
                const type = periodTypeSelect.value;
                const year = yearSelect.value;

                if (type === 'month') {
                    const month = monthSelect.value;
                    return `${AI_API_BASE}/reports/monthly?company_id=${encodeURIComponent(companyId)}&year=${encodeURIComponent(year)}&month=${encodeURIComponent(month)}`;
                }
                if (type === 'quarter') {
                    const quarter = quarterSelect.value;
                    return `${AI_API_BASE}/reports/quarterly?company_id=${encodeURIComponent(companyId)}&year=${encodeURIComponent(year)}&quarter=${encodeURIComponent(quarter)}`;
                }
                // year
                return `${AI_API_BASE}/reports/annual?company_id=${encodeURIComponent(companyId)}&year=${encodeURIComponent(year)}`;
            }

            // ---------- Fetch principal ----------
            async function loadReport() {
                setLoading(true);

                try {
                    const url = buildReportUrl();
                    const res = await fetch(url);
                    const data = await res.json().catch(() => null);

                    if (!res.ok || !data || data.error) {
                        throw new Error(data?.error || `HTTP ${res.status}`);
                    }

                    renderGeneratedFor(data.period?.label ?? '');
                    renderSummary(data.summary);
                    renderComparison(data.comparison);
                    renderClients(data.topClients);
                    renderSuggestions(data.suggestions);
                    renderExports(data.actions);

                    loadingEl.classList.add('d-none');
                    contentEl.classList.remove('d-none');
                } catch (err) {
                    console.error('Erro ao carregar relatório de insights:', err);
                    errorMsgEl.textContent = 'Não foi possível obter o relatório do serviço de IA. Verifica se o serviço está disponível e tenta novamente.';
                    loadingEl.classList.add('d-none');
                    errorEl.classList.remove('d-none');
                } finally {
                    setLoading(false);
                }
            }

            function reload() {
                loadReport();
            }

            periodTypeSelect.addEventListener('change', () => {
                updateControlsVisibility();
                reload();
            });
            refreshBtn.addEventListener('click', reload);
            retryBtn.addEventListener('click', reload);
            monthSelect.addEventListener('change', reload);
            quarterSelect.addEventListener('change', reload);
            yearSelect.addEventListener('change', reload);

            document.addEventListener('DOMContentLoaded', () => {
                if (window.lucide) lucide.createIcons();
                updateControlsVisibility();
                reload();
            });
        })();
    </script>

    <?php endif; ?>

    <?php
    require_once '../app/views/footer.php';
    ?>
</body>
</html>