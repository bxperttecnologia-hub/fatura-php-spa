<?php
require_once '../app/views/layout_creation.php';
?>
<link rel="stylesheet" href="/assets/css/documents.css?v=1.2">
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

    .invoice-side-card {
        flex: 0 0 300px;
        width: 300px;
        margin-top: 0;
        padding: 1.25rem;
        color: var(--invoice-text);
        background: var(--invoice-surface);
        border: 1px solid var(--invoice-border);
        border-radius: 16px;
        box-shadow: var(--invoice-shadow);
    }

    .invoice-header__eyebrow {
        display: inline-flex;
        margin-bottom: .35rem;
        color: var(--invoice-muted);
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .invoice-header__title {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: .6rem;
        margin: 0;
        color: var(--invoice-text);
        font-size: .95rem;
        line-height: 1.2;
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

    #status-invoice,
    .proform-document-type {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 90px;
        padding: .4rem .75rem;
        color: #3730a3;
        background: #eef2ff;
        border: 1px solid rgba(55, 48, 163, .18);
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .04em;
        line-height: 1;
        text-transform: uppercase;
    }

    #status-invoice.is-draft {
        color: #374151;
        background: #f3f4f6;
        border-color: rgba(55, 65, 81, .14);
    }

    .invoice-toolbar,
    .invoice-actions {
        display: flex;
        flex-direction: column;
        gap: .6rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--invoice-border);
    }

    .invoice-actions {
        gap: .15rem;
    }

    .invoice-toolbar .btn-invoice {
        width: 100%;
    }

    .btn-invoice {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        width: 100%;
        min-height: 44px;
        padding: .7rem 1rem;
        color: var(--invoice-text);
        background: var(--invoice-surface);
        border: 1px solid var(--invoice-secondary-border);
        border-radius: 12px;
        font-size: .92rem;
        font-weight: 600;
        line-height: 1.1;
        text-decoration: none;
        cursor: pointer;
    }

    .btn-invoice:hover {
        color: var(--invoice-text);
        background: var(--invoice-secondary);
        border-color: #c8d0db;
    }

    .btn-invoice--primary {
        color: #fff;
        background: var(--invoice-primary);
        border-color: var(--invoice-primary);
    }

    .btn-invoice--primary:hover {
        color: #fff;
        background: var(--invoice-primary-strong);
        border-color: var(--invoice-primary-strong);
    }

    .invoice-actions .dropdown-item {
        display: flex;
        align-items: center;
        gap: .6rem;
        width: 100%;
        min-height: 40px;
        padding: .55rem .8rem;
        color: var(--invoice-text);
        background: transparent;
        border: 0;
        border-radius: 10px;
        font-size: .92rem;
        font-weight: 600;
        text-align: left;
    }

    .invoice-actions .dropdown-item:hover,
    .invoice-actions .dropdown-item:focus-visible {
        background: var(--invoice-secondary);
    }

    .invoice-header__title:focus-visible,
    .btn-invoice:focus-visible,
    .invoice-actions .dropdown-item:focus-visible {
        outline: 3px solid var(--invoice-focus-soft);
        outline-offset: 2px;
    }

    .invoice-header {
        background: #f6f6f6;
        border: 1px solid #e1e1e1;
        border-top-left-radius: 3px;
        border-top-right-radius: 3px;
        padding: 18px 20px 10px 20px;
    }

    .pagea4 {
        width: 190mm;
        max-width: 100%;
        margin: 0 auto;
    }

    .invoice-header .d-flex {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .invoice-header span {
        font-size: 1.25rem;
        font-weight: 500;
        color: #232323;
    }

    .invoice-header #fatura-id {
        font-weight: 600;
    }

    .invoice-header .subtitle {
        display: block;
        font-size: .75rem;
        color: #666;
        margin-top: -3px;
        letter-spacing: .5px;
    }

    #status-invoice {
        border: 1px solid #267fa8;
        color: #267fa8;
        border-radius: 4px;
        padding: 2px 16px;
        font-size: 1em;
        font-weight: 500;
        background: #fff;
        min-width: 64px;
        text-align: center;
    }

    /* ② –– painel lateral */
    @media (min-width: 992px) {
        .invoice-layout {
            padding-right: calc(300px + 1.25rem);
        }

        .invoice-side-card {
            position: fixed;
            top: 1rem;
            right: max(1rem, calc((100% - 1240px) / 2 + 1rem));
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
        }
    }

    @media (max-width: 991.98px) {
        .invoice-layout {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .invoice-side-card,
        .invoice-preview-panel {
            width: 100%;
            flex-basis: auto;
        }
    }

    @media (max-width: 767.98px) {
        .invoice-shell {
            margin-top: 1.25rem;
            padding-inline: .75rem;
        }
    }
</style>

<main class="invoice-shell bx-document-view">
    <div class="invoice-layout no-print">
        <div class="invoice-preview-panel">

            <div class="invoice-header pagea4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="mb-0">Proforma nº <span id="fatura-id"></span></span>
                        <span class="subtitle" id="subtitle-client"></span>
                    </div>
                    <div>
                        <span class="proform-document-type">PROFORMA</span>
                    </div>
                </div>
            </div>
            <div id="preloader" style="display:none;">Carregando...</div>
            <div id="fatura-container"
                class="invoiceContainer shadow-sm bg-white ">
                <!-- aqui dentro já está todo o HTML da fatura -->
            </div>
        </div>

        <aside class="invoice-side-card" aria-label="Ações da proforma">
            <div class="invoice-header__eyebrow">Proforma</div>
            <h1 class="invoice-header__title">
                <strong id="action-proform-number">-</strong>
                <span id="status-invoice" class="invoice-status-badge d-none"></span>
            </h1>
            <span class="invoice-header__subtitle" id="action-proform-client">-</span>
            <div class="invoice-header__meta">Original</div>

            <div class="invoice-toolbar" aria-label="Ações principais">
                <button type="button" class="bx-btn-primary" id="btnEditar">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    Editar
                </button>
                <button type="button" class="bx-btn-secondary" id="btnChangeToInvoice">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Emitir Fatura
                </button>
                <button type="button" class="bx-btn-secondary d-none" id="generatePdf" aria-label="Imprimir ou baixar a proforma">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    Imprimir / Baixar
                </button>
                <button type="button" class="bx-btn-secondary d-none" id="btnEnviar"
                data-bs-toggle="modal" data-bs-target="#modalEnviarEmail">
                    <i class="bi bi-send" aria-hidden="true"></i>
                    Enviar Proforma
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
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>
                        Enviar proforma por e‑mail
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
                            <i class="bi bi-file-earmark-pdf align-middle" aria-hidden="true"></i>
                            Anexar PDF da proforma
                        </label>
                    </div>

                    <input type="hidden" name="invoice_id" id="email_invoice_id">
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send align-middle me-1" aria-hidden="true"></i>
                        Enviar e-mail
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="/assets/js/document-tax.js"></script>
<script src="proform/invoice.js?v=0.6"></script>

<?php require_once '../app/views/footer.php'; ?>