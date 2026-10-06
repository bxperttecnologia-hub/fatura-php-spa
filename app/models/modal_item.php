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

    /* ========================= */
    /* CADASTRO RÁPIDO */
    /* ========================= */
    .quick-cat-btn {
        font-size: 12.5px;
        font-weight: 500;
    }

    .quick-item-btn {
        border-radius: 14px;
        border: 1px solid #eef0f6;
        padding: 12px 14px;
    }

    .quick-item-btn:hover {
        border-color: #007abd;
        box-shadow: 0 6px 16px rgba(0, 122, 189, 0.12);
    }

    #quickItemsList {
        max-height: 320px;
        overflow-y: auto;
    }
</style>

<div class="modal fade" id="itemModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-item">
                <div>
                    <h5 class="modal-title mb-1" style="background: none !important;"><?= t('Adicionar Novo Item') ?></h5>
                    <small class="opacity-75">Escolha um item pronto ou crie um personalizado</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body p-3">

                <!-- ========================= -->
                <!-- CADASTRO RÁPIDO -->
                <!-- ========================= -->
                <div id="quickAddPanel">

                    <div class="mb-3">
                        <label class="form-label"><?= t('Pesquisar item pré-configurado') ?></label>
                        <input type="text" id="quickSearch" class="form-control" placeholder="Ex: arroz, cimento, consultoria...">
                    </div>

                    <div id="quickCategories" class="d-flex flex-wrap gap-2 mb-3"></div>

                    <div id="quickItemsList" class="row g-2 mb-3"></div>

                    <div class="text-center pt-3 border-top">
                        <p class="text-muted small mb-2"><?= t('Não encontrou o que precisa na lista?') ?></p>
                        <button type="button" id="btnCustomItem" class="btn btn-outline-primary rounded-pill">
                            <i class="bi bi-plus-circle"></i> <?= t('Criar item personalizado') ?>
                        </button>
                    </div>

                </div>

                <!-- ========================= -->
                <!-- FORMULÁRIO COMPLETO -->
                <!-- ========================= -->
                <div id="fullFormWrapper" style="display:none;">

                    <button type="button" id="btnBackToQuick" class="btn btn-sm btn-link px-0 mb-2">
                        <i class="bi bi-arrow-left"></i> <?= t('Voltar à lista rápida') ?>
                    </button>

                    <form id="itemForm">

                        <input type="hidden" value="<?= $_SESSION['user']['company_id'] ?>" name="id_company">
                        <input type="hidden" value="0" name="item_id" id="item_id">

                        <!-- ========================= -->
                        <!-- 1. CLASSIFICAÇÃO -->
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
                        <!-- 2. IDENTIFICAÇÃO -->
                        <!-- ========================= -->
                        <div class="soft-card mb-3">
                            <h6 class="mb-3 fw-bold"><?= t('Identificação do Item') ?></h6>

                            <div class="row g-3 align-items-end">

                                <!-- CÓDIGO -->
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('Código') ?></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" id="codigo" name="codigo" required>
                                        <i class="bi bi-info-circle text-muted"
                                            data-bs-toggle="tooltip"
                                            title="Gerado automaticamente ao selecionar o stock"></i>
                                    </div>
                                </div>

                                <!-- NOME -->
                                <div class="col-md-8" id="nameBlock">
                                    <label class="form-label"><?= t('Nome') ?></label>
                                    <input type="text" class="form-control" name="name" id="name" required>
                                </div>

                                <!-- DESCRIÇÃO -->
                                <div class="col-12">
                                    <label class="form-label"><?= t('Descrição detalhada') ?> <small>(opcional)</small></label>
                                    <textarea class="form-control" rows="2" name="descricao" id="descricao"></textarea>
                                </div>

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
                        <!-- 4. FISCAL -->
                        <!-- ========================= -->
                        <div class="soft-card mb-2">
                            <h6 class="mb-3 fw-bold"><?= t('Fiscalidade') ?></h6>

                            <div class="row g-3">

                                <div class="col-md-4 mb-2">
                                    <label class="form-label"><?= t('Preço Unitário') ?></label>
                                    <input type="text" class="form-control" id="unit_price_display" data-price-display inputmode="decimal" autocomplete="off" required>
                                    <input type="hidden" name="unit_price" id="unit_price" data-price-value>
                                </div>

                                <!-- IVA -->
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('IVA') ?></label>
                                    <input class="form-control" type="text" name="tax_vat" id="taxVat">
                                </div>

                                <!-- RETENÇÃO -->
                                <div class="col-md-4">
                                    <label class="form-label"><?= t('Retenção') ?></label>
                                    <select class="form-select" name="retention" id="retention_tax">
                                        <option value="0">Não aplicar</option>
                                        <option value="6.5">Aplicar (6.5%)</option>
                                    </select>
                                </div>

                            </div>
                        </div>

                        <!-- ========================= -->
                        <!-- 5. FINANCEIRO -->
                        <!-- ========================= -->
                        <div class="soft-card mb-3" id="financeBlock">
                            <h6 class="mb-3 fw-bold"><?= t('Preços') ?></h6>

                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label class="form-label"><?= t('Preço de Custo') ?></label>
                                    <input type="number" class="form-control" name="cost_price" step="0.01">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label"><?= t('Preço de Venda') ?></label>
                                    <input type="number" class="form-control" name="sale_price" step="0.01">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label"><?= t('PVP') ?></label>
                                    <input type="number" class="form-control" name="pvp" step="0.01">
                                </div>

                            </div>
                        </div>

                    </form>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer px-4 pb-4 border-0 p-0" id="itemModalFooter" style="display:none;">
                <button class="btn btn-success w-1/5" id="saveItem">
                    <?= t('Salvar Item') ?>
                </button>

            </div>
        </div>
    </div>
</div>

<script>
(() => {
    if (window.__itemPriceFormattingInstalled) return;
    window.__itemPriceFormattingInstalled = true;

    const canonicalPrice = (value) => {
        let normalized = String(value ?? '').trim().replace(/\s/g, '');
        if (!normalized) return '';

        if (normalized.includes(',')) {
            normalized = normalized.replace(/\./g, '').replace(',', '.');
        } else if ((normalized.match(/\./g) || []).length > 1) {
            normalized = normalized.replace(/\./g, '');
        }

        if (!/^-?\d+(?:\.\d{0,2})?$/.test(normalized)) return '';
        const number = Number(normalized);
        return Number.isFinite(number) ? String(number) : '';
    };

    const displayPrice = (value) => {
        const canonical = canonicalPrice(value);
        if (!canonical) return '';
        const [integer, decimal] = canonical.split('.');
        const sign = integer.startsWith('-') ? '-' : '';
        const digits = sign ? integer.slice(1) : integer;
        const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        const formattedInteger = `${sign}${grouped}`;
        return decimal ? `${formattedInteger},${decimal}` : formattedInteger;
    };

    const priceValueField = (display) => display.form?.querySelector('[data-price-value]');

    document.addEventListener('input', (event) => {
        if (!event.target.matches('[data-price-display]')) return;
        const valueField = priceValueField(event.target);
        if (!valueField) return;
        const canonical = canonicalPrice(event.target.value);
        valueField.value = canonical;
        event.target.setCustomValidity(
            event.target.value.trim() && !canonical ? 'Introduza um preço numérico válido.' : ''
        );
    });

    document.addEventListener('focusin', (event) => {
        if (!event.target.matches('[data-price-display]')) return;
        const valueField = priceValueField(event.target);
        if (!valueField) return;
        const canonical = canonicalPrice(valueField.value);
        if (canonical !== '') event.target.value = canonical;
    });

    document.addEventListener('focusout', (event) => {
        if (!event.target.matches('[data-price-display]')) return;
        const valueField = priceValueField(event.target);
        if (!valueField) return;
        const canonical = canonicalPrice(event.target.value);
        valueField.value = canonical;
        event.target.setCustomValidity(
            event.target.value.trim() && !canonical ? 'Introduza um preço numérico válido.' : ''
        );
        if (canonical !== '') event.target.value = displayPrice(canonical);
    });

    document.addEventListener('click', (event) => {
        const saveButton = event.target.closest('#saveItem, #saveEdit');
        if (!saveButton) return;
        const form = saveButton.id === 'saveItem'
            ? document.getElementById('itemForm')
            : document.getElementById('editItemForm');
        if (form && !form.reportValidity()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
})();
</script>
