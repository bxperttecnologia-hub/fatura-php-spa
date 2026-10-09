<style>
    #itemsPage {
        --items-blue: #0d6efd;
        --items-ink: #212529;
        --items-muted: #6b7280;
        --items-line: #e9edf2;
        color: var(--items-ink);
    }

    #itemsPage .items-page-header,
    #itemsPage .items-toolbar,
    #itemsPage .items-selection {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    #itemsPage .items-page-header {
        align-items: flex-start;
        margin-bottom: 16px;
    }

    #itemsPage .items-page-title {
        margin: 0;
        font-size: clamp(1.25rem, 2vw, 1.6rem);
        font-weight: 700;
    }

    #itemsPage .items-page-subtitle {
        margin: 4px 0 0;
        color: var(--items-muted);
        font-size: 0.88rem;
    }

    #itemsPage .items-toolbar {
        align-items: center;
        margin-bottom: 12px;
    }

    #itemsPage .items-search {
        position: relative;
        display: block;
        flex: 1 1 420px;
        max-width: 640px;
        margin: 0;
    }

    #itemsPage .items-search .form-control {
        height: 38px;
        padding-left: 38px;
        border-color: #dce2e9;
        border-radius: 9px;
        font-size: 0.9rem;
    }

    #itemsPage .items-search .bi-search {
        position: absolute;
        top: 50%;
        left: 13px;
        color: var(--items-muted);
        transform: translateY(-50%);
    }

    #itemsPage .items-primary-action {
        min-height: 38px;
        padding: 0 14px;
        border-radius: 9px;
        white-space: nowrap;
    }

    #itemsPage .items-export {
        position: relative;
        flex: 0 0 auto;
    }

    #itemsPage .items-export-toggle {
        display: inline-flex;
        min-width: 116px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        line-height: 1;
    }

    #itemsPage .items-export-menu {
        position: absolute;
        z-index: 1040;
        top: calc(100% + 6px);
        right: 0;
        min-width: 170px;
        padding: 5px;
        border: 1px solid var(--items-line);
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(33, 37, 41, 0.12);
    }

    #itemsPage .items-export-menu[hidden] {
        display: none !important;
    }

    #itemsPage .items-export-menu button {
        display: flex;
        width: 100%;
        align-items: center;
        gap: 9px;
        padding: 8px 10px;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: var(--items-ink);
        text-align: left;
    }

    #itemsPage .items-export-menu button:hover,
    #itemsPage .items-export-menu button:focus-visible {
        background: #f3f6fa;
    }

    #itemsPage .items-selection {
        min-height: 42px;
        margin-bottom: 10px;
        padding: 6px 12px;
        border: 1px solid #dbeafe;
        border-radius: 9px;
        background: #f5f9ff;
        color: #344054;
        font-size: 0.87rem;
    }

    #itemsPage .items-selection[hidden] {
        display: none !important;
    }

    #itemsPage .items-filters {
        display: grid;
        grid-template-columns: repeat(4, minmax(145px, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }

    #itemsPage .items-filter label {
        display: block;
        margin: 0 0 4px;
        color: var(--items-muted);
        font-size: 0.72rem;
        font-weight: 600;
    }

    #itemsPage .items-filter .form-select {
        min-height: 36px;
        padding-top: 5px;
        padding-bottom: 5px;
        border-color: #dce2e9;
        border-radius: 8px;
        font-size: 0.84rem;
    }

    #itemsPage .items-filter-clear {
        grid-column: 1 / -1;
        margin-top: -6px;
        text-align: right;
    }

    #itemsPage .items-filter-clear .btn {
        padding: 0;
        color: var(--items-blue);
        font-size: 0.78rem;
        text-decoration: none;
    }

    #itemsPage .items-selection-count {
        font-weight: 600;
    }

    #itemsPage #deleteSelected {
        min-height: 32px;
        padding: 0 10px;
        border-radius: 8px;
        font-size: 0.84rem;
    }

    #itemsPage .items-table-wrap {
        overflow-x: auto;
        border-radius: 9px;
        -webkit-overflow-scrolling: touch;
    }

    #itemsPage #itemsTable {
        width: 100%;
        min-width: 850px;
        margin: 0;
        border-collapse: separate !important;
        border-spacing: 0 10px;
        background: transparent;
    }

    #itemsPage #itemsTable thead th {
        padding: 9px 12px;
        border: 0 !important;
        border-bottom: 1px solid var(--items-line) !important;
        background: #f8fafc !important;
        color: #737d8c;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.045em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    #itemsPage #itemsTable thead th[data-key] {
        cursor: pointer;
        user-select: none;
    }

    #itemsPage #itemsTable thead th.item-price-heading {
        text-align: left;
    }

    #itemsPage #itemsTable thead th.item-tax-heading,
    #itemsPage #itemsTable thead th.item-actions-heading {
        text-align: left;
    }

    #itemsPage #itemsTable tbody td {
        height: 54px;
        padding: 8px 12px;
        border: 0 !important;
        border-bottom: 0 !important;
        background: #fff !important;
        color: #344054;
        font-size: 0.87rem;
        vertical-align: middle;
    }

    #itemsPage #itemsTable tbody tr:hover td {
        background: #f8fafc !important;
    }

    #itemsPage #itemsTable tbody tr.is-selected td {
        background: #f1f7ff !important;
    }

    #itemsPage #itemsTable .item-select-cell,
    #itemsPage #itemsTable .item-select-heading {
        width: 40px;
        text-align: center;
    }

    #itemsPage #itemsTable .item-icon-cell,
    #itemsPage #itemsTable .item-icon-heading {
        width: 40px;
        text-align: center;
    }

    #itemsPage #itemsTable tbody td:first-child {
        border-top-left-radius: 5px;
        border-bottom-left-radius: 5px;
    }

    #itemsPage #itemsTable tbody td:last-child {
        border-top-right-radius: 5px;
        border-bottom-right-radius: 5px;
    }

    #itemsPage #itemsTable .item-name {
        min-width: 170px;
        color: #202a38;
        font-weight: 600;
        text-align: left !important;
    }

    #itemsPage #itemsTable .item-name small {
        display: block;
        margin-top: 2px;
        color: #8993a1;
        font-size: 0.72rem;
        font-weight: 400;
    }

    #itemsPage #itemsTable .item-description {
        max-width: 280px;
        overflow: hidden;
        color: #667085;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: left !important;
    }

    #itemsPage #itemsTable .item-price,
    #itemsPage #itemsTable .item-pvp,
    #itemsPage #itemsTable .item-tax {
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        text-align: center !important;
    }

    #itemsPage #itemsTable .item-price,
    #itemsPage #itemsTable .item-pvp {
        text-align: left !important;
    }

    #itemsPage #itemsTable .item-price {
        color: #15803d;
        font-weight: 600;
    }

    #itemsPage #itemsTable .item-pvp {
        color: #202a38;
        font-weight: 700;
    }

    #itemsPage #itemsTable .item-tax {
        text-align: left !important;
    }

    #itemsPage #itemsTable .item-actions {
        width: 96px;
        text-align: left !important;
        white-space: nowrap;
    }

    #itemsPage .items-icon-btn {
        display: inline-flex;
        width: 32px;
        height: 32px;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #526071;
    }

    #itemsPage .items-icon-btn:hover {
        background: #eef3f8;
        color: #172033;
    }

    #itemsPage .items-icon-btn.delete-btn:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    #itemsPage .items-icon-btn:focus-visible,
    #itemsPage .items-export-menu button:focus-visible {
        outline: 2px solid var(--items-blue);
        outline-offset: 2px;
    }

    #itemsPage .items-empty {
        padding: 28px 16px !important;
        color: var(--items-muted) !important;
        text-align: center;
    }

    #itemsPage .items-empty button {
        margin-top: 6px;
    }

    #itemsPage .items-pagination {
        margin-top: 12px;
    }

    #itemsPage .modal-content {
        border: 0;
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    }

    #itemsPage .card-header.bg-primary,
    #itemsPage .card-header.bg-info,
    #itemsPage .card-header.bg-secondary,
    #itemsPage .card-header.bg-success,
    #itemsPage .card-header.bg-warning {
        border-radius: 12px 12px 0 0;
        font-size: 0.95rem;
    }

    @media (max-width: 767.98px) {
        #itemsPage .items-page-header {
            align-items: flex-start;
            flex-wrap: wrap;
        }

        #itemsPage .items-page-title {
            max-width: none;
        }

        #itemsPage .items-page-header>.items-primary-action {
            flex: 0 0 auto;
            margin-left: auto;
        }

        #itemsPage .items-toolbar {
            align-items: stretch;
            flex-wrap: wrap;
        }

        #itemsPage .items-search {
            flex: 1 1 100%;
            max-width: none;
        }

        #itemsPage .items-export {
            flex: 1 1 auto;
        }

        #itemsPage .items-filters {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        #itemsPage .items-export>.btn {
            width: 100%;
        }

        #itemsPage .items-primary-action {
            flex: 1 1 auto;
            justify-content: center;
        }

        #itemsPage #itemsTable {
            min-width: 790px;
        }

        #itemsPage .items-selection {
            align-items: flex-start;
        }
    }

    @media (max-width: 420px) {
        #itemsPage .items-filters {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>

<body>
    <main id="itemsPage" class="products-page">
        <div class="container-fluid px-lg-5 px-2 pt-5">
            <div class="items-page-header pt-3">
                <div>
                    <h2 class="items-page-title">Lista de Produtos/Serviços</h2>
                    <p class="items-page-subtitle">Produtos e serviços cadastrados</p>
                </div>
                <button id="newContact" data-bs-toggle="modal" data-bs-target="#itemModal" class="btn btn-primary items-primary-action">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Novo Produto
                </button>
            </div>

            <div class="card p-2 mb-3 border-0 bx-list-filter-panel">
                <div class="items-toolbar">
                    <label class="items-search" for="searchInput">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <input type="search" id="searchInput" class="form-control" placeholder="Pesquisar produtos ou serviços..." autocomplete="off">
                    </label>
                    <div class="items-export">
                        <button type="button" id="itemsExportToggle" class="btn btn-outline-secondary items-primary-action items-export-toggle"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="itemsExportMenu">
                            <i class="bi bi-download" aria-hidden="true"></i><span>Exportar</span>
                            <i class="bi bi-chevron-down small" aria-hidden="true"></i>
                        </button>
                        <div class="items-export-menu" id="itemsExportMenu" role="menu" aria-labelledby="itemsExportToggle" hidden>
                            <button type="button" id="downloadCSV" role="menuitem"><i class="bi bi-filetype-csv text-success" aria-hidden="true"></i> CSV</button>
                            <button type="button" id="downloadExcel" role="menuitem"><i class="bi bi-filetype-xls text-primary" aria-hidden="true"></i> Excel</button>
                            <button type="button" id="downloadPDF" role="menuitem"><i class="bi bi-filetype-pdf text-danger" aria-hidden="true"></i> PDF</button>
                        </div>
                    </div>
                </div>

                <div class="items-filters" aria-label="Filtros de produtos e serviços">
                    <div class="items-filter">
                        <label for="filterOrder">Ordenar por</label>
                        <select id="filterOrder" class="form-select">
                            <option value="newest">Mais recentes</option>
                            <option value="oldest">Mais antigos</option>
                            <option value="name_asc">Nome: A–Z</option>
                            <option value="name_desc">Nome: Z–A</option>
                            <option value="column">Ordenação pela coluna</option>
                        </select>
                    </div>
                    <div class="items-filter">
                        <label for="filterTax">Taxa de IVA</label>
                        <select id="filterTax" class="form-select">
                            <option value="">Todas as taxas</option>
                        </select>
                    </div>
                    <div class="items-filter">
                        <label for="filterItemType">Tipo</label>
                        <select id="filterItemType" class="form-select">
                            <option value="">Produtos e serviços</option>
                        </select>
                    </div>
                    <div class="items-filter">
                        <label for="filterCategory">Categoria</label>
                        <select id="filterCategory" class="form-select">
                            <option value="">Todas as categorias</option>
                        </select>
                    </div>
                    <div class="items-filter-clear">
                        <button type="button" id="clearItemsFilters" class="btn btn-link btn-sm">Limpar filtros</button>
                    </div>
                </div>
            </div>

            <div class="items-selection" id="itemsSelectionBar" aria-live="polite" hidden>
                <span class="items-selection-count" id="itemsSelectionCount">0 selecionados</span>
                <button type="button" id="deleteSelected" class="btn btn-danger">
                    <i class="bi bi-trash" aria-hidden="true"></i> Eliminar selecionados
                </button>
            </div>

            <div class="items-table-wrap">
                <table id="itemsTable" class="table w-100 products-table">
                    <colgroup>
                        <col style="width:40px;">
                        <col style="width:40px;">
                        <col style="width:22%; text-align: left !important;">
                        <col style="width:25%; text-align: left !important;">
                        <col style="width:15%; text-align: left !important;">
                        <col style="width:10%; text-align: left !important;">
                        <col style="width:15%; text-align: left !important;">
                        <col style="width:96px; text-align: left !important;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="item-select-heading">
                                <input type="checkbox" id="selectAll" class="form-check-input" aria-label="Selecionar todos os produtos desta página">
                            </th>
                            <th class="item-icon-heading" aria-label="Tipo"></th>
                            <th data-key="name">Nome <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th data-key="description">Descrição <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="item-price-heading" data-key="unit_price">Preço Unitário <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="item-tax-heading" data-key="tax">Taxa/IVA <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="item-price-heading" data-key="pvp">PVP <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="item-actions-heading">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <div class="items-pagination d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Mostrar</span>
                    <select id="pageSizeSelect" class="form-select form-select-sm" style="width:auto;">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-muted small" id="tableInfo"></span>
                </div>

                <nav>
                    <ul class="pagination pagination-sm mb-0" id="tablePagination"></ul>
                </nav>
            </div>
        </div>

        <?php require_once '../app/models/modal_editItem.php'; ?>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
    <script src="items/items.js?v=0.9" data-spa-repeat></script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>