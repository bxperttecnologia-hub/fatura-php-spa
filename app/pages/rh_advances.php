<?php
require_once '../app/views/layout_creation.php';
?>
<link rel="stylesheet" href="/assets/css/rh.css?v=1.5">

<main class="main-content rh-page">
    <div class="container-fluid">
        <div class="rh-head">
            <div>
                <h1>Adiantamentos e empréstimos</h1>
                <p>Valores adiantados aos funcionários, descontados automaticamente na folha em prestações mensais.</p>
            </div>
            <div class="rh-actions">
                <a href="/payroll" data-spa class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Folha de pagamento</a>
                <button type="button" class="btn btn-sm btn-primary" id="btnNewAdvance"><i class="bi bi-plus-lg"></i> Novo registo</button>
            </div>
        </div>

        <div class="rh-kpis">
            <div class="rh-kpi"><div class="label">Activos</div><div class="value" id="kpiActive">0</div><div class="hint">a descontar nas próximas folhas</div></div>
            <div class="rh-kpi accent"><div class="label">Por descontar</div><div class="value" id="kpiOutstanding">0,00 Kz</div><div class="hint">saldo em dívida dos activos</div></div>
        </div>

        <div class="rh-card p-0">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                <div class="rh-filters">
                    <input type="search" class="form-control form-control-sm" id="advSearch" placeholder="Pesquisar…" aria-label="Pesquisar">
                    <select class="form-select form-select-sm" id="advStatus" aria-label="Estado">
                        <option value="">Todos</option>
                        <option value="ativo" selected>Activos</option>
                        <option value="liquidado">Liquidados</option>
                        <option value="cancelado">Cancelados</option>
                    </select>
                </div>
                <div id="advControls"></div>
            </div>
            <div class="table-responsive">
                <table class="table rh-table" id="advTable">
                    <thead><tr><th>Funcionário</th><th>Tipo</th><th>Início</th><th class="num">Valor</th><th>Prestações</th><th class="num">Descontado</th><th class="num">Em dívida</th><th>Estado</th><th class="text-end">Ações</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="px-3 pb-3"></div>
        </div>
    </div>
</main>

<div class="modal fade rh-modal" id="modalAdvance" tabindex="-1" aria-labelledby="modalAdvanceLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formAdvance" autocomplete="off">
                <input type="hidden" name="id" id="advId">
                <input type="hidden" name="action" value="save">
                <div class="modal-header"><h5 class="modal-title fw-bold" id="modalAdvanceLabel">Novo adiantamento / empréstimo</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="advEmployee">Funcionário</label><select class="form-select" name="employee_id" id="advEmployee" required></select></div>
                        <div class="col-md-6"><label class="form-label" for="advType">Tipo</label>
                            <select class="form-select" name="type" id="advType"><option value="adiantamento">Adiantamento salarial</option><option value="emprestimo">Empréstimo</option></select></div>
                        <div class="col-md-6"><label class="form-label" for="advGranted">Data da concessão</label><input type="date" class="form-control" name="granted_date" id="advGranted"></div>
                        <div class="col-md-6"><label class="form-label" for="advTotal">Valor total (Kz)</label><input type="number" step="0.01" min="1" class="form-control" name="total_amount" id="advTotal" required></div>
                        <div class="col-md-6"><label class="form-label" for="advInst">N.º de prestações</label><input type="number" min="1" max="120" class="form-control" name="installments" id="advInst" value="1" required></div>
                        <div class="col-md-6"><label class="form-label" for="advStart">Primeiro desconto em</label><input type="month" class="form-control" name="start_month" id="advStart" required></div>
                        <div class="col-md-6 d-flex align-items-end"><div class="small text-muted" id="advInstHint">—</div></div>
                        <div class="col-12"><label class="form-label" for="advDesc">Descrição</label><input type="text" class="form-control" name="description" id="advDesc" maxlength="255" placeholder="Ex.: adiantamento de Novembro, empréstimo para…"></div>
                    </div>
                </div>
                <div class="modal-footer bg-white"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary px-4"><i class="bi bi-check2"></i> Gravar</button></div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/rh.js?v=1.1"></script>
<script>
$(function () {
    const { esc, kz, money, monthLabel, currentMonth, post, toast, fail, confirm } = window.RH;
    const statusPill = s => ({ ativo: '<span class="rh-pill approved">Activo</span>', liquidado: '<span class="rh-pill paid">Liquidado</span>', cancelado: '<span class="rh-pill muted">Cancelado</span>' })[s] || esc(s);

    const table = BootstrapTable.create({
        table: '#advTable', toolbar: '#advControls', searchInput: '#advSearch', pageSize: 25, pageSizes: [25, 50, 100],
        emptyText: 'Sem registos.',
        ajax: { url: 'rh/ajax/advances.php', data: () => ({ action: 'list', status: $('#advStatus').val() }) },
        dataSource: resp => { $('#kpiActive').text(resp.totals.active); $('#kpiOutstanding').text(kz(resp.totals.outstanding)); return resp.data; },
        columns: [
            { data: 'employee_name', render: (v, r) => `<div class="who">${esc(v)}<small>${esc(r.description || '')}</small></div>` },
            { data: 'type', render: v => v === 'emprestimo' ? 'Empréstimo' : 'Adiantamento' },
            { data: 'start_month', render: v => esc(monthLabel(v)) },
            { data: 'total_amount', render: v => `<span class="num d-block">${money(v)}</span>`, sortValue: r => Number(r.total_amount) },
            { data: 'installments', render: (v, r) => `${Number(r.paid_installments)} / ${Number(v)} <small class="text-muted">(${money(r.installment_amount)}/mês)</small>` },
            { data: 'paid_amount', render: v => `<span class="num d-block">${money(v)}</span>`, sortValue: r => Number(r.paid_amount) },
            { data: 'remaining', render: v => `<span class="num d-block fw-bold">${money(v)}</span>`, sortValue: r => Number(r.remaining) },
            { data: 'status', render: statusPill },
            { data: null, sortable: false, searchable: false, render: (_, r) => `<div class="row-actions">
                ${r.status !== 'cancelado' ? `<button type="button" class="btn btn-sm btn-light border adv-edit" data-id="${r.id}" title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
                ${r.status === 'ativo' ? `<button type="button" class="btn btn-sm btn-light border adv-cancel" data-id="${r.id}" title="Cancelar descontos"><i class="bi bi-slash-circle"></i></button>` : ''}
                ${Number(r.paid_amount) === 0 ? `<button type="button" class="btn btn-sm btn-light border adv-delete" data-id="${r.id}" title="Eliminar"><i class="bi bi-trash text-danger"></i></button>` : ''}
            </div>` }
        ]
    });
    $('#advStatus').on('change', () => table.reload());

    const $modal = $('#modalAdvance');
    window.RH.employeeSelect($('#advEmployee'), { dropdownParent: $modal });
    const updateHint = () => {
        const total = Number($('#advTotal').val() || 0), n = Math.max(1, Number($('#advInst').val() || 1));
        $('#advInstHint').text(total ? `Prestação mensal: ${kz(Math.ceil(total / n * 100) / 100)}` : '—');
    };
    $('#advTotal, #advInst').on('input', updateHint);

    $('#btnNewAdvance').on('click', () => {
        $('#formAdvance')[0].reset(); $('#advId').val(''); $('#advEmployee').empty().trigger('change');
        $('#advStart').val(currentMonth()); $('#advGranted').val(new Date().toISOString().slice(0, 10));
        $('#modalAdvanceLabel').text('Novo adiantamento / empréstimo'); updateHint(); $modal.modal('show');
    });
    $('#advTable').on('click', '.adv-edit', function () {
        const r = table.getRows().find(x => Number(x.id) === Number(this.dataset.id)); if (!r) return;
        $('#advId').val(r.id); $('#advEmployee').empty().append(new Option(r.employee_name, r.employee_id, true, true)).trigger('change');
        $('#advType').val(r.type); $('#advGranted').val(r.granted_date || ''); $('#advTotal').val(r.total_amount); $('#advInst').val(r.installments);
        $('#advStart').val(r.start_month); $('#advDesc').val(r.description || ''); $('#modalAdvanceLabel').text('Editar registo'); updateHint(); $modal.modal('show');
    });
    $('#advTable').on('click', '.adv-cancel', async function () {
        if (!await confirm('Cancelar os descontos?', 'O valor em dívida deixa de ser descontado nas próximas folhas. O que já foi descontado mantém-se.', 'Cancelar descontos', 'warning')) return;
        try { toast((await post('rh/ajax/advances.php', { action: 'cancel', id: this.dataset.id })).message); table.reload(); } catch (e) { fail(e); }
    });
    $('#advTable').on('click', '.adv-delete', async function () {
        if (!await confirm('Eliminar registo?', 'Esta acção não pode ser anulada.', 'Eliminar', 'warning')) return;
        try { toast((await post('rh/ajax/advances.php', { action: 'delete', id: this.dataset.id })).message); table.reload(); } catch (e) { fail(e); }
    });
    $('#formAdvance').on('submit', async function (e) {
        e.preventDefault();
        try { const resp = await post('rh/ajax/advances.php', $(this).serialize()); $modal.modal('hide'); toast(resp.message); table.reload(); } catch (err) { fail(err); }
    });
});
</script>
<?php require_once '../app/views/footer.php'; ?>
