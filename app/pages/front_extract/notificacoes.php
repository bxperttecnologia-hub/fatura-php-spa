<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    .notifications-page {
        --bg-soft: #f4f7fb;
        --panel: #ffffff;
        --panel-alt: #f8f9fc;
        --line: #e8edf5;
        --line-strong: #dfe7f2;
        --primary: #1f6feb;
        --primary-soft: rgba(31, 111, 235, 0.12);
        --success: #1ca86e;
        --success-soft: rgba(28, 168, 110, 0.12);
        --warning: #f59f00;
        --warning-soft: rgba(245, 159, 0, 0.12);
        --danger: #e34d56;
        --danger-soft: rgba(227, 77, 86, 0.12);
        --text: #17222f;
        --muted: #6d7b8c;
        --shadow: 0 16px 36px rgba(15, 23, 42, 0.08);
    }

    .notifications-page {
        padding: 84px 22px 36px;
        background: linear-gradient(180deg, #f5f8ff 0%, #fff 100%);
        min-height: calc(100vh - 80px);
    }

    .notifications-page .notifications-shell {
        max-width: 980px;
        margin: 0 auto;
    }

    .notifications-page .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 22px;
        flex-wrap: wrap;
    }

    .notifications-page .notifications-title-wrap {
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    .notifications-page .notifications-icon {
        width: 52px;
        height: 52px;
        min-width: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.45rem;
        background: #6fa8ff;
        color: #fff;
        box-shadow: inset 0 0 0 1px rgba(31, 111, 235, 0.06);
    }

    .notifications-page .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(111, 66, 193, 0.08);
        color: #4f7cf5;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .notifications-page .notifications-title {
        font-size: clamp(1.8rem, 2vw, 2.4rem);
        font-weight: 800;
        letter-spacing: -0.04em;
        margin: 0;
        color: var(--text);
    }

    .notifications-page .notifications-subtitle {
        color: var(--muted);
        margin: 8px 0 0;
        max-width: 640px;
        font-size: 0.96rem;
    }

    .notifications-page .header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .notifications-page .btn-modern {
        border: 1px solid var(--line-strong);
        background: #fff;
        color: var(--text);
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 600;
        transition: 0.2s ease;
    }

    .notifications-page .btn-modern:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
    }

    .notifications-page .btn-primary-modern {
        background: linear-gradient(135deg, var(--primary), #4f7cf5);
        border-color: transparent;
        color: #fff;
        box-shadow: 0 12px 24px rgba(31, 111, 235, 0.2);
    }

    .notifications-page .btn-primary-modern:hover {
        color: #fff;
    }

    .notifications-page .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 18px;
    }

    .notifications-page .stat-card {
        background: var(--panel);
        border-radius: 18px;
        border: 1px solid var(--line);
        box-shadow: var(--shadow);
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: 0.2s ease;
    }

    .notifications-page .stat-card:hover {
        transform: translateY(-2px);
    }

    .notifications-page .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
    }

    .notifications-page .stat-icon.primary {
        background: var(--primary-soft);
        color: var(--primary);
    }

    .notifications-page .stat-icon.warning {
        background: var(--warning-soft);
        color: var(--warning);
    }

    .notifications-page .stat-icon.success {
        background: var(--success-soft);
        color: var(--success);
    }

    .notifications-page .stat-icon.danger {
        background: var(--danger-soft);
        color: var(--danger);
    }

    .notifications-page .stat-label {
        margin: 0;
        color: var(--muted);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .notifications-page .stat-value {
        margin: 4px 0 0;
        font-size: clamp(1.2rem, 1.5vw, 1.75rem);
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    .notifications-page .collection-feature {
        background: linear-gradient(135deg, #172033, #24456f);
        border: 0;
        border-radius: 20px;
        box-shadow: 0 18px 35px rgba(23, 32, 51, .16);
        color: #fff;
        display: flex;
        gap: 18px;
        justify-content: space-between;
        margin-bottom: 18px;
        padding: 20px;
    }

    .notifications-page .collection-feature h2 {
        font-size: 1.05rem;
        margin: 0 0 6px;
    }

    .notifications-page .collection-feature p {
        color: rgba(255, 255, 255, .75);
        font-size: .86rem;
        margin: 0;
        max-width: 620px;
    }

    .notifications-page .collection-feature__meta {
        align-items: center;
        display: flex;
        gap: 10px;
        margin-top: 12px;
    }

    .notifications-page .collection-feature__count {
        color: #fff;
        font-size: 1.35rem;
        font-weight: 800;
    }

    .notifications-page .collection-feature__channels {
        color: rgba(255, 255, 255, .72);
        font-size: .78rem;
    }

    .notifications-page .collection-feature .btn {
        align-self: center;
        white-space: nowrap;
    }

    .notifications-page .notifications-layout {
        display: grid;
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 18px;
    }

    .notifications-page .panel {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 20px;
        box-shadow: var(--shadow);
    }

    .notifications-page .sidebar-panel {
        padding: 14px;
    }

    .notifications-page .filter-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .notifications-page .filter-option {
        width: 100%;
        border: 1px solid transparent;
        background: transparent;
        padding: 12px 14px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: var(--text);
        font-weight: 600;
        transition: 0.2s ease;
    }

    .notifications-page .filter-option:hover {
        background: var(--panel-alt);
    }

    .notifications-page .filter-option.active {
        background: var(--primary-soft);
        color: var(--primary);
        border-color: rgba(31, 111, 235, 0.12);
    }

    .notifications-page .filter-label {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notifications-page .filter-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        font-size: 0.72rem;
        padding: 0 8px;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.12);
        color: var(--muted);
        font-weight: 700;
    }

    .notifications-page .filter-option.active .filter-badge {
        background: rgba(31, 111, 235, 0.14);
        color: var(--primary);
    }

    .notifications-page .content-panel {
        padding: 18px;
    }

    .notifications-page .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .notifications-page .search-box {
        position: relative;
        flex: 1;
        min-width: 220px;
    }

    .notifications-page .search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--muted);
    }

    .notifications-page .search-input {
        width: 100%;
        background: var(--panel-alt);
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 10px 14px 10px 38px;
        color: var(--text);
        outline: none;
        transition: 0.2s ease;
    }

    .notifications-page .search-input:focus {
        border-color: rgba(31, 111, 235, 0.35);
        box-shadow: 0 0 0 4px rgba(31, 111, 235, 0.08);
        background: #fff;
    }

    .notifications-page .toolbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .notifications-page .empty-state {
        text-align: center;
        padding: 36px 20px 22px;
        border: 1px dashed var(--line-strong);
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(244, 247, 251, 0.7), rgba(255,255,255,0.7));
    }

    .notifications-page .empty-state i {
        font-size: 2rem;
        color: var(--muted);
        margin-bottom: 12px;
        display: block;
    }

    .notifications-page .notification-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .notifications-page .notification-card {
        display: grid;
        grid-template-columns: 12px minmax(0, 1fr) auto;
        gap: 14px;
        align-items: start;
        background: #fff;
        border: 1px solid var(--line);
        border-left: 0;
        border-radius: 16px;
        padding: 14px 14px 14px 0;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.02);
        transition: 0.2s ease;
    }

    .notifications-page .notification-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
    }

    .notifications-page .notification-card.unread {
        border-color: rgba(245, 159, 0, 0.2);
        background: linear-gradient(180deg, rgba(255, 250, 239, 0.7), rgba(255,255,255,1));
    }

    .notifications-page .notification-card.read {
        opacity: 0.9;
    }

    .notifications-page .notification-indicator {
        height: 100%;
        min-height: 60px;
        border-radius: 0 12px 12px 0;
        width: 100%;
        background: var(--primary);
    }

    .notifications-page .notification-card.unread .notification-indicator {
        background: linear-gradient(180deg, #ffb950, #f59f00);
    }

    .notifications-page .notification-card.success .notification-indicator {
        background: linear-gradient(180deg, #2ec98f, #1ca86e);
    }

    .notifications-page .notification-card.info .notification-indicator {
        background: linear-gradient(180deg, #6fa8ff, var(--primary));
    }

    .notifications-page .notification-card.warning .notification-indicator {
        background: linear-gradient(180deg, #ffbe64, #f59f00);
    }

    .notifications-page .notification-card.danger .notification-indicator {
        background: linear-gradient(180deg, #ff7d87, var(--danger));
    }

    .notifications-page .notification-main {
        min-width: 0;
        padding-right: 10px;
    }

    .notifications-page .notification-topline {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 6px;
    }

    .notifications-page .notification-type {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        border-radius: 999px;
        padding: 5px 8px;
    }

    .notifications-page .notification-type.info {
        background: rgba(31, 111, 235, 0.1);
        color: var(--primary);
    }

    .notifications-page .notification-type.warning {
        background: rgba(245, 159, 0, 0.12);
        color: #b56a00;
    }

    .notifications-page .notification-type.success {
        background: rgba(28, 168, 110, 0.1);
        color: var(--success);
    }

    .notifications-page .notification-type.danger {
        background: rgba(227, 77, 86, 0.12);
        color: var(--danger);
    }

    .notifications-page .notification-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 5px 8px;
        border-radius: 999px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .notifications-page .notification-badge.unread {
        background: rgba(245, 159, 0, 0.12);
        color: #b56a00;
    }

    .notifications-page .notification-badge.read {
        background: rgba(148, 163, 184, 0.12);
        color: var(--muted);
    }

    .notifications-page .notification-title {
        margin: 0;
        font-size: 1.04rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.35;
    }

    .notifications-page .notification-message {
        margin: 8px 0 0;
        color: var(--muted);
        font-size: 0.92rem;
        line-height: 1.6;
    }

    .notifications-page .notification-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .notifications-page .notification-date {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--muted);
        font-size: 0.8rem;
    }

    .notifications-page .notification-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
    }

    .notifications-page .btn-mini {
        border: 1px solid var(--line-strong);
        background: #fff;
        color: var(--text);
        border-radius: 10px;
        padding: 7px 10px;
        font-size: 0.78rem;
        font-weight: 600;
        transition: 0.2s ease;
    }

    .notifications-page .btn-mini:hover {
        background: var(--panel-alt);
    }

    .notifications-page .btn-mini.primary {
        color: var(--primary);
        background: var(--primary-soft);
        border-color: rgba(31, 111, 235, 0.08);
    }

    @media (max-width: 960px) {
        .notifications-page .notifications-layout {
            grid-template-columns: 1fr;
        }

        .notifications-page .stats-grid {
            grid-template-columns: repeat(2, minmax(180px, 1fr));
        }
    }

    @media (max-width: 560px) {
        .notifications-page {
            padding: 76px 14px 28px;
        }

        .notifications-page .stats-grid {
            grid-template-columns: 1fr;
        }

        .notifications-page .notification-card {
            grid-template-columns: 10px minmax(0, 1fr);
            padding: 12px 12px 12px 0;
        }

        .notifications-page .notification-actions {
            justify-content: flex-start;
        }
    }
</style>

<div class="notifications-page">
    <div class="notifications-shell">
        <div class="notifications-header" role="region" aria-labelledby="notificationsPageTitle">
            <div class="notifications-title-wrap">
                <div class="notifications-icon">
                    <i class="bi bi-bell-fill"></i>
                </div>

                <div>
                    <div class="eyebrow">Centro de alertas</div>
                    <h1 class="notifications-title" id="notificationsPageTitle">Notificações</h1>
                    <p class="notifications-subtitle">Acompanhe as alertas do sistema, lembretes de vencimento e ações pendentes em um único painel organizado.</p>
                </div>
            </div>

            <div class="header-actions">
                <button type="button" class="btn btn-modern" id="refreshNotificationsBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
                </button>
                <button type="button" class="btn btn-primary-modern" id="markAllReadBtn">
                    <i class="bi bi-check2-all me-1"></i> Marcar todas como lidas
                </button>
            </div>
        </div>

        <section class="stats-grid" aria-label="Resumo de notificações">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="bi bi-bell"></i></div>
                <div>
                    <p class="stat-label">Total</p>
                    <p class="stat-value" id="statTotal">0</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning"><i class="bi bi-bell-fill"></i></div>
                <div>
                    <p class="stat-label">Não lidas</p>
                    <p class="stat-value" id="statUnread">0</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success"><i class="bi bi-check-circle"></i></div>
                <div>
                    <p class="stat-label">Lidas</p>
                    <p class="stat-value" id="statRead">0</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon danger"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <p class="stat-label">Urgentes</p>
                    <p class="stat-value" id="statUrgent">0</p>
                </div>
            </div>
        </section>

        <section class="collection-feature" aria-labelledby="collectionFeatureTitle">
            <div>
                <h2 id="collectionFeatureTitle"><i class="bi bi-stars me-2"></i>Nova cobrança inteligente BXpert</h2>
                <p>Analisa faturas pendentes, aplica as regras de alerta e encaminha cada lembrete pelo canal de contacto disponível do cliente.</p>
                <div class="collection-feature__meta">
                    <span class="collection-feature__count" id="collectionPendingCount">—</span>
                    <span class="collection-feature__channels" id="collectionRulesSummary">A carregar regras de email e mensagens...</span>
                </div>
            </div>
            <button type="button" class="btn btn-light" id="runCollectionBtn">
                <i class="bi bi-send-check me-1"></i> Executar cobrança
            </button>
            <a class="btn btn-outline-light" href="list_invoices.php?collection=1">
                <i class="bi bi-layout-text-sidebar-reverse me-1"></i> Abrir central
            </a>
        </section>

        <div class="notifications-layout">
            <aside class="panel sidebar-panel">
                <div class="filter-list" id="notificationFilters">
                    <button class="filter-option active" data-filter="all" type="button">
                        <span class="filter-label"><i class="bi bi-inboxes"></i> Todas</span>
                        <span class="filter-badge" id="badgeAll">0</span>
                    </button>

                    <button class="filter-option" data-filter="unread" type="button">
                        <span class="filter-label"><i class="bi bi-bell-fill"></i> Não lidas</span>
                        <span class="filter-badge" id="badgeUnread">0</span>
                    </button>

                    <button class="filter-option" data-filter="read" type="button">
                        <span class="filter-label"><i class="bi bi-check2-circle"></i> Lidas</span>
                        <span class="filter-badge" id="badgeRead">0</span>
                    </button>

                    <button class="filter-option" data-filter="warning" type="button">
                        <span class="filter-label"><i class="bi bi-exclamation-diamond"></i> Importantes</span>
                        <span class="filter-badge" id="badgeWarning">0</span>
                    </button>
                </div>
            </aside>

            <section class="panel content-panel">
                <div class="toolbar">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="search" class="search-input" id="notificationSearch" placeholder="Pesquisar por título, mensagem ou tipo..." aria-label="Pesquisar notificações">
                    </div>

                    <div class="toolbar-actions">
                        <button type="button" class="btn-modern" id="clearSearchBtn">
                            <i class="bi bi-x-lg me-1"></i> Limpar
                        </button>
                    </div>
                </div>

                <div id="notificationList" class="notification-list" aria-live="polite"></div>
            </section>
        </div>
    </div>
</div>

<script>
    (function () {
        const filterButtons = document.querySelectorAll('.filter-option');
        const notificationList = document.getElementById('notificationList');
        const searchInput = document.getElementById('notificationSearch');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const refreshBtn = document.getElementById('refreshNotificationsBtn');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        const runCollectionBtn = document.getElementById('runCollectionBtn');
        const collectionPendingCount = document.getElementById('collectionPendingCount');
        const collectionRulesSummary = document.getElementById('collectionRulesSummary');
        const companyId = <?= json_encode((int)($_SESSION['user']['company_id'] ?? 0)) ?>;
        const apiBaseUrl = ['api-sandibox.bxpert.co.ao', 'www.api-sandibox.bxpert.co.ao'].includes(window.location.hostname)
            ? 'https://api-sandibox.bxpert.co.ao'
            : 'http://localhost:3000';

        const state = {
            notifications: [],
            activeFilter: 'all',
            searchTerm: ''
        };

        function formatDateTimeToBrazilian(iso) {
            if (!iso) return 'Sem data';
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return iso;
            return date.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function normalizeType(type) {
            const value = String(type || '').toLowerCase();
            if (['danger', 'error', 'urgent', 'critical'].includes(value)) return 'danger';
            if (['warning', 'alert', 'attention'].includes(value)) return 'warning';
            if (['success', 'ok', 'info-success'].includes(value)) return 'success';
            return 'info';
        }

        function getBadgeText(notification) {
            if (notification.is_read === true) return 'Lida';
            return 'Nova';
        }

        function getNotificationClass(notification) {
            return notification.is_read === true ? 'read' : 'unread';
        }

        function countBy(filterName) {
            if (filterName === 'all') return state.notifications.length;
            if (filterName === 'unread') return state.notifications.filter(n => n.is_read !== true).length;
            if (filterName === 'read') return state.notifications.filter(n => n.is_read === true).length;
            if (filterName === 'warning') return state.notifications.filter(n => normalizeType(n.type) === 'warning' || normalizeType(n.type) === 'danger').length;
            return 0;
        }

        function updateStats() {
            const total = state.notifications.length;
            const unread = state.notifications.filter(n => n.is_read !== true).length;
            const read = total - unread;
            const urgent = state.notifications.filter(n => normalizeType(n.type) === 'danger' || normalizeType(n.type) === 'warning').length;

            document.getElementById('statTotal').textContent = total;
            document.getElementById('statUnread').textContent = unread;
            document.getElementById('statRead').textContent = read;
            document.getElementById('statUrgent').textContent = urgent;

            document.getElementById('badgeAll').textContent = total;
            document.getElementById('badgeUnread').textContent = unread;
            document.getElementById('badgeRead').textContent = read;
            document.getElementById('badgeWarning').textContent = urgent;
        }

        function getFilteredNotifications() {
            const term = state.searchTerm.trim().toLowerCase();

            return state.notifications.filter((notification) => {
                const matchesFilter = (() => {
                    if (state.activeFilter === 'all') return true;
                    if (state.activeFilter === 'unread') return notification.is_read !== true;
                    if (state.activeFilter === 'read') return notification.is_read === true;
                    if (state.activeFilter === 'warning') {
                        const t = normalizeType(notification.type);
                        return t === 'warning' || t === 'danger';
                    }
                    return true;
                })();

                if (!matchesFilter) return false;
                if (!term) return true;

                const haystack = [
                    notification.title,
                    notification.message,
                    notification.type,
                    notification.created_at,
                    notification.is_read ? 'lida' : 'nova',
                ].join(' ').toLowerCase();

                return haystack.includes(term);
            });
        }

        function renderNotificationCard(notification) {
            const card = document.createElement('article');
            const type = normalizeType(notification.type);
            card.className = `notification-card ${type} ${getNotificationClass(notification)}`;

            const indicator = document.createElement('div');
            indicator.className = 'notification-indicator';

            const main = document.createElement('div');
            main.className = 'notification-main';

            const topLine = document.createElement('div');
            topLine.className = 'notification-topline';

            const typeBadge = document.createElement('span');
            typeBadge.className = `notification-type ${type}`;
            typeBadge.textContent = type === 'danger' ? 'Urgente' : type === 'warning' ? 'Aviso' : type === 'success' ? 'Sucesso' : 'Informação';

            const statusBadge = document.createElement('span');
            statusBadge.className = `notification-badge ${notification.is_read === true ? 'read' : 'unread'}`;
            statusBadge.textContent = getBadgeText(notification);

            topLine.append(typeBadge, statusBadge);

            const title = document.createElement('h3');
            title.className = 'notification-title';
            title.textContent = notification.title || 'Notificação';

            const message = document.createElement('p');
            message.className = 'notification-message';
            message.textContent = notification.message || 'Sem descrição detalhada.';

            const meta = document.createElement('div');
            meta.className = 'notification-meta';

            const date = document.createElement('span');
            date.className = 'notification-date';
            date.innerHTML = '<i class="bi bi-calendar3"></i> ' + formatDateTimeToBrazilian(notification.created_at);

            const actions = document.createElement('div');
            actions.className = 'notification-actions';

            if (notification.is_read !== true) {
                const markReadBtn = document.createElement('button');
                markReadBtn.type = 'button';
                markReadBtn.className = 'btn-mini primary';
                markReadBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Marcar como lida';
                markReadBtn.addEventListener('click', async () => {
                    await markNotificationRead(notification.id);
                });
                actions.appendChild(markReadBtn);
            }

            meta.append(date, actions);

            main.append(topLine, title, message, meta);

            card.append(indicator, main);
            return card;
        }

        function renderNotifications() {
            const filtered = getFilteredNotifications();
            notificationList.innerHTML = '';

            if (!filtered.length) {
                notificationList.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-bell-slash"></i>
                        <h5 class="mb-2">Nenhuma notificação encontrada</h5>
                        <p class="text-muted mb-0">Tente ajustar os filtros ou limpar a pesquisa.</p>
                    </div>
                `;
                return;
            }

            filtered.forEach((notification) => {
                notificationList.appendChild(renderNotificationCard(notification));
            });
        }

        function updateActiveFilterUI() {
            filterButtons.forEach((button) => {
                const active = button.dataset.filter === state.activeFilter;
                button.classList.toggle('active', active);
            });
        }

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                state.activeFilter = button.dataset.filter;
                updateActiveFilterUI();
                renderNotifications();
            });
        });

        searchInput.addEventListener('input', (event) => {
            state.searchTerm = event.target.value;
            renderNotifications();
        });

        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            state.searchTerm = '';
            renderNotifications();
        });

        function normalizeAiInsights(payload) {
            if (Array.isArray(payload)) return payload;
            if (!payload || typeof payload !== 'object') return [];

            const nestedKeys = ['data', 'alerts', 'insights', 'items', 'result', 'results', 'records', 'logs', 'events', 'notification', 'notifications', 'entries', 'dados', 'alertas'];

            for (const key of nestedKeys) {
                if (Array.isArray(payload[key])) {
                    return payload[key];
                }
            }

            return [payload];
        }

        function findFirstTextValue(target, keys) {
            if (!target || typeof target !== 'object') return '';

            const queue = [target];
            const seen = new Set();

            while (queue.length > 0) {
                const current = queue.shift();
                if (!current || typeof current !== 'object' || seen.has(current)) continue;
                seen.add(current);

                for (const [key, value] of Object.entries(current)) {
                    if (keys.includes(key)) {
                        if (value !== undefined && value !== null && String(value).trim() !== '') {
                            return String(value);
                        }
                    }

                    if (value && typeof value === 'object' && !Array.isArray(value)) {
                        queue.push(value);
                    }

                    if (Array.isArray(value)) {
                        value.forEach(item => {
                            if (item && typeof item === 'object') queue.push(item);
                        });
                    }
                }
            }

            return '';
        }

        function getAiText(item, keys) {
            return findFirstTextValue(item, keys);
        }

        function getAiDate(item) {
            return findFirstTextValue(item, ['sent_at', 'created_at', 'createdAt', 'date', 'timestamp', 'updated_at', 'updatedAt']) || '';
        }

        function getAiSummaryText(item) {
            const responseMetaCandidates = [
                item?.response_meta,
                item?.responseMeta,
                item?.data?.response_meta,
                item?.data?.responseMeta,
            ];

            for (const responseMeta of responseMetaCandidates) {
                if (!responseMeta) continue;

                if (typeof responseMeta === 'string') {
                    try {
                        const parsed = JSON.parse(responseMeta);
                        if (parsed && typeof parsed === 'object') {
                            const fromParsed = parsed?.ai_analysis?.summary || parsed?.summary;
                            if (typeof fromParsed === 'string' && fromParsed.trim()) return fromParsed.trim();
                            const metaText = JSON.stringify(parsed);
                            if (metaText && metaText !== '{}') return metaText;
                        }
                    } catch (e) {
                        if (responseMeta.trim()) return responseMeta.trim();
                    }
                }

                if (typeof responseMeta === 'object') {
                    const metaSummary = responseMeta?.ai_analysis?.summary || responseMeta?.summary;
                    if (typeof metaSummary === 'string' && metaSummary.trim()) {
                        return metaSummary.trim();
                    }

                    try {
                        const metaText = JSON.stringify(responseMeta);
                        if (metaText && metaText !== '{}') {
                            return metaText;
                        }
                    } catch (e) {
                        return '';
                    }
                }
            }

            const directCandidates = [
                item?.ai_analysis?.summary,
                item?.summary,
                item?.message,
                item?.details,
                item?.description,
            ];

            for (const value of directCandidates) {
                if (typeof value === 'string' && value.trim()) {
                    return value.trim();
                }
            }

            const nestedSummary = findFirstTextValue(item, ['summary', 'description', 'message', 'details']);
            return nestedSummary || '';
        }

        function isWithinLastWeek(dateValue) {
            if (!dateValue) return true;

            const date = new Date(dateValue);
            if (Number.isNaN(date.getTime())) return true;

            const now = new Date();
            const diffMs = now.getTime() - date.getTime();
            const oneWeekMs = 7 * 24 * 60 * 60 * 1000;

            return diffMs >= 0 && diffMs <= oneWeekMs;
        }

        function getAiTitle(item) {
            const step = getAiText(item, ['step', 'title', 'name', 'subject', 'summary', 'type']);
            const titleMap = {
                'invoice_due_-7': 'Lembrete de vencimento em 7 dias',
                'invoice_due_0': 'Vencimento agendado para hoje',
                'invoice_due_1': 'Pagamento em atraso de 1 dia',
                'invoice_due_3': 'Pagamento em atraso de 3 dias',
            };

            if (step && titleMap[step]) return titleMap[step];
            if (step) return step.replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
            return 'Alerta do sistema';
        }

        function buildAiSummary(items) {
            const grouped = new Map();

            items.forEach((item) => {
                const summaryText = getAiSummaryText(item) || getAiText(item, ['step', 'title', 'name', 'subject', 'summary', 'type']) || 'alerta';
                const key = summaryText;

                if (!grouped.has(key)) {
                    grouped.set(key, {
                        step: summaryText,
                        count: 0,
                        channels: [],
                        statuses: [],
                        entityIds: [],
                        latestDate: '',
                        summaryText,
                    });
                }

                const group = grouped.get(key);
                group.count += 1;

                const channel = getAiText(item, ['channel', 'sent_via', 'method']);
                if (channel && !group.channels.includes(channel)) group.channels.push(channel);

                const status = getAiText(item, ['status']);
                if (status && !group.statuses.includes(status)) group.statuses.push(status);

                const entityId = getAiText(item, ['entity_id', 'entityId']);
                if (entityId && entityId !== 'N/D' && !group.entityIds.includes(entityId)) group.entityIds.push(entityId);

                const date = getAiDate(item);
                if (date) {
                    const current = new Date(date);
                    const latest = group.latestDate ? new Date(group.latestDate) : null;
                    if (!latest || current > latest) group.latestDate = date;
                }
            });

            return Array.from(grouped.values()).map((group) => {
                const channels = Array.isArray(group.channels) ? group.channels : [];
                const statuses = Array.isArray(group.statuses) ? group.statuses : [];
                const entityIds = Array.isArray(group.entityIds) ? group.entityIds : [];
                const title = getAiTitle({ response_meta: { ai_analysis: { summary: group.summaryText } } });
                const summaryText = group.summaryText || `Foram registados ${group.count} eventos para ${title.toLowerCase()}. ${channels.length ? `A comunicação foi realizada por ${channels.join(', ')}.` : ''} ${statuses.length ? `Estado atual: ${statuses.join(', ')}.` : ''} ${entityIds.length ? `Faturas envolvidas: ${entityIds.slice(0, 6).join(', ')}${entityIds.length > 6 ? '...' : ''}.` : ''} ${group.latestDate ? `Último registo em ${formatDateTimeToBrazilian(group.latestDate)}.` : ''}`;

                return {
                    step: group.step,
                    count: group.count,
                    channels,
                    statuses,
                    entity_ids: entityIds,
                    createdAt: group.latestDate,
                    title,
                    message: summaryText,
                };
            });
        }

        async function ajaxLoadAIInsights() {
            const btn = document.getElementById('aiAgentBtn');
            const countEl = document.getElementById('aiInsightCount');
            const listEl = document.getElementById('aiInsightList');
            if (!btn || !countEl || !listEl) return;

            try {
                const response = await fetch(`${apiBaseUrl}/api/alert-logs?company_id=${encodeURIComponent(companyId)}&entity_type=invoice`);
                const rawInsights = await response.json();

                const normalized = normalizeAiInsights(rawInsights);
                const insights = normalized.filter((item) => isWithinLastWeek(getAiDate(item)));
                const summary = buildAiSummary(insights);

                const count = summary.length;
                countEl.textContent = count > 99 ? '99+' : String(count);
                countEl.hidden = count === 0;
                btn.classList.toggle('has-alert', count > 0);

                listEl.innerHTML = '';

                if (count === 0) {
                    listEl.innerHTML = '<small class="d-block px-3 py-2 text-muted">Sem alertas na última semana</small>';
                    return;
                }

                async function loadCollectionRules() {
                    try {
                        const [rulesResponse, logsResponse] = await Promise.all([
                            fetch(`${apiBaseUrl}/api/alert-rules?company_id=${encodeURIComponent(companyId)}`),
                            fetch(`${apiBaseUrl}/api/alert-logs?company_id=${encodeURIComponent(companyId)}&entity_type=invoice`)
                        ]);
                        const rules = await rulesResponse.json();
                        const logs = await logsResponse.json();
                        const activeRules = Array.isArray(rules) ? rules.filter(rule => rule.active !== false) : [];
                        const channels = [...new Set(activeRules.flatMap(rule => Array.isArray(rule.channels) ? rule.channels : []))];
                        collectionPendingCount.textContent = `${logs.filter(log => log.status === 'sent').length} alertas`;
                        collectionRulesSummary.textContent = activeRules.length
                            ? `${activeRules.length} regras ativas · ${channels.join(' + ') || 'canal padrão'}`
                            : 'Escalonamento padrão ativo para faturas pendentes';
                    } catch (error) {
                        collectionRulesSummary.textContent = 'Regras geridas pelo motor de alertas';
                    }
                }

                async function runCollection() {
                    runCollectionBtn.disabled = true;
                    runCollectionBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A analisar...';
                    try {
                        const response = await fetch(`${apiBaseUrl}/api/robot/check-invoices`, { method: 'POST' });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.error || 'Não foi possível executar a cobrança.');
                        runCollectionBtn.innerHTML = '<i class="bi bi-check2 me-1"></i> Cobrança executada';
                        await loadCollectionRules();
                        await ajaxLoadAIInsights();
                    } catch (error) {
                        console.error('Erro ao executar cobrança:', error);
                        runCollectionBtn.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Tentar novamente';
                    } finally {
                        runCollectionBtn.disabled = false;
                    }
                }

                const fragment = document.createDocumentFragment();
                summary.forEach((n) => {
                    const item = document.createElement('div');
                    item.className = 'ai-panel-item';

                    const title = document.createElement('small');
                    title.className = 'd-block fw-bold';
                    title.textContent = n.title;
                    title.style.textWrap = 'wrap';

                    const message = document.createElement('small');
                    message.className = 'd-block';
                    message.textContent = n.message;
                    message.style.fontSize = '0.85rem';
                    message.style.color = '#555';
                    message.style.textWrap = 'wrap';

                    const date = document.createElement('small');
                    date.className = 'text-muted';
                    date.textContent = n.createdAt ? formatDateTimeToBrazilian(n.createdAt) : 'Sem data';

                    item.append(title, message, date);
                    fragment.appendChild(item);
                });

                listEl.appendChild(fragment);
            } catch (err) {
                console.error('Erro ao carregar insights do agente IA:', err);
                countEl.hidden = true;
                btn.classList.remove('has-alert');
                listEl.innerHTML = '<small class="d-block px-3 py-2 text-muted">Sem alertas na última semana</small>';
            }
        }

        async function fetchNotifications() {
            try {
                const response = await fetch('index/ajax/get_notifications.php?limit=100&page=1');
                const data = await response.json();

                if (!data || !data.success) {
                    throw new Error(data?.message || 'Erro ao carregar notificações');
                }

                state.notifications = Array.isArray(data.notifications) ? data.notifications : [];
                updateStats();
                renderNotifications();
            } catch (error) {
                console.error(error);
                notificationList.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-exclamation-circle"></i>
                        <h5 class="mb-2">Não foi possível carregar</h5>
                        <p class="text-muted mb-0">Tente novamente mais tarde.</p>
                    </div>
                `;
            }
        }

        async function markNotificationRead(id) {
            try {
                const form = new FormData();
                form.append('action', 'mark_read');
                form.append('notification_id', id);

                const response = await fetch('index/ajax/notifications_actions.php', {
                    method: 'POST',
                    body: form
                });

                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Erro ao atualizar');

                await fetchNotifications();
            } catch (error) {
                console.error(error);
            }
        }

        async function markAllNotificationsRead() {
            try {
                const form = new FormData();
                form.append('action', 'mark_all_read');

                const response = await fetch('index/ajax/notifications_actions.php', {
                    method: 'POST',
                    body: form
                });

                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Erro ao atualizar');

                await fetchNotifications();
            } catch (error) {
                console.error(error);
            }
        }

        markAllReadBtn.addEventListener('click', markAllNotificationsRead);
        refreshBtn.addEventListener('click', fetchNotifications);
        runCollectionBtn.addEventListener('click', runCollection);

        fetchNotifications();
        loadCollectionRules();
    })();
</script>
