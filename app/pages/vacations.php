<?php
require_once '../app/views/layout_creation.php';
?>
<link rel="stylesheet" href="/assets/css/rh.css?v=1.5">

<main class="main-content rh-page">
    <div class="container-fluid">
        <div class="rh-head">
            <div>
                <h1>Férias e Licenças</h1>
                <p>Pedidos, aprovação, saldo de dias por funcionário e calendário da equipa.</p>
            </div>
            <div class="rh-actions">
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportVacPdf"><i class="bi bi-filetype-pdf"></i> Exportar PDF</button>
                <button type="button" class="btn btn-sm btn-primary" id="addVacationBtn"><i class="bi bi-plus-lg"></i> Registar período</button>
            </div>
        </div>

        <ul class="nav nav-pills gap-2 mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabRequests" type="button" role="tab"><i class="bi bi-list-check"></i> Pedidos <span class="badge text-bg-warning ms-1 d-none" id="pendingBadge">0</span></button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabBalances" type="button" role="tab" id="tabBalancesBtn"><i class="bi bi-hourglass-split"></i> Saldos</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabCalendar" type="button" role="tab" id="tabCalendarBtn"><i class="bi bi-calendar3"></i> Calendário da equipa</button></li>
        </ul>

        <div class="tab-content">
            <!-- Pedidos -->
            <div class="tab-pane fade show active" id="tabRequests" role="tabpanel">
                <div class="rh-card p-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                        <div class="rh-filters">
                            <input type="month" id="filter_mes" class="form-control form-control-sm" aria-label="Mês">
                            <div style="min-width:220px"><select id="filter_funcionario" class="form-select form-select-sm" aria-label="Funcionário"></select></div>
                            <select id="filter_status" class="form-select form-select-sm" aria-label="Estado">
                                <option value="">Todos os estados</option><option value="Pendente">Pendentes</option><option value="Aprovado">Aprovados</option><option value="Rejeitado">Rejeitados</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-link text-muted" id="btnLimparVac">Limpar filtros</button>
                        </div>
                        <div id="vacationsTableControls"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="vacationsTable" class="table rh-table">
                            <thead><tr><th>Funcionário</th><th>Tipo</th><th>Período</th><th class="num">Dias úteis</th><th>Estado</th><th class="text-end">Ações</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3"></div>
                </div>
            </div>

            <!-- Saldos -->
            <div class="tab-pane fade" id="tabBalances" role="tabpanel">
                <div class="rh-card p-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                        <div class="rh-filters">
                            <select id="balYear" class="form-select form-select-sm" aria-label="Ano"></select>
                            <span class="small text-muted">Dias úteis. Direito: <span id="balRule"></span></span>
                        </div>
                        <div id="balControls"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="balTable" class="table rh-table">
                            <thead><tr><th>Funcionário</th><th>Admissão</th><th class="num">Direito</th><th class="num">Transitados</th><th class="num">Gozados</th><th class="num">Pedidos pendentes</th><th class="num">Saldo</th><th>Utilização</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3 small text-muted">Os dias transitados de anos anteriores editam-se na ficha do funcionário.</div>
                </div>
            </div>

            <!-- Calendário -->
            <div class="tab-pane fade" id="tabCalendar" role="tabpanel">
                <div class="rh-card">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-light border" id="calPrev" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></button>
                            <input type="month" id="calMonth" class="form-control form-control-sm" style="width:auto" aria-label="Mês">
                            <button type="button" class="btn btn-sm btn-light border" id="calNext" aria-label="Mês seguinte"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        <div class="rh-legend">
                            <span><i style="background:#22c55e"></i>Férias aprovadas</span>
                            <span><i style="background:#fbbf24"></i>Pendente</span>
                            <span><i style="background:#60a5fa"></i>Licença médica/maternidade</span>
                            <span><i style="background:#a78bfa"></i>Outros</span>
                            <span><i style="background:#fef2f2;border:1px solid #fecaca"></i>Feriado</span>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height:65vh"><table class="rh-cal" id="calTable"></table></div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade rh-modal" id="modalVacation" tabindex="-1" aria-labelledby="modalVacationLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formVacation" autocomplete="off">
                <input type="hidden" name="id" id="vacId">
                <div class="modal-header"><h5 class="modal-title fw-bold" id="modalVacationLabel">Registar período</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="employee_id">Funcionário</label><select name="employee_id" id="employee_id" class="form-select" required></select></div>
                        <div class="col-12"><div class="small" id="vacBalanceInfo"></div></div>
                        <div class="col-12"><label class="form-label" for="vacType">Tipo</label>
                            <select name="type" id="vacType" class="form-select">
                                <option value="Férias">Férias</option><option value="Licença Médica">Licença médica</option><option value="Licença Maternidade">Licença de maternidade</option><option value="Outros">Outros</option>
                            </select></div>
                        <div class="col-md-6"><label class="form-label" for="vacStart">Início</label><input type="date" name="start_date" id="vacStart" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label" for="vacEnd">Fim</label><input type="date" name="end_date" id="vacEnd" class="form-control" required></div>
                        <div class="col-12"><label class="form-label" for="vacReason">Motivo / observações</label><textarea name="reason" id="vacReason" rows="2" class="form-control"></textarea></div>
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
    const { esc, monthLabel, currentMonth, post, get, toast, fail, confirm, dateLabel } = window.RH;
    const fmtDays = d => Number(d).toLocaleString('pt-PT', { maximumFractionDigits: 1 });
    const pill = s => `<span class="rh-pill ${s === 'Aprovado' ? 'paid' : s === 'Pendente' ? 'pending' : 'danger'}">${esc(s)}</span>`;
    const typeLabel = t => ({ 'Férias': 'Férias', 'Licença Médica': 'Licença médica', 'Licença Maternidade': 'Licença de maternidade', 'Outros': 'Outros' })[t] || t;

    // ---------- Pedidos ----------
    $('#filter_mes').val('');
    window.RH.employeeSelect($('#filter_funcionario'), { placeholder: 'Todos os funcionários' });
    const table = BootstrapTable.create({
        table: '#vacationsTable', toolbar: '#vacationsTableControls', pageSize: 25, pageSizes: [25, 50, 100],
        emptyText: 'Sem períodos registados.',
        ajax: { url: 'rh/ajax/list_vacations.php', data: () => ({ mes: $('#filter_mes').val(), funcionario: $('#filter_funcionario').val() || '', status: $('#filter_status').val() }) },
        dataSource: resp => {
            const pending = (resp.data || []).filter(r => r.status === 'Pendente').length;
            $('#pendingBadge').text(pending).toggleClass('d-none', !pending);
            return resp.data;
        },
        columns: [
            { data: 'employee_name', render: (v, r) => `<div class="who">${esc(v)}${r.reason ? `<small>${esc(r.reason)}</small>` : ''}${r.requested_via === 'portal' ? '<small><i class="bi bi-person-badge"></i> pedido no portal</small>' : ''}</div>` },
            { data: 'type', render: typeLabel },
            { data: 'start_date', render: (v, r) => `${dateLabel(r.start_date)} → ${dateLabel(r.end_date)}` },
            { data: 'working_days', render: v => `<span class="num d-block">${fmtDays(v)}</span>`, sortValue: r => Number(r.working_days) },
            { data: 'status', render: pill },
            { data: null, sortable: false, searchable: false, render: (_, r) => `<div class="row-actions">
                ${r.status !== 'Aprovado' ? `<button type="button" class="btn btn-sm btn-light border vac-status" data-id="${r.id}" data-status="Aprovado" title="Aprovar"><i class="bi bi-check-lg text-success"></i></button>` : ''}
                ${r.status !== 'Rejeitado' ? `<button type="button" class="btn btn-sm btn-light border vac-status" data-id="${r.id}" data-status="Rejeitado" title="Rejeitar"><i class="bi bi-x-lg text-danger"></i></button>` : ''}
                <button type="button" class="btn btn-sm btn-light border vac-edit" data-id="${r.id}" title="Editar"><i class="bi bi-pencil"></i></button>
                <button type="button" class="btn btn-sm btn-light border vac-delete" data-id="${r.id}" title="Eliminar"><i class="bi bi-trash"></i></button>
            </div>` }
        ]
    });
    $('#filter_mes, #filter_status').on('change', () => table.reload());
    $('#filter_funcionario').on('change', () => table.reload());
    $('#btnLimparVac').on('click', () => { $('#filter_mes').val(''); $('#filter_status').val(''); $('#filter_funcionario').val(null).trigger('change'); });

    $('#vacationsTable').on('click', '.vac-status', async function () {
        const status = this.dataset.status;
        if (status === 'Rejeitado' && !await confirm('Rejeitar este pedido?', '', 'Rejeitar', 'warning')) return;
        try { toast((await post('rh/ajax/update_vacation_status.php', { id: this.dataset.id, status })).message); refreshAll(); } catch (e) { fail(e); }
    });
    $('#vacationsTable').on('click', '.vac-delete', async function () {
        if (!await confirm('Eliminar período?', 'Esta acção não pode ser anulada.', 'Eliminar', 'warning')) return;
        try { toast((await post('rh/ajax/delete_vacation.php', { id: this.dataset.id })).message || 'Eliminado.'); refreshAll(); } catch (e) { fail(e); }
    });

    $('#btnExportVacPdf').on('click', () => {
        const mes = $('#filter_mes').val() || currentMonth();
        window.open(`/rh/ajax/export_vacations_pdf.php?mes=${encodeURIComponent(mes)}&employee_id=${encodeURIComponent($('#filter_funcionario').val() || '')}`, '_blank');
    });

    // ---------- Modal ----------
    const $modal = $('#modalVacation');
    window.RH.employeeSelect($('#employee_id'), { dropdownParent: $modal });
    async function showBalance() {
        const emp = $('#employee_id').val();
        const year = ($('#vacStart').val() || new Date().toISOString()).slice(0, 4);
        if (!emp) return $('#vacBalanceInfo').html('');
        try {
            const r = (await get('rh/ajax/vacation_balance.php', { year, employee_id: emp })).data[0];
            if (!r) return $('#vacBalanceInfo').html('');
            $('#vacBalanceInfo').html(`<div class="rh-card py-2 px-3" style="background:#f0fdfa;border-color:#99f6e4">
                <strong>Saldo de férias ${year}:</strong> ${fmtDays(r.balance)} dia(s) úteis
                <span class="text-muted">(direito ${fmtDays(r.entitlement)} + transitados ${fmtDays(r.carry)} − gozados ${fmtDays(r.used)}${r.pending ? ` · ${fmtDays(r.pending)} em pedidos pendentes` : ''})</span></div>`);
        } catch (e) { $('#vacBalanceInfo').html(''); }
    }
    $('#employee_id').on('change', showBalance);
    $('#vacStart').on('change', function () { if (!$('#vacEnd').val() || $('#vacEnd').val() < this.value) $('#vacEnd').val(this.value); showBalance(); });

    $('#addVacationBtn').on('click', () => {
        $('#formVacation')[0].reset(); $('#vacId').val(''); $('#employee_id').empty().trigger('change'); $('#vacBalanceInfo').html('');
        $('#modalVacationLabel').text('Registar período'); $modal.modal('show');
    });
    $('#vacationsTable').on('click', '.vac-edit', function () {
        const r = table.getRows().find(x => Number(x.id) === Number(this.dataset.id)); if (!r) return;
        $('#formVacation')[0].reset(); $('#vacId').val(r.id);
        $('#employee_id').empty().append(new Option(r.employee_name, r.employee_id, true, true)).trigger('change');
        $('#vacType').val(r.type); $('#vacStart').val(r.start_date); $('#vacEnd').val(r.end_date); $('#vacReason').val(r.reason || '');
        $('#modalVacationLabel').text('Editar período'); $modal.modal('show');
    });
    $('#formVacation').on('submit', async function (e) {
        e.preventDefault();
        try {
            const resp = await post('rh/ajax/save_vacation.php', $(this).serialize());
            $modal.modal('hide');
            if (resp.warning) Swal.fire({ icon: 'warning', title: resp.message, text: resp.warning }); else toast(resp.message);
            refreshAll();
        } catch (err) { fail(err); }
    });

    // ---------- Saldos ----------
    const y = new Date().getFullYear();
    $('#balYear').html([y - 1, y, y + 1].map(v => `<option ${v === y ? 'selected' : ''}>${v}</option>`).join(''));
    let balTable = null;
    function loadBalances() {
        if (!balTable) {
            balTable = BootstrapTable.create({
                table: '#balTable', toolbar: '#balControls', pageSize: 50, pageSizes: [50, 100],
                ajax: { url: 'rh/ajax/vacation_balance.php', data: () => ({ year: $('#balYear').val() }) },
                emptyText: 'Sem funcionários activos.',
                columns: [
                    { data: 'name', render: v => `<span class="who">${esc(v)}</span>` },
                    { data: 'admission_date', render: dateLabel },
                    { data: 'entitlement', render: v => `<span class="num d-block">${fmtDays(v)}</span>`, sortValue: r => Number(r.entitlement) },
                    { data: 'carry', render: v => `<span class="num d-block">${fmtDays(v)}</span>`, sortValue: r => Number(r.carry) },
                    { data: 'used', render: v => `<span class="num d-block">${fmtDays(v)}</span>`, sortValue: r => Number(r.used) },
                    { data: 'pending', render: v => `<span class="num d-block text-warning">${Number(v) ? fmtDays(v) : '—'}</span>`, sortValue: r => Number(r.pending) },
                    { data: 'balance', render: v => `<span class="num d-block fw-bold ${Number(v) < 0 ? 'text-danger' : ''}">${fmtDays(v)}</span>`, sortValue: r => Number(r.balance) },
                    { data: null, sortable: false, searchable: false, render: (_, r) => { const total = Number(r.entitlement) + Number(r.carry); const pct = total ? Math.min(100, Math.round(Number(r.used) / total * 100)) : 0; return `<div class="d-flex align-items-center gap-2"><div class="rh-meter flex-grow-1"><span style="width:${pct}%"></span></div><small class="text-muted">${pct}%</small></div>`; } }
                ]
            });
        } else balTable.reload();
    }
    $('#tabBalancesBtn').on('shown.bs.tab', loadBalances);
    $('#balYear').on('change', loadBalances);
    get('rh/ajax/rh_settings.php').then(r => $('#balRule').text(`${r.settings.vacation_days_per_year} dias/ano; no ano de admissão ${r.settings.vacation_days_first_year_per_month} dias por mês completo`)).catch(() => {});

    // ---------- Calendário ----------
    $('#calMonth').val(currentMonth());
    async function loadCalendar() {
        try {
            const c = await get('rh/ajax/vacation_calendar.php', { mes: $('#calMonth').val() });
            const [yy, mm] = c.month.split('-').map(Number);
            const dow = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
            const days = [...Array(c.days).keys()].map(i => {
                const d = new Date(yy, mm - 1, i + 1); const iso = `${c.month}-${String(i + 1).padStart(2, '0')}`;
                return { n: i + 1, iso, we: d.getDay() === 0 || d.getDay() === 6, hol: c.holidays[iso] || null, dow: dow[d.getDay()] };
            });
            const cls = d => `${d.we ? 'we' : ''} ${d.hol ? 'hol' : ''}`;
            let html = `<thead><tr><th class="text-start ps-2">Funcionário</th>${days.map(d => `<th class="${cls(d)}" title="${esc(d.hol || '')}">${d.dow}<br>${d.n}</th>`).join('')}</tr></thead><tbody>`;
            for (const e of c.employees) {
                const mine = c.periods.filter(p => Number(p.employee_id) === Number(e.id));
                html += `<tr><td class="name">${esc(e.name)}</td>${days.map(d => {
                    const p = mine.find(p => p.start_date <= d.iso && p.end_date >= d.iso);
                    if (!p) return `<td class="${cls(d)}"></td>`;
                    const kind = p.status === 'Pendente' ? 'Pendente' : p.type === 'Férias' ? 'Aprovado' : (p.type === 'Outros' ? 'other' : 'sick');
                    return `<td class="${cls(d)}" title="${esc(typeLabel(p.type))} · ${esc(p.status)}"><span class="bar ${kind}"></span></td>`;
                }).join('')}</tr>`;
            }
            if (!c.employees.length) html += `<tr><td class="rh-empty" colspan="${c.days + 1}">Sem funcionários activos.</td></tr>`;
            $('#calTable').html(html + '</tbody>');
        } catch (e) { fail(e); }
    }
    $('#tabCalendarBtn').on('shown.bs.tab', loadCalendar);
    $('#calMonth').on('change', loadCalendar);
    $('#calPrev, #calNext').on('click', function () {
        const [yy, mm] = $('#calMonth').val().split('-').map(Number);
        const d = new Date(yy, mm - 1 + (this.id === 'calNext' ? 1 : -1), 1);
        $('#calMonth').val(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`); loadCalendar();
    });

    function refreshAll() {
        table.reload();
        if (balTable) balTable.reload();
        if ($('#tabCalendar').hasClass('active')) loadCalendar();
    }
});
</script>
<?php require_once '../app/views/footer.php'; ?>
