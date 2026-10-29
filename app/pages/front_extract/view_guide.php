<?php require_once '../app/views/layout_creation.php'; ?>
<style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .pagea4 {
        width: 190mm;
        max-width: 100%;
        margin: 0 auto;
    }

    .guide-header {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: .5rem .5rem 0 0;
        padding: 20px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .guide-header span {
        font-size: 1.25rem;
        font-weight: 600;
        color: #343a40;
    }

    .guide-header .subtitle {
        font-size: 0.875rem;
        color: #6c757d;
    }

    #status-guide {
        border: 1px solid #0d6efd;
        color: #0d6efd;
        background: #e7f1ff;
        padding: 4px 16px;
        font-size: 0.875rem;
        border-radius: .375rem;
        font-weight: 500;
    }

    .action-panel {
        position: sticky;
        top: 60px;
        width: 260px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        box-shadow: 0 0 1rem rgba(0, 0, 0, .05);
        padding: 1rem;
        z-index: 10;
    }

    .action-panel .btn {
        display: flex;
        align-items: center;
        gap: .5rem;
        font-weight: 500;
    }

    .action-panel .section-title {
        font-size: .75rem;
        text-transform: uppercase;
        font-weight: 600;
        margin: 1rem 0 .5rem;
        color: #6c757d;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 2px;
    }

    #guide-container {
        border-radius: 0 0 .5rem .5rem;
        overflow: hidden;
    }

    .modal-content {
        border-radius: .75rem;
    }

    label.form-label {
        font-weight: 500;
        color: #343a40;
    }

    .form-control,
    .form-select {
        border-radius: .375rem;
    }

    .btn-close {
        background: none;
        border: none;
    }

    .material-icons-outlined {
        vertical-align: middle;
    }

    .no-print {
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .d-flex.gap-4 {
            flex-direction: column;
            align-items: stretch;
        }

        .action-panel {
            width: 100%;
            position: relative;
        }

        .pagea4 {
            width: 100%;
        }
    }
</style>

<body>
<main>
    <div class="d-flex gap-4 mt-5 no-print justify-content-center align-items-start">
        <!-- Painel Lateral -->
        <aside class="action-panel">
            <button class="btn btn-danger w-100 mb-2" id="generateGuidePdf">
                <span class="material-icons-outlined">picture_as_pdf</span>
                Baixar PDF
            </button>

            <button class="btn btn-primary w-100 mb-2" id="btnSendGuide" data-bs-toggle="modal" data-bs-target="#modalSendGuide">
                <span class="material-icons-outlined">send</span>
                Enviar Guia
            </button>
        </aside>

        <!-- Conteúdo Principal -->
        <div class="flex-column d-flex justify-content-center"> 

            <div id="preloader" style="display:none;" class="text-center py-3">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div>
                <p class="mt-2">Carregando guia...</p>
            </div>

            <div id="guide-container" class="invoiceContainer shadow   pagea4">
                <!-- Conteúdo da guia será inserido aqui -->
            </div>
        </div>
    </div>

    <!-- MODAL: Recebimento -->
    <div class="modal fade" id="modalRecebimento" tabindex="-1">
        <div class="modal-dialog">
            <form id="formRecebimento" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recebimento / Recibo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Valor</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" id="rc_valor" name="amount" class="form-control" required>
                            <span class="input-group-text" id="rc_saldo"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Data</label>
                        <input type="date" id="rc_data" name="receive_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Série</label>
                        <select id="rc_serie" name="serie" class="form-select" required>
                            <option value="2025">2025</option>
                            <option value="A">A</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meio de recebimento</label>
                        <select id="rc_meio" name="receive_method" class="form-select" required>
                            <option>Transferência bancária</option>
                            <option>Dinheiro</option>
                            <option>Cheque</option>
                            <option>TPA / Cartão</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea id="rc_obs" name="notes" rows="2" class="form-control"></textarea>
                    </div>
                    <input type="hidden" name="guide_id" value="">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100">
                        Registar recebimento e criar recibo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: Enviar Guia -->
    <div class="modal fade" id="modalSendGuide" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formSendGuide" class="modal-content needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons-outlined me-1">mail</span> Enviar guia por e-mail
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Para</label>
                            <input type="email" class="form-control" name="to" required>
                            <div class="invalid-feedback">E-mail inválido.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cc (opcional)</label>
                            <input type="email" class="form-control" name="cc">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Assunto</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Mensagem</label>
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
                        <div id="editor-container" style="height:280px"></div>
                        <textarea name="body" id="body-hidden" class="d-none"></textarea>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="chkAnexarGuide" name="attach" checked>
                        <label class="form-check-label" for="chkAnexarGuide">
                            <span class="material-icons-outlined align-middle">picture_as_pdf</span> Anexar PDF da guia
                        </label>
                    </div>
                    <input type="hidden" name="guide_id" id="email_guide_id">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary w-100">
                        <span class="material-icons-outlined align-middle me-1">send</span> Enviar e-mail
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="guides/view_guides.js?v=<?= filemtime(__DIR__ . '/guides/view_guides.js') ?>"></script>

<!-- Biblioteca html2pdf.js para gerar PDF do HTML (Client-side) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const btn = document.getElementById("generateGuidePdf");
        
        // Clona o botão para remover eventos anteriores (do view_guides.js) e garantir que só este funcione
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener("click", function() {
            const element = document.getElementById("guide-container");
            
            const opt = {
                margin:       [5, 5, 5, 5], // Margens: Topo, Esquerda, Baixo, Direita
                filename:     'Guia_Transporte.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Gera o PDF a partir da DIV visualizada
            html2pdf().set(opt).from(element).save();
        });
    });
</script>

<?php require_once '../app/views/footer.php'; ?>
</body>
</html>
