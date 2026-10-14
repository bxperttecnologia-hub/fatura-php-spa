<?php
require_once '../app/views/layout_creation.php';

/*
 * Ajusta aqui as rotas e o tema do ecrã.
 * $invTheme: 'dark' (igual à captura) ou 'light'.
 */
$homeUrl       = 'index.php';
$listUrl       = 'invoices.php';
$invTheme      = 'dark';
$retentionRate = 6.5; // % aplicada pela caixa "Aplicar Retenção na Fonte"
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<style id="inv-styles">
    /* =====================================================================
   EMISSÃO DE DOCUMENTO — create_invoices.css
   Tudo vive sob .inv-page e usa o prefixo .inv- para não colidir com o
   Bootstrap nem com o resto da aplicação.
   Cores e raios estão nos tokens abaixo: muda-os aqui e o ecrã inteiro segue.
   Para o tema claro, adiciona a classe .inv-page--light ao <main>.
   ===================================================================== */

    .inv-page {
        --inv-bg: #fff;
        --inv-card: #fff;
        --inv-card-border: #3030301e;
        --inv-head: #0d8bdc;
        --inv-field: #fff;
        --inv-field-border: #3030301e;
        --inv-text: #000;
        --inv-muted: #8593ad;
        --inv-primary: #0d8bdc;
        --inv-primary-hover: #26a0ee;
        --inv-danger: #ef5b6b;
        --inv-radius-card: 24px;
        --inv-radius: 12px;

        color-scheme: dark;
        background: var(--inv-bg);
        color: var(--inv-text);
        padding: 3rem 0 4rem;
        min-height: 100vh;
    }

    .inv-page--light {
        --inv-bg: #f3f6fb;
        --inv-card: #ffffff;
        --inv-card-border: #e2e8f2;
        --inv-head: #eef2f9;
        --inv-field: #f0f3f9;
        --inv-field-border: #dfe6f1;
        --inv-text: #000;
        --inv-muted: #66738e;
        --inv-primary: #0a7fc9;
        --inv-primary-hover: #0b6fb0;
        color-scheme: light;
    }

    .inv-page *,
    .inv-page *::before,
    .inv-page *::after {
        box-sizing: border-box;
    }

    .inv-page :focus-visible {
        outline: 2px solid var(--inv-primary);
        outline-offset: 2px;
    }

    /* ---------- Topo: navegação + acções ---------- */

    .inv-top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem 2rem;
        margin-bottom: 2rem;
    }

    .inv-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.6rem;
        margin-bottom: 1.4rem;
        color: var(--inv-muted);
        font-size: 0.95rem;
    }

    .inv-breadcrumb a,
    .inv-back {
        color: var(--inv-muted);
        text-decoration: none;
    }

    #cancelInvoiceBtn {
        background: #ef5b6b;
        color: #fff;
    }

    .inv-breadcrumb a:hover,
    .inv-back:hover {
        color: var(--inv-text);
    }

    .inv-breadcrumb [aria-current="page"] {
        color: var(--inv-text);
        font-weight: 600;
    }

    .inv-breadcrumb .bi-chevron-right {
        font-size: 0.7rem;
    }

    .inv-back {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        font-size: 0.95rem;
    }

    .inv-top-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.9rem;
    }

    /* ---------- Botões ---------- */

    .inv-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        height: 46px;
        padding: 0 1.4rem;
        border: 1px solid transparent;
        border-radius: var(--inv-radius);
        font: inherit;
        font-size: 1rem;
        font-weight: 600;
        line-height: 1;
        cursor: pointer;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .inv-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .inv-btn-primary {
        background: var(--inv-primary);
        color: #fff;
    }

    .inv-btn-primary:hover:not(:disabled) {
        background: var(--inv-primary-hover);
    }

    .inv-btn-dark {
        background: var(--inv-field);
        color: var(--inv-text);
    }

    .inv-btn-dark:hover {
        border-color: var(--inv-muted);
    }

    .inv-btn-ghost {
        height: 38px;
        padding: 0 1rem;
        background: transparent;
        border-color: var(--inv-field-border);
        color: var(--inv-text);
        font-size: 0.9rem;
    }

    .inv-btn-ghost:hover {
        border-color: var(--inv-primary);
        color: var(--inv-primary-hover);
    }

    /* ---------- Cartões ---------- */

    .inv-card {
        margin-bottom: 1.75rem;
        padding: 2rem;
        background: var(--inv-card);
        border: 1px solid var(--inv-card-border);
        border-radius: var(--inv-radius-card);
    }

    .inv-card-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .inv-card-title {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--inv-text);
    }

    .inv-card>.inv-card-title {
        margin-bottom: 1.5rem;
    }

    .inv-card-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .inv-count {
        display: inline-block;
        min-width: 1.6rem;
        margin-left: 0.5rem;
        padding: 0.1rem 0.5rem;
        background: var(--inv-field);
        border-radius: 999px;
        color: var(--inv-muted);
        font-size: 0.8rem;
        font-weight: 600;
        text-align: center;
        vertical-align: middle;
    }

    /* ---------- Campos ---------- */

    .inv-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .inv-field {
        min-width: 0;
    }

    .inv-label {
        display: block;
        margin-bottom: 0.5rem;
        color: var(--inv-text);
        font-size: 0.88rem;
        font-weight: 600;
    }

    .inv-input,
    .inv-select {
        display: block;
        width: 100%;
        height: 44px;
        padding: 0 1rem;
        background: var(--inv-field);
        border: 1px solid var(--inv-field-border);
        border-radius: var(--inv-radius);
        color: var(--inv-text);
        font: inherit;
        font-size: 0.92rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .inv-select-btn {
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        /* flex: 0 0 44px; */
        height: 44px;
        background: var(--inv-field);
        border: 1px solid var(--inv-field-border);
        border-radius: var(--inv-radius);
        color: var(--inv-primary-hover);
        text-decoration: none;
    }

    .inv-select-btn:hover {
        border-color: var(--inv-primary);
        color: var(--inv-bg);
        background: #0a7fc9;
    }

    .inv-input:focus,
    .inv-select:focus {
        border-color: var(--inv-primary);
        box-shadow: 0 0 0 3px rgba(13, 139, 220, 0.25);
        outline: none;
    }

    .inv-input[readonly] {
        color: var(--inv-muted);
    }

    .inv-select {
        appearance: none;
        padding-right: 2.5rem;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5' fill='none' stroke='%238593ad' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
    }

    .inv-textarea {
        height: auto;
        min-height: 118px;
        padding: 0.8rem 1rem;
        resize: vertical;
    }

    .inv-with-action {
        display: flex;
        gap: 0.5rem;
    }

    .inv-with-action>select {
        flex: 1 1 auto;
        min-width: 0;
    }

    .inv-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 44px;
        height: 44px;
        background: var(--inv-field);
        border: 1px solid var(--inv-field-border);
        border-radius: var(--inv-radius);
        color: var(--inv-primary-hover);
        text-decoration: none;
    }

    .inv-icon-btn:hover {
        border-color: var(--inv-primary);
    }

    .inv-divider {
        height: 0;
        margin: 1.5rem 0 1.25rem;
        border: 0;
        border-top: 1px solid var(--inv-card-border);
    }

    .inv-terms {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem 2rem;
    }

    .inv-check {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        margin: 0;
        color: var(--inv-text);
        font-size: 0.92rem;
        cursor: pointer;
    }

    .inv-check input {
        width: 18px;
        height: 18px;
        margin: 0;
        accent-color: var(--inv-primary);
    }

    .inv-chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        color: var(--inv-muted);
        font-size: 0.88rem;
    }

    .inv-chip {
        height: 34px;
        padding: 0 0.95rem;
        background: transparent;
        border: 1px solid var(--inv-field-border);
        border-radius: 999px;
        color: var(--inv-muted);
        font: inherit;
        font-size: 0.85rem;
        cursor: pointer;
        transition: border-color 0.15s ease, color 0.15s ease, background-color 0.15s ease;
    }

    .inv-chip:hover {
        color: var(--inv-text);
    }

    .inv-chip.active {
        background: rgba(13, 139, 220, 0.15);
        border-color: var(--inv-primary);
        color: var(--inv-text);
    }

    .inv-fx {
        max-width: 260px;
        margin-top: 1.25rem;
    }

    /* ---------- Ficha do cliente (só leitura) ---------- */

    .inv-client {
        margin-top: 1.5rem;
        padding: 1.25rem 1.5rem;
        background: rgba(127, 145, 180, 0.05);
        border: 1px dashed var(--inv-field-border);
        border-radius: 16px;
    }

    .inv-client-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem 1.5rem;
    }

    .inv-client-grid .inv-field-wide {
        grid-column: 1 / -1;
    }

    .inv-client .inv-label {
        margin-bottom: 0.25rem;
        color: var(--inv-muted);
        font-size: 0.78rem;
        font-weight: 500;
    }

    .inv-client .inv-input {
        height: 34px;
        padding: 0;
        background: transparent;
        border-color: transparent;
        box-shadow: none;
    }

    .inv-client textarea.inv-input {
        height: 52px;
        min-height: 0;
        padding: 0;
        resize: none;
    }

    .inv-client .inv-input:disabled {
        color: var(--inv-text);
        -webkit-text-fill-color: var(--inv-text);
        opacity: 1;
    }

    /* ---------- Linhas do documento ---------- */

    .inv-lines-head,
    .inv-line {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 84px 140px 92px 96px 150px 44px;
        gap: 0.75rem;
        align-items: center;
    }

    .inv-lines-head {
        display: none;
        padding: 0.9rem 1rem;
        background: var(--inv-head);
        border-radius: var(--inv-radius);
        color: #fff;
        font-size: 0.85rem;
        font-weight: 700;
    }

    .inv-lines-head>div:not(:first-child) {
        text-align: center;
    }

    .has-lines .inv-lines-head {
        display: grid;
    }

    .inv-line {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--inv-card-border);
    }

    .inv-cell {
        min-width: 0;
    }

    .inv-cell-desc {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .inv-line-desc {
        width: 100%;
        padding: 0;
        background: transparent;
        border: 0;
        color: var(--inv-text);
        font: inherit;
        font-size: 0.95rem;
        font-weight: 600;
        text-overflow: ellipsis;
    }

    .inv-line-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .inv-line-code {
        width: 8.5rem;
        max-width: 100%;
        padding: 0;
        background: transparent;
        border: 0;
        color: var(--inv-muted);
        font: inherit;
        font-size: 0.78rem;
    }

    .inv-tag {
        padding: 0.05rem 0.55rem;
        background: rgba(13, 139, 220, 0.15);
        border-radius: 999px;
        color: var(--inv-primary-hover);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .inv-cell-input {
        display: block;
        width: 100%;
        height: 38px;
        padding: 0 0.5rem;
        background: var(--inv-field);
        border: 1px solid transparent;
        border-radius: 10px;
        color: var(--inv-text);
        font: inherit;
        font-size: 0.9rem;
        font-variant-numeric: tabular-nums;
        text-align: center;
        -moz-appearance: textfield;
    }

    .inv-cell-input::-webkit-outer-spin-button,
    .inv-cell-input::-webkit-inner-spin-button {
        margin: 0;
        -webkit-appearance: none;
    }

    .inv-cell-input:focus {
        border-color: var(--inv-primary);
        outline: none;
    }

    .inv-pill {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 38px;
        background: var(--inv-field);
        border-radius: 10px;
        color: var(--inv-text);
        font-size: 0.9rem;
    }

    .inv-pill input {
        width: 2.4ch;
        padding: 0;
        background: transparent;
        border: 0;
        color: inherit;
        font: inherit;
        text-align: right;
        -moz-appearance: textfield;
    }

    .inv-pill input::-webkit-outer-spin-button,
    .inv-pill input::-webkit-inner-spin-button {
        margin: 0;
        -webkit-appearance: none;
    }

    .inv-cell-total {
        color: var(--inv-text);
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        text-align: right;
        white-space: nowrap;
    }

    .inv-trash {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: transparent;
        border: 0;
        border-radius: 8px;
        color: var(--inv-muted);
        cursor: pointer;
    }

    .inv-trash:hover {
        background: rgba(239, 91, 107, 0.12);
        color: var(--inv-danger);
    }

    .inv-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.4rem;
        padding: 2.25rem 1rem 1.5rem;
        color: var(--inv-muted);
        font-size: 0.92rem;
        text-align: center;
    }

    .inv-empty .bi {
        font-size: 1.6rem;
    }

    .has-lines .inv-empty {
        display: none;
    }

    .inv-picker {
        margin-top: 1rem;
        padding: 1rem 1.25rem 1.25rem;
        border: 1px dashed var(--inv-field-border);
        border-radius: 16px;
    }

    /* ---------- Rodapé: observações + resumo ---------- */

    .inv-footer {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
        gap: 2rem;
        margin-top: 1.75rem;
        padding-top: 1.75rem;
        border-top: 1px solid var(--inv-card-border);
    }

    .inv-tax-wrap {
        margin-top: 1.25rem;
        overflow-x: auto;
    }

    .inv-tax-title {
        margin: 0 0 0.5rem;
        color: var(--inv-muted);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .inv-tax {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        font-variant-numeric: tabular-nums;
    }

    .inv-tax th,
    .inv-tax td {
        padding: 0.5rem 0.6rem;
        border-bottom: 1px solid var(--inv-card-border);
        text-align: right;
        white-space: nowrap;
    }

    .inv-tax th {
        color: #fff;
        font-weight: 600;
    }

    .inv-tax th:first-child,
    .inv-tax td:first-child {
        text-align: left;
    }

    .inv-tax td {
        color: #fff;
    }

    .inv-tax td.inv-tax-empty {
        color: var(--inv-muted);
        text-align: center;
    }

    .inv-summary {
        align-self: start;
        padding: 1.25rem 1.5rem;
        background: rgba(127, 145, 180, 0.05);
        border: 1px solid var(--inv-card-border);
        border-radius: 18px;
    }

    .inv-sum-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.35rem 0;
        color: var(--inv-muted);
        font-size: 0.92rem;
        font-variant-numeric: tabular-nums;
    }

    .inv-sum-row.d-none {
        display: none;
    }

    .inv-sum-total {
        margin-top: 0.7rem;
        padding-top: 0.95rem;
        border-top: 1px solid var(--inv-card-border);
        color: var(--inv-text);
        font-size: 1.25rem;
        font-weight: 700;
    }

    /* ---------- Select2 (o dropdown vive dentro de #invPage) ---------- */

    .inv-page .select2-container {
        width: 100% !important;
    }

    .inv-page .select2-container--default .select2-selection--single {
        display: flex;
        align-items: center;
        height: 44px;
        background: var(--inv-field);
        border: 1px solid var(--inv-field-border);
        border-radius: var(--inv-radius);
    }

    .inv-page .select2-container--default .select2-selection--single .select2-selection__rendered {
        width: 100%;
        padding: 0 2.5rem 0 1rem;
        color: var(--inv-text);
        font-size: 0.92rem;
        line-height: 44px;
    }

    .inv-page .select2-container--default .select2-selection--single .select2-selection__arrow {
        right: 0.75rem;
        height: 44px;
    }

    .inv-page .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: var(--inv-muted) transparent transparent transparent;
    }

    .inv-page .select2-container--default.select2-container--open .select2-selection--single,
    .inv-page .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--inv-primary);
    }

    .inv-page .select2-container--default.select2-container--disabled .select2-selection--single {
        background: transparent;
        border-color: transparent;
        cursor: default;
    }

    .inv-page .select2-container--default.select2-container--disabled .select2-selection__arrow {
        display: none;
    }

    .inv-page .select2-container--default.select2-container--disabled .select2-selection__rendered {
        padding: 0;
        color: var(--inv-text);
    }

    .inv-page .select2-dropdown {
        overflow: hidden;
        background: var(--inv-card);
        border: 1px solid var(--inv-card-border);
        border-radius: var(--inv-radius);
        color: var(--inv-text);
    }

    .inv-page .select2-container--default .select2-search--dropdown .select2-search__field {
        height: 38px;
        padding: 0 0.75rem;
        background: var(--inv-field);
        border: 1px solid var(--inv-field-border);
        border-radius: 8px;
        color: var(--inv-text);
    }

    .inv-page .select2-results__option {
        padding: 0.6rem 1rem;
        color: var(--inv-text);
    }

    .inv-page .select2-container--default .select2-results__option--highlighted {
        background: var(--inv-primary);
        color: #fff;
    }

    .inv-page .select2-container--default .select2-results__option[aria-selected="true"],
    .inv-page .select2-container--default .select2-results__option--selected {
        background: var(--inv-primary);
    }

    /* ---------- Responsivo ---------- */

    @media (max-width: 1199.98px) {
        .inv-grid-4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .inv-footer {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 991.98px) {
        .inv-card {
            padding: 1.4rem;
        }

        .inv-client-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        /* Linhas passam a cartões: cada célula mostra o seu rótulo */
        .has-lines .inv-lines-head {
            display: none;
        }

        .inv-line {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: 0.75rem;
            padding: 1rem;
            border: 1px solid var(--inv-card-border);
            border-radius: 16px;
        }

        .inv-cell[data-label]::before {
            content: attr(data-label);
            display: block;
            margin-bottom: 0.25rem;
            color: var(--inv-muted);
            font-size: 0.75rem;
        }

        .inv-cell-desc,
        .inv-cell-total {
            grid-column: 1 / -1;
        }

        .inv-cell-desc::before {
            display: none !important;
        }

        .inv-cell-act {
            grid-column: 1 / -1;
            justify-self: end;
        }
    }

    @media (max-width: 575.98px) {

        .inv-grid-4,
        .inv-client-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .inv-top-actions,
        .inv-top-actions .inv-btn {
            width: 100%;
        }

        .inv-card-actions .inv-btn {
            flex: 1 1 auto;
        }
    }

    .inv-with-action .select2-container {
        flex: 1 1 auto;
        min-width: 0;
    }
</style>
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
            <input type="hidden" id="anonymous_client" name="anonymous_client" value="0">
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

            <h1 class="visually-hidden"><?= t('Emissão de Fatura') ?></h1>

            <!-- ============ TOPO ============ -->
            <div class="inv-top">
                <div>
                    <div class="inv-breadcrumb" role="navigation" aria-label="breadcrumb">
                        <a href="<?= $homeUrl ?>"><i class="bi bi-house"></i> <?= t('Início') ?></a>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <a href="<?= $listUrl ?>"><?= t('Facturação') ?></a>
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <span id="inv_crumb_current" aria-current="page"><?= t('Emitir Novo Documento') ?></span>
                    </div>
                    <a href="<?= $listUrl ?>" class="inv-back">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= t('Voltar à lista de facturas') ?>
                    </a>
                </div>

                <div class="inv-top-actions">
                    <button type="button" id="cancelInvoiceBtn" class="inv-btn inv-btn-dark" data-href="<?= $listUrl ?>">
                        <?= t('Cancelar') ?>
                    </button>
                    <button type="button" id="saveInvoiceBtn" class="inv-btn inv-btn-primary">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                        <span class="btn-label"><?= t('Emitir Fatura') ?></span>
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
                            id="due_date_picker"
                            name="due_date">

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
    const DOCUMENT_DRAFT_KEY = "invoiceDraft";

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

<!-- v=2.2: nova versão para o navegador não usar o JS antigo em cache -->
<script src="create_invoices/create_invoices.js?v=2.3"></script>