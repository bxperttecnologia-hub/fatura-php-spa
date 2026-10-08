<?php
require_once '../app/views/layout_creation.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<link rel="stylesheet" href="/assets/css/documents.css?v=1.2">


<body>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalTitle">Confirmar exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    Tem certeza que deseja eliminar?
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger" id="confirmDelete">Eliminar</button>
                </div>

            </div>
        </div>
    </div>


    <div id="preloader" style="display: none; position: fixed; bottom: 20px; right: 20px; width: 200px; padding: 10px; background: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
        <p style="margin: 0; font-size: 14px;">Gerando arquivo...</p>
        <div style="height: 5px; width: 100%; background: #ddd; border-radius: 3px; overflow: hidden; margin-top: 5px;">
            <div id="progressBar" style="height: 100%; width: 0%; background: #007bff;"></div>
        </div>
    </div>

    <main id="bxProformasPage" class="bx-documents-page">
        <div class="container mt-5">
            <div class="bx-page-header bx-list-header">
                <h2 class="bx-page-title"><?= t('Minhas Proformas') ?></h2>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a class="bx-btn-secondary" href="/proformas/create" data-spa>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <span>Nova proforma</span>
                    </a>
                    <div class="bx-export" id="exportMenu">
                        <button type="button" class="bx-btn-primary" id="exportMenuBtn"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="exportMenuList">
                            <i class="bi bi-download" aria-hidden="true"></i>
                            <span>Exportar</span>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>

                        <div class="bx-menu" id="exportMenuList" role="menu" aria-labelledby="exportMenuBtn">
                            <div id="bxExportSelectedGroup" hidden>
                                <div class="bx-menu__label" id="bxExportSelectedLabel">Selecionados</div>
                                <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-pdf">
                                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Exportar seleção em PDF
                                </button>
                                <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-csv">
                                    <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar selecionados (CSV)
                                </button>
                                <div class="bx-menu__divider" role="separator"></div>
                            </div>
                            <div class="bx-menu__label">Exportar lista</div>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="list-pdf">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Exportar PDF
                            </button>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="list-excel">
                                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Exportar Excel
                            </button>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="list-csv">
                                <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <section aria-labelledby="proformaOverviewTitle">
                <h3 class="bx-section-title" id="proformaOverviewTitle">Visão geral</h3>
                <div class="bx-cards">
                    <article class="bx-card"><div class="bx-card__top"><span>Total</span></div><div class="bx-card__value" id="proformaInsightTotal">0</div><div class="bx-card__hint">Proformas registadas</div></article>
                    <article class="bx-card"><div class="bx-card__top"><span>Pendentes</span></div><div class="bx-card__value" id="proformaInsightPending">0</div><div class="bx-card__hint">A aguardar pagamento</div></article>
                    <article class="bx-card"><div class="bx-card__top"><span>Pagas / convertidas</span></div><div class="bx-card__value" id="proformaInsightPaid">0</div><div class="bx-card__hint">Concluídas</div></article>
                    <article class="bx-card"><div class="bx-card__top"><span>Rascunhos</span></div><div class="bx-card__value" id="proformaInsightDrafts">0</div><div class="bx-card__hint">Ainda não emitidas</div></article>
                </div>
            </section>

            <!-- FILTROS -->
            <div class="bx-filters">
                    <div class="bx-filters__grid">

                        <div class="bx-field bx-field--wide">
                            <label for="filterClient">Cliente</label>
                            <input type="text" id="filterClient" class="form-control" placeholder="Pesquisar cliente...">
                        </div>

                        <div class="bx-field">
                            <label for="filterStatus">Status</label>
                            <select id="filterStatus" class="form-select">
                                <option value="">Todos</option>
                                <option value="pendente">Pendente</option>
                                <option value="parcial">Parcial</option>
                                <option value="pago">Pago</option>
                                <option value="convertida">Convertida</option>
                                <option value="rascunho">Rascunho</option>
                            </select>
                        </div>

                        <div class="bx-field">
                            <label for="filterStartDate">Data Inicial</label>
                            <input type="date" id="filterStartDate" class="form-control">
                        </div>

                        <div class="bx-field">
                            <label for="filterEndDate">Data Final</label>
                            <input type="date" id="filterEndDate" class="form-control">
                        </div>

                        <div class="bx-filters__actions">
                            <button id="btnClearFilters" type="button" class="bx-btn-secondary">Limpar filtros</button>
                        </div>

                    </div>
            </div>

            <div class="bx-selbar" id="proformaSelectionBar" aria-live="polite">
                <div class="bx-selbar__info"><span id="proformaSelectionCount">0 selecionadas</span></div>
                <div class="bx-selbar__actions">
                    <button type="button" class="bx-btn-light" data-export-action="selected-pdf">Baixar PDFs</button>
                    <button type="button" class="bx-btn-light" data-export-action="selected-csv">Exportar CSV</button>
                    <button type="button" class="bx-btn-light" id="clearProformaSelection">Limpar seleção</button>
                </div>
            </div>

            <div class="bx-table-wrap table-responsive">
                <table id="invoicesTable" class="table-bx-standard table nowrap w-100 bx-list-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" aria-label="Selecionar todas as proformas visíveis"> <?= t('Status') ?></th>
                            <th data-key="codigo"><?= t('Proforma') ?> <i class="sort-icon bi bi-arrow-down text-muted ms-1"></i></th>
                            <th data-key="cliente"><?= t('Cliente') ?> <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th data-key="issue_date"><?= t('Emissão') ?> <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th data-key="due_date"><?= t('Vencimento') ?> <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th><?= t('Moeda') ?></th>
                            <th data-key="final_total"><?= t('Valor Final') ?> <i class="sort-icon bi bi-arrow-down-up text-muted ms-1"></i></th>
                            <th class="text-end"><?= t('Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dados gerados via JS -->
                    </tbody>
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

                <nav aria-label="Paginação de proformas">
                    <ul class="pagination pagination-sm mb-0" id="tablePagination"></ul>
                </nav>
            </div>
        </div>
        <div id="proforma-container" class="d-none"></div>
    </main>

    <script src="/assets/js/document-tax.js"></script>
    <script src="proform/list_proforms.js?v=2.5"></script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>