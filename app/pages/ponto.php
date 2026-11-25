<?php
require_once '../app/views/layout_creation.php';
?>
<link rel="stylesheet" href="/assets/css/rh.css?v=1.5">

<main class="main-content rh-page">
    <div class="container-fluid">
        <div class="rh-head">
            <div>
                <h1>Ponto e Faltas</h1>
                <p>Registo diário de entradas e saídas, atrasos, horas extra e faltas. As faltas e horas extra entram na folha de pagamento.</p>
            </div>
            <div class="rh-actions">
                <button type="button" class="btn btn-sm btn-primary" id="btnNewRecord"><i class="bi bi-plus-lg"></i> Registo individual</button>
            </div>
        </div>

        <ul class="nav nav-pills gap-2 mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabDay" type="button" role="tab"><i class="bi bi-calendar-day"></i> Folha diária</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabSummary" type="button" role="tab" id="tabSummaryBtn"><i class="bi bi-bar-chart"></i> Resumo mensal</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabRecords" type="button" role="tab" id="tabRecordsBtn"><i class="bi bi-list-ul"></i> Registos</button></li>
        </ul>

        <div class="tab-content">
            <!-- Folha diária -->
            <div class="tab-pane fade show active" id="tabDay" role="tabpanel">
                <div class="rh-card p-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-light border" id="dayPrev" aria-label="Dia anterior"><i class="bi bi-chevron-left"></i></button>
                            <input type="date" id="dayDate" class="form-control form-control-sm" style="width:auto" aria-label="Dia">
                            <button type="button" class="btn btn-sm btn-light border" id="dayNext" aria-label="Dia seguinte"><i class="bi bi-chevron-right"></i></button>
                            <button type="button" class="btn btn-sm btn-link" id="dayToday">Hoje</button>
                            <span id="dayInfo" class="small"></span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-light border" id="btnAllPresent" title="Marca presença com o horário normal em quem ainda não tem registo"><i class="bi bi-check2-all"></i> Presença a todos sem registo</button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnSaveDay"><i class="bi bi-save"></i> Gravar dia</button>
                        </div>
                    </div>
                    <div class="small text-muted px-3 pt-2" id="dayRules"></div>
                    <div class="table-responsive">
                        <table class="table rh-table align-middle" id="dayTable">
                            <thead><tr><th>Funcionário</th><th style="width:150px">Registo</th><th style="width:120px">Entrada</th><th style="width:120px">Saída</th><th class="num">Trabalhado</th><th class="num">Atraso</th><th class="num">Extra</th><th>Observação</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3 small text-muted">Deixe "Registo" em "—" para não lançar nada (ou apagar o registo existente do dia). Os minutos são calculados ao gravar, segundo as <a href="/rh-definicoes">Definições de RH</a>.</div>
                </div>
            </div>

            <!-- Resumo mensal -->
            <div class="tab-pane fade" id="tabSummary" role="tabpanel">
                <div class="rh-kpis mb-3" id="sumKpis"></div>
                <div class="rh-card p-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                        <div class="rh-filters">
                            <input type="month" id="sumMonth" class="form-control form-control-sm" aria-label="Mês">
                            <span class="small text-muted" id="sumInfo"></span>
                        </div>
                        <div id="sumControls"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="sumTable" class="table rh-table">
                            <thead><tr><th>Funcionário</th><th class="num">Presenças</th><th class="num">Faltas</th><th class="num">Atestados</th><th class="num">Folgas</th><th class="num">Férias/licença</th><th class="num">Sem registo</th><th class="num">Horas trab.</th><th class="num">Atrasos</th><th class="num">Horas extra</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3 small text-muted">"Sem registo" = dias úteis (até hoje, desde a admissão) sem presença, falta, atestado, folga nem férias aprovadas. Faltas em feriados não contam.</div>
                </div>
            </div>

            <!-- Registos -->
            <div class="tab-pane fade" id="tabRecords" role="tabpanel">
                <div class="rh-card p-0">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                        <div class="rh-filters">
                            <input type="month" id="recMonth" class="form-control form-control-sm" aria-label="Mês">
                            <div style="min-width:220px"><select id="recEmployee" class="form-select form-select-sm" aria-label="Funcionário"></select></div>
                            <select id="recType" class="form-select form-select-sm" aria-label="Tipo">
                                <option value="">Todos os tipos</option><option value="presença">Presenças</option><option value="falta">Faltas</option><option value="atestado">Atestados</option><option value="folga">Folgas</option>
                            </select>
                        </div>
                        <div id="recControls"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="recTable" class="table rh-table">
                            <thead><tr><th>Data</th><th>Funcionário</th><th>Tipo</th><th>Horário</th><th class="num">Trabalhado</th><th class="num">Atraso</th><th class="num">Extra</th><th>Observação</th><th class="text-end">Ações</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="px-3 pb-3"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="modal fade rh-modal" id="modalRecord" tabindex="-1" aria-labelledby="modalRecordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formRecord" autocomplete="off">
                <input type="hidden" name="id" id="recId">
                <div class="modal-header"><h5 class="modal-title fw-bold" id="modalRecordLabel">Registo de ponto</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="recEmp">Funcionário</label><select name="employee_id" id="recEmp" class="form-select" required></select></div>
                        <div class="col-md-6"><label class="form-label" for="recDate">Data</label><input type="date" name="date" id="recDate" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label" for="recKind">Tipo</label>
                            <select name="type" id="recKind" class="form-select"><option value="presença">Presença</option><option value="falta">Falta</option><option value="atestado">Atestado</option><option value="folga">Folga</option></select></div>
                        <div class="col-md-6 rec-times"><label class="form-label" for="recIn">Entrada</label><input type="time" name="check_in" id="recIn" class="form-control"></div>
                        <div class="col-md-6 rec-times"><label class="form-label" for="recOut">Saída</label><input type="time" name="check_out" id="recOut" class="form-control"></div>
                        <div class="col-12"><label class="form-label" for="recJust">Justificação / observação</label><textarea name="justification" id="recJust" rows="2" class="form-control"></textarea></div>
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
    const { esc, currentMonth, post, get, toast, fail, report, confirm, dateLabel } = window.RH;
    const hm = m => { m = Number(m) || 0; if (!m) return '—'; const h = Math.floor(m / 60), r = m % 60; return h ? `${h}h${String(r).padStart(2, '0')}` : `${r} min`; };
    const typeLabel = t => ({ 'presença': 'Presença', 'falta': 'Falta', 'atestado': 'Atestado', 'folga': 'Folga' })[t] || t;
    const typePill = t => `<span class="rh-pill ${t === 'presença' ? 'paid' : t === 'falta' ? 'danger' : t === 'atestado' ? 'approved' : 'muted'}">${esc(typeLabel(t))}</span>`;
    const isoDate = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const toMin = t => { const m = /^(\d{1,2}):(\d{2})/.exec(t || ''); return m ? Number(m[1]) * 60 + Number(m[2]) : null; };

    // ---------- Folha diária ----------
    let day = null;
    $('#dayDate').val(isoDate(new Date()));

    // Mesmo cálculo do servidor (lib/attendance_lib.php), só para pré-visualização.
    function calc(inT, outT, s, workday) {
        const r = { worked: 0, late: 0, overtime: 0 }; const i = toMin(inT); let o = toMin(outT);
        if (i === null) return r;
        const start = toMin(s.work_start_time) ?? 480;
        if (workday && i - start > s.late_tolerance_minutes && i - start <= 360) r.late = i - start;
        if (o === null) return r;
        if (o < i) o += 1440;
        const span = o - i; r.worked = span > 360 ? Math.max(0, span - s.break_minutes) : span;
        r.overtime = workday ? Math.max(0, r.worked - Math.round(s.daily_hours * 60)) : r.worked;
        return r;
    }
    function updateRow($tr) {
        const type = $tr.find('.d-type').val();
        const isPres = type === 'presença';
        $tr.find('.d-in, .d-out').prop('disabled', !isPres || day.closed);
        const c = isPres ? calc($tr.find('.d-in').val(), $tr.find('.d-out').val(), day.settings, day.workday) : { worked: 0, late: 0, overtime: 0 };
        $tr.find('.d-worked').text(hm(c.worked));
        $tr.find('.d-late').html(c.late ? `<span class="text-danger">${hm(c.late)}</span>` : '—');
        $tr.find('.d-extra').html(c.overtime ? `<span class="text-primary fw-semibold">${hm(c.overtime)}</span>` : '—');
        $tr.toggleClass('table-danger', type === 'falta');
    }
    function defaultOut(s) { const m = (toMin(s.work_start_time) ?? 480) + Math.round(s.daily_hours * 60) + s.break_minutes; return `${String(Math.floor(m / 60) % 24).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`; }

    async function loadDay() {
        try {
            day = await get('rh/ajax/attendance_day.php', { date: $('#dayDate').val() });
            const s = day.settings;
            const tags = [];
            if (day.holiday) tags.push(`<span class="rh-pill danger"><i class="bi bi-flag"></i> Feriado: ${esc(day.holiday)}</span>`);
            else if (!day.workday) tags.push('<span class="rh-pill muted">Fim-de-semana</span>');
            if (day.closed) tags.push('<span class="rh-pill closed"><i class="bi bi-lock"></i> Mês fechado</span>');
            $('#dayInfo').html(tags.join(' '));
            $('#dayRules').text(`Horário: entrada ${s.work_start_time}, ${s.daily_hours} h/dia, pausa ${s.break_minutes} min (descontada acima de 6 h), tolerância de atraso ${s.late_tolerance_minutes} min.${day.workday ? '' : ' Dia não útil: todo o tempo trabalhado conta como extra.'}`);
            $('#btnSaveDay, #btnAllPresent').prop('disabled', day.closed);
            const rows = day.employees.map(e => {
                const r = e.record || {};
                const leave = e.leave ? `<small><i class="bi bi-airplane"></i> ${esc(e.leave)} (aprovada)</small>` : '';
                const na = e.not_admitted ? '<small class="text-warning">ainda não admitido</small>' : '';
                return `<tr data-id="${e.id}" data-has="${e.record ? 1 : 0}">
                    <td><div class="who">${esc(e.name)}${e.position ? `<small>${esc(e.position)}</small>` : ''}${leave}${na}</div></td>
                    <td><select class="form-select form-select-sm d-type" ${day.closed ? 'disabled' : ''}>
                        <option value="">—</option>${['presença', 'falta', 'atestado', 'folga'].map(t => `<option value="${t}" ${r.type === t ? 'selected' : ''}>${typeLabel(t)}</option>`).join('')}</select></td>
                    <td><input type="time" class="form-control form-control-sm d-in" value="${esc((r.check_in || '').slice(0, 5))}"></td>
                    <td><input type="time" class="form-control form-control-sm d-out" value="${esc((r.check_out || '').slice(0, 5))}"></td>
                    <td class="num d-worked"></td><td class="num d-late"></td><td class="num d-extra"></td>
                    <td><input type="text" class="form-control form-control-sm d-just" value="${esc(r.justification || '')}" placeholder="${e.leave ? 'Em férias/licença' : ''}" ${day.closed ? 'disabled' : ''}></td>
                </tr>`;
            });
            $('#dayTable tbody').html(rows.join('') || '<tr><td colspan="8" class="rh-empty">Sem funcionários activos.</td></tr>');
            $('#dayTable tbody tr[data-id]').each(function () { updateRow($(this)); });
        } catch (e) { fail(e); }
    }
    $('#dayTable').on('change input', '.d-type, .d-in, .d-out', function () {
        const $tr = $(this).closest('tr');
        if ($(this).hasClass('d-type') && this.value === 'presença' && !$tr.find('.d-in').val()) {
            $tr.find('.d-in').val(day.settings.work_start_time); $tr.find('.d-out').val(defaultOut(day.settings));
        }
        updateRow($tr);
    });
    $('#btnAllPresent').on('click', async () => {
        if (!day.workday && !await confirm('Dia não útil', `${day.holiday ? 'Feriado: ' + esc(day.holiday) : 'Fim-de-semana'}. Marcar presença a todos? Todo o tempo conta como horas extra.`, 'Marcar', 'warning')) return;
        $('#dayTable tbody tr[data-id]').each(function () {
            const $tr = $(this); const e = day.employees.find(x => String(x.id) === this.dataset.id);
            if ($tr.find('.d-type').val() || (e && (e.leave || e.not_admitted))) return;
            $tr.find('.d-type').val('presença'); $tr.find('.d-in').val(day.settings.work_start_time); $tr.find('.d-out').val(defaultOut(day.settings));
            updateRow($tr);
        });
    });
    $('#btnSaveDay').on('click', async () => {
        const data = { date: day.date };
        let n = 0;
        $('#dayTable tbody tr[data-id]').each(function () {
            const $tr = $(this); const type = $tr.find('.d-type').val();
            if (!type && this.dataset.has !== '1') return;   // nada a fazer
            const k = `rows[${this.dataset.id}]`;
            data[`${k}[type]`] = type; data[`${k}[check_in]`] = $tr.find('.d-in').val(); data[`${k}[check_out]`] = $tr.find('.d-out').val(); data[`${k}[justification]`] = $tr.find('.d-just').val();
            n++;
        });
        if (!n) return toast('Nada para gravar neste dia.', 'info');
        const $b = $('#btnSaveDay').prop('disabled', true);
        try { report(await post('rh/ajax/attendance_day.php', data)); loadDay(); } catch (e) { fail(e); } finally { $b.prop('disabled', day && day.closed); }
    });
    $('#dayDate').on('change', loadDay);
    $('#dayToday').on('click', () => { $('#dayDate').val(isoDate(new Date())); loadDay(); });
    $('#dayPrev, #dayNext').on('click', function () {
        const d = new Date(`${$('#dayDate').val()}T12:00:00`); d.setDate(d.getDate() + (this.id === 'dayNext' ? 1 : -1));
        $('#dayDate').val(isoDate(d)); loadDay();
    });
    loadDay();

    // ---------- Resumo mensal ----------
    $('#sumMonth').val(currentMonth());
    let sumTable = null;
    const cnt = (v, cls = '') => `<span class="num d-block ${Number(v) ? cls : 'text-muted'}">${Number(v) || '—'}</span>`;
    function loadSummary() {
        if (!sumTable) {
            sumTable = BootstrapTable.create({
                table: '#sumTable', toolbar: '#sumControls', pageSize: 50, pageSizes: [50, 100], emptyText: 'Sem funcionários activos.',
                ajax: { url: 'rh/ajax/attendance_summary.php', data: () => ({ mes: $('#sumMonth').val() }) },
                dataSource: resp => {
                    const rows = resp.data || []; const sum = k => rows.reduce((a, r) => a + Number(r[k] || 0), 0);
                    $('#sumInfo').text(`${resp.working_days} dias úteis no mês`);
                    $('#sumKpis').html([
                        ['Faltas', sum('faltas'), 'bi-person-x', 'text-danger'],
                        ['Dias sem registo', sum('sem_registo'), 'bi-question-circle', 'text-warning'],
                        ['Atrasos', `${sum('late_days')} · ${hm(sum('late_minutes'))}`, 'bi-alarm', ''],
                        ['Horas extra', hm(sum('overtime_minutes')), 'bi-lightning-charge', 'text-primary'],
                    ].map(([l, v, i, c]) => `<div class="rh-kpi"><div class="label"><i class="bi ${i}"></i> ${l}</div><div class="value ${c}">${esc(v)}</div></div>`).join(''));
                    return rows;
                },
                columns: [
                    { data: 'name', render: v => `<span class="who">${esc(v)}</span>` },
                    { data: 'presencas', render: v => cnt(v), sortValue: r => +r.presencas },
                    { data: 'faltas', render: v => cnt(v, 'text-danger fw-bold'), sortValue: r => +r.faltas },
                    { data: 'atestados', render: v => cnt(v), sortValue: r => +r.atestados },
                    { data: 'folgas', render: v => cnt(v), sortValue: r => +r.folgas },
                    { data: 'ferias', render: v => cnt(v), sortValue: r => +r.ferias },
                    { data: 'sem_registo', render: v => cnt(v, 'text-warning fw-bold'), sortValue: r => +r.sem_registo },
                    { data: 'worked_minutes', render: v => `<span class="num d-block">${hm(v)}</span>`, sortValue: r => +r.worked_minutes },
                    { data: 'late_minutes', render: (v, r) => `<span class="num d-block">${r.late_days ? `${r.late_days}× · ${hm(v)}` : '—'}</span>`, sortValue: r => +r.late_minutes },
                    { data: 'overtime_minutes', render: v => `<span class="num d-block">${hm(v)}</span>`, sortValue: r => +r.overtime_minutes },
                ]
            });
        } else sumTable.reload();
    }
    $('#tabSummaryBtn').on('shown.bs.tab', loadSummary);
    $('#sumMonth').on('change', loadSummary);

    // ---------- Registos ----------
    $('#recMonth').val(currentMonth());
    window.RH.employeeSelect($('#recEmployee'), { placeholder: 'Todos os funcionários' });
    let recTable = null;
    function loadRecords() {
        if (!recTable) {
            recTable = BootstrapTable.create({
                table: '#recTable', toolbar: '#recControls', pageSize: 25, pageSizes: [25, 50, 100], emptyText: 'Sem registos.',
                ajax: { url: 'rh/ajax/list_attendance.php', data: () => ({ mes: $('#recMonth').val(), funcionario: $('#recEmployee').val() || '', type: $('#recType').val() }) },
                dataSource: resp => resp.data,
                columns: [
                    { data: 'date', render: (v, r) => `${dateLabel(v)}${r.holiday ? ` <span class="rh-pill danger" title="${esc(r.holiday)}">feriado</span>` : ''}`, sortValue: r => r.date },
                    { data: 'employee_name', render: v => `<span class="who">${esc(v)}</span>` },
                    { data: 'type', render: typePill },
                    { data: 'check_in', render: (v, r) => v ? `${v.slice(0, 5)} – ${(r.check_out || '').slice(0, 5) || '…'}` : '—' },
                    { data: 'worked_minutes', render: v => `<span class="num d-block">${hm(v)}</span>`, sortValue: r => +r.worked_minutes },
                    { data: 'late_minutes', render: v => `<span class="num d-block ${+v ? 'text-danger' : ''}">${hm(v)}</span>`, sortValue: r => +r.late_minutes },
                    { data: 'overtime_minutes', render: v => `<span class="num d-block">${hm(v)}</span>`, sortValue: r => +r.overtime_minutes },
                    { data: 'justification', render: v => v ? `<small>${esc(v)}</small>` : '' },
                    { data: null, sortable: false, searchable: false, render: (_, r) => `<div class="row-actions">
                        <button type="button" class="btn btn-sm btn-light border rec-edit" data-id="${r.id}" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-sm btn-light border rec-delete" data-id="${r.id}" title="Eliminar"><i class="bi bi-trash"></i></button></div>` }
                ]
            });
        } else recTable.reload();
    }
    $('#tabRecordsBtn').on('shown.bs.tab', loadRecords);
    $('#recMonth, #recType, #recEmployee').on('change', () => recTable && recTable.reload());
    $('#recTable').on('click', '.rec-delete', async function () {
        if (!await confirm('Eliminar registo de ponto?', '', 'Eliminar', 'warning')) return;
        try { toast((await post('rh/ajax/delete_attendance.php', { id: this.dataset.id })).message); refreshAll(); } catch (e) { fail(e); }
    });

    // ---------- Modal individual ----------
    const $modal = $('#modalRecord');
    window.RH.employeeSelect($('#recEmp'), { dropdownParent: $modal });
    const toggleTimes = () => $('.rec-times').toggle($('#recKind').val() === 'presença');
    $('#recKind').on('change', toggleTimes);
    $('#btnNewRecord').on('click', () => {
        $('#formRecord')[0].reset(); $('#recId').val(''); $('#recEmp').empty().trigger('change');
        $('#recDate').val($('#dayDate').val()); $('#modalRecordLabel').text('Registo de ponto'); toggleTimes(); $modal.modal('show');
    });
    $('#recTable').on('click', '.rec-edit', function () {
        const r = recTable.getRows().find(x => String(x.id) === this.dataset.id); if (!r) return;
        $('#formRecord')[0].reset(); $('#recId').val(r.id);
        $('#recEmp').empty().append(new Option(r.employee_name, r.employee_id, true, true)).trigger('change');
        $('#recDate').val(r.date); $('#recKind').val(r.type); $('#recIn').val((r.check_in || '').slice(0, 5)); $('#recOut').val((r.check_out || '').slice(0, 5)); $('#recJust').val(r.justification || '');
        $('#modalRecordLabel').text('Editar registo'); toggleTimes(); $modal.modal('show');
    });
    $('#formRecord').on('submit', async function (e) {
        e.preventDefault();
        try { toast((await post('rh/ajax/save_attendance.php', $(this).serialize())).message); $modal.modal('hide'); refreshAll(); } catch (err) { fail(err); }
    });

    function refreshAll() { loadDay(); if (sumTable) sumTable.reload(); if (recTable) recTable.reload(); }
});
</script>
<?php require_once '../app/views/footer.php'; ?>
