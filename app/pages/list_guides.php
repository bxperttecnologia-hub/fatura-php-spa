<?php require_once '../app/views/layout_creation.php'; ?>
<link rel="stylesheet" href="/assets/css/documents.css?v=1.3">

<main id="bxGuidesPage" class="bx-documents-page">
    <div class="container mt-5">
        <div class="bx-page-header bx-list-header">
            <h2 class="bx-page-title"><?= t('Guias de Transporte') ?></h2>
            <a class="bx-btn-primary" href="/guides/create" data-spa>
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Nova guia
            </a>
        </div>

        <section class="bx-cards mb-3" aria-label="Resumo das guias">
            <article class="bx-card">
                <div class="bx-card__top"><span>Total</span><span class="bx-card__icon"><i class="bi bi-truck"></i></span></div>
                <div class="bx-card__value" id="guideCountAll">0</div>
                <div class="bx-card__hint">Guias registadas</div>
            </article>
            <article class="bx-card">
                <div class="bx-card__top"><span>Em trânsito</span><span class="bx-card__icon"><i class="bi bi-arrow-left-right"></i></span></div>
                <div class="bx-card__value" id="guideCountTransit">0</div>
                <div class="bx-card__hint">A aguardar entrega</div>
            </article>
            <article class="bx-card">
                <div class="bx-card__top"><span>Entregues</span><span class="bx-card__icon"><i class="bi bi-check-circle"></i></span></div>
                <div class="bx-card__value" id="guideCountDelivered">0</div>
                <div class="bx-card__hint">Transporte concluído</div>
            </article>
            <article class="bx-card">
                <div class="bx-card__top"><span>Rascunhos</span><span class="bx-card__icon"><i class="bi bi-pencil"></i></span></div>
                <div class="bx-card__value" id="guideCountDraft">0</div>
                <div class="bx-card__hint">Ainda não emitidos</div>
            </article>
        </section>

        <section class="bx-filters bx-list-filter-panel" aria-label="Filtros">
            <div class="bx-filters__grid">
                <div class="bx-field bx-field--wide">
                    <label for="guideSearch">Cliente ou nº da guia</label>
                    <input id="guideSearch" type="search" class="form-control" placeholder="Pesquisar cliente, série ou número">
                </div>
                <div class="bx-field">
                    <label for="guideStatus">Estado</label>
                    <select id="guideStatus" class="form-select">
                        <option value="">Todos</option>
                        <option value="rascunho">Rascunho</option>
                        <option value="emitido">Emitido</option>
                        <option value="em_preparacao">Em preparação</option>
                        <option value="em_transito">Em trânsito</option>
                        <option value="entrega_parcial">Entrega parcial</option>
                        <option value="entregue">Entregue</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="bx-field"><label for="guideStart">Data inicial</label><input id="guideStart" type="date" class="form-control"></div>
                <div class="bx-field"><label for="guideEnd">Data final</label><input id="guideEnd" type="date" class="form-control"></div>
                <div class="bx-filters__actions bx-field--wide">
                    <button type="button" id="guideFilter" class="bx-btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <button type="button" id="guideClear" class="bx-btn-secondary">Limpar</button>
                </div>
            </div>
        </section>

        <div class="bx-docs-header">
            <h3 class="bx-section-title">Guias</h3>
            <span class="bx-muted" id="guideResultCount"></span>
        </div>

        <div class="table-responsive">
            <table class="table-bx-standard table nowrap w-100 bx-list-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Destinatário</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>Data</th>
                        <th>Estado</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody id="guidesTableBody"></tbody>
            </table>
        </div>
    </div>
</main>

<script>
$(document).ready(function () {
    let guides = [];

    function normalizeStatus(status) {
        return String(status || '').toLowerCase().replace(/\s+/g, '_');
    }

    function statusBadge(status) {
        const normalized = normalizeStatus(status);
        const map = {
            rascunho: 'bx-badge--rascunho',
            emitido: 'bx-badge--pendente',
            em_preparacao: 'bx-badge--pendente',
            em_transito: 'bx-badge--parcial',
            entrega_parcial: 'bx-badge--parcial',
            entregue: 'bx-badge--pago',
            cancelado: 'bx-badge--vencido'
        };
        return `<span class="bx-badge ${map[normalized] || 'bx-badge--outro'}">${normalized.replaceAll('_', ' ') || '-'}</span>`;
    }

    function render() {
        const search = ($('#guideSearch').val() || '').trim().toLowerCase();
        const status = $('#guideStatus').val();
        const start = $('#guideStart').val();
        const end = $('#guideEnd').val();
        const filtered = guides.filter(guide => {
            const haystack = `${guide.document_number} ${guide.client_name} ${guide.vehicle_plate || ''}`.toLowerCase();
            if (search && !haystack.includes(search)) return false;
            if (status && normalizeStatus(guide.transport_status || guide.status) !== status) return false;
            if (start && guide.cargo_date_iso && guide.cargo_date_iso < start) return false;
            if (end && guide.cargo_date_iso && guide.cargo_date_iso > end) return false;
            return true;
        });

        $('#guideResultCount').text(`${filtered.length} documento${filtered.length === 1 ? '' : 's'}`);
        if (!filtered.length) {
            $('#guidesTableBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Nenhuma guia encontrada.</td></tr>');
            return;
        }
        $('#guidesTableBody').html(filtered.map(guide => `
            <tr class="document-row" data-href="/guides/view?id=${encodeURIComponent(guide.id)}">
                <td><div class="bx-doc"><span class="bx-doc__type">Guia de transporte</span><span class="bx-doc__num">${escapeHtml(guide.document_number)}</span></div></td>
                <td>${escapeHtml(guide.client_name)}</td>
                <td>${escapeHtml(guide.cargo_address || '-')}</td>
                <td>${escapeHtml(guide.delivery_address || '-')}</td>
                <td class="bx-num">${escapeHtml(guide.cargo_date)}</td>
                <td>${statusBadge(guide.transport_status || guide.status)}</td>
                <td class="text-end"><div class="bx-actions"><a class="bx-icon-btn" href="/guides/view?id=${encodeURIComponent(guide.id)}" data-spa title="Consultar"><i class="bi bi-eye"></i></a></div></td>
            </tr>
        `).join(''));
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    $.getJSON('guides/ajax/list_guides.php')
        .done(response => {
            guides = Array.isArray(response) ? response : (response.data || []);
            $('#guideCountAll').text(guides.length);
            $('#guideCountTransit').text(guides.filter(g => normalizeStatus(g.transport_status) === 'em_transito').length);
            $('#guideCountDelivered').text(guides.filter(g => normalizeStatus(g.transport_status) === 'entregue').length);
            $('#guideCountDraft').text(guides.filter(g => normalizeStatus(g.status) === 'rascunho').length);
            render();
        })
        .fail(() => $('#guidesTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Não foi possível carregar as guias.</td></tr>'));

    $('#guideFilter').on('click', render);
    $('#guideClear').on('click', function () {
        $('#guideSearch, #guideStatus, #guideStart, #guideEnd').val('');
        render();
    });
    $('#guideSearch').on('input', render);
    $(document).on('click', '#bxGuidesPage tr.document-row', function (event) {
        if (!$(event.target).closest('a,button').length) window.location.href = $(this).data('href');
    });
});
</script>
<?php require_once '../app/views/footer.php'; ?>
