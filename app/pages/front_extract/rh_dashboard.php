<?php
require_once '../app/config/db.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

try {
    subscription_require_feature($pdo, (int)($_SESSION['user']['company_id'] ?? 0), 'rh');
} catch (Exception $e) {
    $cid = (int)($_SESSION['user']['company_id'] ?? 0);
    header('Location: subscription.php?company_id=' . $cid . '&upgrade=rh');
    exit;
}

require_once '../app/views/layout_creation.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<style>
    .kpi-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        padding: 18px 20px;
        height: 100%;
    }

    .kpi-value {
        font-size: 26px;
        font-weight: 700;
        color: #111827;
    }

    .kpi-label {
        font-size: 12px;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .chart-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        padding: 18px 20px;
        height: 100%;
    }

    .alert-item {
        border-left: 3px solid #f59e0b;
        padding: 8px 12px;
        margin-bottom: 8px;
        background: #fffbeb;
        border-radius: 6px;
        font-size: 13px;
    }

    .alert-item.urgente {
        border-left-color: #dc2626;
        background: #fef2f2;
    }
</style>

<main class="main-content">
<div class="container-fluid mt-5">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0">Dashboard de RH</h4>
        <input type="month" id="inputDashboardMes" class="form-control" style="max-width:180px" value="<?= date('Y-m') ?>">
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Headcount Total</div>
                <div class="kpi-value" id="kpiHeadcount">—</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Custo Total de Folha (com INSS patronal)</div>
                <div class="kpi-value" id="kpiCustoFolha">—</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Absentismo do Mês</div>
                <div class="kpi-value" id="kpiAbsentismo">—</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">Turnover do Mês</div>
                <div class="kpi-value" id="kpiTurnover">—</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="chart-card">
                <h6>Headcount por Departamento</h6>
                <canvas id="chartDepartamentos"></canvas>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="chart-card">
                <h6>Distribuição de Idade</h6>
                <canvas id="chartIdade"></canvas>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="chart-card">
                <h6>Distribuição de Tempo de Casa</h6>
                <canvas id="chartTempoCasa"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="chart-card">
                <h6>Alertas (próximos 30 dias)</h6>
                <div id="alertsList"><p class="text-muted">A carregar...</p></div>
            </div>
        </div>
    </div>
</div>
</main>

<script>
    let chartDepartamentos, chartIdade, chartTempoCasa;

    const palette = ['#2563EB', '#7C3AED', '#DB2777', '#F59E0B', '#10B981', '#0891B2', '#64748B'];

    function renderPieChart(canvasId, labels, data, existingChart) {
        if (existingChart) existingChart.destroy();
        const ctx = document.getElementById(canvasId).getContext('2d');
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{ data, backgroundColor: palette }]
            },
            options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    }

    function loadDashboard() {
        const mes = $('#inputDashboardMes').val();
        $.getJSON('rh/ajax/get_dashboard_stats.php', { mes }, function(resp) {
            if (!resp.success) return;

            $('#kpiHeadcount').text(resp.headcount_total);
            $('#kpiCustoFolha').text('Kz ' + parseFloat(resp.custo_folha.custo_total_empresa).toLocaleString('pt-AO', { minimumFractionDigits: 2 }));
            $('#kpiAbsentismo').text(resp.absentismo.taxa_pct + '%');
            $('#kpiTurnover').text(resp.turnover.taxa_pct + '% (' + resp.turnover.desligamentos + ' saída(s))');

            chartDepartamentos = renderPieChart(
                'chartDepartamentos',
                resp.headcount_por_departamento.map(d => d.department_name),
                resp.headcount_por_departamento.map(d => d.total),
                chartDepartamentos
            );

            chartIdade = renderPieChart(
                'chartIdade',
                Object.keys(resp.distribuicao_idade),
                Object.values(resp.distribuicao_idade),
                chartIdade
            );

            chartTempoCasa = renderPieChart(
                'chartTempoCasa',
                Object.keys(resp.distribuicao_tempo_casa),
                Object.values(resp.distribuicao_tempo_casa),
                chartTempoCasa
            );
        });
    }

    function loadAlerts() {
        $.getJSON('rh/ajax/get_alerts.php', { dias: 30 }, function(resp) {
            const list = $('#alertsList').empty();
            const alerts = resp.data || [];
            if (alerts.length === 0) {
                list.html('<p class="text-muted">Sem alertas nos próximos 30 dias.</p>');
                return;
            }
            alerts.forEach(a => {
                const urgente = (a.dias_restantes !== undefined && a.dias_restantes <= 7);
                list.append(`<div class="alert-item ${urgente ? 'urgente' : ''}">${a.mensagem}</div>`);
            });
        });
    }

    $('#inputDashboardMes').on('change', loadDashboard);

    $(document).ready(function() {
        loadDashboard();
        loadAlerts();
    });
</script>

<?php require_once '../app/views/footer.php'; ?>