<?php
require_once '../app/views/layout_creation.php';

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
$currentPlanCode = strtoupper((string)($_SESSION['user']['plan_code'] ?? ''));
if ($companyId > 0 && $currentPlanCode === '') {
    $planStmt = $pdo->prepare('SELECT plan_code FROM companies WHERE id = ? LIMIT 1');
    $planStmt->execute([$companyId]);
    $currentPlanCode = strtoupper((string)($planStmt->fetchColumn() ?: ''));
}

$intelligenceAvailable = in_array($currentPlanCode, ['XPERT', 'ENTERPRISE'], true);
?>

<style>
    :root {
        --intel-bg: #f5f7ff;
        --intel-panel: #ffffff;
        --intel-panel-soft: #f8f9fe;
        --intel-line: #e9edf6;
        --intel-primary: #5f3dc4;
        --intel-primary-soft: rgba(95, 61, 196, 0.11);
        --intel-text: #1f2433;
        --intel-muted: #667085;
        --intel-success: #1ea673;
        --intel-warning: #f4b740;
        --intel-danger: #e45d5d;
        --intel-shadow: 0 16px 40px rgba(17, 24, 39, 0.08);
    }

    body {
        background: linear-gradient(180deg, #f7f8fd 0%, var(--intel-bg) 100%);
    }

    .intel-page {
        max-width: 1360px;
        margin: 28px auto 60px;
        padding: 0 18px;
    }

    .intel-hero {
        background: linear-gradient(135deg, #1d1b2e 0%, #35275d 30%, #5f3dc4 100%);
        color: #fff;
        border-radius: 26px;
        padding: 28px 28px 20px;
        box-shadow: 0 24px 50px rgba(53, 39, 93, 0.18);
        position: relative;
        overflow: hidden;
    }

    .intel-hero::before,
    .intel-hero::after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(255,255,255,0.07);
    }

    .intel-hero::before {
        top: -110px;
        right: -80px;
    }

    .intel-hero::after {
        left: -55px;
        bottom: -110px;
    }

    .intel-hero-inner {
        position: relative;
        z-index: 1;
    }

    .intel-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.12);
        color: #fff;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 0.7rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 700;
    }

    .intel-title {
        margin: 18px 0 10px;
        font-size: clamp(2rem, 3.5vw, 3.1rem);
        font-weight: 800;
        letter-spacing: -0.04em;
    }

    .intel-subtitle {
        max-width: 760px;
        color: rgba(255,255,255,0.82);
        margin: 0;
    }

    .intel-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 22px;
    }

    .intel-btn {
        border-radius: 12px;
        padding: 10px 16px;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .intel-btn.primary {
        background: #fff;
        color: var(--intel-primary);
    }

    .intel-btn.secondary {
        background: rgba(255,255,255,0.07);
        color: #fff;
        border-color: rgba(255,255,255,0.12);
    }

    .intel-grid {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 22px;
        margin-top: 26px;
    }

    .intel-panel {
        background: rgba(255,255,255,0.86);
        border: 1px solid var(--intel-line);
        border-radius: 24px;
        box-shadow: var(--intel-shadow);
        overflow: hidden;
    }

    .intel-panel-header {
        padding: 18px 20px;
        border-bottom: 1px solid var(--intel-line);
        background: rgba(95, 61, 196, 0.03);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .intel-panel-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 800;
        color: var(--intel-text);
        font-size: 1.02rem;
    }

    .intel-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: var(--intel-primary-soft);
        color: var(--intel-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .intel-panel-body {
        padding: 18px 18px 12px;
    }

    .score-strip {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .small-card {
        border: 1px solid var(--intel-line);
        background: linear-gradient(180deg, #fff 0%, #fafbff 100%);
        border-radius: 16px;
        padding: 16px 14px;
    }

    .small-card .label {
        display: block;
        color: var(--intel-muted);
        font-size: 0.72rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 700;
    }

    .small-card .value {
        display: block;
        margin-top: 8px;
        font-size: 1.6rem;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--intel-text);
    }

    .section-block {
        border: 1px solid var(--intel-line);
        background: var(--intel-panel-soft);
        border-radius: 18px;
        padding: 16px;
        margin-bottom: 14px;
    }

    .section-block h4 {
        margin: 0 0 12px;
        font-size: 0.9rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--intel-muted);
    }

    .alert-row {
        border: 1px solid rgba(95,61,196,0.12);
        background: #fff;
        border-radius: 14px;
        padding: 14px 14px 12px;
        margin-bottom: 12px;
    }

    .alert-row.critical {
        border-color: rgba(228,93,93,0.18);
    }

    .alert-row.warning {
        border-color: rgba(244,183,64,0.18);
    }

    .alert-row.good {
        border-color: rgba(30,167,115,0.18);
    }

    .alert-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
    }

    .alert-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        color: var(--intel-text);
    }

    .chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 8px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 700;
        border: 1px solid transparent;
    }

    .chip.critical {
        background: rgba(228,93,93,0.1);
        color: var(--intel-danger);
        border-color: rgba(228,93,93,0.18);
    }

    .chip.warning {
        background: rgba(244,183,64,0.12);
        color: #976a0a;
        border-color: rgba(244,183,64,0.18);
    }

    .chip.good {
        background: rgba(30,167,115,0.1);
        color: var(--intel-success);
        border-color: rgba(30,167,115,0.18);
    }

    .alert-message {
        margin: 0;
        color: #3b4352;
        line-height: 1.7;
        font-size: 0.94rem;
    }

    .alert-action {
        margin-top: 10px;
        font-size: 0.8rem;
        color: var(--intel-primary);
        font-weight: 700;
    }

    .side-stack {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .metric-box {
        padding: 20px 18px;
    }

    .metric-box .big {
        font-size: 2.2rem;
        font-weight: 800;
        letter-spacing: -0.05em;
        margin: 8px 0 0;
        color: var(--intel-text);
    }

    .metric-box .small {
        color: var(--intel-muted);
        font-size: 0.82rem;
        margin: 0 0 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .trend-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        background: rgba(30,167,115,0.1);
        color: var(--intel-success);
    }

    .health-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .health-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border: 1px solid var(--intel-line);
        border-radius: 14px;
        background: #fff;
        padding: 12px 14px;
    }

    .health-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .score-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--intel-success);
        box-shadow: 0 0 0 6px rgba(30,167,115,0.12);
    }

    .health-label {
        font-weight: 700;
        color: var(--intel-text);
    }

    .health-value {
        font-weight: 800;
        color: var(--intel-text);
    }

    .empty-state {
        border: 1px dashed var(--intel-line);
        background: rgba(95,61,196,0.02);
        color: var(--intel-muted);
        border-radius: 16px;
        padding: 22px 18px;
        text-align: center;
    }

    @media (max-width: 980px) {
        .intel-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if (!$intelligenceAvailable): ?>
    <main class="intel-page">
        <section class="intel-hero" style="margin-bottom: 18px;">
            <div class="intel-hero-inner">
                <span class="intel-kicker"><i class="bi bi-rocket-takeoff"></i> Upgrade necessário</span>
                <h1 class="intel-title">Agent IA e Intelligence não estão disponíveis no seu plano atual</h1>
                <p class="intel-subtitle">O período de teste de 30 dias não inclui acesso ao Intelligence. Para desbloquear recomendações, alertas e ações automáticas, atualize para um plano XPERT ou ENTERPRISE.</p>
                <div class="intel-actions">
                    <a href="subscription.php" class="intel-btn primary text-decoration-none">Ver planos</a>
                </div>
            </div>
        </section>
    </main>
<?php else: ?>
<main class="intel-page">
    <section class="intel-hero">
        <div class="intel-hero-inner">
            <span class="intel-kicker"><i class="bi bi-robot me-1"></i> BXPERT Intelligence</span>
            <h1 class="intel-title">Meu Dia</h1>
            <p class="intel-subtitle">Resumo executivo conectando saúde financeira, clientes em risco e ações prioritárias de negócio para o dia de hoje.</p>
            <div class="intel-actions">
                <button class="intel-btn primary" type="button" id="refreshMyDayBtn"><i class="bi bi-arrow-clockwise me-1"></i> Atualizar</button>
                <a class="intel-btn secondary" href="ai_actions.php"><i class="bi bi-lightning-charge me-1"></i> Ver ações da IA</a>
            </div>
        </div>
    </section>

    <section class="intel-grid">
        <div class="intel-panel">
            <div class="intel-panel-header">
                <div class="intel-panel-title">
                    <span class="intel-icon"><i class="bi bi-stars"></i></span>
                    Resumo do dia
                </div>
                <span class="trend-pill" id="healthBanner"><i class="bi bi-graph-up-arrow"></i> Saúde em análise</span>
            </div>
            <div class="intel-panel-body">
                <div class="score-strip">
                    <div class="small-card">
                        <span class="label">Health Score</span>
                        <span class="value" id="healthScoreValue">--</span>
                    </div>
                    <div class="small-card">
                        <span class="label">Crítico</span>
                        <span class="value" id="criticalCount">0</span>
                    </div>
                    <div class="small-card">
                        <span class="label">Oportunidades</span>
                        <span class="value" id="opportunityCount">0</span>
                    </div>
                </div>

                <div class="section-block">
                    <h4>Crítico</h4>
                    <div id="criticalBox"></div>
                </div>

                <div class="section-block">
                    <h4>Atenção</h4>
                    <div id="attentionBox"></div>
                </div>

                <div class="section-block">
                    <h4>Oportunidades</h4>
                    <div id="opportunityBox"></div>
                </div>
            </div>
        </div>

        <div class="side-stack">
            <div class="intel-panel metric-box">
                <div class="small">Saúde da empresa</div>
                <div class="big" id="overallHealth">--</div>
                <div class="trend-pill mt-3" id="healthTrend"><i class="bi bi-arrow-up-right"></i> Baseado nos dados mais recentes</div>
            </div>

            <div class="intel-panel metric-box">
                <div class="small">Indicadores principais</div>
                <ul class="health-list" id="healthIndicators">
                    <li><div class="health-left"><span class="score-dot"></span><span class="health-label">Financeiro</span></div><span class="health-value">--</span></li>
                    <li><div class="health-left"><span class="score-dot"></span><span class="health-label">Clientes</span></div><span class="health-value">--</span></li>
                    <li><div class="health-left"><span class="score-dot"></span><span class="health-label">Operações</span></div><span class="health-value">--</span></li>
                </ul>
            </div>
        </div>
    </section>
</main>

<script>
    (function () {
        const companyId = <?= (int)($companyId ?: 0) ?>;
        const healthScoreValue = document.getElementById('healthScoreValue');
        const criticalCount = document.getElementById('criticalCount');
        const opportunityCount = document.getElementById('opportunityCount');
        const criticalBox = document.getElementById('criticalBox');
        const attentionBox = document.getElementById('attentionBox');
        const opportunityBox = document.getElementById('opportunityBox');
        const healthBanner = document.getElementById('healthBanner');
        const overallHealth = document.getElementById('overallHealth');
        const healthIndicators = document.getElementById('healthIndicators');

        function badgeTone(severity) {
            const map = {
                high: 'critical',
                medium: 'warning',
                low: 'good'
            };
            return map[severity] || 'good';
        }

        function renderAlertList(target, items, typeKey) {
            if (!target) return;
            if (!Array.isArray(items) || items.length === 0) {
                target.innerHTML = '<div class="empty-state">Sem itens nesta categoria.</div>';
                return;
            }

            target.innerHTML = items.map(item => {
                const severity = item?.severity || 'low';
                const title = item?.title || 'Alerta';
                const message = item?.message || item?.action || 'Sem detalhe adicional.';
                const action = item?.action || 'Revisar com prioridade.';
                return `
                    <div class="alert-row ${severity === 'high' ? 'critical' : (severity === 'medium' ? 'warning' : 'good')}">
                        <div class="alert-head">
                            <h5 class="alert-title">${title}</h5>
                            <span class="chip ${badgeTone(severity)}">${severity === 'high' ? 'Crítico' : (severity === 'medium' ? 'Atenção' : 'Oportunidade')}</span>
                        </div>
                        <p class="alert-message">${message}</p>
                        <div class="alert-action">${action}</div>
                    </div>
                `;
            }).join('');
        }

        function renderIndicators(data) {
            if (!healthIndicators || !data) return;
            const indicators = [
                ['Financeiro', data.financial_score ?? data.financeiro_score ?? 0],
                ['Clientes', data.customer_score ?? data.clientes_score ?? 0],
                ['Operações', data.operations_score ?? data.operacoes_score ?? 0],
            ];

            healthIndicators.innerHTML = indicators.map(([label, value]) => `
                <li>
                    <div class="health-left">
                        <span class="score-dot"></span>
                        <span class="health-label">${label}</span>
                    </div>
                    <span class="health-value">${value}</span>
                </li>
            `).join('');
        }

        async function loadMyDay() {
            try {
                const response = await fetch(`index/ajax/get_intelligence_my_day.php?company_id=${encodeURIComponent(companyId)}`);
                const data = await response.json();

                if (!data || data.success === false) {
                    throw new Error(data?.error || 'Sem resposta válida');
                }

                const critical = Array.isArray(data.critico) ? data.critico : [];
                const attention = Array.isArray(data.atencao) ? data.atencao : [];
                const opportunities = Array.isArray(data.oportunidades) ? data.oportunidades : [];
                const healthScore = Number(data.health_score ?? data.healthScore ?? 0);

                healthScoreValue.textContent = healthScore ? `${healthScore}/100` : '--';
                criticalCount.textContent = String(critical.length);
                opportunityCount.textContent = String(opportunities.length);
                overallHealth.textContent = healthScore ? `${healthScore}/100` : '--';

                healthBanner.innerHTML = '<i class="bi bi-graph-up-arrow"></i> Saúde em análise';
                renderAlertList(criticalBox, critical, 'critical');
                renderAlertList(attentionBox, attention, 'warning');
                renderAlertList(opportunityBox, opportunities, 'good');
                renderIndicators(data);
            } catch (error) {
                console.error(error);
                criticalBox.innerHTML = '<div class="empty-state">Não foi possível carregar os alertas do dia.</div>';
                attentionBox.innerHTML = '<div class="empty-state">Sem dados para a análise de atenção.</div>';
                opportunityBox.innerHTML = '<div class="empty-state">Sem oportunidades disponíveis no momento.</div>';
                healthScoreValue.textContent = '--';
                criticalCount.textContent = '0';
                opportunityCount.textContent = '0';
                overallHealth.textContent = '--';
            }
        }

        document.getElementById('refreshMyDayBtn')?.addEventListener('click', loadMyDay);
        loadMyDay();
    })();
</script>
<?php endif; ?>
</body>
</html>
</script>
