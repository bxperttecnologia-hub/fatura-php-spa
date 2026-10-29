<?php
require_once '../app/views/layout_creation.php';
?>
<style>
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
    .action-panel {
        position: sticky;
        top: 60px;
        /* ou 16px, ajusta pra não grudar total no topo */
        align-self: flex-start;
        /* mantém os outros estilos */
        width: 240px;
        background: #fff;
        /* border: 1px solid #dee2e6; */
        border-radius: .5rem;
        /* box-shadow: 0 0 .75rem rgba(0, 0, 0, .08); */
        padding: 1rem;
        font-size: .925rem;
        z-index: 10;
        /* pra ficar acima de conteúdo se preciso */
    }

    .action-panel .btn {
        display: flex;
        align-items: center;
        /* ícone + texto centralizados */
        gap: .35rem;
    }

    .action-panel .section-title {
        font-weight: 600;
        font-size: .75rem;
        letter-spacing: .02em;
        text-transform: uppercase;
        margin: .75rem 0 .25rem;
        border-bottom: 1px solid #ced4da;
        padding-bottom: 2px;
        color: #6c757d;
    }
</style>

<main>
    <!-- ===== CONTAINER LADO‑A‑LADO ===== -->
    <div class="d-flex gap-4 mt-5 no-print justify-content-center align-items-center">
        <!-- ==== FATURA (cresce até encher) ==== -->
        <div class=" flex-column d-flex justify-content-center">

            <div class="invoice-header pagea4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="mb-0">Proforma nº <span id="fatura-id"></span></span>
                        <span class="subtitle" id="subtitle-client"></span>
                    </div>
                    <div>
                        <span id="status-invoice" class="d-none"></span>
                    </div>
                </div>
            </div>
            <div id="preloader" style="display:none;">Carregando...</div>
            <div id="fatura-container"
                class="invoiceContainer shadow-sm bg-white ">
                <!-- aqui dentro já está todo o HTML da fatura -->
            </div>
        </div>

        <!-- ③ –– Painel -->
        <aside class="action-panel shadow-sm">

            <button class="btn text-center align-items-center align-content-center btn-primary w-100 mb-2" id="btnEditar">
                <span class="material-icons-outlined">edit</span>
                Editar 
            </button>

            <button class="btn btn-warning w-100 mb-2" id="btnChangeToInvoice">
                <span class="material-icons-outlined">check_circle</span>
                Emitir Fatura
            </button>

            <!-- grupo Documento -->

            <button class="btn text-center d-none align-items-center align-content-center btn-danger w-100 mb-2" id="generatePdf">
                <span class="material-icons-outlined">picture_as_pdf</span>
                Baixar PDF
            </button>

            <button class="btn text-center d-none align-items-center align-content-center btn-primary text-white w-100 mb-2" id="btnEnviar"
                data-bs-toggle="modal" data-bs-target="#modalEnviarEmail">
                <span class="material-icons-outlined">send</span>
                Enviar Proforma
            </button>

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
                            <span class="material-icons-outlined align-middle">picture_as_pdf</span>
                            Anexar PDF da proforma
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
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="proform/invoice.js?v=0.4"></script>

<?php require_once '../app/views/footer.php'; ?>