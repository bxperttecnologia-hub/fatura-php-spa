<style>
    .app-navbar {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid #eee;
        z-index: 5;
    }

    .app-navbar.mobile-active {
        left: 250px;
        width: calc(100% - 250px);
    }

    /* Botões */
    .nav-icon-btn {
        border: none;
        background: transparent;
        padding: 8px;
        border-radius: 10px;
        transition: 0.2s;
    }

    .nav-icon-btn:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    /* Pill empresa */
    .nav-pill {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 6px 12px;
    }

    /* Perfil */
    .profile-img {
        width: 36px;
        height: 36px;
        border: 2px solid var(--primary-color);
    }

    /* Dropdown moderno */
    .dropdown-modern {
        position: absolute;
        right: 0;
        top: 110%;
        min-width: 260px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        display: none;
        animation: fadeIn 0.2s ease;
        overflow: hidden;
        z-index: 999;
    }

    .dropdown-modern.show {
        display: block;
    }

    /* Badge */
    .notificationCount {
        position: absolute;
        top: 2px;
        right: 2px;
        background: red;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 50px;
        min-width: 14px;
        text-align: center;
    }

    .notificationCount[hidden] {
        display: none;
    }

    .dropdown-modern .dropdown-item.ai-insight-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        cursor: default;
        border-left: 3px solid #6f42c1;
    }

    /* Aside IA Agent (em vez de dropdown) */
    .ai-panel-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.25);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
        z-index: 1030;
    }

    .ai-panel-backdrop.show {
        opacity: 1;
        pointer-events: auto;
    }

    .notification-panel {
        position: fixed;
        top: 0;
        right: -420px;
        width: min(420px, 92vw);
        height: 100vh;
        background: #fff;
        border-left: 1px solid #eee;
        box-shadow: -18px 0 40px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
        transition: right 0.25s ease;
        z-index: 1040;
    }

    .notification-panel.show {
        right: 0;
    }

    .ai-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 18px 18px 12px;
        border-bottom: 1px solid #f0f0f0;
        background: #faf7ff;
    }

    .notification-panel .ai-panel-header {
        padding: 14px 14px 10px;
        background: #fff;
    }

    .notification-panel .ai-panel-header .fw-semibold {
        font-size: 0.96rem;
    }

    .ai-panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 12px 12px 16px;
    }

    .notification-panel .ai-panel-body {
        padding: 10px;
    }

    .ai-panel-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #f8f9fa;
        border: 1px solid #f0f0f0;
        margin-bottom: 10px;
    }

    .notification-panel .ai-panel-item {
        padding: 10px 12px;
        border-radius: 10px;
        gap: 4px;
        margin-bottom: 8px;
    }

    .notification-panel .ai-panel-item.unread {
        background: #fff8f3;
        border-color: #ffd8b8;
        box-shadow: inset 0 0 0 1px rgba(255, 146, 52, 0.05);
    }

    .notification-panel .ai-panel-item.read {
        background: #f7f8fa;
        border-color: #eceef2;
        opacity: 0.9;
    }

    .notification-panel .notification-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 2px;
    }

    .notification-panel .notification-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.68rem;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 0.03em;
        border-radius: 999px;
        padding: 4px 7px;
        text-transform: uppercase;
    }

    .notification-panel .notification-badge.new {
        background: #fff0e4;
        color: #b55b00;
    }

    .notification-panel .notification-badge.grouped {
        background: #eef3ff;
        color: #244db5;
    }

    .notification-panel .notification-badge.read {
        background: #eef2f5;
        color: #5f6777;
    }

    .notification-panel .ai-panel-item small {
        display: block;
    }

    /* Itens */
    .dropdown-modern .dropdown-item {
        padding: 10px 15px;
        transition: 0.2s;
    }

    .dropdown-modern .dropdown-item:hover {
        background: #f5f5f5;
    }

    .dropdown-modern .dropdown-item.notification-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        cursor: default;
    }

    .dropdown-menu-custom {
        position: absolute;
        left: 0;
        top: 110%;
        min-width: 220px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        list-style: none;
        margin: 0;
        padding: 8px 0;
        font-size: 14px;
        z-index: 999;
    }

    .dropdown-menu-custom li {
        padding: 0;
    }

    .dropdown-menu-custom .dropdown-item {
        display: block;
        padding: 8px 15px;
        color: #212529;
        text-decoration: none;
        transition: 0.2s;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        cursor: pointer;
    }

    .dropdown-menu-custom .dropdown-item:hover {
        background: #f5f5f5;
    }

    /* Animação */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #perfil-menu {
        min-width: 200px;
        width: auto !important;
    }

    /* Botão hamburguer (apenas mobile/tablet) */
    #mobileMenuBtn {
        display: none;
        border: none;
        background: transparent;
        padding: 8px;
        border-radius: 10px;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
        color: var(--primary-color, #007abd);
    }

    #mobileMenuBtn:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    #mobileMenuBtn i {
        width: 22px;
        height: 22px;
    }

    @media (max-width: 992px) {
        #mobileMenuBtn {
            display: inline-flex;
        }
    }

    @media (max-width: 576px) {
        .app-navbar {
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        .d-flex.align-items-center.gap-3 {
            gap: 0.5rem !important;
        }
    }
</style>

<?php
// --- Dados de sessão sanitizados uma única vez, aqui, para todo o template ---
$currentUserId  = (int)($_SESSION['user']['id'] ?? 0);
$currentCompany = (int)($_SESSION['user']['company_id'] ?? 0);

$nomeFormatado  = formatName(($_SESSION['user']['name'] ?? $_SESSION['user']['nome'] ?? ''));
$userRole       = t($_SESSION['user']['role'] ?? '');
$nameCompany    = $_SESSION['user']['name_company'] ?? 'Empresa';
$acronym        = strtoupper($_SESSION['user']['acronym'] ?? 'EMP');

// Evita path traversal / XSS no <img src>: só aceita nome de ficheiro simples
$profileImage = basename($_SESSION['user']['image'] ?? '');
if ($profileImage === '' || !preg_match('/^[\w.-]+\.(png|jpe?g|gif|webp)$/i', $profileImage)) {
    $profileImage = 'default.png';
}

$currentPlanCode = strtoupper((string)($_SESSION['user']['plan_code'] ?? ''));
if ($currentCompany > 0 && $currentPlanCode === '') {
    try {
        $planStmt = Database::pdo()->prepare('SELECT plan_code FROM companies WHERE id = ? LIMIT 1');
        $planStmt->execute([$currentCompany]);
        $currentPlanCode = strtoupper((string)($planStmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        error_log('nav.php plan_code: ' . $e->getMessage()); // tabela companies pode não existir
    }
}

$copilotAvailable = in_array($currentPlanCode, ['XPERT', 'ENTERPRISE'], true);

// Token CSRF simples para as chamadas AJAX que alteram estado (ex.: trocar empresa)
$csrfToken = Csrf::token(); // um único token CSRF para toda a app
?>

<header class="app-navbar px-3 py-2">

    <div class="d-flex align-items-center justify-content-between w-100">
        <input type="hidden" id="user_id" value="<?= $currentUserId ?>">
        <input type="hidden" id="company_id" value="<?= $currentCompany ?>">
        <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

        <!-- LEFT -->
        <div class="d-flex align-items-center gap-3">

            <button id="mobileMenuBtn" type="button" title="Ocultar/Mostrar menu" aria-label="Ocultar/Mostrar menu">
                <i data-lucide="panel-left-close"></i>
            </button>

            <!-- Empresa -->
            <div class="dropdown-custom position-relative">
                <button class="btn nav-pill d-flex align-items-center gap-2"
                    type="button" id="empresaDropdown" aria-haspopup="true" aria-expanded="false">

                    <i data-lucide="building-2"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($nameCompany, ENT_QUOTES) ?></span>
                    <span class="d-inline d-sm-none text-uppercase"><?= htmlspecialchars($acronym, ENT_QUOTES) ?></span>
                    <i data-lucide="chevron-down"></i>
                </button>

                <ul class="popup-menu dropdown-menu-custom text-left" id="empresaDropdownMenu">
                    <li><small class="d-block px-3 py-2 text-muted">A carregar…</small></li>
                </ul>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="d-flex align-items-center gap-3">

            <?php if (!$copilotAvailable): ?>
                <a href="/subscription" data-spa class="d-none d-lg-inline-flex align-items-center gap-2 text-decoration-none border rounded-pill px-3 py-1.5 fw-semibold" style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.25); color: #8a5b00;">
                    <i class="bi bi-rocket-takeoff"></i>
                    <span>Upgrade para Agent IA</span>
                </a>
            <?php endif; ?>

            <!-- NOTIFICAÇÕES -->
            <div class="position-relative">
                <button class="btn nav-icon-btn position-relative" type="button" id="notifBtn" aria-haspopup="false" aria-expanded="false" aria-label="Notificações" title="Notificações">
                    <i class="bi bi-bell"></i>
                    <span class="notificationCount" id="notificationCount" hidden>0</span>
                </button>
            </div>

            <div class="ai-panel-backdrop" id="notificationPanelBackdrop"></div>
            <aside class="notification-panel" id="notificationPanel" aria-label="Painel de notificações">
                <div class="ai-panel-header">
                    <div class="d-flex align-items-center gap-2 fw-semibold">
                        <i class="bi bi-bell text-primary"></i>
                        <span>Notificações</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-light border-0" id="closeNotificationPanelBtn" aria-label="Fechar notificações">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="ai-panel-body" id="notificationList">
                    <small class="d-block px-3 py-2 text-muted">Nenhuma notificação</small>
                </div>

                <div class="px-3 pb-3 pt-2 border-top">
                    <a class="btn btn-primary w-100" href="/notificacoes" data-spa>Ver todas</a>
                </div>
            </aside>

            <!-- 👤 PERFIL -->
            <div class="perfil-container position-relative">
                <button type="button" class="d-flex align-items-center gap-2 border-0 bg-transparent text-decoration-none"
                    id="perfilBtn" aria-haspopup="true" aria-expanded="false">

                    <div class="rounded-circle profile-img overflow-hidden">
                        <img class="w-100" src="/assets/img/profiles/<?= htmlspecialchars($profileImage, ENT_QUOTES) ?>" alt="Foto de perfil">
                    </div>

                    <div class="d-none d-sm-flex flex-column align-items-start gap-0">
                        <strong class="mb-0"><?= htmlspecialchars($nomeFormatado, ENT_QUOTES) ?></strong>
                        <small class="text-muted opacity-50" style="margin-top: -5px;"><?= htmlspecialchars($userRole, ENT_QUOTES) ?></small>
                    </div>
                </button>

                <div class="popup-menu text-left mt-2" id="perfil-menu">
                    <a class="popup-item p-2" href="/profile" data-spa><i class="bi bi-person"></i> <?= t('Perfil do utilizador') ?></a>
                    <a class="popup-item p-2" href="/subscription" data-spa><i class="bi bi-credit-card-2-back"></i> Meu Plano</a>
                    <hr class="opacity-25">
                    <a class="popup-item p-2" href="#" id="btn-logout"><i class="bi bi-box-arrow-in-left"></i> <?= t('Sair') ?></a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    (function() {
        'use strict';

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const companyId = document.getElementById('company_id').value;
        const publicPath = window.location.pathname.match(/^(.*\/public)(?:\/|$)/)?.[1] || '';

        window.lucide && lucide.createIcons();

        // ---------- Popups (toggle + fechar ao clicar fora) ----------
        const triggers = {
            'empresaDropdown': 'empresaDropdownMenu',
            'notifBtn': 'notif-menu',
            'perfilBtn': 'perfil-menu',
        };

        function resetPopupAriaExpanded() {
            Object.keys(triggers).forEach((btnId) => {
                document.getElementById(btnId)?.setAttribute('aria-expanded', 'false');
            });
        }

        Object.entries(triggers).forEach(([btnId, menuId]) => {
            const btn = document.getElementById(btnId);
            const menu = document.getElementById(menuId);
            if (!btn || !menu) return;

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = menu.classList.contains('show');
                document.querySelectorAll('.popup-menu').forEach(m => m.classList.remove('show'));
                resetPopupAriaExpanded();
                if (!isOpen) {
                    menu.classList.add('show');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });

        const notifBtn = document.getElementById('notifBtn');
        const notifPanel = document.getElementById('notificationPanel');
        const notifBackdrop = document.getElementById('notificationPanelBackdrop');
        const closeNotifPanelBtn = document.getElementById('closeNotificationPanelBtn');

        function closeNotificationPanel() {
            if (!notifPanel || !notifBackdrop || !notifBtn) return;
            notifPanel.classList.remove('show');
            notifBackdrop.classList.remove('show');
            notifBtn.setAttribute('aria-expanded', 'false');
        }

        function toggleNotificationPanel() {
            if (!notifPanel || !notifBackdrop || !notifBtn) return;
            const isOpen = notifPanel.classList.contains('show');
            document.querySelectorAll('.popup-menu').forEach(menu => menu.classList.remove('show'));
            resetPopupAriaExpanded();
            notifPanel.classList.toggle('show', !isOpen);
            notifBackdrop.classList.toggle('show', !isOpen);
            notifBtn.setAttribute('aria-expanded', String(!isOpen));
        }

        if (notifBtn) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleNotificationPanel();
            });
        }

        if (closeNotifPanelBtn) {
            closeNotifPanelBtn.addEventListener('click', closeNotificationPanel);
        }

        if (notifBackdrop) {
            notifBackdrop.addEventListener('click', closeNotificationPanel);
        }

        document.addEventListener('click', (e) => {
            const clickedInsideNotificationPanel = e.target.closest('.notification-panel, #notifBtn, #notificationPanelBackdrop');
            const clickedInsideDropdown = e.target.closest('.perfil-container, .dropdown-custom, .nav-icon-btn');
            if (!clickedInsideNotificationPanel && !clickedInsideDropdown) {
                document.querySelectorAll('.popup-menu').forEach(menu => menu.classList.remove('show'));
                resetPopupAriaExpanded();
                closeNotificationPanel();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeNotificationPanel();
            }
        });

        // ---------- Helper de fetch com tratamento de erro comum ----------
        async function fetchJSON(url, options = {}) {
            try {
                const res = await fetch(url, options);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return await res.json();
            } catch (err) {
                const isLocalApi = /^https?:\/\/localhost(?::\d+)?\//.test(url);
                if (!isLocalApi) {
                    console.error(`Falha ao aceder a ${url}:`, err);
                }
                return null;
            }
        }

        // ---------- Limites / assinatura ----------
        fetchJSON(`/assets/ajax/get_company_limits.php?company_id=${encodeURIComponent(companyId)}`)
            .then(resp => {
                const subInfo = document.getElementById('subInfo');
                if (!subInfo) return;
                if (!resp || !resp.success) {
                    subInfo.textContent = 'Não foi possível carregar os limites.';
                    return;
                }
                const exp = resp.plan_expires_at ?
                    new Date(resp.plan_expires_at + 'T00:00:00').toLocaleDateString('pt-PT') :
                    '-';
                const days = resp.days_left ?? '-';
                subInfo.textContent = `${resp.plan_name} • vence em ${exp} • ${days} dias restantes`;

                const renewLink = document.getElementById('btnRenewFromIndex');
                if (renewLink) renewLink.href = `subscription.php?company_id=${encodeURIComponent(resp.company_id)}`;
            });

        // ---------- Dados da empresa (regime de IVA etc.) ----------
        fetchJSON(`/assets/ajax/company_data.php?company_id=${encodeURIComponent(companyId)}`)
            .then(resp => {
                if (!resp || !resp.success || !resp.data) return;
                // sessionStorage é preferível a localStorage aqui: some com o fecho da aba
                sessionStorage.setItem('vat_regime', JSON.stringify(resp.data.vat_regime));
            });

        // ---------- Troca de empresa ----------
        async function trocarEmpresa(empresaId, name, registrationNumber, email) {
            const body = new URLSearchParams({
                company_id: empresaId,
                name_company: name,
                registration_number: registrationNumber,
                email_company: email,
                csrf_token: csrfToken,
            });

            const data = await fetchJSON(`${publicPath}/assets/ajax/change_company.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body,
            });

            if (data && data.success) {
                location.reload();
            } else {
                alert('Erro ao trocar de empresa.');
            }
        }

        async function carregarEmpresas() {
            const empresas = await fetchJSON(`${publicPath}/assets/ajax/get_companies.php`);
            const dropdown = document.getElementById('empresaDropdownMenu');
            if (!dropdown) return;

            dropdown.innerHTML = '';

            if (!Array.isArray(empresas) || empresas.length === 0) {
                dropdown.innerHTML = '<li><small class="d-block px-3 py-2 text-muted">Nenhuma empresa encontrada</small></li>';
                return;
            }

            empresas.forEach(empresa => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item';
                a.textContent = empresa.name; // textContent evita XSS via innerHTML

                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    trocarEmpresa(empresa.id, empresa.name, empresa.registration_number, empresa.email);
                });

                li.appendChild(a);
                dropdown.appendChild(li);
            });
        }

        // ---------- Notificações ----------
        function formatDateTimeToBrazilian(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return iso;
            return d.toLocaleString('pt-BR');
        }

        function groupNotifications(items) {
            const map = new Map();

            items.forEach((notification) => {
                const title = notification?.title ?? 'Notificação';
                const message = notification?.message ?? 'Sem detalhes';
                const key = `${title}|${message}`;

                if (!map.has(key)) {
                    map.set(key, {
                        title,
                        message,
                        created_at: notification?.created_at ?? notification?.createdAt ?? new Date().toISOString(),
                        count: 1,
                        unread: notification?.unread ?? notification?.read === false,
                    });
                    return;
                }

                const existing = map.get(key);
                existing.count += 1;
                if (notification?.created_at || notification?.createdAt) {
                    const currentDate = new Date(notification?.created_at ?? notification?.createdAt);
                    const storedDate = new Date(existing.created_at);
                    if (!Number.isNaN(currentDate.getTime()) && (!Number.isNaN(storedDate.getTime()) ? currentDate > storedDate : true)) {
                        existing.created_at = notification.created_at ?? notification.createdAt;
                    }
                }
                existing.unread = existing.unread || (notification?.unread ?? notification?.read === false);
            });

            return Array.from(map.values()).sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
        }

        let isLoadingNotifications = false;

        async function ajaxLoadNotifications() {
            if (isLoadingNotifications) return; // evita corridas por chamadas concorrentes
            isLoadingNotifications = true;

            const countEl = document.getElementById('notificationCount');
            const listEl = document.getElementById('notificationList');
            const notifButton = document.getElementById('notifBtn');
            if (!countEl || !listEl || !notifButton) {
                isLoadingNotifications = false;
                return;
            }

            try {
                const triggers = await Promise.allSettled([
                    fetch('/index/ajax/data_user_notify.php'),
                    fetch('/index/ajax/data_company_notify.php'),
                ]);

                triggers.forEach((result, i) => {
                    const endpoint = i === 0 ? 'data_user_notify.php' : 'data_company_notify.php';
                    if (result.status === 'rejected') {
                        console.warn(`Falha ao gerar notificações (${endpoint}):`, result.reason);
                    } else if (!result.value.ok) {
                        console.warn(`${endpoint} respondeu com status ${result.value.status}`);
                    }
                });

                const data = await fetchJSON('/index/ajax/get_notifications.php');

                if (data?.success === false) {
                    listEl.innerHTML = `<small class="d-block px-3 py-2 text-danger">Erro: ${escapeHtml(data.error ?? 'Erro desconhecido')}</small>`;
                    countEl.hidden = true;
                    notifButton.classList.remove('has-alert');
                    return;
                }

                const notifications = groupNotifications(Array.isArray(data?.notifications) ? data.notifications : []);
                const unreadCount = notifications.filter(item => item.unread !== false).length;
                const totalDisplayCount = unreadCount > 0 ? unreadCount : notifications.length;

                countEl.textContent = totalDisplayCount > 99 ? '99+' : String(totalDisplayCount);
                countEl.hidden = totalDisplayCount === 0;
                notifButton.classList.toggle('has-alert', totalDisplayCount > 0);

                listEl.innerHTML = '';

                if (notifications.length === 0) {
                    listEl.innerHTML = '<small class="d-block px-3 py-2 text-muted">Nenhuma notificação</small>';
                    return;
                }

                const fragment = document.createDocumentFragment();

                notifications.forEach(n => {
                    const item = document.createElement('div');
                    const unreadState = n.unread === false ? 'read' : 'unread';
                    item.className = `ai-panel-item ${unreadState}`;

                    const title = document.createElement('small');
                    title.className = 'd-block fw-bold';
                    title.textContent = n.count > 1 ? `${n.title} (${n.count})` : n.title;
                    title.style.textWrap = 'wrap';

                    const message = document.createElement('small');
                    message.className = 'd-block';
                    message.textContent = n.message;
                    message.style.fontSize = '0.85rem';
                    message.style.color = '#555';
                    message.style.textWrap = 'wrap';

                    const meta = document.createElement('div');
                    meta.className = 'notification-meta';

                    const badge = document.createElement('span');
                    badge.className = `notification-badge ${n.count > 1 ? 'grouped' : (n.unread === false ? 'read' : 'new')}`;
                    badge.textContent = n.count > 1 ? 'Agrupado' : (n.unread === false ? 'Lida' : 'Nova');

                    const date = document.createElement('small');
                    date.className = 'text-muted';
                    date.textContent = formatDateTimeToBrazilian(n.created_at);

                    meta.appendChild(badge);
                    meta.appendChild(date);
                    item.append(title, message, meta);
                    fragment.appendChild(item);
                });

                listEl.appendChild(fragment);
            } catch (err) {
                console.error('Erro ao carregar notificações:', err);
                listEl.innerHTML = '<small class="d-block px-3 py-2 text-danger">Não foi possível carregar as notificações.</small>';
                countEl.hidden = true;
                notifButton.classList.remove('has-alert');
            } finally {
                isLoadingNotifications = false;
            }
        }

        // ---------- Assistente IA (alert_logs) ----------
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
                        // se vier como texto simples, usa diretamente
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
            const responseMetaSummary = getAiSummaryText(item);
            if (responseMetaSummary) return 'Alerta do agente IA';

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

        function buildFormalAlertText(item) {
            const step = getAiText(item, ['step', 'title', 'name', 'subject', 'summary', 'type']) || 'alerta';
            const channel = getAiText(item, ['channel']) || 'canal oficial';
            const status = getAiText(item, ['status']) || 'registrado';
            const entityType = getAiText(item, ['entity_type', 'entityType']) || 'fatura';
            const entityId = getAiText(item, ['entity_id', 'entityId']) || 'N/D';
            const count = Number(item?.count || item?.total || 1);
            const latestDate = getAiDate(item);

            if (step.includes('invoice_due') || step.includes('due')) {
                const details = `Foram enviados ${count} lembretes de faturação para ${entityType}${entityId !== 'N/D' ? ` ${entityId}` : ''}, utilizando ${channel.toLowerCase()} com status ${status.toLowerCase()}.`;
                const time = latestDate ? ` O último envio foi registado em ${formatDateTimeToBrazilian(latestDate)}.` : '';
                return `${details}${time} Recomendamos revisão imediata dos documentos em vencimento para evitar impacto financeiro e de relacionamento com os clientes.`;
            }

            return `Foi identificado um alerta relacionado ao processo de ${entityType}. O evento foi registado em ${latestDate ? formatDateTimeToBrazilian(latestDate) : 'data indisponível'} via ${channel.toLowerCase()} e permanece com estado ${status.toLowerCase()}. A revisão deste ponto é recomendada para garantir a continuidade operacional.`;
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
                        channelSet: new Set(),
                        statusSet: new Set(),
                        entityIds: new Set(),
                        latestDate: '',
                        summaryText,
                    });
                }

                const group = grouped.get(key);
                group.count += 1;

                const channel = getAiText(item, ['channel', 'sent_via', 'method']);
                if (channel) group.channelSet.add(channel);

                const status = getAiText(item, ['status']);
                if (status) group.statusSet.add(status);

                const entityId = getAiText(item, ['entity_id', 'entityId']);
                if (entityId && entityId !== 'N/D') group.entityIds.add(entityId);

                const date = getAiDate(item);
                if (date) {
                    const current = new Date(date);
                    const latest = group.latestDate ? new Date(group.latestDate) : null;
                    if (!latest || current > latest) group.latestDate = date;
                }
            });

            return Array.from(grouped.values()).map((group) => ({
                step: group.step,
                count: group.count,
                channels: Array.from(group.channelSet),
                statuses: Array.from(group.statusSet),
                entity_ids: Array.from(group.entityIds),
                createdAt: group.latestDate,
                title: getAiTitle({ response_meta: { ai_analysis: { summary: group.summaryText } } }),
                message: group.summaryText || `Foram registados ${group.count} eventos para ${getAiTitle({ step: group.step }).toLowerCase()}. ${group.channels.length ? `A comunicação foi realizada por ${group.channels.join(', ')}.` : ''} ${group.statuses.length ? `Estado atual: ${group.statuses.join(', ')}.` : ''} ${group.entity_ids.length ? `Faturas envolvidas: ${group.entity_ids.slice(0, 6).join(', ')}${group.entity_ids.length > 6 ? '...' : ''}.` : ''} ${group.latestDate ? `Último registo em ${formatDateTimeToBrazilian(group.latestDate)}.` : ''}`,
            }));
        }

        let isLoadingAIInsights = false;

        async function ajaxLoadAIInsights() {
            if (isLoadingAIInsights) return;
            isLoadingAIInsights = true;

            const btn = document.getElementById('aiAgentBtn');
            const countEl = document.getElementById('aiInsightCount');
            const listEl = document.getElementById('aiInsightList');
            if (!btn || !countEl || !listEl) {
                isLoadingAIInsights = false;
                return;
            }

            try {
                const apiBaseUrl = (() => {
                    const hostname = window.location.hostname;
                    const isProd = hostname === 'api-crm.bxpert.co.ao' || hostname === 'www.api-crm.bxpert.co.ao';
                    if (isProd) {
                        return 'https://api-sandibox.bxpert.co.ao';
                    }
                    return 'http://localhost:3000';
                })();

                const rawInsights = await fetchJSON(`${apiBaseUrl}/api/alert-logs?company_id=${encodeURIComponent(companyId)}&entity_type=invoice`);

                const insights = normalizeAiInsights(rawInsights)
                    .filter((item) => isWithinLastWeek(getAiDate(item)));

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
            } finally {
                isLoadingAIInsights = false;
            }
        }

        // Helper simples para sanitizar texto inserido via innerHTML (mensagens de erro)
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.addEventListener('companies:updated', carregarEmpresas);
            carregarEmpresas();
            ajaxLoadNotifications();
            setInterval(ajaxLoadNotifications, 60000);
        });
    })();
</script>