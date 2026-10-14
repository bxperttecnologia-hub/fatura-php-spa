<?php
require_once '../app/views/layout_creation.php';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<!-- JSZip: só usado para juntar vários PDFs num único .zip ("Baixar PDFs" da barra de seleção) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<link rel="stylesheet" href="invoices/list_invoices.css?v=2.0">

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

    <main id="bxInvoicesPage">
        <div class="container mt-5">

            <!-- ============ HEADER ============
                 CAUSA DO BUG DO DROPDOWN (versão anterior): o botão #exportMenuBtn estava aqui no
                 cabeçalho, mas o menu (.custom-dropdown, position:relative) estava noutra linha, abaixo
                 do <hr>, e vazio de botão. O menu ancorava-se a esse wrapper (top:100%; left:0) e por isso
                 aparecia no canto esquerdo, por baixo da linha. Agora botão e menu partilham o mesmo
                 wrapper .bx-export (position:relative) e o menu usa right:0. -->
            <div class="bx-page-header">
                <h2 class="bx-page-title"><?= t('Minhas Faturas') ?></h2>

                <div class="bx-export" id="exportMenu">
                    <button type="button" class="bx-btn-primary" id="exportMenuBtn"
                        aria-haspopup="menu" aria-expanded="false" aria-controls="exportMenuList">
                        <i class="bi bi-download" aria-hidden="true"></i>
                        <span>Exportar</span>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>

                    <div class="bx-menu" id="exportMenuList" role="menu" aria-labelledby="exportMenuBtn">

                        <!-- só aparece quando há documentos seleccionados -->
                        <div id="bxExportSelectedGroup" hidden>
                            <div class="bx-menu__label" id="bxExportSelectedLabel">Selecionados</div>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-pdfs">
                                <i class="bi bi-file-earmark-zip" aria-hidden="true"></i> Baixar PDFs selecionados
                            </button>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-excel">
                                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Exportar selecionados (Excel)
                            </button>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-csv">
                                <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar selecionados (CSV)
                            </button>
                            <div class="bx-menu__divider" role="separator"></div>
                        </div>

                        <div class="bx-menu__label" id="bxExportTypeLabel">Exportar lista</div>
                        <button type="button" class="bx-menu__item" role="menuitem" data-export-action="list-pdf">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Exportar PDF
                        </button>
                        <button type="button" class="bx-menu__item" role="menuitem" data-export-action="list-excel">
                            <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Exportar Excel
                        </button>
                        <button type="button" class="bx-menu__item" role="menuitem" data-export-action="list-csv">
                            <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar CSV
                        </button>

                        <div class="bx-menu__divider" role="separator"></div>

                        <div class="bx-menu__label">Relatórios</div>
                        <button type="button" class="bx-menu__item" role="menuitem" data-export-action="report" data-report="sales_report">
                            <i class="bi bi-graph-up" aria-hidden="true"></i> Relatório de vendas
                        </button>
                        <button type="button" class="bx-menu__item" role="menuitem" data-export-action="report" data-report="invoices_paid">
                            <i class="bi bi-check-circle" aria-hidden="true"></i> Faturas pagas
                        </button>
                        <button type="button" class="bx-menu__item" role="menuitem" data-export-action="report" data-report="invoices_pending">
                            <i class="bi bi-hourglass-split" aria-hidden="true"></i> Faturas pendentes
                        </button>
                    </div>
                </div>
            </div>

            <!-- <section id="invoiceCollectionFeature" aria-labelledby="invoiceCollectionTitle"
                data-company-id="<?= (int)($_SESSION['user']['company_id'] ?? 0) ?>">
                <div>
                    <h3 id="invoiceCollectionTitle"><i class="bi bi-stars me-2"></i>Nova cobrança inteligente BXpert</h3>
                    <p>Analise faturas pendentes e execute os alertas pelos canais definidos nas regras de cobrança.</p>
                    <div class="collection-summary" id="invoiceCollectionSummary">A carregar regras de alerta...</div>
                </div>
                <button type="button" class="btn btn-light btn-sm" id="invoiceCollectionBtn">
                    <i class="bi bi-send-check me-1"></i> Executar cobrança
                </button>
            </section> -->

            <!-- ============ VISÃO GERAL ============ -->
            <section id="invoiceInsights" aria-labelledby="bxOverviewTitle">
                <div class="bx-section-title" id="bxOverviewTitle">Visão geral</div>
                <div class="bx-cards">
                    <article class="bx-card">
                        <div class="bx-card__top"><span>Faturas</span><span class="bx-card__icon" aria-hidden="true"><i class="bi bi-receipt"></i></span></div>
                        <div class="bx-card__value" id="insightInvoices">0</div>
                        <div class="bx-card__hint" id="insightInvoicesHint">Documentos emitidos e rascunhos</div>
                    </article>
                    <article class="bx-card">
                        <div class="bx-card__top"><span>Notas de crédito</span><span class="bx-card__icon" aria-hidden="true"><i class="bi bi-arrow-return-left"></i></span></div>
                        <div class="bx-card__value" id="insightCreditNotes">0</div>
                        <div class="bx-card__hint">Abatimentos registados</div>
                    </article>
                    <article class="bx-card">
                        <div class="bx-card__top"><span>Recibos</span><span class="bx-card__icon" aria-hidden="true"><i class="bi bi-file-earmark-check"></i></span></div>
                        <div class="bx-card__value" id="insightReceipts">0</div>
                        <div class="bx-card__hint">Comprovativos de pagamento</div>
                    </article>
                    <article class="bx-card">
                        <div class="bx-card__top"><span>Notas de débito</span><span class="bx-card__icon" aria-hidden="true"><i class="bi bi-file-earmark-plus"></i></span></div>
                        <div class="bx-card__value" id="insightDebitNotes">0</div>
                        <div class="bx-card__hint">Cobranças adicionais emitidas</div>
                    </article>
                </div>
            </section>

            <!-- ============ FILTROS ============ -->
            <section class="bx-filters" aria-labelledby="bxFiltersTitle">
                <div class="bx-section-title" id="bxFiltersTitle">Filtros</div>
                <div class="bx-filters__grid">

                    <div class="bx-field">
                        <label for="filterDocType">Tipo de documento</label>
                        <select id="filterDocType" class="form-select">
                            <option value="invoices">Faturas</option>
                            <option value="credit_notes">Notas de crédito</option>
                            <option value="receipts">Recibos</option>
                            <option value="debit_notes">Notas de débito</option>
                            <option value="delivery_notes">Notas de entrega</option>
                        </select>
                    </div>

                    <div class="bx-field bx-field--wide">
                        <label for="filterClient" id="filterClientLabel">Cliente</label>
                        <input type="search" id="filterClient" class="form-control" placeholder="Pesquisar cliente..." autocomplete="off">
                    </div>

                    <div class="bx-field bx-field--wide" id="filterStatusWrapper">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">Todos</option>
                            <option value="pago">Pago</option>
                            <option value="pendente">Pendente</option>
                            <option value="parcial">Parcial</option>
                            <option value="vencido">Vencido</option>
                            <option value="rascunho">Rascunho</option>
                            <option value="anulado">Anulado</option>
                        </select>
                    </div>

                    <div class="bx-field">
                        <label for="filterStartDate">Data inicial</label>
                        <input type="date" id="filterStartDate" class="form-control">
                    </div>

                    <div class="bx-field">
                        <label for="filterEndDate">Data final</label>
                        <input type="date" id="filterEndDate" class="form-control">
                    </div>

                    <div class="bx-filters__actions bx-field--wide">
                        <button type="button" id="btnApplyFilters" class="bx-btn-primary">
                            <i class="bi bi-funnel" aria-hidden="true"></i> Filtrar
                        </button>
                        <button type="button" id="btnClearFilters" class="bx-btn-secondary">Limpar</button>
                    </div>

                </div>
            </section>

            <!-- ============ BARRA DE SELECÇÃO (só visível com documentos seleccionados) ============ -->
            <div class="bx-selbar" id="bxSelectionBar" role="region" aria-label="Ações para documentos selecionados">
                <div class="bx-selbar__info">
                    <span id="bxSelectionCount" aria-live="polite">0 documentos selecionados</span>
                    <button type="button" class="bx-link-btn" id="bxClearSelection">Limpar seleção</button>
                </div>
                <div class="bx-selbar__actions">
                    <button type="button" class="bx-btn-light" id="bxBulkPdf">
                        <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Baixar PDFs
                    </button>
                    <button type="button" class="bx-btn-light" id="bxBulkExcel">
                        <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel
                    </button>
                    <div class="bx-selbar__more">
                        <button type="button" class="bx-btn-light" id="bxBulkMoreBtn"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="bxBulkMoreMenu">
                            Mais <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="bx-menu" id="bxBulkMoreMenu" role="menu" aria-labelledby="bxBulkMoreBtn">
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-csv">
                                <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar CSV
                            </button>
                            <button type="button" class="bx-menu__item" role="menuitem" data-export-action="selected-list-pdf">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Lista em PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ DOCUMENTOS ============ -->
            <div class="bx-docs-header">
                <h3 class="bx-section-title">Documentos</h3>
                <span class="bx-muted" id="bxResultCount" aria-live="polite"></span>
            </div>

            <div class="bx-table-wrap">
                <div class="table-responsive">
                    <table id="invoicesTable" class="table-bx-standard table nowrap w-100">
                        <thead id="invoicesTableHead">
                            <!-- Cabeçalho é gerado dinamicamente via JS conforme o Tipo de Documento selecionado -->
                        </thead>
                        <tbody>
                            <!-- Dados gerados via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Mostrar</span>
                    <select id="pageSizeSelect" class="form-select form-select-sm" style="width:auto;" aria-label="Documentos por página">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-muted small" id="tableInfo"></span>
                </div>

                <nav aria-label="Paginação de documentos">
                    <ul class="pagination pagination-sm mb-0" id="tablePagination"></ul>
                </nav>
            </div>
        </div>

        <div id="fatura-container" class="d-none"></div>
    </main>

    <!-- Menu "⋮" das linhas: um único elemento no nível do <body>, posicionado por JS.
         Assim nunca é cortado pelo overflow da .table-responsive. -->
    <div class="bx-menu" id="bxRowMenu" role="menu" aria-label="Mais ações"></div>

    <!-- <div class="modal fade" id="collectionModal" tabindex="-1" aria-labelledby="collectionModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="collectionModalTitle"><i class="bi bi-stars me-2"></i>Nova cobrança</h5>
                        <small class="opacity-75">Envie agora ou agende uma cobrança personalizada.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <form id="collectionForm">
                    <div class="modal-body">
                        <div class="collection-tip mb-3"><i class="bi bi-magic me-1"></i> O BXpert aplica as regras de alertas e usa os dados de contacto do cliente no momento do envio.</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Faturas selecionadas</label>
                                <div class="collection-selected" id="collectionSelectedList">Nenhuma fatura selecionada.</div>
                                <input type="hidden" id="collectionInvoiceIds">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Canais</label>
                                <div class="d-flex gap-3 pt-2">
                                    <label><input class="form-check-input me-1" type="checkbox" name="collectionChannels" value="email" checked> Email</label>
                                    <label><input class="form-check-input me-1" type="checkbox" name="collectionChannels" value="whatsapp"> Mensagem</label>
                                </div>
                                <small class="text-muted d-block mt-2">Canais sem contacto válido serão reportados como falha.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="collectionScheduleAt">Enviar em</label>
                                <input class="form-control" type="datetime-local" id="collectionScheduleAt" required>
                                <button type="button" class="btn btn-link btn-sm px-0" id="collectionNowBtn">Enviar imediatamente</button>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="collectionSubject">Assunto do email</label>
                                <input class="form-control" id="collectionSubject" value="Lembrete de pagamento da fatura {{fatura}}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="collectionMessage">Mensagem</label>
                                <textarea class="form-control" id="collectionMessage" rows="5" required>Olá {{cliente}}, identificámos um valor em aberto na fatura {{fatura}}, no montante de {{valor}}, com vencimento em {{vencimento}}. Agradecemos a regularização.</textarea>
                                <small class="text-muted">Variáveis disponíveis: {{cliente}}, {{fatura}}, {{valor}}, {{vencimento}}</small>
                            </div>
                        </div>
                        <div class="alert alert-danger d-none mt-3 mb-0" id="collectionError"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark" id="submitCollectionBtn"><i class="bi bi-send-check me-1"></i> Confirmar cobrança</button>
                    </div>
                </form>
            </div>
        </div>
    </div> -->

    <script src="invoices/list_invoices.js?v=2.0"></script>

    <?php require_once '../app/views/footer.php'; ?>
</body>