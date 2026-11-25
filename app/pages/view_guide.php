<?php require_once '../app/views/layout_creation.php'; ?>
<link rel="stylesheet" href="/assets/css/documents.css?v=1.3">
<style>
    :root {
        --guide-surface:#fff; --guide-bg:#f4f7fb; --guide-border:#e5e7eb;
        --guide-muted:#667085; --guide-text:#111827; --guide-primary:#0f172a;
        --guide-shadow:0 16px 32px rgba(15,23,42,.08);
    }
    body{background:var(--guide-bg)}
    .guide-shell{max-width:1240px;margin:2.5rem auto 0;padding:0 1rem 2rem}
    .guide-layout{display:flex;justify-content:center;align-items:flex-start;gap:1.25rem}
    .guide-preview-panel{flex:0 1 210mm;min-width:0}
    #guide-container{width:210mm;max-width:100%;margin:0 auto;height:auto;max-height:none;overflow:visible}
    .guide-side-card{flex:0 0 300px;width:300px;background:var(--guide-surface);border:1px solid var(--guide-border);border-radius:16px;padding:1.25rem;box-shadow:var(--guide-shadow);color:var(--guide-text)}
    .guide-header-eyebrow{font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--guide-muted)}
    .guide-header-title{display:flex;align-items:center;flex-wrap:wrap;gap:.6rem;margin:.35rem 0;font-size:.95rem}
    .guide-header-subtitle{display:block;color:var(--guide-muted);font-size:.82rem}
    .guide-status{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:.45rem .8rem;font-size:.7rem;font-weight:700;text-transform:uppercase;background:#f1f5f9;color:#475569}
    .guide-status.is-transit{background:#dbeafe;color:#1e40af}
    .guide-status.is-delivered{background:#dcfce7;color:#166534}
    .guide-status.is-cancelled{background:#fee2e2;color:#991b1b}
    .guide-toolbar{display:flex;flex-direction:column;gap:.6rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--guide-border)}
    .guide-action-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:44px;width:100%;padding:.7rem 1rem;border-radius:12px;border:1px solid #d9e0ea;background:#fff;color:var(--guide-text);font-weight:600;text-decoration:none;cursor:pointer}
    .guide-action-btn:hover{background:#f8fafc}
    .guide-action-btn.primary{background:var(--guide-primary);border-color:var(--guide-primary);color:#fff}
    .guide-actions{display:flex;flex-direction:column;gap:.15rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--guide-border)}
    .guide-actions-title{font-size:.75rem;font-weight:700;text-transform:uppercase;color:var(--guide-muted);margin-bottom:.5rem}
    .guide-action-link{display:flex;align-items:center;gap:.6rem;width:100%;min-height:42px;padding:.7rem .8rem;border:0;border-radius:10px;background:transparent;color:var(--guide-text);font-weight:600;text-align:left;cursor:pointer}
    .guide-action-link:hover{background:#f8fafc}
    @media(min-width:992px){
        .guide-layout{padding-right:321px}
        .guide-side-card{position:fixed;top:2.25rem;right:max(1rem,calc((100% - 1360px)/2 + 1rem));max-height:calc(100vh - 2rem);overflow-y:auto}
    }
    @media(max-width:991.98px){.guide-layout{flex-direction:column-reverse;align-items:stretch}.guide-side-card{position:static;width:100%;flex-basis:auto}.guide-preview-panel{flex-basis:auto}.guide-shell{margin-top:1.25rem}}
    @media(max-width:767.98px){.guide-shell{padding-inline:.75rem}}
</style>

<main class="guide-shell">
    <div class="guide-layout no-print">
        <div class="guide-preview-panel">
            <div id="preloader" class="text-center py-3 d-none">
                <div class="spinner-border text-primary"></div>
            </div>
            <div id="guide-container" class="invoiceContainer shadow-sm bg-white">
                <div class="p-4 text-muted">A carregar guia...</div>
            </div>
        </div>

        <aside class="guide-side-card" aria-label="Ações da guia">
            <div class="guide-header-eyebrow">Guia de transporte</div>
            <h1 class="guide-header-title">
                <strong id="guideNumber">-</strong>
                <span id="guideStatus" class="guide-status">-</span>
            </h1>
            <span class="guide-header-subtitle" id="guideClient">-</span>

            <div class="guide-toolbar">
                <button type="button" class="guide-action-btn primary" id="generateGuidePdf">
                    <i class="bi bi-printer"></i> Imprimir / Baixar PDF
                </button>
                <button type="button" class="guide-action-btn" id="btnSendGuide" data-bs-toggle="modal" data-bs-target="#modalSendGuide">
                    <i class="bi bi-send"></i> Enviar guia
                </button>
            </div>

            <div class="guide-actions">
                <span class="guide-actions-title">Operações</span>
                <button type="button" class="guide-action-link" id="btnEmitGuide"><i class="bi bi-check-circle"></i> Emitir</button>
                <button type="button" class="guide-action-link" id="btnDepartGuide"><i class="bi bi-truck"></i> Iniciar transporte</button>
                <button type="button" class="guide-action-link" id="btnDeliverGuide"><i class="bi bi-clipboard-check"></i> Registar entrega</button>
                <a class="guide-action-link" href="/guides" data-spa><i class="bi bi-clock-history"></i> Voltar à listagem</a>
            </div>
        </aside>
    </div>

    <div class="modal fade" id="modalSendGuide" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formSendGuide" class="modal-content needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Enviar guia por e-mail</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-8"><label class="form-label">Para</label><input type="email" class="form-control" name="to" required></div>
                        <div class="col-md-4"><label class="form-label">Cc</label><input type="email" class="form-control" name="cc"></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Assunto</label><input type="text" class="form-control" name="subject" required></div>
                    <div class="mt-3"><label class="form-label">Mensagem</label><textarea class="form-control" name="body" rows="8"></textarea></div>
                    <input type="hidden" name="attach" value="1">
                    <input type="hidden" name="guide_id" id="email_guide_id">
                </div>
                <div class="modal-footer"><button type="submit" class="guide-action-btn primary">Enviar</button></div>
            </form>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="guides/view_guides.js?v=<?= filemtime(__DIR__ . '/guides/view_guides.js') ?>-spa2"></script>
<?php require_once '../app/views/footer.php'; ?>
