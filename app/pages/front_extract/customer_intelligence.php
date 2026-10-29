<?php
require_once '../app/views/layout_creation.php';

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
?>

<style>
    :root {
        --ci-bg: #f5f7ff;
        --ci-panel: #ffffff;
        --ci-line: #e9edf6;
        --ci-primary: #5f3dc4;
        --ci-primary-soft: rgba(95, 61, 196, 0.11);
        --ci-text: #1f2433;
        --ci-muted: #667085;
        --ci-success: #1ea673;
        --ci-warning: #f4b740;
        --ci-danger: #e45d5d;
    }

    .ci-page {
        max-width: 1280px;
        margin: 28px auto 50px;
        padding: 0 18px;
    }

    .ci-hero {
        background: linear-gradient(135deg, #111827 0%, #2d2447 35%, #5f3dc4 100%);
        border-radius: 28px;
        color: #fff;
        padding: 26px 28px;
        box-shadow: 0 20px 45px rgba(31, 24, 52, 0.18);
    }

    .ci-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        padding: 7px 10px;
        font-size: 0.68rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 700;
    }

    .ci-title {
        margin: 16px 0 10px;
        font-size: clamp(2rem, 3vw, 2.8rem);
        font-weight: 800;
        letter-spacing: -0.04em;
    }

    .ci-subtitle {
        max-width: 760px;
        margin: 0;
        color: rgba(255,255,255,0.82);
    }

    .ci-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 22px;
        margin-top: 24px;
    }

    .ci-panel {
        background: rgba(255,255,255,0.9);
        border: 1px solid var(--ci-line);
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(15,23,42,0.05);
    }

    .ci-panel-head {
        border-bottom: 1px solid var(--ci-line);
        padding: 18px 20px;
        background: rgba(95,61,196,0.03);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .ci-panel-head h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ci-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: var(--ci-primary-soft);
        color: var(--ci-primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .ci-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        background: rgba(95,61,196,0.08);
        color: var(--ci-primary);
    }

    .ci-body {
        padding: 18px;
    }

    .ci-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ci-table th,
    .ci-table td {
        padding: 14px 10px;
        border-bottom: 1px solid var(--ci-line);
        vertical-align: top;
        font-size: 0.92rem;
    }

    .ci-table th {
        color: var(--ci-muted);
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
    }

    .risk-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 0.72rem;
        font-weight: 800;
    }

    .risk-high {
        background: rgba(228,93,93,0.14);
        color: var(--ci-danger);
    }

    .risk-medium {
        background: rgba(244,183,64,0.14);
        color: #8a6513;
    }

    .risk-low {
        background: rgba(30,167,115,0.12);
        color: var(--ci-success);
    }

    .customer-name {
        font-weight: 800;
        color: var(--ci-text);
    }

    .customer-note {
        color: var(--ci-muted);
        margin-top: 4px;
        font-size: 0.8rem;
    }

    .detail-box {
        border: 1px solid var(--ci-line);
        border-radius: 18px;
        background: linear-gradient(180deg, #fff 0%, #fafbff 100%);
        padding: 20px;
    }

    .detail-box h4 {
        margin: 0 0 12px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--ci-muted);
    }

    .detail-box .big {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--ci-text);
        margin: 0;
    }

    .detail-box .meta {
        color: var(--ci-muted);
        margin-top: 8px;
        line-height: 1.7;
    }

    .empty-state {
        border: 1px dashed var(--ci-line);
        background: rgba(95,61,196,0.02);
        color: var(--ci-muted);
        border-radius: 18px;
        padding: 22px 18px;
        text-align: center;
    }

    @media (max-width: 980px) {
        .ci-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="ci-page">
    <section class="ci-hero">
        <span class="ci-kicker"><i class="bi bi-people me-1"></i> Customer Intelligence</span>
        <h1 class="ci-title">Clientes em risco e oportunidades de retenção</h1>
        <p class="ci-subtitle">A IA identifica clientes com risco de churn, padrões de compra em queda e oportunidades para reforçar relacionamento antes que o impacto financeiro se agrave.</p>
    </section>

    <section class="ci-grid">
        <div class="ci-panel">
            <div class="ci-panel-head">
                <h3><span class="ci-icon"><i class="bi bi-table"></i></span> Ranking por risco</h3>
                <span class="ci-pill"><i class="bi bi-funnel"></i> Últimos 30 dias</span>
            </div>
            <div class="ci-body">
                <table class="ci-table" id="customerTable">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Risco</th>
                            <th>Última compra</th>
                            <th>Ticket médio</th>
                        </tr>
                    </thead>
                    <tbody id="customerTableBody"></tbody>
                </table>
            </div>
        </div>

        <aside class="ci-panel">
            <div class="ci-panel-head">
                <h3><span class="ci-icon"><i class="bi bi-lightning-charge"></i></span> Detalhe do cliente</h3>
            </div>
            <div class="ci-body">
                <div class="detail-box" id="customerDetailBox">
                    <h4>Cliente em destaque</h4>
                    <p class="big">Selecione um cliente</p>
                    <div class="meta">O detalhe do cliente aparecerá aqui com a evidência de risco, motivo e recomendação da IA.</div>
                </div>
            </div>
        </aside>
    </section>
</main>

<script>
    (function () {
        const companyId = <?= (int)($companyId ?: 0) ?>;
        const tableBody = document.getElementById('customerTableBody');
        const detailBox = document.getElementById('customerDetailBox');

        function riskLabel(level) {
            const map = {
                high: 'Alto',
                medium: 'Médio',
                low: 'Baixo'
            };
            return map[level] || 'Baixo';
        }

        function riskClass(level) {
            const map = {
                high: 'risk-high',
                medium: 'risk-medium',
                low: 'risk-low'
            };
            return map[level] || 'risk-low';
        }

        function formatCurrency(value) {
            const n = Number(value || 0);
            return new Intl.NumberFormat('pt-AO', { style: 'currency', currency: 'AOA', maximumFractionDigits: 0 }).format(n);
        }

        function renderDetail(item) {
            if (!item) {
                detailBox.innerHTML = '<h4>Cliente em destaque</h4><p class="big">Selecione um cliente</p><div class="meta">O detalhe do cliente aparecerá aqui com a evidência de risco, motivo e recomendação da IA.</div>';
                return;
            }

            detailBox.innerHTML = `
                <h4>${item.customer_name || 'Cliente'}</h4>
                <p class="big">Risco ${riskLabel(item.risk_level || 'low')}</p>
                <div class="meta">
                    <strong>Motivo:</strong> ${item.reason || 'Sem motivo detalhado.'}<br>
                    <strong>Recomendação:</strong> ${item.recommendation || 'Ação de relacionamento recomendada.'}<br>
                    <strong>Última compra:</strong> ${item.days_since_last_purchase ?? 0} dias atrás<br>
                    <strong>Ticket médio:</strong> ${formatCurrency(item.avg_ticket || 0)}
                </div>
            `;
        }

        async function loadCustomers() {
            try {
                const response = await fetch(`index/ajax/get_intelligence_customers.php?company_id=${encodeURIComponent(companyId)}&risk_level=all`);
                const data = await response.json();

                if (!data || data.success === false) {
                    throw new Error(data?.error || 'Erro ao carregar clientes');
                }

                const items = Array.isArray(data.items) ? data.items : [];
                if (!items.length) {
                    tableBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">Sem clientes relevantes para a análise.</div></td></tr>';
                    renderDetail(null);
                    return;
                }

                tableBody.innerHTML = items.map(item => `
                    <tr style="cursor:pointer" data-customer='${JSON.stringify(item).replace(/'/g, "&apos;")}'>
                        <td>
                            <div class="customer-name">${item.customer_name || 'Cliente sem nome'}</div>
                            <div class="customer-note">${item.reason || 'Sem observação detalhada'}</div>
                        </td>
                        <td><span class="risk-badge ${riskClass(item.risk_level || 'low')}">${riskLabel(item.risk_level || 'low')}</span></td>
                        <td>${item.days_since_last_purchase ?? 0} dias</td>
                        <td>${formatCurrency(item.avg_ticket || 0)}</td>
                    </tr>
                `).join('');

                tableBody.querySelectorAll('tr[data-customer]').forEach(row => {
                    row.addEventListener('click', () => {
                        const value = row.getAttribute('data-customer');
                        try {
                            const parsed = JSON.parse(value.replace(/&apos;/g, "'"));
                            renderDetail(parsed);
                        } catch (error) {
                            console.error('Falha ao interpretar cliente:', error);
                        }
                    });
                });

                renderDetail(items[0]);
            } catch (error) {
                console.error(error);
                tableBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">Não foi possível carregar os clientes em risco.</div></td></tr>';
                renderDetail(null);
            }
        }

        loadCustomers();
    })();
</script>
