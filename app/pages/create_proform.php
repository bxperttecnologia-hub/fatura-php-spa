<?php
require_once '../app/views/layout_creation.php';

/*
 * Ajusta aqui as rotas e o tema do ecrã.
 * $invTheme: 'dark' (igual à captura) ou 'light'.
 */
$homeUrl       = 'index.php';
$listUrl       = 'proforms.php'; // lista de proformas (ajusta se o ficheiro tiver outro nome)
$invTheme      = 'dark';
$retentionRate = 6.5; // % aplicada pela caixa "Aplicar Retenção na Fonte"
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<link rel="stylesheet" href="create_proform/create_invoices.css?v=4.0">
<link href="assets/css/select2.min.css" rel="stylesheet" />
<script src="assets/js/select2.min.js"></script>

<main id="invPage" class="inv-page <?= $invTheme === 'light' ? 'inv-page--light' : '' ?>">
    <div class="container">

        <form id="formFatura" autocomplete="off" novalidate>

            <!-- Campos técnicos (não visíveis) -->
            <input type="hidden" id="id_company" name="id_company" value="<?= (int) $_SESSION['user']['company_id'] ?>">
            <input type="hidden" id="company_id" name="company_id" value="<?= (int) $_SESSION['user']['company_id'] ?>">
            <input type="hidden" id="user_id" name="user_id" value="<?= (int) $_SESSION['user']['id'] ?>">
            <input type="hidden" id="edit_invoice_id" name="edit_invoice_id" value="0">
            <input type="hidden" id="contact_id" name="contact_id">
            <input type="hidden" id="retention" name="retention" value="0.00">
            <input type="hidden" id="due_date" name="due_date" value="0">

            <input type="hidden" name="total_sum">
            <input type="hidden" name="total_discount">
            <input type="hidden" name="subtotal_without_tax">
            <input type="hidden" name="total_tax">
            <input type="hidden" name="retention_value">
            <input type="hidden" name="final_total">

            <div class="d-none">
                <select id="currency" name="currency" required>
                    <?= currencySelects(); ?>
                </select>
            </div>

            <h1 class="visually-hidden"><?= t('Emissão de Proforma') ?></h1>

            <!-- ============ TOPO ============ -->
            <div class="inv-top">
                <div>
                    <div class="inv-breadcrumb" role="navigation" aria-label="breadcrumb">
                        <a href="<?= $homeUrl ?>"><i class="bi bi-house"></i> <?= t('Início') ?></a>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <a href="<?= $listUrl ?>"><?= t('Proformas') ?></a>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <span id="inv_crumb_current" aria-current="page"><?= t('Emitir Nova Proforma') ?></span>
                    </div>
                    <a href="<?= $listUrl ?>" class="inv-back">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= t('Voltar à lista de proformas') ?>
                    </a>
                </div>

                <div class="inv-top-actions">
                    <button type="button" id="cancelInvoiceBtn" class="inv-btn inv-btn-dark" data-href="<?= $listUrl ?>">
                        <?= t('Cancelar') ?>
                    </button>
                    <button type="button" id="saveInvoiceBtn" class="inv-btn inv-btn-primary">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        <span class="btn-label"><?= t('Emitir Proforma') ?></span>
                    </button>
                </div>
            </div><!-- /inv-top -->

            <!-- ============ DADOS DO DOCUMENTO & CLIENTE ============ -->
            <div class="inv-card">
                <h2 class="inv-card-title"><?= t('Dados do Documento & Cliente') ?></h2>

                <div class="inv-grid-4">

                    <!-- SÉRIE -->
                    <div class="inv-field">
                        <label class="inv-label" for="series">
                            <?= t('Série') ?> *
                        </label>

                        <input
                            type="text"
                            class="inv-input"
                            id="series"
                            name="series"
                            readonly>
                    </div>


                    <!-- CLIENTE -->
                    <div class="inv-field">

                        <label class="inv-label" for="contact-select">
                            <?= t('Cliente Destinatário') ?> *
                        </label>

                        <div id="select-contact-container" class="inv-with-action">

                            <!-- ID DO CLIENTE SELECIONADO -->
                            <input
                                type="hidden"
                                id="contact_id"
                                name="contact_id"
                                value="">

                            <!-- Campo usado pelo componente de seleção -->
                            <input
                                type="hidden"
                                id="contact-select"
                                value="">

                            <!-- Abrir modal de seleção -->
                            <a
                                href="#"
                                class="inv-select-btn"
                                id="btn-select-contact"
                                title="<?= t('Selecionar cliente') ?>"
                                aria-label="<?= t('Selecionar cliente') ?>">
                                <i class="bi bi-person" aria-hidden="true"></i>

                                <span id="contact-select-label">
                                    <?= t('Selecionar Cliente') ?>
                                </span>
                            </a>

                            <!-- Novo cliente -->
                            <a
                                href="register_contact.php"
                                class="inv-icon-btn"
                                title="<?= t('Novo cliente') ?>"
                                aria-label="<?= t('Novo cliente') ?>">
                                <i class="bi bi-person-plus" aria-hidden="true"></i>
                            </a>

                        </div>

                    </div>


                    <!-- DATA DE EMISSÃO -->
                    <div class="inv-field">

                        <label class="inv-label" for="issue_date">
                            <?= t('Data de Emissão') ?> *
                        </label>

                        <input
                            type="date"
                            class="inv-input"
                            id="issue_date"
                            name="issue_date"
                            value="<?= date('Y-m-d') ?>"
                            required>

                    </div>


                    <!-- DATA DE VENCIMENTO -->
                    <div class="inv-field">

                        <label class="inv-label" for="due_date_picker">
                            <?= t('Data de Vencimento') ?> *
                        </label>

                        <input
                            type="date"
                            class="inv-input"
                            id="due_date_picker">

                    </div>

                </div>

                <hr class="inv-divider">

                <div class="inv-terms">
                    <label class="inv-check" for="apply_retention">
                        <input type="checkbox" id="apply_retention" data-rate="<?= $retentionRate ?>">
                        <span><?= t('Aplicar Retenção na Fonte de Angola') ?> (<?= str_replace('.', ',', (string) $retentionRate) ?>% <?= t('de acordo com o Código do IRT/IVA') ?>)</span>
                    </label>

                    <div class="inv-chips" id="due_chips" role="group" aria-label="<?= t('Prazo de pagamento') ?>">
                        <span><?= t('Prazo') ?>:</span>
                        <button type="button" class="inv-chip" data-days="0"><?= t('Pronto pagamento') ?></button>
                        <button type="button" class="inv-chip" data-days="15">15 <?= t('dias') ?></button>
                        <button type="button" class="inv-chip" data-days="30">30 <?= t('dias') ?></button>
                        <button type="button" class="inv-chip" data-days="45">45 <?= t('dias') ?></button>
                        <button type="button" class="inv-chip" data-days="60">60 <?= t('dias') ?></button>
                        <button type="button" class="inv-chip" data-days="90">90 <?= t('dias') ?></button>
                    </div>
                </div>

                <div class="inv-fx" id="exchange_rate_container" style="display: none;">
                    <label class="inv-label" for="manual_exchange_rate"><?= t('Câmbio') ?></label>
                    <input type="number" step="0.0001" class="inv-input" id="manual_exchange_rate" name="manual_exchange_rate">
                </div>

                <!-- Ficha do cliente: preenchida pelo JS quando se escolhe um cliente -->
                <div id="contact-form" class="inv-client" style="display: none;">
                    <div class="inv-client-grid">
                        <div class="inv-field">
                            <label class="inv-label" for="contact_name"><?= t('Nome') ?></label>
                            <input type="text" class="inv-input" id="contact_name" name="name">
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="contributor"><?= t('NIF') ?></label>
                            <input type="text" class="inv-input" id="contributor" name="contributor">
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="email"><?= t('Email') ?></label>
                            <input type="email" class="inv-input" id="email" name="email">
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="po_box"><?= t('Telefone') ?></label>
                            <input type="tel" class="inv-input" id="po_box" name="po_box">
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="country"><?= t('País') ?></label>
                            <select class="inv-select" id="country" name="country">
                                <option value=""><?= t('Carregando países') ?>...</option>
                            </select>
                        </div>
                        <div class="inv-field">
                            <label class="inv-label" for="city"><?= t('Cidade') ?></label>
                            <select class="inv-select" id="city" name="city">
                                <option value=""><?= t('Escolha um país primeiro') ?></option>
                            </select>
                        </div>
                        <div class="inv-field inv-field-wide">
                            <label class="inv-label" for="address"><?= t('Endereço') ?></label>
                            <textarea class="inv-input" id="address" name="address"></textarea>
                        </div>
                    </div>
                </div>
            </div><!-- /inv-card -->

            <!-- ============ LINHAS DO DOCUMENTO ============ -->
            <div class="inv-card" id="lines_card">
                <div class="inv-card-head">
                    <h2 class="inv-card-title">
                        <?= t('Linhas do Documento') ?>
                        <span class="inv-count" id="lines_count">0</span>
                    </h2>

                    <div class="inv-card-actions">
                        <button type="button" class="inv-btn inv-btn-ghost" data-bs-toggle="modal" data-bs-target="#itemModal">
                            <i class="bi bi-box-seam" aria-hidden="true"></i> <?= t('Novo produto/serviço') ?>
                        </button>
                        <button type="button" class="inv-btn inv-btn-ghost" id="addLineBtn">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= t('Adicionar Linha') ?>
                        </button>
                    </div>
                </div>

                <div class="inv-lines-head" aria-hidden="true">
                    <div><?= t('Artigo / Descrição') ?></div>
                    <div><?= t('Qtd') ?></div>
                    <div><?= t('P. Unitário') ?></div>
                    <div><?= t('Desc (%)') ?></div>
                    <div><?= t('IVA (%)') ?></div>
                    <div><?= t('Total Linha') ?></div>
                    <div></div>
                </div>

                <div id="items_list"></div>

                <div class="inv-empty" id="items_empty">
                    <i class="bi bi-receipt" aria-hidden="true"></i>
                    <span><?= t('Ainda não há linhas neste documento. Escolha um artigo do catálogo para começar.') ?></span>
                </div>

                <div class="inv-picker">
                    <label class="inv-label" for="item_select"><?= t('Adicionar artigo do catálogo') ?></label>
                    <select class="select2 inv-select" id="item_select" data-width="100%">
                        <option value=""><?= t('Carregando itens...') ?></option>
                    </select>
                </div>

                <!-- Observações + resumo -->
                <div class="inv-footer">
                    <div>
                        <label class="inv-label" for="observation"><?= t('Observações / Instruções de Pagamento') ?></label>
                        <textarea class="inv-input inv-textarea" id="observation" name="observation"
                            placeholder="<?= t('ex: Coordenadas bancárias para liquidação ou referência do cliente…') ?>"></textarea>

                        <div class="inv-tax-wrap">
                            <p class="inv-tax-title"><?= t('Resumo por taxa de IVA') ?></p>
                            <table class="inv-tax">
                                <thead>
                                    <tr>
                                        <th><?= t('Taxa') ?></th>
                                        <th><?= t('Incidência') ?></th>
                                        <th><?= t('Valor (IVA)') ?></th>
                                        <th><?= t('Retenção') ?></th>
                                        <th><?= t('Total') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tax_summary">
                                    <tr>
                                        <td colspan="5" class="inv-tax-empty"><?= t('Nenhum item adicionado') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="inv-summary" role="complementary" aria-label="<?= t('Resumo do documento') ?>">
                        <div class="inv-sum-row">
                            <span><?= t('Soma') ?></span>
                            <span id="total_sum">0,00</span>
                        </div>
                        <div class="inv-sum-row">
                            <span><?= t('Desconto') ?></span>
                            <span id="total_discount">0,00</span>
                        </div>
                        <div class="inv-sum-row">
                            <span><?= t('Incidência (Subtotal Líquido)') ?>:</span>
                            <span id="subtotal_without_tax">0,00</span>
                        </div>
                        <div class="inv-sum-row">
                            <span><?= t('Total de IVA') ?>:</span>
                            <span id="total_tax">0,00</span>
                        </div>
                        <div class="inv-sum-row d-none" id="retention_sumary">
                            <span><?= t('Retenção na Fonte') ?>:</span>
                            <span id="retention_value">0,00</span>
                        </div>
                        <div class="inv-sum-row inv-sum-total">
                            <span><?= t('Total a Liquidar') ?>:</span>
                            <span id="final_total">0,00</span>
                        </div>
                    </div><!-- /inv-summary -->
                </div>
            </div><!-- /inv-card -->

        </form>
    </div>
</main>

<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let company = null;

    const form = document.getElementById("formFatura");
    const DOCUMENT_DRAFT_KEY = "proformaDraft";

    /* ===== RASCUNHO (localStorage) ===== */
    function saveDraft() {
        if (!form) return;

        const data = new FormData(form);
        const obj = {
            form: {},
            meta: {
                contact_select: document.getElementById("contact-select")?.value || "",
            },
            items: []
        };

        data.forEach((value, key) => {
            if (key === "edit_invoice_id" && (!value || value === "0")) return;
            obj.form[key] = value;
        });

        document.querySelectorAll("#items_list .item-list").forEach((row) => {
            obj.items.push({
                id: row.dataset.id,
                code: row.querySelector(".field_code")?.value || "",
                description: row.querySelector(".field_description")?.value || "",
                quantity: row.querySelector(".field_qtd")?.value || 1,
                unit_price: row.querySelector(".field_price")?.value || 0,
                tax: row.querySelector(".field_tax")?.value || 0,
                discount: row.querySelector(".field_desc")?.value || 0,
                retention: row.querySelector(".field_retention")?.value || 0
            });
        });

        localStorage.setItem(DOCUMENT_DRAFT_KEY, JSON.stringify(obj));
    }

    function readDraft() {
        try {
            return JSON.parse(localStorage.getItem(DOCUMENT_DRAFT_KEY) || "null");
        } catch (e) {
            return null;
        }
    }

    /* Repõe os campos simples. O cliente e as linhas são repostos pelo create_invoices.js */
    function loadDraft() {
        const draft = readDraft();
        if (!form || !draft) return;

        Object.keys(draft.form || {}).forEach(key => {
            if (key === "series") return; // a série é sempre o ano corrente
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.value = draft.form[key];
        });
    }

    /* Chamada pelo create_invoices.js quando addItemRow já existe */
    function restoreDraftItems(addRow) {
        const draft = readDraft();
        const items = Array.isArray(draft?.items) ? draft.items : [];

        items.forEach(item => {
            const id = item.id || item.item_id;
            if (!id) return;

            addRow({
                id: id,
                code: item.code || "",
                description: item.description || item.name || "",
                line_quantity: item.quantity || 1,
                unit_price: item.unit_price || 0,
                tax: item.tax || 0,
                discount: item.discount || 0,
                retention: item.retention || 0
            });
        });
    }

    function clearDraft() {
        localStorage.removeItem(DOCUMENT_DRAFT_KEY);
    }

    window.addEventListener("beforeunload", function(event) {
        if (localStorage.getItem(DOCUMENT_DRAFT_KEY)) {
            event.preventDefault();
            event.returnValue = "Há um documento em rascunho. Deseja terminar a emissão antes de sair?";
        }
    });

    form?.addEventListener("input", saveDraft);
    form?.addEventListener("change", saveDraft);

    document.addEventListener("DOMContentLoaded", loadDraft);

    /* ===== MOEDA ===== */
    let userCurrency = "<?= $_SESSION['user']['iso_code'] ?>";
    let currencySymbol = "<?= $_SESSION['user']['symbol'] ?>";
    let currencyPosition = "<?= $_SESSION['user']['position'] ?>";

    /* ===== SÉRIE ===== */
    const seriesField = document.getElementById("series");

    if (seriesField) {
        seriesField.value = new Date().getFullYear();

        ["keydown", "paste", "drop"].forEach(evt =>
            seriesField.addEventListener(evt, e => e.preventDefault())
        );
    }

    /* ===== EMPRESA ===== */
    async function fetchCompany() {
        try {
            const response = await fetch(`assets/ajax/company_data.php`);

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            const data = await response.json();

            if (data?.data) {
                company = data.data;
            } else {
                console.warn("Nenhum dado encontrado");
            }
        } catch (error) {
            console.error("Erro ao buscar empresa:", error);
        }
    }

    setTimeout(fetchCompany, 100);
</script>

<script src="create_proform/create_invoices.js?v=4.0"></script>