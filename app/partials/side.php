<style>
    :root {
        --sidebar-bg: #007abd;
        --hover-bg: rgba(255, 255, 255, 0.12);
        --active-bg: rgba(255, 255, 255, 0.18);
        --text: #ffffff;
        --muted: rgba(255, 255, 255, 0.75);
        --border: rgba(255, 255, 255, 0.15);
    }

    /* SIDEBAR */
    .sidebar {
        width: 260px;
        left: 0px;
        transition: all ease-in-out 0.3s;
        background: var(--sidebar-bg);
        height: 100vh;
        display: flex;
        flex-direction: column;
        border-right: 1px solid var(--border);
        position: fixed;
        z-index: 999;
    }

    .sidebar.mobile-active {
        left: 0px;
    }

    /* Ajusta o conteúdo ao lado da sidebar */
    main {
        flex-grow: 1;
        margin-left: 250px;
        padding: 20px;
        width: calc(100% - 250px);
        transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        padding-bottom: 2rem;
    }

    /* Garante que a navbar ocupe a tela corretamente */
    header {
        width: calc(100% - 250px);
        padding: 10px 20px;
        position: fixed;
        top: 0;
        left: 250px;
        right: 0;
        z-index: 1000;
        transition:
            left 0.3s ease-in-out,
            width 0.3s ease-in-out;
    }

    .brand {
        padding: 14px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .brand img {
        width: 120px;
        transition: opacity 0.2s ease-in-out;
    }

    /* BOTÃO TOGGLE */
    .sidebar-toggle-btn {
        background: transparent;
        border: none;
        color: var(--text);
        cursor: pointer;
        padding: 6px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background .2s;
    }

    .sidebar-toggle-btn:hover {
        background: var(--hover-bg);
    }

    .sidebar-toggle-btn i {
        width: 20px;
        height: 20px;
    }

    .nav-section {
        padding: 10px;
        overflow-y: auto;
        overflow-x: hidden;
        flex: 1;
    }

    /* SCROLL */
    .nav-section::-webkit-scrollbar {
        width: 6px;
    }

    .nav-section::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.25);
        border-radius: 10px;
    }

    .nav-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        color: var(--muted);
        text-decoration: none;
        font-weight: 500;
        transition: .2s;
        cursor: pointer;
        width: 100%;
        border: none;
        background: transparent;
        position: relative;
        white-space: nowrap;
    }

    .nav-item span {
        display: flex;
        align-items: center;
        gap: 10px;
        color: inherit;
    }

    .nav-item i {
        color: inherit;
        flex-shrink: 0;
    }

    .nav-item:hover {
        background: var(--hover-bg);
        color: var(--text);
        transform: translateX(2px);
    }

    .nav-item.active {
        background: var(--active-bg);
        color: var(--text);
        font-weight: 600;
    }

    .nav-item.active::before {
        content: "";
        position: absolute;
        left: 0;
        top: 15%;
        height: 70%;
        width: 3px;
        background: #ffffff;
        border-radius: 3px;
    }

    .nav-item.active i {
        color: #ffffff;
    }

    .nav-icon {
        width: 18px;
        height: 18px;
    }

    .section-title {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--muted);
        padding: 12px 12px 6px;
        font-weight: bold;
        margin-top: 10px;
        white-space: nowrap;
        overflow: hidden;
    }

    .submenu {
        overflow: hidden;
        max-height: 0;
        display: block;
        opacity: 0;
        transform: translateY(15px);
        filter: blur(4px);
        pointer-events: none;
        transition:
            max-height 0.4s ease,
            opacity 0.4s ease,
            transform 0.4s ease,
            filter 0.4s ease;
        padding-left: 12px;
    }

    .submenu.open {
        max-height: 500px;
        opacity: 1;
        transform: translateY(0);
        filter: blur(0);
        pointer-events: auto;
    }

    .submenu a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 8px;
        color: var(--muted);
        text-decoration: none;
        font-size: 14px;
        transition: .2s;
        white-space: nowrap;
    }

    .submenu a:hover {
        background: var(--hover-bg);
        color: var(--text);
    }

    .nav-section .submenu svg {
        width: 15px !important;
    }

    .nav-item i:last-child {
        transition: transform .3s;
    }

    .nav-item.active i:last-child {
        transform: rotate(180deg);
    }

    .footer {
        padding: 12px;
    }

    .footer .above {
        border-top: 1px solid var(--border);
    }

    .bg-plan {
        background: rgba(255, 255, 255, 0.15) !important;
    }

    /* ESTADO COLAPSADO (desktop) */
    .sidebar.collapsed {
        width: 76px;
    }

    .sidebar.collapsed .brand {
        justify-content: center;
        padding: 14px 8px;
    }

    .sidebar.collapsed .brand img {
        display: none;
    }

    .sidebar.collapsed .section-title,
    .sidebar.collapsed .nav-item span span,
    .sidebar.collapsed .submenu,
    .sidebar.collapsed .footer .above {
        display: none;
    }

    .sidebar.collapsed .nav-item span {
        gap: 0;
    }

    .sidebar.collapsed .nav-item {
        justify-content: center;
        padding: 10px;
    }

    .sidebar.collapsed .nav-item i:last-child {
        display: none;
    }

    .sidebar.collapsed .footer .nav-item span {
        justify-content: center;
    }

    main.collapsed {
        margin-left: 76px;
        width: calc(100% - 76px);
    }

    header.collapsed {
        left: 76px;
        width: calc(100% - 76px);
    }

    .sidebar.collapsed .nav-item {
        position: relative;
    }

    .sidebar.collapsed .nav-item:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 10px;
        background: #1e1e1e;
        color: #fff;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 1100;
        pointer-events: none;
    }

    @media (max-width: 992px) {

        .sidebar {
            width: min(80vw, 280px);
            transform: translateX(-100%);
            transition: transform .3s ease;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        /* A sidebar fica fixa por cima do conteúdo (sem overlay/backdrop);
           o conteúdo ocupa sempre 100% da largura em mobile/tablet */
        main,
        header {
            margin-left: 0 !important;
            left: 0 !important;
            width: 100% !important;
        }
    }

    /* Trava o scroll do fundo da página enquanto o menu mobile está aberto
       (sem escurecer nem cobrir o conteúdo com nenhuma camada extra) */
    body.menu-open {
        overflow: hidden;
    }
</style>

<!-- Estilos do painel lateral de itens (necessário em todas as páginas onde a sidebar aparece) -->
<link rel="stylesheet" href="/items/item_drawer.css?v=1.1">

<div class="sidebar" id="sidebar">
    <div class="brand">
        <a href="/" data-spa>
            <img src="/assets/img/logo/BXpert2-Branca.png" alt="logo">
        </a>
        <button id="sidebarToggle" class="sidebar-toggle-btn" title="Ocultar/Mostrar menu">
            <i data-lucide="panel-left-close"></i>
        </button>
    </div>
    <div class="nav-section">
        <br>

        <a href="/" class="nav-item" data-spa data-tooltip="Painel de controle">
            <span><i data-lucide="layout-dashboard" class="nav-icon"></i> <span>Painel de controle</span></span>
        </a>

        <div class="section-title">Gestão</div>

        <button class="nav-item" data-submenu="#clientes" data-tooltip="Empresa/Cliente">
            <span><i data-lucide="building-2"></i> <span>Empresa/Cliente</span></span>
            <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
        </button>
        <div class="submenu" id="clientes">
            <a href="/contacts/create" data-spa><i data-lucide="plus"></i> Adicionar Cliente/Empresa</a>
            <a href="/contacts" data-spa><i data-lucide="list"></i> Lista de Clientes/Empresas</a>
        </div>

        <button class="nav-item" data-submenu="#produtos" data-tooltip="Produtos/Serviços">
            <span><i data-lucide="package"></i> <span>Produtos/Serviços</span></span>
            <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
        </button>
        <div class="submenu" id="produtos">
            <!-- sem id="newContact": esse id já existe no botão "Novo item" do items.php -->
            <a href="items.php" data-drawer-open="create">
                <i class="text-white" data-lucide="plus"></i> Adicionar
            </a>
            <a href="/items" data-spa> <i data-lucide="list"></i> Meus Itens </a>
        </div>

        <div class="section-title">Operações</div>

        <button class="nav-item" data-submenu="#vendas" data-tooltip="Vendas">
            <span><i data-lucide="receipt"></i> <span>Vendas</span></span>
            <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
        </button>

        <div class="submenu" id="vendas">

            <button class="nav-item" data-submenu="#proformas">
                <span><i data-lucide="file-text"></i> Proformas</span>
                <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
            </button>
            <div class="submenu" id="proformas">
                <a href="/proformas/create" data-spa><i data-lucide="plus"></i> Emitir</a>
                <a href="/proformas" data-spa><i data-lucide="list"></i> Listar</a>
            </div>

            <button class="nav-item" data-submenu="#facturas">
                <span><i data-lucide="file-check"></i> Facturas</span>
                <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
            </button>
            <div class="submenu" id="facturas">
                <a href="/invoices/create" data-spa><i data-lucide="plus"></i> Emitir</a>
                <a href="/invoices" data-spa><i data-lucide="list"></i> Listar</a>
            </div>

        </div>

        <button class="nav-item d-none" data-submenu="#stock" data-tooltip="Stock">
            <span><i data-lucide="boxes"></i> <span>Stock</span></span>
            <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
        </button>
        <div class="submenu d-none" id="stock">
            <a href="/stock" data-spa><i data-lucide="database"></i> Inventário</a>
            <a href="purchases.php"><i data-lucide="shopping-cart"></i> Compras</a>
        </div>

        <button class="nav-item submenu-rh" data-submenu="#rh" data-tooltip="Recursos Humanos">
            <span><i data-lucide="users"></i> <span>Recursos Humanos</span></span>
            <i class="text-white menu-link-icon" data-lucide="chevron-down"></i>
        </button>
        <div class="submenu submenu-rh" id="rh">
            <a href="/departments" data-spa><i data-lucide="building-2"></i> Departamentos</a>
            <a href="/employees" data-spa><i data-lucide="users"></i> Funcionários</a>
            <a href="/ponto" data-spa><i data-lucide="clock"></i> Registro de Pontos</a>
            <a href="/vacations" data-spa><i data-lucide="calendar"></i> Férias / Licenças</a>
            <a href="/positions" data-spa><i data-lucide="briefcase"></i> Cargos / Salários</a>
            <a href="/payroll" data-spa><i data-lucide="file-text"></i> Folha de Pagamento</a>
        </div>

    </div>

    <div class="footer">

        <a href="/subscription" class="nav-item" data-spa data-tooltip="Definições">
            <span><i data-lucide="settings"></i> <span>Definições</span></span>
        </a>

        <a href="help.php" target="_blank" class="nav-item" data-tooltip="Ajuda">
            <span><i data-lucide="help-circle"></i> <span>Ajuda</span></span>
        </a>

        <div class="col-12 above">
            <div class="card border-0 rounded-xl bg-plan p-3">
                <div class="d-flex justify-content-between align-items-center align-content-center flex-wrap gap-3 mb-2">
                    <div>
                        <h5 class="mb-1 fw-bolder text-white" style="font-size: 12pt;">Plano &nbsp;</h5>
                        <div id="subInfo" class="text-white" style="font-size:0.85rem;">Carregando...</div>
                    </div>
                    <a href="/subscription" class="btn bg-light text-dark w-100 d-flex align-items-center justify-content-center gap-2 py-2" data-spa>
                        Renovar
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- items.js NÃO vai aqui: é só da página items.php (chama loadItems() ao arrancar) -->

<script>
    window.lucide && lucide.createIcons();

    $(document).ready(function() {

        // Accordion dos submenus (responsabilidade exclusiva da sidebar)
        document.querySelectorAll('.nav-item[data-submenu]').forEach(button => {
            button.addEventListener('click', e => {
                e.stopPropagation();

                const submenu = document.querySelector(button.dataset.submenu);
                if (!submenu) return;

                const isOpen = submenu.classList.contains('open');

                const parent = button.parentElement;
                Array.from(parent.children).forEach(el => {
                    if (el.classList.contains('submenu') && el !== submenu) {
                        el.classList.remove('open');
                    }
                    if (el.classList.contains('nav-item') && el !== button) {
                        el.classList.remove('active');
                    }
                });

                if (isOpen) {
                    submenu.classList.remove('open');
                    button.classList.remove('active');

                } else {
                    submenu.classList.add('open');
                    button.classList.add('active');
                }
            });
        });

        document.addEventListener('click', e => {
            document.querySelectorAll('.submenu').forEach(sm => sm.classList.remove('open'));
            document.querySelectorAll('.nav-item.active').forEach(btn => btn.classList.remove('active'));
        });

    }); // era "})();" -> chamava o resultado do ready() como função e dava TypeError
</script>
