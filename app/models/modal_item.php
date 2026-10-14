<style>
    #itemModal .modal-item {
        background: linear-gradient(135deg, #007abd, #6ea8ff);
        color: #fff;
        border-bottom: none;
    }

    #itemModal .modal-item .btn-close {
        filter: invert(1);
    }

    #itemModal .modal-content {
        background: #f5f5f5;
        border-radius: 18px;
        overflow: hidden;
        border: none;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        padding: 0;
    }

    #itemModal .form-control,
    #itemModal .form-select {
        border-radius: 12px;
        padding: 0px 12px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }

    #itemModal .form-control:focus,
    #itemModal .form-select:focus {
        border-color: #007abd;
        box-shadow: 0 0 0 3px rgba(47, 107, 255, 0.1);
    }

    #itemModal .form-label {
        font-weight: 600;
        font-size: 13px;
        margin-bottom: 6px;
    }

    #itemModal .soft-card {
        background: #fff;
        border-radius: 14px;
        padding: 16px;
        border: 1px solid #eef0f6;
    }

    #itemModal .btn-primary {
        background: #007abd;
        border: none;
        border-radius: 12px;
    }

    #itemModal .btn-success {
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
    }

    #itemModal .btn-outline-secondary {
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

    #itemModal .modal-dialog {
        max-width: 700px;
    }

    #itemModal [hidden] {
        display: none !important;
    }

    #itemModal .modal-content {
        display: flex;
        max-height: min(78vh, 680px);
    }

    #itemModal .modal-header,
    #itemModal .modal-footer {
        flex: 0 0 auto;
    }

    #itemModal .modal-body {
        min-height: 0;
        overflow-y: auto;
    }

    #itemModal .selector-search {
        position: relative;
    }

    #itemModal .selector-search .form-control {
        min-height: 54px;
        padding-left: 44px;
        font-size: 1.05rem;
    }

    #itemModal .selector-search .bi-search {
        position: absolute;
        top: 50%;
        left: 15px;
        z-index: 2;
        transform: translateY(-50%);
        color: #64748b;
    }

    #itemModal .selector-categories {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        padding: .65rem 0;
    }

    #itemModal .selector-category {
        border: 1px solid #d7dee8;
        border-radius: 999px;
        background: #fff;
        color: #334155;
        padding: .35rem .7rem;
        font-size: .8rem;
    }

    #itemModal .selector-category:hover,
    #itemModal .selector-category.active {
        border-color: #007abd;
        background: #eaf6fc;
        color: #006aa5;
    }

    #itemModal .selector-results {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    #itemModal .selector-result {
        display: flex;
        width: 100%;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        border: 0;
        border-bottom: 1px solid #e9edf2;
        border-radius: 0;
        background: transparent;
        padding: .65rem .25rem;
        text-align: left;
        transition: background-color .12s ease;
    }

    #itemModal .selector-result:hover,
    #itemModal .selector-result:focus-visible {
        background: #f1f8fc;
        outline: 2px solid #007abd;
        outline-offset: -2px;
    }

    #itemModal .selector-result[aria-selected="true"] {
        background: #eaf6fc;
    }

    #itemModal .selector-result-name {
        color: #172033;
        font-size: .9rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    #itemModal .selector-result-meta {
        color: #64748b;
        font-size: .75rem;
    }

    #itemModal .selector-status {
        padding: .9rem .25rem;
        color: #64748b;
        font-size: .9rem;
    }

    #itemModal .selector-custom {
        border-top: 0;
    }

    #itemModal .quick-create-footer {
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: #fff;
        border-top: 1px solid #e9edf2 !important;
    }

    #itemModal .quick-create-footer p {
        margin: 0;
    }

    #itemModal .quick-category-toggle:focus-visible,
    #itemModal .quick-create-footer button:focus-visible {
        outline: 2px solid #007abd;
        outline-offset: 2px;
    }

    @media (max-width: 575.98px) {
        #itemModal .modal-dialog {
            margin: .25rem;
        }

        #itemModal .modal-content {
            max-height: calc(100dvh - .5rem);
            height: calc(100dvh - .5rem);
        }

        #itemModal .modal-body {
            padding: 1rem !important;
        }

        #itemModal .quick-create-footer {
            align-items: stretch;
            flex-direction: column;
            gap: .55rem;
        }

        #itemModal .quick-create-footer .btn {
            width: 100%;
        }
    }
</style>

<div class="modal fade" id="itemModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header modal-item">
                <div>
                    <h5 class="modal-title mb-1" id="itemModalLabel" style="background: none !important;"><?= t('Adicionar Novo Item') ?></h5>
                    <small class="opacity-75" id="itemModalSubtitle">Pesquise e escolha um item.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body p-3">

                <!-- ========================= -->
                <!-- CADASTRO RÁPIDO -->
                <!-- ========================= -->
                <div id="quickAddPanel">

                    <div class="selector-search mb-3">
                        <i class="bi bi-search" aria-hidden="true"></i>
                        <label class="visually-hidden" for="quickSearch"><?= t('Pesquisar produto ou serviço') ?></label>
                        <input type="search" id="quickSearch" class="form-control" placeholder="Pesquisar produto ou serviço..." autocomplete="off" aria-controls="quickItemsList">
                    </div>

                    <button type="button" class="btn btn-sm btn-link px-0 mb-2 quick-category-toggle" id="quickCategoryToggle" aria-expanded="false" aria-controls="quickCategories">
                        Categoria <span aria-hidden="true">▾</span>
                    </button>
                    <div id="quickCategories" class="selector-categories mb-2" aria-label="Filtrar por categoria" hidden></div>

                    <h6 id="quickResultsHeading" class="small fw-bold text-secondary mb-2">Itens disponíveis</h6>
                    <div id="quickItemsList" class="selector-results mb-3" role="listbox" aria-label="Itens disponíveis">
                        <div class="selector-status" id="quickItemsStatus" role="status">Carregando itens...</div>
                    </div>
                    <button type="button" id="quickRetry" class="btn btn-sm btn-outline-secondary mb-3" hidden>Tentar novamente</button>

                </div>

                <!-- ========================= -->
                <!-- FORMULÁRIO COMPLETO -->
                <!-- ========================= -->
                <div id="fullFormWrapper" style="display:none;">

                    <button type="button" id="btnBackToQuick" class="btn btn-sm btn-link px-0 mb-2" hidden>
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

            <div class="modal-footer quick-create-footer" id="itemQuickFooter" hidden>
                <p class="text-muted small" id="quickCustomPrompt"><?= t('Não encontrou o que precisa?') ?></p>
                <button type="button" id="btnCustomItem" class="btn btn-sm btn-outline-primary rounded-pill">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><span id="quickCustomLabel"><?= t('Criar item personalizado') ?></span>
                </button>
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
            if (event.target.dataset.moneyInitialized === 'true') return;
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
            if (event.target.dataset.moneyInitialized === 'true') return;
            const valueField = priceValueField(event.target);
            if (!valueField) return;
            const canonical = canonicalPrice(valueField.value);
            if (canonical !== '') event.target.value = canonical;
        });

        document.addEventListener('focusout', (event) => {
            if (!event.target.matches('[data-price-display]')) return;
            if (event.target.dataset.moneyInitialized === 'true') return;
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
            const form = saveButton.id === 'saveItem' ?
                document.getElementById('itemForm') :
                document.getElementById('editItemForm');
            if (form && !form.reportValidity()) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    })();
</script>
<script>
    (() => {
        if (window.__itemSelectorInitialized) return;
        window.__itemSelectorInitialized = true;

        const modal = document.getElementById('itemModal');
        if (!modal) return;
        const search = document.getElementById('quickSearch');
        const categories = document.getElementById('quickCategories');
        const categoryToggle = document.getElementById('quickCategoryToggle');
        const results = document.getElementById('quickItemsList');
        const retry = document.getElementById('quickRetry');
        const searchBox = search.closest('.selector-search');
        const heading = document.getElementById('quickResultsHeading');
        let items = [];
        let activeCategory = '';
        let activeResult = -1;
        let loadPromise = null;
        let debounceTimer = null;
        let requestId = 0;

        const normalize = (value) =>
            String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase('pt');

        const itemName = (item) => item.name || item.description || item.code || 'Item';
        const itemCategory = (item) => String(item.category || '').trim();
        const isService = (item) =>
            String(item.item_type_raw || item.item_type || '').toLowerCase().includes('service');
        const itemTypeLabel = (item) => isService(item) ? 'Serviço' : 'Produto';

        function setStatus(message, isError = false) {
            results.replaceChildren();
            const messageEl = document.createElement('div');
            messageEl.className = 'selector-status';
            messageEl.setAttribute('role', isError ? 'alert' : 'status');
            messageEl.textContent = message;
            if (message === 'Carregando itens...') {
                messageEl.setAttribute('aria-busy', 'true');
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm me-2';
                spinner.setAttribute('aria-hidden', 'true');
                messageEl.prepend(spinner);
            }
            results.append(messageEl);
            retry.hidden = !isError;
        }

        function getCategories() {
            const counts = new Map();
            items.forEach((item) => {
                const name = itemCategory(item);
                if (name) counts.set(name, (counts.get(name) || 0) + 1);
            });
            return [...counts.keys()].sort((a, b) =>
                (counts.get(b) - counts.get(a)) || a.localeCompare(b, 'pt')
            );
        }

        function renderCategories() {
            categories.replaceChildren();
            const all = ['Todas as categorias', ...getCategories()];
            all.forEach((name) => {
                const button = document.createElement('button');
                button.type = 'button';
                const category = name === 'Todas as categorias' ? '' : name;
                button.className = 'selector-category' + (activeCategory === category ? ' active' : '');
                button.textContent = name;
                button.setAttribute('aria-pressed', String(activeCategory === category));
                button.addEventListener('click', () => {
                    activeCategory = category;
                    categories.hidden = true;
                    categoryToggle.setAttribute('aria-expanded', 'false');
                    categoryToggle.innerHTML = activeCategory
                        ? `Categoria · ${name} <span aria-hidden="true">▾</span>`
                        : 'Categoria <span aria-hidden="true">▾</span>';
                    renderCategories();
                    renderResults();
                });
                categories.append(button);
            });
        }

        function selectItem(item) {
            const select = document.getElementById('item_select');
            if (!select) return;

            const option = Array.from(select.options).find(
                (candidate) => String(candidate.value) === String(item.id),
            );
            if (!option) {
                setStatus('Este item não está disponível no seletor do documento. Atualize a página e tente novamente.', true);
                return;
            }

            window.jQuery(select).val(String(item.id)).trigger('change');
            bootstrap.Modal.getOrCreateInstance(modal).hide();
        }

        function renderResults() {
            const currentRequest = ++requestId;
            const query = normalize(search.value.trim());
            const filtered = items.filter((item) => {
                if (activeCategory && itemCategory(item) !== activeCategory) return false;
                if (!query) return true;
                return normalize([
                    item.name,
                    item.description,
                    item.code,
                    item.category,
                    item.unit_measure,
                    item.item_type,
                ].join(' ')).includes(query);
            });

            results.replaceChildren();
            activeResult = -1;
            heading.textContent = query ? `Resultados para “${search.value.trim()}”` : 'Itens disponíveis';

            if (!filtered.length) {
                setStatus(
                    query ?
                    `Nenhum item encontrado para “${search.value.trim()}”.` :
                    'Não há itens disponíveis no catálogo.',
                );
                updateCustomLabel();
                return;
            }

            retry.hidden = true;
            const visible = filtered.slice(0, 50);
            visible.forEach((item, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'selector-result';
                button.setAttribute('role', 'option');
                button.setAttribute('aria-selected', 'false');
                button.dataset.resultIndex = String(index);
                button.setAttribute('aria-label', `Adicionar ${itemName(item)}`);

                const text = document.createElement('span');
                text.className = 'min-w-0';
                const name = document.createElement('span');
                name.className = 'selector-result-name d-block';
                name.textContent = itemName(item);
                const meta = document.createElement('span');
                meta.className = 'selector-result-meta';
                meta.textContent = `${itemTypeLabel(item)} · ${item.unit_measure || 'Unidade'}`;
                text.append(name, meta);

                const plus = document.createElement('span');
                plus.className = 'text-primary fs-5';
                plus.setAttribute('aria-hidden', 'true');
                plus.textContent = '+';
                button.append(text, plus);
                button.addEventListener('click', () => selectItem(item));
                results.append(button);
            });

            if (filtered.length > visible.length) {
                const capNotice = document.createElement('div');
                capNotice.className = 'selector-status';
                capNotice.textContent = `A mostrar ${visible.length} de ${filtered.length} itens. Refine a pesquisa para encontrar outros.`;
                results.append(capNotice);
            }
            if (currentRequest !== requestId) return;
            updateCustomLabel();
        }

        function updateCustomLabel() {
            const query = search.value.trim();
            document.getElementById('quickCustomPrompt').textContent = query ?
                'Não encontrou o que procura?' :
                'Não encontrou o que precisa na lista?';
            document.getElementById('quickCustomLabel').textContent = query ?
                `Criar “${query}”` :
                'Criar produto ou serviço';
        }

        function displayCatalog(list) {
            items = Array.isArray(list) ? list : [];
            retry.hidden = true;
            renderCategories();
            if (!items.length) setStatus('Não há itens disponíveis no catálogo.');
            else renderResults();
        }

        function getExistingCatalog() {
            if (window.catalogItems && typeof window.catalogItems === 'object') {
                return Promise.resolve(Object.values(window.catalogItems));
            }
            if (loadPromise) return loadPromise;

            loadPromise = new Promise((resolve, reject) => {
                let settled = false;
                const finish = (callback, value) => {
                    if (settled) return;
                    settled = true;
                    clearTimeout(fallbackTimer);
                    window.removeEventListener('items:catalog-ready', onReady);
                    callback(value);
                };
                const onReady = (event) => finish(resolve, event.detail?.items || []);
                window.addEventListener('items:catalog-ready', onReady, {
                    once: true
                });
                const fallbackTimer = setTimeout(() => {
                    fetch('items/ajax/get_items.php', {
                            headers: {
                                Accept: 'application/json'
                            },
                        })
                        .then((response) => {
                            if (!response.ok) throw new Error(`HTTP ${response.status}`);
                            return response.json();
                        })
                        .then((data) => {
                            if (!Array.isArray(data?.data)) throw new Error('Formato de catálogo inválido.');
                            finish(resolve, data.data);
                        })
                        .catch((error) => finish(reject, error));
                }, 400);
            }).finally(() => {
                loadPromise = null;
            });
            return loadPromise;
        }

        function loadCatalog() {
            if (!document.getElementById('item_select')) {
                searchBox.hidden = false;
                categories.hidden = true;
                heading.hidden = false;
                results.hidden = false;
                return;
            }
            searchBox.hidden = false;
            categories.hidden = true;
            heading.hidden = false;
            results.hidden = false;
            document.getElementById('quickAddPanel').hidden = false;
            document.getElementById('itemQuickFooter').hidden = false;
            setStatus('Carregando itens...');
            getExistingCatalog()
                .then(displayCatalog)
                .catch((error) => {
                    console.error('Não foi possível carregar o catálogo de itens:', error);
                    setStatus('Não foi possível carregar os produtos/serviços.', true);
                });
        }

        search.addEventListener('input', () => {
            if (!document.getElementById('item_select')) return;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(renderResults, 150);
        });

        search.addEventListener('keydown', (event) => {
            if (!document.getElementById('item_select')) return;
            const options = Array.from(results.querySelectorAll('.selector-result'));
            if (!options.length || !['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key)) return;
            event.preventDefault();
            if (event.key === 'Enter') {
                (activeResult >= 0 ? options[activeResult] : options[0])?.click();
                return;
            }
            activeResult = event.key === 'ArrowDown'
                ? (activeResult + 1) % options.length
                : (activeResult <= 0 ? options.length - 1 : activeResult - 1);
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === activeResult)));
            options[activeResult].focus();
        });

        retry.addEventListener('click', () => {
            loadPromise = null;
            loadCatalog();
        });

        results.addEventListener('keydown', (event) => {
            if (!document.getElementById('item_select')) return;
            const options = Array.from(results.querySelectorAll('.selector-result'));
            if (!options.length || !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            const current = options.indexOf(document.activeElement);
            activeResult = event.key === 'ArrowDown'
                ? (current + 1) % options.length
                : (current <= 0 ? options.length - 1 : current - 1);
            options.forEach((option, index) => {
                option.setAttribute('aria-selected', String(index === activeResult));
            });
            options[activeResult].focus();
        });

        results.addEventListener('keydown', (event) => {
            if (!document.getElementById('item_select')) return;
            if (event.key !== 'Enter' || !event.target.matches('.selector-result')) return;
            event.preventDefault();
            event.target.click();
        });

        document.getElementById('btnCustomItem').addEventListener('click', () => {
            if (!document.getElementById('item_select')) return;
            const query = search.value.trim();
            document.getElementById('itemForm').reset();
            document.getElementById('item_id').value = '0';
            document.getElementById('category').dispatchEvent(new Event('change', { bubbles: true }));
            document.getElementById('quickAddPanel').hidden = true;
            document.getElementById('itemQuickFooter').hidden = true;
            document.getElementById('fullFormWrapper').style.display = '';
            document.getElementById('itemModalFooter').style.display = '';
            document.getElementById('btnBackToQuick').hidden = false;
            document.getElementById('itemModal').querySelector('.modal-title').textContent = 'Criar item personalizado';
            document.getElementById('itemModalSubtitle').textContent = 'Preencha os dados do produto ou serviço.';
            const name = document.getElementById('name');
            name.value = query;
            setTimeout(() => name.focus(), 0);
        });

        document.getElementById('btnBackToQuick').addEventListener('click', () => {
            if (!document.getElementById('item_select')) return;
            document.getElementById('quickAddPanel').hidden = false;
            document.getElementById('itemQuickFooter').hidden = false;
            document.getElementById('fullFormWrapper').style.display = 'none';
            document.getElementById('itemModalFooter').style.display = 'none';
            document.getElementById('btnBackToQuick').hidden = true;
            document.getElementById('itemModal').querySelector('.modal-title').textContent =
                'Adicionar Novo Produto/Serviço';
            document.getElementById('itemModalSubtitle').textContent = 'Pesquise e escolha um item.';
            search.focus();
        });

        modal.addEventListener('show.bs.modal', () => {
            if (!document.getElementById('item_select')) return;
            setTimeout(() => {
                if (modal.classList.contains('show') && !searchBox.hidden) search.focus();
            }, 350);
            loadCatalog();
        });

        modal.addEventListener('hidden.bs.modal', () => {
            document.getElementById('quickAddPanel').hidden = false;
            document.getElementById('itemQuickFooter').hidden = true;
            document.getElementById('fullFormWrapper').style.display = 'none';
            document.getElementById('itemModalFooter').style.display = 'none';
            document.getElementById('btnBackToQuick').hidden = true;
            document.getElementById('itemModal').querySelector('.modal-title').textContent =
                'Adicionar Novo Produto/Serviço';
            document.getElementById('itemModalSubtitle').textContent = 'Pesquise e escolha um item.';
            search.value = '';
            activeCategory = '';
            categories.hidden = true;
            categoryToggle.setAttribute('aria-expanded', 'false');
            categoryToggle.innerHTML = 'Categoria <span aria-hidden="true">▾</span>';
            updateCustomLabel();
        });

        categoryToggle.addEventListener('click', () => {
            if (!document.getElementById('item_select')) return;
            const expanded = categoryToggle.getAttribute('aria-expanded') === 'true';
            categoryToggle.setAttribute('aria-expanded', String(!expanded));
            categories.hidden = expanded;
            if (!expanded) categories.querySelector('.selector-category.active')?.focus();
        });
    })();
</script>

<script src="/assets/js/preset_items.js?v=1.2"></script>
<script src="/assets/js/quick_add.js?v=1.6"></script>
<script src="/assets/js/modal_item.js?v=1.9"></script>