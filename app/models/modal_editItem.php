<style>
    .modal-item {
        background: linear-gradient(135deg, #007abd, #6ea8ff);
        color: #fff;
        border-bottom: none;
    }

    .modal-item .btn-close {
        filter: invert(1);
    }

    .modal-content {
        background: #f5f5f5;
        border-radius: 18px;
        overflow: hidden;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        padding: 0;
    }

    .form-control,
    .form-select {
        border-radius: 12px;
        padding: 0px 12px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #007abd;
        box-shadow: 0 0 0 3px rgba(47, 107, 255, 0.1);
    }

    .form-label {
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .soft-card {
        background: #fff;
        border-radius: 14px;
        padding: 16px;
        border: 1px solid #eef0f6;
    }

    .btn-primary {
        background: #007abd;
        border: none;
        border-radius: 12px;
    }

    .btn-success {
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
    }

    .btn-outline-secondary {
        border-radius: 12px;
    }

    #gerarCodigo {
        border-radius: 10px;
        width: 100px;
        height: 40px;
        margin-left: -5px;

    }

    #codeInput {
        width: 70%;
    }

    #codeInput_content {
        display: flex;
        gap: 10px;
        align-items: center;
        align-content: center;
        justify-items: center;
        justify-content: center;
    }

    #depot {
        transform: scale(-50px);
        opacity: 0;
        height: 0px;
        transition: all ease-in-out .3s;
        position: absolute;
        z-index: -999;
    }

    #depot.active {
        transform: translateY(0px);
        opacity: 1;
        height: auto;
        transition: .3s;
        position: inherit;
        z-index: inherit;
    }
</style>

<!-- Modal Editar Item -->
<div class="modal fade" id="editItemModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-item">
                <div>
                    <h5 class="modal-title"><?= t('Editar Item') ?></h5>
                    <small class="opacity-75" style="margin-left: -50px !important;">
                        Preencha os dados do produto/serviço
                    </small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body p-3">
                <form id="editItemForm">

                    <!-- IDS -->
                    <input type="hidden" name="id">
                    <input type="hidden" name="id_company" value="<?= $_SESSION['user']['company_id'] ?>">

                    <!-- ========================= -->
                    <!-- 1. IDENTIFICAÇÃO -->
                    <!-- ========================= -->
                    <div class="soft-card mb-3">
                        <h6 class="mb-3 fw-bold"><?= t('Identificação do Item') ?></h6>

                        <div class="row g-3 align-items-end">

                            <!-- CÓDIGO -->
                            <div class="col-md-4">
                                <label class="form-label"><?= t('Código') ?></label>

                                <div class="d-flex align-items-center gap-2">
                                    <!-- VISUAL -->
                                    <input type="text" class="form-control" id="codigo_display" disabled>

                                    <!-- REAL (vai no POST) -->
                                    <input type="hidden" name="codigo">

                                    <i class="bi bi-info-circle text-muted"
                                        data-bs-toggle="tooltip"
                                        title="Código não editável"></i>
                                </div>
                            </div>

                            <!-- NOME -->
                            <div class="col-md-8">
                                <label class="form-label">
                                    <?= t('Nome / Descrição') ?>
                                    <small class="text-danger">(opcional)</small>
                                </label>
                                <input type="text" class="form-control" name="name" required>
                            </div>

                            <!-- DESCRIÇÃO -->
                            <div class="col-12">
                                <label class="form-label"><?= t('Descrição detalhada') ?></label>
                                <textarea class="form-control" rows="2" name="descricao"></textarea>
                            </div>

                        </div>
                    </div>

                    <!-- ========================= -->
                    <!-- 2. CLASSIFICAÇÃO -->
                    <!-- ========================= -->
                    <div class="soft-card mb-3">
                        <h6 class="mb-3 fw-bold"><?= t('Classificação') ?></h6>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label"><?= t('Tipo') ?></label>
                                <select class="form-select" name="item_type" id="category" required>
                                    <option value="selecione o Tipo de item" selected></option>
                                    <option value="service"><?= t('Serviço') ?></option>
                                    <option value="product"><?= t('Produto') ?></option>
                                    <option value="consumable"><?= t('Consumível') ?></option>
                                    <option value="raw_material"><?= t('Matéria-prima') ?></option>
                                    <option value="finished_good"><?= t('Produto final') ?></option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Subcategoria</label>
                                <select class="form-select" name="subcategory" id="subcategory">
                                    <option value="">Selecione</option>
                                    <option value="essential">Bens essenciais</option>
                                    <option value="agriculture">Insumos agrícolas</option>
                                    <option value="other">Outros produtos</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="soft-card mb-3 d-flex flex-wrap gap-2">
                        <div class="col-md-5">
                            <label class="form-label"><?= t('Unidade') ?></label>
                            <select class="form-select" name="unit_measure">
                                <option value="unit">Unidade</option>
                                <option value="kg">Kg</option>
                                <option value="liter">Litro</option>
                                <option value="meter">Metro</option>
                                <option value="service">Serviço</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"><?= t('Moeda') ?></label>
                            <select class="form-select" name="currency">
                                <?= currencySelects(); ?>
                            </select>
                        </div>
                    </div>


                    <!-- ========================= -->
                    <!-- 3. STOCK -->
                    <!-- ========================= -->
                    <div class="soft-card mb-3" id="depot">
                        <h6 class="mb-3 fw-bold"><?= t('Gestão de Stock') ?></h6>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label"><?= t('Depósito / Stock') ?></label>
                                <select class="form-select" name="stock_id" id="stock_id">
                                    <option value=""><?= t('Selecione o stock') ?></option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label"><?= t('Quantidade Inicial') ?></label>
                                <input type="number" class="form-control" name="quantidade" min="0">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label"><?= t('Stock mínimo') ?></label>
                                <input type="number" class="form-control" name="min_stock" min="1" value="1">
                            </div>

                        </div>
                    </div>

                    <!-- ========================= -->
                    <!-- FISCAL -->
                    <!-- ========================= -->
                    <div class="soft-card mb-2">
                        <h6 class="mb-3 fw-bold"><?= t('Fiscalidade') ?></h6>

                        <div class="row g-3">

                            <div class="col-md-4">
                                <label class="form-label"><?= t('Preço Unitário') ?></label>
                                <input type="number" class="form-control" name="unit_price" step="0.01" required>
                            </div>

                            <!-- IVA -->
                            <div class="col-md-4">
                                <label class="form-label"><?= t('IVA') ?></label>
                                <input class="form-control" name="tax" readonly>
                            </div>

                            <!-- RETENÇÃO -->
                            <div class="col-md-4">
                                <label class="form-label"><?= t('Retenção') ?></label>
                                <select class="form-select" name="retention">
                                    <option value="0">Não aplicar</option>
                                    <option value="6.5">Aplicar (6.5%)</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <!-- ========================= -->
                    <!-- FINANCEIRO -->
                    <!-- ========================= -->
                    <div class="soft-card mb-3" id="financeBlock">
                        <h6 class="mb-3 fw-bold"><?= t('Preços') ?></h6>

                        <div class="row g-3">

                            <div class="col-md-4">
                                <label class="form-label"><?= t('Preço de Custo') ?></label>
                                <input type="number" class="form-control" name="cost_price">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><?= t('Preço de Venda') ?></label>
                                <input type="number" class="form-control" name="sale_price">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><?= t('PVP') ?></label>
                                <input type="number" class="form-control" name="pvp">
                            </div>

                        </div>
                    </div>

                </form>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success" id="saveEdit">
                    <?= t('Salvar') ?>
                </button>
            </div>

        </div>
    </div>
</div>
