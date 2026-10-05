<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    #contactModal .modal-dialog {
        max-width: 1080px;
    }

    #contactModal .modal-content {
        overflow: hidden;
        border: 0;
        border-radius: 22px;
        background: #f8fafc !important;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.24);
    }

    #contactModal .modal-header {
        padding: 22px 28px;
        background: linear-gradient(135deg, #007abd 0%, #2563eb 100%);
    }

    #contactModal .modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    #contactModal .modal-body {
        padding: 28px;
    }

    #contactModal .card-clean {
        height: 100%;
        padding: 20px;
        border: 1px solid #e2e8f0;
        border-left: 4px solid #007abd !important;
        border-radius: 16px;
        background: #fff !important;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
    }

    #contactModal .section-title-modal {
        margin: 0 0 18px;
        padding-bottom: 12px;
        border-bottom: 1px solid #eef2f7;
        color: #172033;
        font-size: 1rem;
        font-weight: 700;
    }

    #contactModal .section-title-modal i {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border-radius: 10px;
        background: #eaf3ff;
        color: #1671c9;
    }

    #contactModal .label {
        margin-bottom: 3px;
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    #contactModal .value {
        min-height: 20px;
        margin-bottom: 12px;
        color: #172033;
        font-size: .94rem;
        font-weight: 500;
        overflow-wrap: anywhere;
    }

    #contactModal .phone-badge {
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    #contactModal .btn-close {
        filter: brightness(0) invert(1);
        opacity: .9;
    }

    /* ===== HEADER ===== */

    .container {
        margin-top: 80px !important;
    }

    /* ===== CARD ===== */
    .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
    }

    /* HEADER DO CARD */
    .card-header {
        background: transparent !important;
        border-bottom: none;
        padding: 20px;
    }

    .card-header .btn {
        border-radius: 999px;
        font-weight: 500;
    }

    /* ===== MODAL MAIS PREMIUM ===== */
    .contacts-page .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    }

    /* ===== CARDS DO MODAL ===== */
    .card-header.bg-primary,
    .card-header.bg-info,
    .card-header.bg-secondary,
    .card-header.bg-success,
    .card-header.bg-warning {
        border-radius: 12px 12px 0 0;
        font-size: 0.95rem;
    }

    /* ===== PHONE CARDS ===== */
    .phone-card {
        display: inline-flex;
        align-items: center;
        background: #f3f4f6;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .phone-card:hover {
        background: #e5e7eb;
    }

    /* ===== MODAL HEADER ===== */
    .modal-header {
        background: linear-gradient(135deg, #007abd, #00c6ff);
        color: #fff;
        border: none;
    }

    .modal-title {
        font-weight: 600;
    }

    .modal-content {
        background: #ffff !important;
    }

    /* ===== CARD CLEAN ===== */
    .card-clean {
        background: none !important;
        border-radius: 14px;
        padding: 18px;
        /* box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05); */
        transition: 0.2s;
        border: 1px solid #e5e7eb;
        border-left: 4px solid #007abd !important;
    }

    .card-clean:hover {
        transform: translateY(-2px);
    }

    /* ===== CORES POR CARD ===== */
    .card-clean.primary {
        border-left-color: #6a5cff;
    }

    .card-clean.info {
        border-left-color: #00c6ff;
    }

    .card-clean.success {
        border-left-color: #10b981;
    }

    .card-clean.danger {
        border-left-color: #f43f5e;
    }

    /* ===== TITULO ===== */
    .section-title-modal {
        font-weight: 600;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #111827;
        margin-left: -10px;
    }

    .section-title-modal i {
        background: #eef2ff;
        color: #6a5cff;
        padding: 8px;
        border-radius: 10px;
        font-size: 18px;
    }

    /* ===== LABEL / VALUE ===== */
    .label {
        font-size: 12px;
        color: #6b7280;
    }

    .value {
        font-size: 14px;
        font-weight: 500;
        color: #111827;
        margin-bottom: 8px;
    }

    /* ===== PHONE BADGE ===== */
    .phone-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #eef6ff;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 13px;
        color: #1d4ed8;
        font-weight: 500;
    }

    .phone-badge i {
        font-size: 16px;
    }

    /* ===== RESPONSIVO ===== */
    @media (max-width: 768px) {
        .modal-dialog {
            margin: 10px;
        }
    }

    /* Modal de contacto: cartão compacto e minimalista */
    #contactModal .modal-dialog {
        max-width: 760px;
    }

    #contactModal .modal-content {
        border-radius: 18px;
        background: #fff !important;
        box-shadow: 0 20px 55px rgba(15, 23, 42, .2);
    }

    #contactModal .modal-header {
        min-height: 64px;
        padding: 16px 22px;
        background: linear-gradient(135deg, #007abd, #2563eb);
    }

    #contactModal .modal-title {
        font-size: 1.05rem;
        letter-spacing: -.01em;
    }

    #contactModal .modal-body {
        max-height: min(620px, calc(100vh - 150px));
        padding: 18px;
        background: #f8fafc;
    }

    #contactModal .modal-body>.container-fluid {
        padding: 0;
    }

    #contactModal .modal-body .row {
        --bs-gutter-x: 10px;
        --bs-gutter-y: 10px;
    }

    #contactModal .card-clean {
        height: auto;
        margin-bottom: 0 !important;
        padding: 14px 16px;
        border: 1px solid #e8edf4;
        border-left: 3px solid #60a5fa !important;
        border-radius: 13px;
        box-shadow: none;
    }

    #contactModal .section-title-modal {
        gap: 8px;
        margin: 0 0 12px;
        padding: 0 0 9px;
        border-bottom: 1px solid #eef2f7;
        font-size: .86rem;
    }

    #contactModal .section-title-modal i {
        width: 26px;
        height: 26px;
        font-size: .82rem;
        border-radius: 8px;
    }

    #contactModal .card-clean .mb-2 {
        margin-bottom: 8px !important;
    }

    #contactModal .label {
        margin-bottom: 1px;
        font-size: .64rem;
        letter-spacing: .06em;
    }

    #contactModal .value {
        min-height: 17px;
        margin-bottom: 0;
        font-size: .84rem;
        line-height: 1.35;
    }

    #contactModal .phone-badge {
        padding: 6px 10px;
        font-size: .78rem;
    }

    #contactModal .phone-badge i {
        font-size: .82rem;
    }

    @media (max-width: 768px) {
        #contactModal .modal-dialog {
            margin: .5rem;
        }

        #contactModal .modal-body {
            padding: 12px;
        }
    }
</style>
<link rel="stylesheet" href="contacts/contacts.css?v=1.0">

<body>

    <?php require_once __DIR__ . '/contacts/partials/contact_form_modal.php'; ?>

    <div class="modal fade" id="contactModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-person-vcard"></i>
                        Detalhes do Contato
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="container-fluid">
                        <div class="row g-4">

                            <!-- ESQUERDA -->
                            <div class="col-lg-6">

                                <!-- Empresa -->
                                <div class="card-clean mb-3">
                                    <div class="section-title-modal">
                                        <i class="bi bi-building"></i>
                                        Empresa
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Nome</div>
                                        <div class="value" id="contactName"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Tipo</div>
                                        <div class="value" id="contactType"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">NIF</div>
                                        <div class="value" id="contactContributor"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Email</div>
                                        <div class="value" id="contactEmail"></div>
                                    </div>
                                </div>

                                <!-- Contatos -->
                                <div class="card-clean mb-3">
                                    <div class="section-title-modal">
                                        <i class="bi bi-telephone"></i>
                                        Contatos
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <div class="phone-badge">
                                            <i class="bi bi-telephone"></i>
                                            <span id="contactTelephone"></span>
                                        </div>

                                        <div class="phone-badge">
                                            <i class="bi bi-phone"></i>
                                            <span id="contactCellphone"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Localização -->
                                <div class="card-clean">
                                    <div class="section-title-modal">
                                        <i class="bi bi-geo-alt"></i>
                                        Localização
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Endereço</div>
                                        <div class="value" id="contactAddress"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Cidade</div>
                                        <div class="value" id="contactLocation"></div>
                                    </div>
                                </div>

                            </div>

                            <!-- DIREITA -->
                            <div class="col-lg-6">

                                <!-- Contato principal -->
                                <div class="card-clean mb-3">
                                    <div class="section-title-modal">
                                        <i class="bi bi-person"></i>
                                        Contato Principal
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Nome</div>
                                        <div class="value" id="contactPrefName"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Email</div>
                                        <div class="value" id="contactPrefEmail"></div>
                                    </div>
                                </div>

                                <!-- Configurações -->
                                <div class="card-clean">
                                    <div class="section-title-modal">
                                        <i class="bi bi-gear"></i>
                                        Configurações
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Pagamento</div>
                                        <div class="value" id="contactPaymentMethod"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Moeda</div>
                                        <div class="value" id="contactCurrency"></div>
                                    </div>

                                    <div class="mb-2">
                                        <div class="label">Atualizado</div>
                                        <div class="value" id="contactUpdatedAt"></div>
                                    </div>
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <main class="contacts-page" id="contactsApp">
        <div class="container mt-5">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><?= t('Meus Clientes') ?></h2>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" id="newContact" class="btn btn-primary d-flex align-items-center rounded-pill gap-2 p-4"><i class="bi bi-plus-circle align-middle fs-6"></i> <?= t('Novo Cliente') ?></button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="cp-tabs" role="tablist" aria-label="Estado dos clientes">
                <button type="button" role="tab" id="cpTab-active" class="cp-tab" data-tab="active" aria-selected="true" aria-controls="clientsPanel">
                    <i class="bi bi-people" aria-hidden="true"></i>Ativos <span class="cp-count" aria-label="total">–</span>
                </button>
                <button type="button" role="tab" id="cpTab-archived" class="cp-tab" data-tab="archived" aria-selected="false" aria-controls="clientsPanel" tabindex="-1">
                    <i class="bi bi-archive" aria-hidden="true"></i>Arquivados <span class="cp-count" aria-label="total">–</span>
                </button>
            </div>

            <section class="" aria-label="Lista de clientes">

                <!-- Toolbar -->
                <div class="cp-toolbar">
                    <div class="cp-toolbar-left">
                        <form class="cp-search" role="search" onsubmit="return false;">
                            <label for="cpSearch" class="visually-hidden-cp">Pesquisar clientes</label>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" id="cpSearch" placeholder="Pesquisar clientes..." autocomplete="off" aria-keyshortcuts="Control+K Meta+K" aria-describedby="cpSearchHint">
                            <span id="cpSearchHint" class="visually-hidden-cp">Pesquisa por nome, email, telefone, país ou cidade. Atalho: Ctrl mais K.</span>
                            <kbd aria-hidden="true">Ctrl K</kbd>
                            <button type="button" class="cp-btn cp-btn-icon cp-search-clear" aria-label="Limpar pesquisa"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </form>

                        <!-- Filtros -->
                        <div class="cp-pop">
                            <button type="button" id="cpFilterBtn" class="cp-btn cp-btn-ghost" data-pop aria-haspopup="dialog" aria-expanded="false" aria-controls="cpFilterPanel" aria-label="Filtros">
                                <i class="bi bi-sliders" aria-hidden="true"></i><span>Filtros</span><span class="cp-badge-dot" aria-hidden="true" hidden>0</span>
                            </button>
                            <div class="cp-pop-panel cp-filter-panel" id="cpFilterPanel" role="dialog" aria-label="Filtros de clientes">
                                <label for="cpFilterCountry">País</label>
                                <select id="cpFilterCountry"></select>
                                <label for="cpFilterCity">Cidade</label>
                                <select id="cpFilterCity"></select>
                                <label class="cp-check"><input type="checkbox" id="cpFilterPhone"> Clientes com telefone</label>
                                <label class="cp-check"><input type="checkbox" id="cpFilterEmail"> Clientes com email</label>
                                <div class="cp-filter-foot">
                                    <button type="button" class="cp-btn cp-btn-ghost" data-filters="clear">Limpar filtros</button>
                                    <button type="button" class="cp-btn cp-btn-primary" data-filters="close">Concluir</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="cp-toolbar-right">
                        <!-- ViewSwitcher -->
                        <div class="cp-segmented" role="group" aria-label="Forma de visualização">
                            <button type="button" class="cp-btn" data-view="cards" aria-pressed="true"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i><span>Blocos</span></button>
                            <button type="button" class="cp-btn" data-view="list" aria-pressed="false"><i class="bi bi-list-ul" aria-hidden="true"></i><span>Lista</span></button>
                        </div>

                        <!-- Exportar -->
                        <div class="cp-pop">
                            <button type="button" id="cpExportBtn" class="cp-btn cp-btn-ghost" data-pop aria-haspopup="menu" aria-expanded="false" aria-controls="cpExportMenu" aria-busy="false">
                                <span class="cp-export-icon"><i class="bi bi-download" aria-hidden="true"></i></span><span class="cp-export-text">Exportar</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                            <div class="cp-pop-panel is-end" id="cpExportMenu" role="menu" aria-label="Exportar clientes">
                                <div class="cp-menu-title" aria-hidden="true">Exportar clientes</div>
                                <div class="cp-menu-sep" role="separator"></div>
                                <button type="button" role="menuitem" class="cp-menuitem" data-export="csv"><i class="bi bi-download" aria-hidden="true"></i><?= t('Baixar em CSV') ?></button>
                                <button type="button" role="menuitem" class="cp-menuitem" data-export="excel"><i class="bi bi-filetype-xls" aria-hidden="true"></i><?= t('Baixar em Excel') ?></button>
                                <button type="button" role="menuitem" class="cp-menuitem" data-export="pdf"><i class="bi bi-filetype-pdf" aria-hidden="true"></i><?= t('Baixar em PDF') ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros ativos -->
                <div class="cp-chips" id="cpChips" hidden></div>

                <!-- Barra contextual de seleção (só aparece com seleção) -->
                <div class="cp-bulkbar" id="cpBulkbar" role="region" aria-label="Ações para clientes selecionados" hidden>
                    <span class="cp-bulk-count" aria-live="polite"></span>
                    <button type="button" class="cp-linkbtn" data-bulk="all">Selecionar todos</button>
                    <button type="button" class="cp-linkbtn" data-bulk="none">Limpar seleção</button>
                    <button type="button" class="cp-btn cp-btn-ghost" data-bulk="status"></button>
                    <button type="button" class="cp-btn cp-btn-ghost" data-bulk="export"><i class="bi bi-download" aria-hidden="true"></i>Exportar</button>
                    <button type="button" class="cp-btn cp-btn-danger" data-bulk="delete"><i class="bi bi-trash" aria-hidden="true"></i>Excluir</button>
                </div>

                <!-- Conteúdo: Lista ou Blocos (mesma fonte de dados) -->
                <div class="cp-body" id="clientsPanel" role="tabpanel" aria-labelledby="cpTab-active" aria-busy="true">
                    <div id="clientsView"></div>
                </div>

                <!-- Paginação -->
                <div class="cp-footer" id="clientsFooter" hidden></div>
            </section>
        </div>

        <!-- Anúncios para leitores de ecrã e notificações -->
        <div id="cpLive" class="visually-hidden-cp" aria-live="polite" aria-atomic="true"></div>
        <div id="cpToasts" class="cp-toasts" role="region" aria-label="Notificações"></div>
    </main>


    <script src="contacts/contacts.js?v=1.0"></script>
    <script src="contacts/register_contact.js?v=1.0"></script>
    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>