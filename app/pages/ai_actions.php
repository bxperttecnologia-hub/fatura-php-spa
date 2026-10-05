<?php
require_once '../app/views/layout_creation.php';
require_once '../app/helpers/subscription.php';

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
$planCode = 'BXPERT_BAZA';

if ($companyId > 0) {
    $company = subscription_get_company($pdo, $companyId);
    $planCode = strtoupper((string)($company['plan_code'] ?? 'BXPERT_BAZA'));
}

$expertPlan = in_array($planCode, ['XPERT', 'ENTERPRISE'], true);
?>

<style>
    :root {
        --ai-primary: #5f3dc4;
        --ai-primary-2: #7c4dff;
        --ai-primary-soft: rgba(95, 61, 196, 0.12);
        --ai-success: #17a673;
        --ai-warning: #f59e0b;
        --ai-danger: #e54848;
        --ai-surface: #ffffff;
        --ai-surface-soft: #f7f7fc;
        --ai-border: #e9e7f6;
        --ai-dark: #171722;
        --ai-text: #2b2b36;
        --ai-muted: #6a6d7f;
    }

    body {
        background: #f5f6fb;
    }

    .ai-actions-shell {
        max-width: 1360px;
        margin: 32px auto 64px;
        padding: 0 18px;
    }

    .hero-panel {
        background: linear-gradient(135deg, #1f1e33 0%, #3a2d6d 35%, #5f3dc4 100%);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 28px;
        padding: 28px 30px;
        box-shadow: 0 20px 50px rgba(47, 41, 82, 0.2);
        position: relative;
        overflow: hidden;
    }

    .hero-panel::before,
    .hero-panel::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,0.08);
    }

    .hero-panel::before {
        width: 260px;
        height: 260px;
        top: -120px;
        right: -60px;
    }

    .hero-panel::after {
        width: 200px;
        height: 200px;
        bottom: -90px;
        left: -50px;
    }

    .hero-content {
        position: relative;
        z-index: 1;
        color: white;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.12);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.16);
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-weight: 700;
    }

    .hero-title {
        font-weight: 800;
        letter-spacing: -0.04em;
        margin: 18px 0 12px;
        font-size: clamp(2rem, 4vw, 3rem);
    }

    .hero-subtitle {
        color: rgba(255,255,255,0.8);
        max-width: 760px;
        margin-bottom: 0;
        font-size: 1rem;
    }

    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 22px;
    }

    .btn-hero {
        border-radius: 12px;
        padding: 0.7rem 1.1rem;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .btn-hero-primary {
        background: #fff;
        color: var(--ai-primary);
        border: none;
    }

    .btn-hero-secondary {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.12);
        color: #fff;
    }

    .ai-actions-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 24px;
        margin-top: 28px;
    }

    .chat-shell,
    .side-panel {
        background: rgba(255,255,255,0.88);
        backdrop-filter: blur(10px);
        border: 1px solid var(--ai-border);
        border-radius: 24px;
        box-shadow: 0 10px 25px rgba(33, 24, 62, 0.06);
    }

    .chat-shell {
        overflow: hidden;
    }

    .chat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 22px;
        border-bottom: 1px solid var(--ai-border);
        background: rgba(95, 61, 196, 0.03);
    }

    .chat-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        color: var(--ai-dark);
    }

    .ai-icon {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: var(--ai-primary-soft);
        color: var(--ai-primary);
        font-size: 1.1rem;
    }

    .chat-list {
        max-height: 730px;
        overflow: auto;
        padding: 18px 18px 8px;
        background: linear-gradient(180deg, #fff 0%, #faf9ff 100%);
    }

    .chat-message {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 18px;
        animation: fadeUp 0.4s ease;
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .chat-bubble {
        background: #ffffff;
        border: 1px solid var(--ai-border);
        border-radius: 18px 18px 18px 8px;
        padding: 16px 16px 14px;
        box-shadow: 0 8px 20px rgba(39, 31, 58, 0.04);
        flex: 1;
        min-width: 0;
    }

    .chat-bubble-ai {
        background: linear-gradient(180deg, #ffffff 0%, #f9f6ff 100%);
        border-color: rgba(95, 61, 196, 0.12);
    }

    .message-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
        color: var(--ai-muted);
        font-size: 0.75rem;
        font-weight: 600;
    }

    .message-title {
        font-size: 0.9rem;
        font-weight: 800;
        color: var(--ai-dark);
        margin-bottom: 8px;
        line-height: 1.35;
    }

    .message-body {
        font-size: 0.96rem;
        line-height: 1.7;
        color: #3d4051;
        margin: 0;
        white-space: pre-wrap;
    }

    .white-space-restore {
        white-space: normal;
    }

    .chat-actions {
        margin-top: 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .mini-action {
        border: 1px solid rgba(95, 61, 196, 0.18);
        background: rgba(95, 61, 196, 0.04);
        color: var(--ai-primary);
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.78rem;
        padding: 8px 12px;
    }

    .mini-action:hover {
        background: rgba(95, 61, 196, 0.08);
    }

    .side-panel {
        padding: 20px 18px;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .stat-card {
        border-radius: 18px;
        border: 1px solid var(--ai-border);
        background: linear-gradient(180deg, #fff 0%, #faf8ff 100%);
        padding: 18px 16px;
    }

    .stat-card h3 {
        margin: 0 0 8px;
        font-size: 0.8rem;
        color: var(--ai-muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .stat-card .value {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--ai-dark);
    }

    .stat-card .sub {
        margin: 6px 0 0;
        color: var(--ai-muted);
        font-size: 0.8rem;
    }

    .lock-card {
        background: linear-gradient(180deg, #fffaf3 0%, #fff 100%);
        border: 1px solid rgba(245, 158, 11, 0.18);
        border-radius: 20px;
        padding: 18px 16px;
        color: #76521f;
    }

    .lock-card strong {
        display: block;
        margin-bottom: 8px;
    }

    .action-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .action-item {
        border: 1px solid rgba(95, 61, 196, 0.14);
        border-radius: 16px;
        background: #fff;
        padding: 14px 14px 12px;
        transition: all 0.2s ease;
    }

    .action-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 25px rgba(95, 61, 196, 0.08);
    }

    .action-item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(23, 166, 115, 0.08);
        color: var(--ai-success);
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 0.7rem;
        font-weight: 700;
        border: 1px solid rgba(23, 166, 115, 0.12);
    }

    .action-item p {
        margin: 0;
        color: var(--ai-text);
        line-height: 1.6;
        font-size: 0.92rem;
    }

    .expert-actions {
        display: none;
    }

    .expert-actions.visible {
        display: block;
    }

    .expert-only-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(245, 158, 11, 0.12);
        color: #8d5a00;
        border: 1px solid rgba(245, 158, 11, 0.2);
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .assistant-empty {
        border: 1px dashed var(--ai-border);
        padding: 22px 18px;
        border-radius: 18px;
        background: rgba(95, 61, 196, 0.02);
        color: var(--ai-muted);
        text-align: center;
    }

    @media (max-width: 1100px) {
        .ai-actions-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="ai-actions-shell">
    <section class="hero-panel">
        <div class="hero-content">
            <span class="hero-badge">
                <i class="bi bi-robot"></i>
                IA Agent · ações recomendadas
            </span>
            <h1 class="hero-title">Central de ações do agente IA</h1>
            <p class="hero-subtitle">
                Reúne os alertas relevantes da sua empresa em um fluxo visual tipo chat. Cada item mostra o contexto, o risco e a ação mais adequada para você agir com rapidez e confiança.
            </p>

            <div class="hero-actions">
                <button class="btn btn-hero btn-hero-primary" type="button" id="refreshAlertsBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar alertas
                </button>
                <button class="btn btn-hero btn-hero-secondary" type="button" id="jumpToNextBtn">
                    <i class="bi bi-chevron-double-down me-1"></i> Próxima ação
                </button>
            </div>
        </div>
    </section>

    <section class="ai-actions-grid">
        <div class="chat-shell">
            <div class="chat-header">
                <div class="chat-title">
                    <span class="ai-icon"><i class="bi bi-chat-left-dots"></i></span>
                    Fluxo de alertas
                </div>
                <span id="alertCountBadge" class="expert-only-pill">
                    <i class="bi bi-bell"></i>
                    <span>0 alertas</span>
                </span>
            </div>

            <div class="chat-list" id="aiActionList">
                <div class="assistant-empty">
                    <i class="bi bi-hourglass-split d-block mb-2 fs-4"></i>
                    A carregar os alertas mais recentes…
                </div>
            </div>
        </div>

        <aside class="side-panel">
            <div class="stat-card">
                <h3>Plano atual</h3>
                <p class="value" id="planNameLabel">Carregando…</p>
                <p class="sub" id="planStatusLabel">Verificando o seu acesso à IA Expert</p>
            </div>

            <div id="expertGateCard" class="lock-card">
                <strong><i class="bi bi-shield-lock me-1"></i> Funcionalidade Expert</strong>
                <div id="expertGateText">
                    Este plano ainda não permite executar ações automáticas recomendadas pela IA.
                </div>
            </div>

            <div id="expertActionsWrap" class="expert-actions">
                <div class="stat-card">
                    <h3>Ações recomendadas</h3>
                    <div class="action-list" id="expertActionList"></div>
                </div>
            </div>
        </aside>
    </section>
</main>

<script>
    (function() {
        const companyId = <?= $companyId ?> || 0;
        const expertPlan = <?= $expertPlan ? 'true' : 'false' ?>;

        const aiActionList = document.getElementById('aiActionList');
        const expertActionList = document.getElementById('expertActionList');
        const expertActionsWrap = document.getElementById('expertActionsWrap');
        const expertGateCard = document.getElementById('expertGateCard');
        const expertGateText = document.getElementById('expertGateText');
        const planNameLabel = document.getElementById('planNameLabel');
        const planStatusLabel = document.getElementById('planStatusLabel');
        const alertCountBadge = document.getElementById('alertCountBadge');
        const refreshBtn = document.getElementById('refreshAlertsBtn');
        const jumpToNextBtn = document.getElementById('jumpToNextBtn');

        const API_BASE = (() => {
            const hostname = window.location.hostname;
            const isProd = hostname === 'api-crm.bxpert.co.ao' || hostname === 'www.api-crm.bxpert.co.ao';
            return isProd ? 'https://api-crm.bxpert.co.ao' : 'http://localhost:3000';
        })();

        const AI_ENDPOINT = `${API_BASE}/api/alert-logs`;

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        }

        function formatDate(dateValue) {
            if (!dateValue) return 'Sem data';
            const d = new Date(dateValue);
            if (Number.isNaN(d.getTime())) return dateValue;
            return d.toLocaleString('pt-PT');
        }

        function normalizePayload(payload) {
            if (Array.isArray(payload)) return payload;
            if (!payload || typeof payload !== 'object') return [];

            const nestedKeys = ['data', 'alerts', 'items', 'logs', 'records', 'results', 'insights', 'notification', 'notifications'];
            for (const key of nestedKeys) {
                if (Array.isArray(payload[key])) return payload[key];
            }

            return [payload];
        }

        function findText(obj, keys) {
            if (!obj || typeof obj !== 'object') return '';
            const queue = [obj];
            const seen = new Set();

            while (queue.length) {
                const current = queue.shift();
                if (!current || typeof current !== 'object' || seen.has(current)) continue;
                seen.add(current);

                for (const [key, value] of Object.entries(current)) {
                    if (keys.includes(key)) {
                        if (typeof value === 'string' && value.trim()) return value.trim();
                        if (typeof value === 'number') return String(value);
                    }

                    if (value && typeof value === 'object') {
                        if (Array.isArray(value)) {
                            value.forEach(item => {
                                if (item && typeof item === 'object') queue.push(item);
                            });
                        } else {
                            queue.push(value);
                        }
                    }
                }
            }

            return '';
        }

        function getSummary(item) {
            const metaCandidates = [
                item?.response_meta,
                item?.responseMeta,
                item?.data?.response_meta,
                item?.data?.responseMeta,
            ];

            for (const meta of metaCandidates) {
                if (!meta) continue;

                if (typeof meta === 'string') {
                    try {
                        const parsed = JSON.parse(meta);
                        const parsedSummary = parsed?.ai_analysis?.summary || parsed?.summary;
                        if (typeof parsedSummary === 'string' && parsedSummary.trim()) {
                            return parsedSummary.trim();
                        }
                        const jsonText = JSON.stringify(parsed);
                        if (jsonText && jsonText !== '{}') return jsonText;
                    } catch (e) {
                        if (meta.trim()) return meta.trim();
                    }
                }

                if (typeof meta === 'object') {
                    const direct = meta?.ai_analysis?.summary || meta?.summary;
                    if (typeof direct === 'string' && direct.trim()) return direct.trim();
                    try {
                        const jsonText = JSON.stringify(meta);
                        if (jsonText && jsonText !== '{}') return jsonText;
                    } catch (e) {}
                }
            }

            const fallbacks = [
                item?.ai_analysis?.summary,
                item?.summary,
                item?.message,
                item?.details,
                item?.description,
                findText(item, ['summary', 'description', 'message', 'details'])
            ];

            for (const value of fallbacks) {
                if (typeof value === 'string' && value.trim()) return value.trim();
            }

            return 'Sem resumo detalhado disponível para este alerta.';
        }

        function getDate(item) {
            return findText(item, ['created_at', 'createdAt', 'sent_at', 'sentAt', 'date', 'timestamp']) || new Date().toISOString();
        }

        function getTitle(item) {
            const summary = getSummary(item);
            if (summary && summary.length > 80) return 'Alerta do agente IA';
            return findText(item, ['step', 'title', 'name', 'subject']) || 'Alerta do agente IA';
        }

        function getActionsForItem(item) {
            const summary = getSummary(item);
            const lower = summary.toLowerCase();

            const actions = [
                'Rever a dívida e confirmar o estado da cobrança',
                'Validar o cliente e atualizar a comunicação comercial',
                'Mapear a ação mais urgente em 48 horas'
            ];

            if (lower.includes('jurid') || lower.includes('cobran')) {
                actions.unshift('Encaminhar para departamento jurídico');
            }

            if (lower.includes('forne') || lower.includes('servi') || lower.includes('suspend')) {
                actions.unshift('Suspender novos fornecimentos ou serviços');
            }

            if (lower.includes('email') || lower.includes('notifica')) {
                actions.unshift('Enviar notificação formal ao cliente');
            }

            return actions.slice(0, 3);
        }

        function renderActions(actions) {
            expertActionList.innerHTML = '';
            if (!actions || !actions.length) {
                expertActionList.innerHTML = '<small class="text-muted">Sem ações recomendadas disponíveis.</small>';
                return;
            }

            actions.forEach((action, index) => {
                const item = document.createElement('div');
                item.className = 'action-item';
                const actionId = `ai-action-${index}`;
                item.innerHTML = `
                    <div class="action-item-header">
                        <span class="action-badge"><i class="bi bi-check2-circle"></i> Recomendado</span>
                    </div>
                    <p>${escapeHtml(action)}</p>
                    <div class="chat-actions mt-3">
                        <button type="button" class="btn mini-action" data-action-id="${actionId}" data-action-text="${escapeHtml(action)}" data-action-confirm="approve">
                            Confirmar
                        </button>
                        <button type="button" class="btn mini-action" data-action-id="${actionId}" data-action-text="${escapeHtml(action)}" data-action-confirm="reject">
                            Rejeitar
                        </button>
                    </div>
                `;

                const confirmButton = item.querySelector('[data-action-confirm="approve"]');
                const rejectButton = item.querySelector('[data-action-confirm="reject"]');

                if (confirmButton) {
                    confirmButton.addEventListener('click', async () => {
                        if (!expertPlan) {
                            expertGateText.textContent = 'Acesso Expert necessário para confirmar e executar ações automáticas.';
                            return;
                        }

                        const confirmText = confirmButton.dataset.actionText || action;
                        const result = await confirmAiAction(actionId, confirmText, true);
                        if (result?.success) {
                            confirmButton.disabled = true;
                            rejectButton.disabled = true;
                            confirmButton.textContent = 'Confirmada';
                        }
                    });
                }

                if (rejectButton) {
                    rejectButton.addEventListener('click', async () => {
                        const rejectText = rejectButton.dataset.actionText || action;
                        const result = await confirmAiAction(actionId, rejectText, false);
                        if (result?.success) {
                            rejectButton.disabled = true;
                            rejectButton.textContent = 'Rejeitada';
                            if (confirmButton) confirmButton.disabled = false;
                        }
                    });
                }

                expertActionList.appendChild(item);
            });
        }

        async function confirmAiAction(actionId, actionText, approve) {
            try {
                const response = await fetch('index/ajax/ai_action_confirm.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        company_id: companyId,
                        action_id: actionId,
                        action_text: actionText,
                        approve: Boolean(approve),
                    })
                });

                const data = await response.json();
                if (!data || data.success === false) {
                    throw new Error(data?.error || 'Não foi possível confirmar a ação.');
                }

                const status = approve ? 'confirmada' : 'rejeitada';
                expertGateText.textContent = `Ação ${status} com sucesso. O próximo passo pode ser revisado no histórico do assistente.`;
                return data;
            } catch (error) {
                console.error('Erro ao confirmar ação:', error);
                expertGateText.textContent = 'Não foi possível confirmar esta ação. Tente novamente em instantes.';
                return null;
            }
        }

        function renderMessages(items) {
            if (!items.length) {
                aiActionList.innerHTML = `
                    <div class="assistant-empty">
                        <i class="bi bi-clipboard2-x d-block mb-2 fs-4"></i>
                        Não existem alertas relevantes neste momento.
                    </div>
                `;
                alertCountBadge.innerHTML = '<i class="bi bi-bell"></i> <span>0 alertas</span>';
                return;
            }

            const selected = items[0];
            const actions = getActionsForItem(selected);
            renderActions(actions);

            alertCountBadge.innerHTML = `<i class="bi bi-bell"></i> <span>${items.length} alertas</span>`;

            aiActionList.innerHTML = items.map((item, index) => {
                const summary = getSummary(item);
                const title = getTitle(item);
                const date = formatDate(getDate(item));
                const actionButtons = getActionsForItem(item).slice(0, 2).map(action => {
                    return `<button type="button" class="btn mini-action" data-index="${index}">${escapeHtml(action)}</button>`;
                }).join('');

                return `
                    <div class="chat-message">
                        <span class="ai-icon"><i class="bi bi-robot"></i></span>
                        <div class="chat-bubble chat-bubble-ai">
                            <div class="message-meta">
                                <span>IA Agent</span>
                                <span>${escapeHtml(date)}</span>
                            </div>
                            <div class="message-title">${escapeHtml(title)}</div>
                            <p class="message-body white-space-restore">${escapeHtml(summary)}</p>
                            <div class="chat-actions">
                                ${actionButtons}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            aiActionList.querySelectorAll('.mini-action').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = Number(btn.dataset.index || 0);
                    const item = items[idx];
                    const summary = getSummary(item);
                    const actions = getActionsForItem(item);
                    renderActions(actions);
                    const text = expertPlan
                        ? `Ação selecionada pela IA: ${summary}`
                        : 'Acesso Expert necessário para executar ações automáticas.';
                    expertGateText.textContent = text;
                });
            });
        }

        function updatePlanUi() {
            const isExpert = expertPlan;
            if (isExpert) {
                expertGateCard.style.background = 'linear-gradient(180deg, #f1fff9 0%, #fff 100%)';
                expertGateCard.style.borderColor = 'rgba(23, 166, 115, 0.2)';
                expertGateCard.style.color = '#1d5d46';
                expertGateText.textContent = 'Seu plano Expert permite executar as ações recomendadas pela IA diretamente a partir desta página.';
                expertActionsWrap.classList.add('visible');
            } else {
                expertGateCard.style.background = 'linear-gradient(180deg, #fffaf3 0%, #fff 100%)';
                expertGateCard.style.borderColor = 'rgba(245, 158, 11, 0.18)';
                expertGateCard.style.color = '#76521f';
                expertGateText.textContent = 'Este plano ainda não permite executar ações automáticas recomendadas pela IA.';
                expertActionsWrap.classList.remove('visible');
            }

            planNameLabel.textContent = isExpert ? 'XPERT' : 'BXPERT BÁSICO';
            planStatusLabel.textContent = isExpert ? 'Acesso IA Expert liberado' : 'Upgrade recomendado para ações automáticas';
        }

        async function loadAlerts() {
            try {
                const url = `${AI_ENDPOINT}?company_id=${encodeURIComponent(companyId)}&entity_type=invoice`;
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const raw = await response.json().catch(() => null);
                if (!response.ok || !raw) throw new Error('Sem resposta válida da API');

                const alerts = normalizePayload(raw)
                    .filter(Boolean)
                    .filter(item => {
                        const date = new Date(getDate(item));
                        const now = new Date();
                        const diff = now.getTime() - date.getTime();
                        const oneWeek = 7 * 24 * 60 * 60 * 1000;
                        return !Number.isNaN(date.getTime()) && diff >= 0 && diff <= oneWeek;
                    });

                renderMessages(alerts);
            } catch (err) {
                console.error('Erro ao carregar alertas IA:', err);
                aiActionList.innerHTML = `
                    <div class="assistant-empty">
                        <i class="bi bi-wifi-off d-block mb-2 fs-4"></i>
                        Não foi possível carregar os alertas no momento. Tente novamente mais tarde.
                    </div>
                `;
                alertCountBadge.innerHTML = '<i class="bi bi-bell"></i> <span>0 alertas</span>';
            }
        }

        refreshBtn.addEventListener('click', loadAlerts);
        jumpToNextBtn.addEventListener('click', () => {
            const cards = [...aiActionList.querySelectorAll('.chat-message')];
            const first = cards[1];
            if (first) {
                first.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });

        updatePlanUi();
        loadAlerts();
    })();
</script>
