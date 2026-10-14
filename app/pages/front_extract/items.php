<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    #itemsTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    #itemsTable thead th {
        border: none;
        font-size: 12px;
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 12px 16px;
        text-align: left;
        border-right: 1px solid #e5e7eb57;
    }

    #itemsTable thead th[data-key] {
        cursor: pointer;
        user-select: none;
    }

    #itemsTable thead th:last-child {
        border-right: none;
    }

    #itemsTable tbody tr td {
        border-right: 1px solid #e5e7eb57;
    }

    #itemsTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }

    #itemsTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    #itemsTable tbody td {
        border: none;
        padding: 18px 16px;
        vertical-align: middle;
        font-size: 0.95rem;
        background: #fff !important;
        text-align: left !important;
    }

    #itemsTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: #fff !important;
        font-weight: 600;
        color: #111;
    }

    #itemsTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    #itemsTable tbody td small {
        display: block;
        color: #6b7280;
    }

    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
    }

    .card-header.bg-primary,
    .card-header.bg-info,
    .card-header.bg-secondary,
    .card-header.bg-success,
    .card-header.bg-warning {
        border-radius: 12px 12px 0 0;
        font-size: 0.95rem;
    }

    .text-truncate-custom {
        max-width: 280px !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    @media (max-width: 768px) {
        #itemsTable tbody td:last-child {
            border-bottom: none;
            flex-direction: row;
            justify-content: flex-end;
            padding-top: 15px;
        }

        #itemsTable tbody td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        #itemsTable tbody td[data-label="Descrição"] {
            font-weight: bold;
            color: #212529;
            font-size: 1.1rem;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 8px;
        }
    }

    #downloadCSV,
    #downloadExcel,
    #downloadPDF,
    #newContact {
        transform: translateY(-1px);
        font-size: 0.9rem;
        padding: 5px 18px !important;
        height: 35px !important;
        margin-top: 10px;
    }

    #downloadCSV,
    #downloadExcel {
        border-radius: 999px;
        font-weight: 500;
    }

    #downloadCSV:hover {
        background: #16a34a;
        color: #fff;
    }

    #downloadExcel:hover {
        background: #2563eb;
        color: #fff;
    }

    #downloadPDF:hover {
        background: #dc2626;
        color: #fff;
    }
</style>

<body>
    <main>
        <div class="container-fluid px-lg-5 px-2 mt-5">
            <div class="d-flex justify-content-between">
                <h2 class="mb-4">Lista de Produtos/Serviços</h2>
                <div class="d-flex flex-wrap gap-2">
                    <button id="downloadCSV" class="btn btn-outline-success rounded-pill d-flex align-items-center gap-2"><i class="bi bi-download align-middle fs-6"></i> Baixar em CSV</button>
                    <button id="downloadExcel" class="btn btn-outline-primary rounded-pill d-flex align-items-center gap-2"><i class="bi bi-filetype-xls align-middle fs-6"></i> Baixar em Excel</button>
                    <button id="downloadPDF" class="btn btn-outline-danger rounded-pill d-flex align-items-center gap-2"><i class="bi bi-filetype-pdf align-middle fs-6"></i> Baixar em PDF</button>
                    <button id="newContact" data-bs-toggle="modal" data-bs-target="#itemModal" class="btn btn-primary d-flex align-items-center gap-2"><i class="bi bi-plus-circle align-middle fs-6"></i> Novo Produto</button>
                </div>
            </div>

            <div class="row g-2 mb-3 align-items-center">
                <div class="col-md-8">
                    <div class="pt-4 rounded-3 d-flex justify-content-between align-items-center">
                        <button id="deleteSelected" class="btn btn-danger rounded-pill d-flex align-items-center gap-2"><i class="bi bi-trash align-middle fs-6"></i>Eliminar selecionados</button>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="searchInput" class="form-control ps-5" placeholder="Pesquisar produtos ou serviços...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="itemsTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th style="width:40px;"></th>
                            <th data-key="code">Código <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th data-key="name">Nome <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th data-key="description">Descrição <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="text-end" data-key="unit_price">Preço Unitário <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="text-center" data-key="tax">Taxa/IVA <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="text-end" data-key="pvp">PVP <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="text-center" style="width:120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
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
    <script src="items/items.js?v=0.6"></script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>