<?php
require_once '../app/views/layout_creation.php';
$listUrl = '/guides';
?>

<link rel="stylesheet" href="/assets/css/documents.css?v=1.3">
<link href="/assets/css/select2.min.css" rel="stylesheet">
<style>
    .guide-page {
        --guide-bg:#fff; --guide-card:#fff; --guide-card-border:#3030301e;
        --guide-head:#eef2f9; --guide-field:#f0f3f9; --guide-field-border:#dfe6f1;
        --guide-text:#172033; --guide-muted:#66738e; --guide-primary:#0a7fc9;
        --guide-primary-hover:#0b6fb0; --guide-danger:#ef5b6b;
        --guide-radius-card:24px; --guide-radius:12px;
        color-scheme:light; color:var(--guide-text); padding:3rem 0 4rem; min-height:100vh;
    }
    .guide-page *,.guide-page *::before,.guide-page *::after{box-sizing:border-box}
    .guide-page :focus-visible{outline:2px solid var(--guide-primary);outline-offset:2px}
    .guide-top{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin-bottom:2rem}
    .guide-back{display:inline-flex;align-items:center;gap:.6rem;color:var(--guide-muted);text-decoration:none}
    .guide-back:hover{color:var(--guide-text)}
    .guide-actions{display:flex;flex-wrap:wrap;gap:.6rem}
    .guide-btn{display:inline-flex;align-items:center;justify-content:center;gap:.6rem;height:42px;padding:0 1.25rem;border:1px solid transparent;border-radius:999px;font-weight:600;text-decoration:none;cursor:pointer}
    .guide-btn-primary{background:var(--guide-primary);color:#fff}
    .guide-btn-primary:hover{background:var(--guide-primary-hover);color:#fff}
    .guide-btn-secondary{background:#fff;border-color:#e8edf4;color:#344054}
    .guide-btn-secondary:hover{background:#f1f5f9}
    .guide-card{margin-bottom:1.75rem;padding:2rem;background:var(--guide-card);border:1px solid var(--guide-card-border);border-radius:var(--guide-radius-card)}
    .guide-card-title{margin:0 0 1.5rem;font-size:1.2rem;font-weight:700}
    .guide-grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1.25rem}
    .guide-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem}
    .guide-field{min-width:0}
    .guide-label{display:block;margin-bottom:.5rem;font-size:.88rem;font-weight:600}
    .guide-input,.guide-select{display:block;width:100%;height:44px;padding:0 1rem;background:var(--guide-field);border:1px solid var(--guide-field-border);border-radius:var(--guide-radius);color:var(--guide-text);font:inherit}
    textarea.guide-input{height:auto;min-height:86px;padding:.8rem 1rem;resize:vertical}
    .guide-input:focus,.guide-select:focus{border-color:var(--guide-primary);box-shadow:0 0 0 3px rgba(13,139,220,.25);outline:none}
    .guide-divider{margin:1.5rem 0;border:0;border-top:1px solid var(--guide-card-border)}
    .guide-section-title{margin:0 0 1rem;font-size:1.05rem;font-weight:700}
    .guide-client-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem 1.5rem;margin-top:1rem;padding:1.25rem 1.5rem;background:#f8fafc;border:1px dashed var(--guide-field-border);border-radius:16px}
    .guide-client-grid .wide{grid-column:1/-1}
    .guide-card-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.5rem}
    .guide-count{margin-left:.5rem;padding:.1rem .5rem;background:var(--guide-field);border-radius:999px;color:var(--guide-muted);font-size:.8rem}
    .guide-lines-head,.guide-line{display:grid;grid-template-columns:minmax(220px,1fr) 120px 44px;gap:.75rem;align-items:center}
    .guide-lines-head{display:none;padding:.9rem 1rem;background:var(--guide-head);border-radius:var(--guide-radius);font-size:.85rem;font-weight:700}
    .guide-lines-head>div:not(:first-child){text-align:center}
    .guide-page.has-lines .guide-lines-head{display:grid}
    .guide-line{padding:.85rem 1rem;border-bottom:1px solid var(--guide-card-border)}
    .guide-line-desc{width:100%;padding:0;background:transparent;border:0;color:var(--guide-text);font-weight:600}
    .guide-line-code{display:block;color:var(--guide-muted);font-size:.78rem}
    .guide-line-input{width:100%;height:38px;padding:0 .5rem;background:var(--guide-field);border:1px solid transparent;border-radius:10px;text-align:center}
    .guide-line-input:focus{border-color:var(--guide-primary);outline:none}
    .guide-trash{width:36px;height:36px;background:transparent;border:0;border-radius:8px;color:var(--guide-muted);cursor:pointer}
    .guide-trash:hover{background:rgba(239,91,107,.12);color:var(--guide-danger)}
    .guide-empty{display:flex;flex-direction:column;align-items:center;padding:2.25rem 1rem;color:var(--guide-muted);text-align:center}
    .guide-page.has-lines .guide-empty{display:none}
    .guide-picker{margin-top:1rem;padding:1rem 1.25rem;border:1px dashed var(--guide-field-border);border-radius:16px}
    .guide-footer{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);gap:2rem;margin-top:1.75rem;padding-top:1.75rem;border-top:1px solid var(--guide-card-border)}
    .guide-summary{padding:1rem 1.25rem;background:rgba(127,145,180,.05);border:1px solid var(--guide-card-border);border-radius:18px}
    .guide-sum-row{display:flex;justify-content:space-between;gap:1rem;padding:.35rem 0;color:var(--guide-muted)}
    .guide-page .select2-container{width:100%!important}
    .guide-page .select2-selection--single{height:44px!important;background:var(--guide-field)!important;border:1px solid var(--guide-field-border)!important;border-radius:var(--guide-radius)!important}
    .guide-page .select2-selection__rendered{line-height:44px!important;padding-left:1rem!important;color:var(--guide-text)!important}
    .guide-page .select2-selection__arrow{height:44px!important}
    @media(max-width:991.98px){.guide-grid-4{grid-template-columns:repeat(2,minmax(0,1fr))}.guide-footer{grid-template-columns:1fr}.guide-client-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:575.98px){.guide-grid-4,.guide-grid-2,.guide-client-grid{grid-template-columns:1fr}.guide-client-grid .wide{grid-column:auto}.guide-top,.guide-actions,.guide-actions .guide-btn{width:100%}.guide-card{padding:1.25rem}.guide-lines-head{display:none!important}.guide-line{grid-template-columns:1fr 100px 44px}}
</style>
<script src="/assets/js/select2.min.js"></script>

<main class="guide-page" id="guidePage">
    <div class="container">
        <form id="formGuia" autocomplete="off" novalidate>
            <input type="hidden" name="company_id" value="<?= (int) ($_SESSION['user']['company_id'] ?? 0) ?>">
            <input type="hidden" name="user_id" value="<?= (int) ($_SESSION['user']['id'] ?? 0) ?>">

            <div class="guide-top">
                <a href="<?= $listUrl ?>" class="guide-back" data-spa>
                    <i class="bi bi-arrow-left"></i> <?= t('Voltar à lista de guias') ?>
                </a>
                <div class="guide-actions">
                    <button type="button" class="guide-btn guide-btn-secondary" id="cancelGuideBtn" data-href="<?= $listUrl ?>">
                        <?= t('Cancelar') ?>
                    </button>
                    <button type="submit" class="guide-btn guide-btn-primary" id="saveGuideBtn">
                        <i class="bi bi-truck"></i> <span><?= t('Criar Rascunho') ?></span>
                    </button>
                </div>
            </div>

            <div class="guide-card">
                <h2 class="guide-card-title"><?= t('Documento') ?></h2>
                <div class="guide-grid-4">
                    <div class="guide-field">
                        <label class="guide-label" for="series"><?= t('Série') ?> *</label>
                        <input class="guide-input" type="text" id="series" name="serie" value="<?= date('Y') ?>" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="document_date"><?= t('Data do Documento') ?> *</label>
                        <input class="guide-input" type="date" id="document_date" name="data_documento" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="cargo_date"><?= t('Data de Carga') ?> *</label>
                        <input class="guide-input" type="date" id="cargo_date" name="data_carga" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="departure_at"><?= t('Hora de Início') ?></label>
                        <input class="guide-input" type="datetime-local" id="departure_at" name="inicio_transporte">
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="transport_reason"><?= t('Motivo do Transporte') ?></label>
                        <input class="guide-input" type="text" id="transport_reason" name="motivo_transporte" placeholder="<?= t('Venda, transferência, devolução...') ?>">
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="reference"><?= t('Documento de Origem') ?></label>
                        <select class="guide-select" id="origin_document_select" name="referencia">
                            <option value=""><?= t('Pesquisar factura ou proforma') ?></option>
                        </select>
                        <input type="hidden" id="origin_document_id" name="documento_origem_id" value="">
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="origin_document_type"><?= t('Tipo de Origem') ?></label>
                        <select class="guide-select" id="origin_document_type" name="documento_origem_tipo">
                            <option value="">Sem referência</option>
                            <option value="encomenda">Encomenda</option>
                            <option value="factura">Factura</option>
                            <option value="proforma">Proforma</option>
                        </select>
                    </div>
                </div>

                <hr class="guide-divider">
                <h3 class="guide-section-title"><?= t('Destinatário') ?></h3>
                <div class="guide-field" style="max-width:560px">
                    <label class="guide-label" for="contact-select"><?= t('Cliente Destinatário') ?> *</label>
                    <select class="guide-select" id="contact-select" name="contact_id" required>
                        <option value=""><?= t('Selecionar cliente') ?></option>
                    </select>
                </div>
                <div class="guide-client-grid" id="contact-summary" style="display:none">
                    <div class="guide-field"><span class="guide-label"><?= t('Nome') ?></span><strong id="contact-name">—</strong></div>
                    <div class="guide-field"><span class="guide-label"><?= t('NIF') ?></span><strong id="contact-nif">—</strong></div>
                    <div class="guide-field"><span class="guide-label"><?= t('Contacto') ?></span><strong id="contact-phone">—</strong></div>
                    <div class="guide-field wide"><span class="guide-label"><?= t('Morada') ?></span><strong id="contact-address">—</strong></div>
                </div>
            </div>

            <div class="guide-card">
                <h2 class="guide-card-title"><?= t('Origem e Destino') ?></h2>
                <div class="guide-grid-2">
                    <div class="guide-field">
                        <label class="guide-label" for="origin_address"><?= t('Origem — Endereço') ?> *</label>
                        <input class="guide-input" type="text" id="origin_address" name="endereco_carga" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="destination_address"><?= t('Destino — Endereço') ?> *</label>
                        <input class="guide-input" type="text" id="destination_address" name="endereco_entrega" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="origin_city"><?= t('Cidade de Origem') ?> *</label>
                        <input class="guide-input" type="text" id="origin_city" name="cidade_carga" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="destination_city"><?= t('Cidade de Destino') ?> *</label>
                        <input class="guide-input" type="text" id="destination_city" name="cidade_entrega" required>
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="origin_warehouse"><?= t('Armazém de Origem') ?></label>
                        <input class="guide-input" type="number" min="1" id="origin_warehouse" name="armazem_origem">
                    </div>
                    <div class="guide-field">
                        <label class="guide-label" for="destination_warehouse"><?= t('Armazém de Destino') ?></label>
                        <input class="guide-input" type="number" min="1" id="destination_warehouse" name="armazem_destino">
                    </div>
                </div>
            </div>

            <div class="guide-card">
                <h2 class="guide-card-title"><?= t('Transporte') ?></h2>
                <div class="guide-grid-4">
                    <div class="guide-field"><label class="guide-label" for="transporter"><?= t('Transportador') ?></label><input class="guide-input" id="transporter" name="transportador"></div>
                    <div class="guide-field"><label class="guide-label" for="transporter_nif"><?= t('NIF do Transportador') ?></label><input class="guide-input" id="transporter_nif" name="transportador_nif"></div>
                    <div class="guide-field"><label class="guide-label" for="vehicle"><?= t('Veículo') ?></label><input class="guide-input" id="vehicle" name="veiculo"></div>
                    <div class="guide-field"><label class="guide-label" for="matricula"><?= t('Matrícula') ?></label><input class="guide-input" id="matricula" name="matricula"></div>
                    <div class="guide-field"><label class="guide-label" for="driver"><?= t('Motorista') ?></label><input class="guide-input" id="driver" name="motorista"></div>
                    <div class="guide-field"><label class="guide-label" for="driver_document"><?= t('Documento do Motorista') ?></label><input class="guide-input" id="driver_document" name="motorista_documento"></div>
                    <div class="guide-field"><label class="guide-label" for="driver_phone"><?= t('Contacto do Motorista') ?></label><input class="guide-input" id="driver_phone" name="motorista_contacto"></div>
                </div>
            </div>

            <div class="guide-card" id="lines_card">
                <div class="guide-card-head">
                    <h2 class="guide-card-title"><?= t('Bens Transportados') ?><span class="guide-count" id="lines_count">0</span></h2>
                </div>
                <div class="guide-lines-head" aria-hidden="true">
                    <div><?= t('Artigo / Descrição') ?></div>
                    <div><?= t('Quantidade') ?></div>
                    <div></div>
                </div>
                <div id="items_list"></div>
                <div class="guide-empty" id="items_empty">
                    <i class="bi bi-box-seam"></i>
                    <span><?= t('Nenhum bem adicionado. Selecione um artigo do catálogo.') ?></span>
                </div>
                <div class="guide-picker">
                    <label class="guide-label" for="item_select"><?= t('Adicionar artigo do catálogo') ?></label>
                    <select class="guide-select" id="item_select">
                        <option value=""><?= t('Carregando itens') ?>...</option>
                    </select>
                </div>
                <div class="guide-footer">
                    <div>
                        <label class="guide-label" for="observacoes"><?= t('Observações / Ocorrências') ?></label>
                        <textarea class="guide-input" id="observacoes" name="observacoes" placeholder="<?= t('Informações relevantes para transporte ou fiscalização') ?>"></textarea>
                    </div>
                    <div class="guide-summary">
                        <div class="guide-sum-row"><span><?= t('Linhas') ?></span><strong id="summary_lines">0</strong></div>
                        <div class="guide-sum-row"><span><?= t('Quantidade total') ?></span><strong id="summary_quantity">0</strong></div>
                    </div>
                </div>
            </div>

            <div class="guide-actions" style="justify-content:flex-end">
                <button type="button" class="guide-btn guide-btn-secondary" id="cancelGuideBtnBottom" data-href="<?= $listUrl ?>"><?= t('Cancelar') ?></button>
                <button type="submit" class="guide-btn guide-btn-primary"><i class="bi bi-truck"></i> <?= t('Criar Rascunho') ?></button>
            </div>
        </form>
    </div>
</main>

<script src="/assets/js/select2.min.js"></script>
<script src="guides/guides.js?v=<?= filemtime(__DIR__ . '/guides/guides.js') ?>"></script>
<?php require_once '../app/views/footer.php'; ?>
