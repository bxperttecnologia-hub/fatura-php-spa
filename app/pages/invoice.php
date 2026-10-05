<?php
require_once '../app/views/layout_creation.php';
?>
<style>
    :root {
        --invoice-surface: #ffffff;
        --invoice-bg: #f4f7fb;
        --invoice-border: #e5e7eb;
        --invoice-muted: #667085;
        --invoice-text: #111827;
        --invoice-subtle: #475467;
        --invoice-primary: #0f172a;
        --invoice-primary-strong: #0b1220;
        --invoice-secondary: #f8fafc;
        --invoice-secondary-border: #d9e0ea;
        --invoice-focus: #2563eb;
        --invoice-focus-soft: rgba(37, 99, 235, 0.18);
        --invoice-shadow: 0 16px 32px rgba(15, 23, 42, 0.08);
    }

    body {
        background: var(--invoice-bg);
    }

    .invoice-shell {
        max-width: 1240px;
        margin: 2.5rem auto 0;
        padding: 0 1rem 2rem;
    }

    /* ===== layout: fatura à esquerda, card de ações à direita ===== */
    .invoice-layout {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        gap: 1.25rem;
    }

    .invoice-preview-panel {
        flex: 0 1 210mm;
        min-width: 0;
    }

    /* div que mostra o layout da fatura: uma página A4; o conteúdo rola dentro dela */
    #fatura-container {
        width: 210mm;
        max-width: 100%;
        margin: 0 auto;
        height: 297mm;
        max-height: calc(100vh - 2rem);
        overflow-x: hidden;
        overflow-y: scroll;
    }

    /* ===== card lateral ===== */
    .invoice-side-card {
        flex: 0 0 300px;
        width: 300px;
        background: var(--invoice-surface);
        border: 1px solid var(--invoice-border);
        border-radius: 16px;
        padding: 1.25rem;
        box-shadow: var(--invoice-shadow);
        color: var(--invoice-text);
        margin-top: 90px;
    }

    .invoice-header__eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--invoice-muted);
        margin-bottom: .35rem;
    }

    .invoice-header__title {
        margin: 0;
        font-size: clamp(1.35rem, 2vw, 1.7rem);
        line-height: 1.2;
        font-weight: 700;
        color: var(--invoice-text);
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .6rem;
    }

    .invoice-header__title strong {
        font-weight: 700;
    }

    .invoice-header__subtitle {
        display: block;
        margin-top: .35rem;
        color: var(--invoice-subtle);
        font-size: .82rem;
        font-weight: 500;
    }

    .invoice-header__meta {
        margin: .4rem 0 0;
        color: var(--invoice-muted);
        font-size: .8rem;
        font-weight: 600;
    }

    #status-invoice {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: .45rem .8rem;
        font-size: .7rem;
        line-height: 1;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        background: #eef2ff;
        color: #3730a3;
        border: 1px solid rgba(55, 48, 163, 0.18);
        min-width: 90px;
    }

    .invoice-status-badge.is-draft {
        background: #f3f4f6;
        color: #374151;
        border-color: rgba(55, 65, 81, 0.14);
    }

    .invoice-toolbar {
        display: flex;
        flex-direction: column;
        gap: .6rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--invoice-border);
    }

    .invoice-toolbar .btn-invoice {
        width: 100%;
    }

    .invoice-actions {
        display: flex;
        flex-direction: column;
        gap: .15rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--invoice-border);
    }

    .invoice-actions .dropdown-item {
        min-height: 40px;
    }

    .btn-invoice {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        min-height: 44px;
        padding: .7rem 1rem;
        border-radius: 12px;
        font-size: .92rem;
        font-weight: 600;
        line-height: 1.1;
        border: 1px solid transparent;
        transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease, border-color .15s ease;
        cursor: pointer;
        text-decoration: none;
    }

    .btn-invoice:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
    }

    .btn-invoice:focus-visible,
    .dropdown-toggle:focus-visible,
    .dropdown-item:focus-visible,
    .btn:focus-visible,
    .form-control:focus-visible,
    .form-select:focus-visible {
        outline: 3px solid var(--invoice-focus-soft);
        outline-offset: 2px;
        box-shadow: 0 0 0 3px var(--invoice-focus-soft);
    }

    .btn-invoice:disabled,
    .dropdown-item:disabled {
        opacity: .55;
        cursor: not-allowed;
        pointer-events: none;
    }

    .btn-invoice--primary {
        background: var(--invoice-primary);
        border-color: var(--invoice-primary);
        color: #fff;
    }

    .btn-invoice--primary:hover {
        background: var(--invoice-primary-strong);
        border-color: var(--invoice-primary-strong);
        color: #fff;
    }

    .btn-invoice--secondary {
        background: var(--invoice-surface);
        border-color: var(--invoice-secondary-border);
        color: var(--invoice-text);
    }

    .btn-invoice--secondary:hover {
        background: var(--invoice-secondary);
        border-color: #c8d0db;
        color: var(--invoice-text);
    }

    .btn-invoice--ghost {
        background: transparent;
        border-color: var(--invoice-secondary-border);
        color: var(--invoice-text);
    }

    .btn-invoice--ghost:hover {
        background: var(--invoice-secondary);
        border-color: #c8d0db;
    }

    .dropdown-menu {
        border: 1px solid var(--invoice-border);
        border-radius: 12px;
        box-shadow: 0 18px 32px rgba(15, 23, 42, 0.12);
        padding: .5rem;
        min-width: 220px;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: .6rem;
        width: 100%;
        padding: .7rem .8rem;
        border: none;
        border-radius: 10px;
        background: transparent;
        color: var(--invoice-text);
        font-size: .92rem;
        font-weight: 600;
        text-align: left;
        min-height: 42px;
    }

    .dropdown-item:hover,
    .dropdown-item:focus-visible {
        background: var(--invoice-secondary);
        color: var(--invoice-text);
    }

    .dropdown-item.danger {
        color: #b42318;
    }

    .dropdown-item.danger:hover,
    .dropdown-item.danger:focus-visible {
        background: #fff1f2;
    }

    .pp-label {
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: .5rem;
        display: block;
    }

    .pp-format {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .75rem .9rem;
        margin-bottom: .6rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
    }

    .pp-format input {
        display: none;
    }

    .pp-format small {
        display: block;
        color: #6b7280;
        font-size: .75rem;
    }

    .pp-format:has(input:checked) {
        border-color: #007abd;
        background: #eef7fc;
    }

    .pp-format:has(input:checked) .material-icons-outlined {
        color: #007abd;
    }

    .pp-stage {
        position: relative;
        height: 70vh;
        background: #525659;
    }

    .pp-stage iframe {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
    }

    .pp-overlay {
        position: absolute;
        inset: 0;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: .75rem;
        padding: 1.5rem;
        text-align: center;
        color: #fff;
        background: rgba(60, 63, 65, .92);
    }

    /* ===== desktop: card de ações fixo no canto direito ===== */
    @media (min-width: 992px) {

        /* espaço reservado para o card fixo */
        .invoice-layout {
            padding-right: calc(300px + 1.25rem);
        }

        /* fixo, alinhado com a largura máxima da página */
        .invoice-side-card {
            position: fixed;
            top: 1rem;
            right: max(1rem, calc((100% - 1240px) / 2 + 1rem));
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
        }
    }

    /* ===== responsivo ===== */
    @media (max-width: 991.98px) {
        .pp-stage {
            height: 60vh;
        }

        /* card em cima, fatura em baixo */
        .invoice-layout {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .invoice-side-card {
            position: static;
            width: 100%;
            flex-basis: auto;
        }

        .invoice-preview-panel {
            flex-basis: auto;
        }

        /* no ecrã pequeno a fatura faz scroll horizontal em vez de ser cortada */
        #fatura-container {
            overflow-x: auto;
        }
    }

    @media (max-width: 767.98px) {
        .invoice-shell {
            margin-top: 1.25rem;
            padding-inline: .75rem;
        }
    }
</style>

<main class="invoice-shell">
    <div class="invoice-layout no-print">

        <!-- Esquerda: layout da fatura -->
        <div class="invoice-preview-panel">
            <div id="preloader" style="display:none;">Carregando...</div>
            <div id="fatura-container" class="invoiceContainer shadow-sm bg-white">
                <!-- aqui dentro já está todo o HTML da fatura -->
            </div>
        </div>

        <!-- Direita: card com informação + botões -->
        <aside class="invoice-side-card" aria-label="Ações da fatura">
            <div class="invoice-header__eyebrow">Factura</div>
            <h1 class="invoice-header__title">
                <strong id="fatura-id">-</strong>
                <span id="status-invoice" class="invoice-status-badge is-draft d-none"></span>
            </h1>
            <span class="invoice-header__subtitle" id="subtitle-client">-</span>
            <div class="invoice-header__meta">Original</div>

            <div class="invoice-toolbar" aria-label="Ações principais">
                <button type="button" class="btn btn-success d-none" id="btnRecibo" aria-label="Pagamento e recibo">
                    <span class="material-icons-outlined" aria-hidden="true">paid</span>
                    Pagamento / Recibo
                </button>

                <div class="d-none" id="generatePdf">
                    <button class="btn-invoice btn-invoice--secondary" type="button" id="btnFormatoImpressao"
                        data-bs-toggle="modal" data-bs-target="#modalPrintPreview" aria-label="Imprimir ou baixar a factura">
                        <span class="material-icons-outlined" aria-hidden="true">print</span>
                        Imprimir / Baixar
                    </button>
                </div>

                <button type="button" class="btn-invoice btn-invoice--secondary d-none" id="btnEnviar"
                    data-bs-toggle="modal" data-bs-target="#modalEnviarEmail" aria-label="Enviar factura por e-mail">
                    <span class="material-icons-outlined" aria-hidden="true">send</span>
                    Enviar factura
                </button>
            </div>

            <div class="invoice-actions" id="moreActionsDropdown">
                <span class="pp-label">Mais ações</span>

                <button type="button" class="dropdown-item d-none" id="btnCloneToInvoice" aria-label="Clonar factura">
                    <span class="material-icons-outlined" aria-hidden="true">content_copy</span>
                    Clonar factura
                </button>

                <button type="button" class="dropdown-item d-none" id="btnNotaCredito" aria-label="Emitir nota de crédito">
                    <span class="material-icons-outlined" aria-hidden="true">assignment_return</span>
                    Nota de crédito
                </button>

                <button type="button" class="dropdown-item d-none" id="btnNotaDebito" aria-label="Emitir nota de débito">
                    <span class="material-icons-outlined" aria-hidden="true">request_quote</span>
                    Nota de débito
                </button>

                <button type="button" class="dropdown-item d-none" id="btnNotaEntrega" aria-label="Emitir nota de entrega">
                    <span class="material-icons-outlined" aria-hidden="true">local_shipping</span>
                    Nota de entrega
                </button>

                <button type="button" class="dropdown-item d-none" id="btnEditar" aria-label="Editar factura">
                    <span class="material-icons-outlined" aria-hidden="true">edit</span>
                    Editar
                </button>

                <button type="button" class="dropdown-item d-none" id="btnFinalizar" aria-label="Finalizar factura">
                    <span class="material-icons-outlined" aria-hidden="true">check_circle</span>
                    Finalizar
                </button>

                <button type="button" class="dropdown-item danger d-none" id="btnDeleteInvoice" aria-label="Apagar factura">
                    <span class="material-icons-outlined" aria-hidden="true">delete</span>
                    Apagar
                </button>
            </div>
        </aside>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalPagamento" tabindex="-1">
        <div class="modal-dialog">
            <form id="formPagamento" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pagamento / Recibo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Valor -->
                    <div class="p-3 bg-white rounded shadow-sm mb-2">
                        <div class="mb-3">
                            <label class="form-label">Valor</label>
                            <div class="input-group d-flex gap-0">
                                <input type="number" step="0.01" min="0" id="pg_valor"
                                    name="amount" class="form-control" required>
                                <span class="input-group-text" id="pg_saldo"></span>
                            </div>
                            <!-- o .invalid-feedback será inserido aqui quando necessário -->
                        </div>


                    </div>

                    <!-- Série -->
                    <div class="p-3 bg-white rounded shadow-sm mb-2 d-flex gap-2">
                        <!-- Data -->
                        <div class="mb-3 col-6">
                            <label class="form-label">Data</label>
                            <input type="date" id="pg_data" name="pay_date"
                                class="form-control" required>
                        </div>

                        <div class="mb-3 col-6">
                            <label class="form-label">Série</label>
                            <input id="pg_serie" name="serie" class="form-control" required placeholder="EX: 12/2026">
                        </div>

                        <!-- Meio de pagamento -->
                    </div>

                    <div class="mb-3 p-3 bg-white rounded shadow-sm mb-2">
                        <label class="form-label">Meio de pagamento</label>
                        <select id="pg_meio" name="payment_method" class="form-select" required>
                            <option>Transferência bancária</option>
                            <option>Dinheiro</option>
                            <option>Cheque</option>
                            <option>TPA / Cartão</option>
                        </select>
                    </div>

                    <!-- Observações -->
                    <div class="p-3 bg-white rounded shadow-sm mb-2">

                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea id="pg_obs" name="notes" rows="2"
                                class="form-control"></textarea>
                        </div>

                        <!-- campo oculto com ID da fatura -->
                        <input type="hidden" name="invoice_id" value="">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        Registar pagamento e criar recibo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="modalReceipts" tabindex="-1" aria-labelledby="modalReceiptsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow border-0">

                <!-- Header -->
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-semibold" id="modalReceiptsLabel">
                        <i class="bi bi-receipt me-2"></i>Recibos
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar">
                    </button>
                </div>

                <!-- Body -->
                <div class="modal-body">

                    <!-- Actions -->
                    <div class="d-flex justify-content-end mb-3">
                        <button
                            type="button"
                            class="btn btn-success"
                            data-bs-toggle="modal"
                            data-bs-target="#modalPagamento">

                            <i class="bi bi-plus-circle me-1"></i>
                            Novo Recibo
                        </button>
                    </div>

                    <!-- Receipts List -->
                    <div id="receiptsList" class="receipts-list">
                        <div class="text-center text-muted py-4">
                            Nenhum recibo encontrado.
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="modalNotes" tabindex="-1" aria-labelledby="modalReceiptsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content shadow border-0">

                <!-- Header -->
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-semibold" id="modalReceiptsLabel">
                        <i class="bi bi-receipt me-2"></i>Recibos
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar">
                    </button>
                </div>

                <!-- Body -->
                <div class="modal-body">

                    <!-- Actions -->
                    <div class="d-flex justify-content-end mb-3">
                        <button
                            type="button"
                            class="btn btn-success"
                            data-bs-toggle="modal"
                            data-bs-target="#modalNotes">

                            <i class="bi bi-plus-circle me-1"></i>
                            Nova nota credito
                        </button>
                    </div>

                    <!-- Receipts List -->
                    <div id="receiptsList" class="receipts-list">
                        <div class="text-center text-muted py-4">
                            Nenhuma nota de credito encontrado.
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>

            </div>
        </div>
    </div>


    <!-- Modal :: Enviar fatura por e‑mail -->
    <div class="modal fade" id="modalEnviarEmail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formEnviarEmail" class="modal-content needs-validation" novalidate>

                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons-outlined me-1">mail</span>
                        Enviar fatura por e‑mail
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- --- Destinatários --- -->
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Para</label>
                            <input type="email" class="form-control" name="to" required>
                            <div class="invalid-feedback">E‑mail inválido.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cc (opcional)</label>
                            <input type="email" class="form-control" name="cc">
                        </div>
                    </div>

                    <!-- --- Assunto --- -->
                    <div class="mt-3">
                        <label class="form-label">Assunto</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>

                    <!-- --- Editor Quill --- -->
                    <div class="mt-3">
                        <label class="form-label">Mensagem</label>

                        <!-- toolbar -->
                        <div id="editor-toolbar">
                            <span class="ql-formats">
                                <button class="ql-bold"></button>
                                <button class="ql-italic"></button>
                                <button class="ql-underline"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered"></button>
                                <button class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link"></button>
                            </span>
                        </div>

                        <!-- área de edição -->
                        <div id="editor-container" style="height:280px"></div>

                        <!-- texto plano/HTML que realmente será enviado -->
                        <textarea name="body" id="body-hidden" class="d-none"></textarea>
                    </div>

                    <!-- Anexar PDF -->
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="chkAnexar" name="attach" checked>
                        <label class="form-check-label" for="chkAnexar">
                            <span class="material-icons-outlined align-middle">picture_as_pdf</span>
                            Anexar PDF da fatura
                        </label>
                    </div>

                    <input type="hidden" name="invoice_id" id="email_invoice_id">
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <span class="material-icons-outlined align-middle me-1">send</span>
                        Enviar e-mail
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- ===== PRÉ-VISUALIZAR E IMPRIMIR ===== -->
    <div class="modal fade" id="modalPrintPreview" tabindex="-1" aria-labelledby="printPreviewTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="printPreviewTitle">
                        <span class="material-icons-outlined align-middle me-1">print</span>
                        Pré-visualizar e imprimir
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Escolha do formato -->
                        <div class="col-lg-4 p-3 border-end">
                            <p class="pp-label">Formato</p>

                            <label class="pp-format">
                                <input type="radio" name="pp_format" value="a4" checked>
                                <span class="material-icons-outlined">description</span>
                                <span>
                                    <strong>Fatura A4</strong>
                                    <small>PDF em folha A4, com Original e Duplicado</small>
                                </span>
                            </label>

                            <label class="pp-format">
                                <input type="radio" name="pp_format" value="thermal">
                                <span class="material-icons-outlined">receipt_long</span>
                                <span>
                                    <strong>Talão térmico</strong>
                                    <small>Rolo de 80mm (impressora POS)</small>
                                </span>
                            </label>

                            <div id="ppCopiesBox" class="mt-3">
                                <label class="pp-label" for="ppCopies">Nº de vias</label>
                                <select id="ppCopies" class="form-select">
                                    <option value="1">1 via</option>
                                    <option value="2" selected>2 vias (Original + Duplicado)</option>
                                    <option value="3">3 vias</option>
                                </select>
                            </div>
                        </div>

                        <!-- Pré-visualização -->
                        <div class="col-lg-8 pp-stage">
                            <div id="ppLoading" class="pp-overlay">
                                <div class="spinner-border text-light" role="status" aria-hidden="true"></div>
                                <span>A preparar a pré-visualização...</span>
                            </div>
                            <div id="ppError" class="pp-overlay d-none">
                                <span class="material-icons-outlined">error_outline</span>
                                <span id="ppErrorText"></span>
                                <button type="button" class="btn btn-light btn-sm" id="ppRetry">Tentar novamente</button>
                            </div>
                            <iframe id="ppFrame" title="Pré-visualização do documento"></iframe>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-outline-primary" id="ppDownload" disabled>
                        <span class="material-icons-outlined align-middle" style="font-size:18px;">download</span>
                        Baixar PDF
                    </button>
                    <button type="button" class="btn btn-primary" id="ppPrint" disabled>
                        <span class="material-icons-outlined align-middle" style="font-size:18px;">print</span>
                        Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="invoices/invoice.js?v=4.9"></script>

<?php require_once '../app/views/footer.php'; ?>