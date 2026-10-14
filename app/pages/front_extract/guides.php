<?php
require_once '../app/views/layout_creation.php';
?>

<link href="guides/guides.css" rel="stylesheet">

<body>
<main class="mt-4">
    <div class="guide-container mb-4">
        <form id="formGuia" autocomplete="off">

            <h3 class="mb-3 fw-bold border-bottom pb-2"><?= t('Nova Guia de Transporte') ?></h3>

            <!-- Seleção de cliente -->
            <section class="mb-4">
                <div class="guide-client-card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <h4 class="mb-0 guide-client-title"><?= t('Dados do Cliente') ?></h4>
                            <span style="cursor:pointer" id="toggle-contact-form" data-bs-title="Inserir novo Contacto" data-bs-toggle="tooltip" data-bs-placement="top">
                                <span class="material-icons-round guide-icon-add" id="spanIconCreateInvoices">person_add</span>
                            </span>
                        </div>
                        <hr class="my-2">

                        <!-- Select2 para escolher contato existente -->
                        <div id="select-contact-container" class="mb-3 d-flex flex-column">
                            <label for="contact-select" class="guide-label"><?= t('Escolha um Contato') ?>:</label>
                            <select id="contact-select" class="guide-select" name="contact_id" required>
                                <option value="">Selecione um contato</option>
                                <!-- Os contatos existentes serão carregados via JS -->
                            </select>
                        </div>

                        <!-- Formulário de Cliente (criar novo) -->
                        <div id="contact-form" style="display:none;">
                            <input type="text" hidden readonly id="contact_id" name="contact_id">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="guide-label"><?= t('Nome') ?>:</label>
                                    <input type="text" class="guide-input form-control" id="name" name="name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="contributor" class="guide-label"><?= t('Registro') ?>:</label>
                                    <input type="text" class="guide-input form-control" id="contributor" name="contributor" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="address" class="guide-label"><?= t('Endereço') ?>:</label>
                                    <textarea style="height: 7rem;" class="guide-textarea form-control" id="address" name="address" required></textarea>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="guide-label"><?= t('Email') ?>:</label>
                                    <input type="email" class="guide-input form-control" id="email" name="email" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="po_box" class="guide-label"><?= t('Caixa Postal') ?>:</label>
                                    <input type="text" class="guide-input form-control" id="po_box" name="po_box" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="country" class="guide-label"><?= t('País') ?>:</label>
                                    <select class="guide-select form-select" id="country" name="country" required>
                                        <option value=""><?= t('Carregando países...') ?></option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="guide-label"><?= t('Cidade') ?>:</label>
                                    <select class="guide-select form-select" id="city" name="city" required>
                                        <option value=""><?= t('Escolha um país primeiro') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="row g-4 mb-3">
                <div class="col-md-6">
                    <div class="guide-block h-100">
                        <label class="guide-label mb-1"><?= t('Matrícula do veículo') ?></label>
                        <input type="text" class="guide-input form-control mb-2" name="matricula">
                        <label class="guide-label mb-1"><?= t('Data de carga') ?> <span class="text-danger">*</span></label>
                        <input type="date" class="guide-input form-control mb-2" name="data_carga" required value="<?= date('Y-m-d') ?>">
                        <label class="guide-label mb-1"><?= t('Motivo do Transporte') ?></label>
                        <input type="text" class="guide-input form-control" name="motivo_transporte" placeholder="<?= t('Ex.: venda, transferência, devolução...') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="guide-block h-100">
                        <label class="guide-label mb-1"><?= t('Observações') ?></label>
                        <input type="text" class="guide-input form-control mb-2" name="observacoes" placeholder="<?= t('Alguma observação extra?') ?>">
                        <label class="guide-label mb-1"><?= t('Retenção (%)') ?></label>
                        <input type="number" class="guide-input form-control" name="retencao" min="0" max="100">
                    </div>
                </div>
            </div>

            <!-- Local de carga e entrega -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="guide-block h-100">
                        <h6 class="fw-bold mb-2"><i class="bi bi-box-arrow-in-down"></i> <?= t('Local de Carga') ?></h6>
                        <div class="mb-2">
                            <label class="guide-label"><?= t('Endereço') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="guide-input form-control" name="endereco_carga" required>
                        </div>
                        <div class="mb-2">
                            <label class="guide-label"><?= t('Cidade') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="guide-input form-control" name="cidade_carga" required>
                        </div>
                        <div>
                            <label class="guide-label"><?= t('Caixa Postal') ?></label>
                            <input type="text" class="guide-input form-control" name="caixa_postal_carga">
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="guide-block h-100">
                        <h6 class="fw-bold mb-2"><i class="bi bi-box-arrow-in-up"></i> <?= t('Local de Entrega') ?></h6>
                        <div class="mb-2">
                            <label class="guide-label"><?= t('Endereço') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="guide-input form-control" name="endereco_entrega" required>
                        </div>
                        <div class="mb-2">
                            <label class="guide-label"><?= t('Cidade') ?> <span class="text-danger">*</span></label>
                            <input type="text" class="guide-input form-control" name="cidade_entrega" required>
                        </div>
                        <div>
                            <label class="guide-label"><?= t('Caixa Postal') ?></label>
                            <input type="text" class="guide-input form-control" name="caixa_postal_entrega">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalhes do documento -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <label class="guide-label"><?= t('Data do Documento') ?></label>
                    <input type="date" class="guide-input form-control" name="data_documento" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="guide-label"><?= t('V/ Ref.') ?></label>
                    <input type="text" class="guide-input form-control" name="referencia">
                </div>
                <div class="col-md-3">
                    <label class="guide-label"><?= t('Série') ?></label>
                    <input type="text" class="guide-input form-control" name="serie" value="<?= date('Y') ?>">
                </div>
                <div class="col-md-3">
                    <label class="guide-label"><?= t('Moeda') ?></label>
                    <select class="guide-select form-select" name="moeda">
                        <?= currencySelects(); ?>
                    </select>
                </div>
            </div>

            <!-- Itens -->
            <section class="mb-4">
                <label class="fw-semibold mb-2"><?= t('Itens') ?> <span class="text-danger">*</span></label>
                <select class="guide-select form-select select2 mb-2" id="item_select">
                    <option value=""><?= t('Pesquise por um produto/serviço existente ou crie novo registro') ?></option>
                </select>
                <div id="items_list" class="guide-items-list mb-3"></div>
                <div class="table-responsive">
                    <table class="guide-items-table table table-sm align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%"><?= t('Código') ?></th>
                                <th style="width: 28%"><?= t('Descrição') ?></th>
                                <th style="width: 14%"><?= t('Preço unitário') ?></th>
                                <th style="width: 10%"><?= t('Qtd.') ?></th>
                                <th style="width: 14%"><?= t('Taxa/IVA') ?></th>
                                <th style="width: 12%"><?= t('Desc.%') ?></th>
                                <th style="width: 8%"></th>
                            </tr>
                        </thead>
                        <tbody id="items_table_body">
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>
            </section>

            <input type="hidden" name="total_sum">
            <input type="hidden" name="total_discount">
            <input type="hidden" name="subtotal_without_tax">
            <input type="hidden" name="total_tax">
            <input type="hidden" name="retention_value">
            <input type="hidden" name="final_total">

            <!-- Resumo -->
            <section class="mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <table class="guide-summary-table table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td><?= t('Soma:') ?></td>
                                    <td id="total_sum">0,00 Kz</td>
                                </tr>
                                <tr>
                                    <td><?= t('Desconto:') ?></td>
                                    <td id="total_discount">0,00 Kz</td>
                                </tr>
                                <tr>
                                    <td><?= t('S/Imposto/IVA/Com Desc.:') ?></td>
                                    <td id="subtotal_without_tax">0,00 Kz</td>
                                </tr>
                                <tr>
                                    <td><?= t('Imposto/IVA:') ?></td>
                                    <td id="total_tax">0,00 Kz</td>
                                </tr>
                                <tr>
                                    <td><?= t('Retenção:') ?></td>
                                    <td id="retention_value">0,00 Kz</td>
                                </tr>
                                <tr class="fw-bold">
                                    <td><?= t('Total:') ?></td>
                                    <td id="final_total">0,00 Kz</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Ações -->
            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                <span class="guide-date-info"><?= t('Pagamento previsto pelo Factplus para') ?> <b><?= date('d/m/Y') ?></b></span>
                <div>
                    <button type="button" class="guide-btn-cancel btn me-2" onclick="window.history.back()"><?= t('Cancelar') ?></button>
                    <button type="submit" class="guide-btn-success btn" id="saveGuideBtn"><?= t('Guardar como rascunho') ?></button>
                </div>
            </div>
        </form>
    </div>
</main>
<script src="guides/guides.js"></script>
<?php require_once '../app/views/footer.php'; ?>
</body>
</html>

