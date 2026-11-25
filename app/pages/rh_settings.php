<?php
require_once '../app/views/layout_creation.php';
?>
<link rel="stylesheet" href="/assets/css/rh.css?v=1.5">

<main class="main-content rh-page rh-settings">
    <div class="container-fluid">
        <div class="rh-head">
            <div>
                <h1>Definições de RH</h1>
                <p>Regras usadas no cálculo da folha, do ponto e das férias. Confirme os valores legais com o contabilista da empresa.</p>
            </div>
            <div class="rh-actions">
                <span class="rh-pill muted" id="dirtyChip"><i class="bi bi-check2-circle"></i> Tudo gravado</span>
            </div>
        </div>

        <div class="row g-3">
            <!-- Navegação -->
            <div class="col-lg-3">
                <nav class="rs-nav rh-card p-2" id="rsNav" aria-label="Secções das definições">
                    <button type="button" class="active" data-pane="impostos"><i class="bi bi-shield-check"></i><span>Impostos e INSS<small>Taxas e subsídios isentos</small></span></button>
                    <button type="button" data-pane="folha"><i class="bi bi-calculator"></i><span>Folha e faltas<small>Divisor, Natal, salário mínimo</small></span></button>
                    <button type="button" data-pane="ponto"><i class="bi bi-clock-history"></i><span>Horário e horas extra<small>Entrada, tolerância, acréscimos</small></span></button>
                    <button type="button" data-pane="ferias"><i class="bi bi-sun"></i><span>Férias<small>Dias por ano</small></span></button>
                    <button type="button" data-pane="irt"><i class="bi bi-bank"></i><span>Tabela de IRT<small>Escalões e simulador</small></span></button>
                    <button type="button" data-pane="feriados"><i class="bi bi-calendar-event"></i><span>Feriados<small id="navHolCount">—</small></span></button>
                    <button type="button" data-pane="email"><i class="bi bi-envelope"></i><span>E-mail do recibo<small>Mensagem enviada</small></span></button>
                </nav>
            </div>

            <div class="col-lg-9">
                <form id="formSettings" autocomplete="off" onsubmit="return false">

                    <!-- Impostos -->
                    <section class="rs-pane active" data-pane="impostos">
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-shield-check"></i><div><h2>Segurança Social (INSS)</h2><p>Percentagens descontadas sobre a base de incidência do INSS.</p></div></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="s_inss_e">Taxa do trabalhador</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="inss_employee_rate" id="s_inss_e" data-no-money><span class="input-group-text">%</span></div>
                                    <div class="rs-def" data-for="inss_employee_rate"></div></div>
                                <div class="col-md-6"><label class="form-label" for="s_inss_p">Taxa da entidade patronal</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="inss_employer_rate" id="s_inss_p" data-no-money><span class="input-group-text">%</span></div>
                                    <div class="rs-def" data-for="inss_employer_rate"></div></div>
                                <div class="col-12">
                                    <label class="rs-switch form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="s_inss_allow">
                                        <span><strong>Incluir a parte isenta dos subsídios na base do INSS</strong><small>Desligado: os subsídios de alimentação e transporte até ao limite isento não pagam INSS.</small></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-basket"></i><div><h2>Subsídios isentos</h2><p>Até este valor por mês o subsídio não paga IRT nem INSS; só o excedente é tributado.</p></div></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="s_food">Alimentação — limite isento</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="food_exempt_limit" id="s_food" data-no-money><span class="input-group-text">Kz/mês</span></div>
                                    <div class="rs-def" data-for="food_exempt_limit"></div></div>
                                <div class="col-md-6"><label class="form-label" for="s_transp">Transporte — limite isento</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="transport_exempt_limit" id="s_transp" data-no-money><span class="input-group-text">Kz/mês</span></div>
                                    <div class="rs-def" data-for="transport_exempt_limit"></div></div>
                            </div>
                        </div>
                    </section>

                    <!-- Folha -->
                    <section class="rs-pane" data-pane="folha">
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-calculator"></i><div><h2>Processamento da folha</h2><p>Como se descontam as faltas e quando se paga o subsídio de Natal.</p></div></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="s_div">Divisor do salário diário</label>
                                    <div class="input-group"><input type="text" inputmode="numeric" class="form-control rs-num" name="absence_divisor" id="s_div" data-no-money><span class="input-group-text">dias</span></div>
                                    <div class="rs-def" data-for="absence_divisor"></div>
                                    <div class="rs-example" id="exAbsence"></div></div>
                                <div class="col-md-6"><label class="form-label" for="s_nat">Mês do subsídio de Natal</label>
                                    <select class="form-select" name="thirteenth_month" id="s_nat"></select>
                                    <div class="rs-def" data-for="thirteenth_month"></div></div>
                                <div class="col-md-6"><label class="form-label" for="s_min">Salário mínimo nacional</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="salario_minimo_nacional" id="s_min" data-no-money><span class="input-group-text">Kz</span></div>
                                    <div class="rs-def" data-for="salario_minimo_nacional"></div>
                                    <div class="form-text">Serve para avisar quando um salário base fica abaixo.</div></div>
                                <div class="col-md-6"><label class="form-label" for="s_notice">Aviso prévio na demissão</label>
                                    <div class="input-group"><input type="text" inputmode="numeric" class="form-control rs-num" name="aviso_previo_demissao_dias" id="s_notice" data-no-money><span class="input-group-text">dias</span></div>
                                    <div class="rs-def" data-for="aviso_previo_demissao_dias"></div></div>
                            </div>
                        </div>
                    </section>

                    <!-- Ponto -->
                    <section class="rs-pane" data-pane="ponto">
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-clock"></i><div><h2>Horário de trabalho</h2><p>Usado na folha diária do ponto para calcular atrasos e horas trabalhadas.</p></div></div>
                            <div class="row g-3">
                                <div class="col-md-3"><label class="form-label" for="s_start">Hora de entrada</label>
                                    <input type="time" class="form-control" name="work_start_time" id="s_start">
                                    <div class="rs-def" data-for="work_start_time"></div></div>
                                <div class="col-md-3"><label class="form-label" for="s_daily">Horas por dia</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="daily_hours" id="s_daily" data-no-money><span class="input-group-text">h</span></div>
                                    <div class="rs-def" data-for="daily_hours"></div></div>
                                <div class="col-md-3"><label class="form-label" for="s_break">Intervalo de almoço</label>
                                    <div class="input-group"><input type="text" inputmode="numeric" class="form-control rs-num" name="break_minutes" id="s_break" data-no-money><span class="input-group-text">min</span></div>
                                    <div class="rs-def" data-for="break_minutes"></div></div>
                                <div class="col-md-3"><label class="form-label" for="s_tol">Tolerância de atraso</label>
                                    <div class="input-group"><input type="text" inputmode="numeric" class="form-control rs-num" name="late_tolerance_minutes" id="s_tol" data-no-money><span class="input-group-text">min</span></div>
                                    <div class="rs-def" data-for="late_tolerance_minutes"></div></div>
                                <div class="col-12"><div class="rs-example" id="exSchedule"></div></div>
                            </div>
                        </div>
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-lightning-charge"></i><div><h2>Horas extra</h2><p>Valor hora = salário base ÷ horas por mês. As primeiras horas do mês têm um acréscimo; acima do limite, outro.</p></div></div>
                            <div class="row g-3">
                                <div class="col-md-3"><label class="form-label" for="s_mh">Horas de trabalho por mês</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="monthly_hours" id="s_mh" data-no-money><span class="input-group-text">h</span></div>
                                    <div class="rs-def" data-for="monthly_hours"></div></div>
                                <div class="col-md-3"><label class="form-label" for="s_otl">Limite mensal</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="overtime_threshold_hours" id="s_otl" data-no-money><span class="input-group-text">h</span></div>
                                    <div class="rs-def" data-for="overtime_threshold_hours"></div></div>
                                <div class="col-md-3"><label class="form-label" for="s_ot1">Acréscimo até ao limite</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="overtime_rate_1" id="s_ot1" data-no-money><span class="input-group-text">%</span></div>
                                    <div class="rs-def" data-for="overtime_rate_1"></div></div>
                                <div class="col-md-3"><label class="form-label" for="s_ot2">Acréscimo acima do limite</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="overtime_rate_2" id="s_ot2" data-no-money><span class="input-group-text">%</span></div>
                                    <div class="rs-def" data-for="overtime_rate_2"></div></div>
                                <div class="col-12"><div class="rs-example" id="exOvertime"></div></div>
                            </div>
                        </div>
                    </section>

                    <!-- Férias -->
                    <section class="rs-pane" data-pane="ferias">
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-sun"></i><div><h2>Férias</h2><p>Dias úteis (sem fins-de-semana nem feriados) a que cada funcionário tem direito.</p></div></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="s_vac">Dias de férias por ano</label>
                                    <div class="input-group"><input type="text" inputmode="numeric" class="form-control rs-num" name="vacation_days_per_year" id="s_vac" data-no-money><span class="input-group-text">dias úteis</span></div>
                                    <div class="rs-def" data-for="vacation_days_per_year"></div></div>
                                <div class="col-md-6"><label class="form-label" for="s_vac1">No ano de admissão, por mês completo</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control rs-num" name="vacation_days_first_year_per_month" id="s_vac1" data-no-money><span class="input-group-text">dias úteis</span></div>
                                    <div class="rs-def" data-for="vacation_days_first_year_per_month"></div></div>
                                <div class="col-12"><div class="rs-example" id="exVacation"></div></div>
                            </div>
                        </div>
                    </section>

                    <!-- IRT -->
                    <section class="rs-pane" data-pane="irt">
                        <div class="rh-card">
                            <div class="rs-title justify-content-between">
                                <span class="d-flex gap-3"><i class="bi bi-bank"></i><div><h2>Tabela de IRT — Grupo A</h2><p>IRT = parcela fixa + taxa × (matéria colectável − excesso sobre). O último escalão fica sem limite.</p></div></span>
                                <span class="d-flex gap-2 flex-shrink-0">
                                    <button type="button" class="btn btn-sm btn-light border" id="btnIrtAdd"><i class="bi bi-plus-lg"></i> Escalão</button>
                                    <button type="button" class="btn btn-sm btn-light border" id="btnIrtReset" title="Voltar à tabela por omissão"><i class="bi bi-arrow-counterclockwise"></i> Repor</button>
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="table rh-table rs-irt" id="irtTable">
                                    <thead><tr><th>#</th><th>Matéria colectável</th><th>Até (Kz)</th><th>Parcela fixa (Kz)</th><th>Taxa</th><th>Excesso sobre (Kz)</th><th></th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="rh-card rs-sim">
                            <div class="rs-title"><i class="bi bi-calculator"></i><div><h2>Simulador</h2><p>Escreva a matéria colectável (salário tributável − INSS) para ver o IRT com a tabela acima.</p></div></div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5"><label class="form-label" for="irtTest">Matéria colectável</label>
                                    <div class="input-group"><input type="text" inputmode="decimal" class="form-control" id="irtTest" placeholder="ex.: 250 000" data-no-money><span class="input-group-text">Kz</span></div></div>
                                <div class="col-md-7"><div class="rs-sim-out" id="irtTestResult"><span class="text-muted">—</span></div></div>
                            </div>
                        </div>
                    </section>

                    <!-- Feriados -->
                    <section class="rs-pane" data-pane="feriados">
                        <div class="rh-card">
                            <div class="rs-title justify-content-between">
                                <span class="d-flex gap-3"><i class="bi bi-calendar-event"></i><div><h2>Feriados</h2><p>Não contam como faltas nem como dias de férias. Gravam-se logo, sem precisar de "Gravar".</p></div></span>
                                <span class="d-flex gap-2 align-items-center flex-shrink-0">
                                    <select class="form-select form-select-sm" id="holYear" style="width:auto" aria-label="Ano"></select>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnHolImport"><i class="bi bi-flag"></i> Importar feriados nacionais</button>
                                </span>
                            </div>
                            <div class="rs-hol-add">
                                <input type="date" class="form-control" id="holDate" aria-label="Data">
                                <input type="text" class="form-control" id="holName" placeholder="Nome (ex.: Tolerância de ponto)" aria-label="Nome" maxlength="150">
                                <button type="button" class="btn btn-primary" id="btnHolAdd"><i class="bi bi-plus-lg"></i> Adicionar</button>
                            </div>
                            <div id="holList" class="rs-hol-list"></div>
                        </div>
                    </section>

                    <!-- E-mail -->
                    <section class="rs-pane" data-pane="email">
                        <div class="rh-card">
                            <div class="rs-title"><i class="bi bi-envelope"></i><div><h2>Mensagem do e-mail do recibo</h2><p>Texto enviado com o recibo em PDF. Clique numa variável para a inserir.</p></div></div>
                            <div class="d-flex flex-wrap gap-2 mb-2" id="mailVars">
                                <button type="button" class="btn btn-sm btn-light border" data-var="{nome}">{nome} <small class="text-muted">nome do funcionário</small></button>
                                <button type="button" class="btn btn-sm btn-light border" data-var="{mes}">{mes} <small class="text-muted">mês</small></button>
                                <button type="button" class="btn btn-sm btn-light border" data-var="{empresa}">{empresa} <small class="text-muted">empresa</small></button>
                                <button type="button" class="btn btn-sm btn-light border" data-var="{liquido}">{liquido} <small class="text-muted">valor líquido</small></button>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6"><textarea class="form-control" rows="9" name="payslip_email_message" id="s_mail" maxlength="2000" aria-label="Mensagem"></textarea></div>
                                <div class="col-md-6"><div class="rs-mail-preview"><div class="small text-muted mb-1">Pré-visualização</div><div id="mailPreview"></div></div></div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </div>

    <div class="rs-savebar" id="saveBar" role="region" aria-label="Alterações por gravar">
        <span><i class="bi bi-pencil-square"></i> <strong id="dirtyCount">0</strong> alteração(ões) por gravar</span>
        <span class="d-flex gap-2">
            <button type="button" class="btn btn-light" id="btnDiscard">Descartar</button>
            <button type="button" class="btn btn-primary px-4" id="btnSaveSettings"><i class="bi bi-check2"></i> Gravar definições</button>
        </span>
    </div>
</main>

<script src="/assets/js/rh.js?v=1.1"></script>
<script>
$(function () {
    const { esc, money, kz, post, get, toast, fail, confirm, dateLabel, num } = window.RH;
    const monthsPt = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    const fmt = v => { const [i, d] = String(Math.round(Number(v || 0) * 100) / 100).split('.'); return i.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + (d ? ',' + d : ''); };
    $('#s_nat').html(monthsPt.map((m, i) => `<option value="${i + 1}">${m}</option>`).join(''));
    const y = new Date().getFullYear();
    $('#holYear').html([y - 1, y, y + 1].map(v => `<option ${v === y ? 'selected' : ''}>${v}</option>`).join(''));

    // ---------- navegação ----------
    function showPane(p) {
        $('#rsNav button').removeClass('active').filter(`[data-pane="${p}"]`).addClass('active');
        $('.rs-pane').removeClass('active').filter(`[data-pane="${p}"]`).addClass('active');
        try { localStorage.setItem('rh_settings_pane', p); } catch (e) {}
    }
    $('#rsNav').on('click', 'button', function () { showPane(this.dataset.pane); });
    try { const p = localStorage.getItem('rh_settings_pane'); if (p && $(`.rs-pane[data-pane="${p}"]`).length) showPane(p); } catch (e) {}

    // ---------- estado / alterações ----------
    let saved = null, defaults = {}, defaultIrt = [];
    const snapshot = () => {
        const d = Object.fromEntries(new FormData($('#formSettings')[0]).entries());
        d.inss_on_exempt_allowances = $('#s_inss_allow').is(':checked') ? 1 : 0;
        d.irt_table = JSON.stringify(readIrt());
        return d;
    };
    const norm = (k, v) => k === 'irt_table' || k === 'payslip_email_message' || k === 'work_start_time' ? String(v ?? '') : String(num(v));
    function refreshDirty() {
        if (!saved) return;
        const now = snapshot();
        const changed = Object.keys(now).filter(k => norm(k, now[k]) !== norm(k, saved[k]));
        $('#formSettings [name]').each(function () { $(this).closest('.input-group, .col-md-6, .col-md-3, .col-12').toggleClass('rs-changed', changed.includes(this.name)); });
        $('#saveBar').toggleClass('show', changed.length > 0);
        $('#dirtyCount').text(changed.length);
        $('#dirtyChip').attr('class', `rh-pill ${changed.length ? 'pending' : 'muted'}`).html(changed.length ? '<i class="bi bi-exclamation-circle"></i> Alterações por gravar' : '<i class="bi bi-check2-circle"></i> Tudo gravado');
        renderDefaults(now);
        renderExamples(now);
    }
    function renderDefaults(now) {
        $('.rs-def').each(function () {
            const k = this.dataset.for; if (!(k in defaults)) return;
            let d = defaults[k], label = k === 'thirteenth_month' ? monthsPt[Number(d) - 1] : k === 'work_start_time' ? String(d).slice(0, 5) : fmt(d);
            const same = k === 'work_start_time' ? String(now[k]).slice(0, 5) === String(d).slice(0, 5) : num(now[k]) === num(d);
            $(this).html(same ? `<i class="bi bi-check2"></i> valor por omissão` : `por omissão: ${esc(label)} · <a href="#" class="rs-reset" data-k="${k}">repor</a>`).toggleClass('diff', !same);
        });
    }
    $('#formSettings').on('click', '.rs-reset', function (e) {
        e.preventDefault();
        const k = this.dataset.k, v = defaults[k];
        $(`#formSettings [name="${k}"]`).val(k === 'work_start_time' ? String(v).slice(0, 5) : v);
        refreshDirty();
    });
    function renderExamples(s) {
        const base = 200000;
        const day = base / Math.max(1, num(s.absence_divisor));
        $('#exAbsence').html(`<i class="bi bi-lightbulb"></i> Exemplo: salário de ${kz(base)} → cada falta desconta <strong>${kz(day)}</strong>.`);
        const toMin = t => { const m = /^(\d{1,2}):(\d{2})/.exec(t || ''); return m ? +m[1] * 60 + +m[2] : 480; };
        const st = toMin(s.work_start_time), end = st + Math.round(num(s.daily_hours) * 60) + num(s.break_minutes);
        const hm = m => `${String(Math.floor(m / 60) % 24).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
        $('#exSchedule').html(`<i class="bi bi-lightbulb"></i> Dia normal: entrada às <strong>${hm(st)}</strong>, saída às <strong>${hm(end)}</strong> (com ${num(s.break_minutes)} min de almoço). Conta atraso quem entrar depois das <strong>${hm(st + num(s.late_tolerance_minutes))}</strong>.`);
        const hour = base / Math.max(1, num(s.monthly_hours));
        $('#exOvertime').html(`<i class="bi bi-lightbulb"></i> Salário de ${kz(base)} → valor hora ${kz(hour)}. Hora extra: <strong>${kz(hour * (1 + num(s.overtime_rate_1) / 100))}</strong> até ${fmt(num(s.overtime_threshold_hours))} h no mês, <strong>${kz(hour * (1 + num(s.overtime_rate_2) / 100))}</strong> acima.`);
        $('#exVacation').html(`<i class="bi bi-lightbulb"></i> Quem entrou em Março tem ${fmt(Math.min(num(s.vacation_days_per_year), 9 * num(s.vacation_days_first_year_per_month)))} dias nesse ano (9 meses × ${fmt(num(s.vacation_days_first_year_per_month))}) e ${fmt(num(s.vacation_days_per_year))} dias nos anos seguintes.`);
        const msg = String(s.payslip_email_message || '');
        $('#mailPreview').html(esc(msg.replace(/\{nome\}/g, 'Ana Paula').replace(/\{mes\}/g, 'Novembro de 2026').replace(/\{empresa\}/g, 'A sua empresa').replace(/\{liquido\}/g, '465 927,94 Kz')).replace(/\n/g, '<br>') || '<span class="text-muted">(vazio)</span>');
    }
    $('#formSettings').on('input change', 'input, select, textarea', refreshDirty);

    // ---------- IRT ----------
    const irtRow = r => `<tr>
        <td class="text-muted rs-irt-n"></td><td class="rs-irt-range small text-muted"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm irt-up" value="${r.up_to === null || r.up_to === undefined || r.up_to === '' ? '' : fmt(r.up_to)}" placeholder="sem limite" data-no-money aria-label="Até"></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm irt-fixed" value="${fmt(r.fixed)}" data-no-money aria-label="Parcela fixa"></td>
        <td><div class="input-group input-group-sm"><input type="text" inputmode="decimal" class="form-control irt-rate" value="${fmt(r.rate)}" data-no-money aria-label="Taxa"><span class="input-group-text">%</span></div></td>
        <td><input type="text" inputmode="decimal" class="form-control form-control-sm irt-over" value="${fmt(r.over)}" data-no-money aria-label="Excesso sobre"></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-light border irt-del" title="Remover escalão"><i class="bi bi-x-lg"></i></button></td></tr>`;
    const readIrt = () => $('#irtTable tbody tr').toArray().map(tr => ({
        up_to: $(tr).find('.irt-up').val().trim() === '' ? null : num($(tr).find('.irt-up').val()),
        fixed: num($(tr).find('.irt-fixed').val()), rate: num($(tr).find('.irt-rate').val()), over: num($(tr).find('.irt-over').val())
    }));
    function irtLabels() {
        let prev = 0;
        $('#irtTable tbody tr').each(function (i) {
            const up = $(this).find('.irt-up').val().trim();
            $(this).find('.rs-irt-n').text(i + 1);
            $(this).find('.rs-irt-range').text(up === '' ? `acima de ${money(prev)}` : `${money(prev ? prev + 0.01 : 0)} – ${money(num(up))}`);
            if (up !== '') prev = num(up);
        });
    }
    function testIrt() {
        irtLabels();
        const v = num($('#irtTest').val());
        if (!v) return $('#irtTestResult').html('<span class="text-muted">—</span>');
        const table = readIrt().map((r, i) => ({ ...r, i })).sort((a, b) => (a.up_to ?? Infinity) - (b.up_to ?? Infinity));
        const r = table.find(t => t.up_to === null || v <= t.up_to);
        if (!r) return $('#irtTestResult').html('<span class="text-danger">Fora da tabela</span>');
        const irt = (r.rate <= 0 && r.fixed <= 0) ? 0 : r.fixed + r.rate / 100 * Math.max(0, v - r.over);
        $('#irtTestResult').html(`<div><span>IRT</span><strong>${kz(irt)}</strong></div>
            <div><span>Escalão</span><strong>${r.i + 1}.º</strong></div>
            <div><span>Taxa efectiva</span><strong>${fmt(irt / v * 100)}%</strong></div>
            <small class="text-muted">${irt ? `${money(r.fixed)} + ${fmt(r.rate)}% × (${money(v)} − ${money(r.over)})` : 'Isento neste escalão'}</small>`);
    }
    $('#irtTest').on('input', testIrt);
    $('#irtTable').on('input', 'input', () => { testIrt(); refreshDirty(); })
        .on('click', '.irt-del', function () { $(this).closest('tr').remove(); testIrt(); refreshDirty(); });
    $('#btnIrtAdd').on('click', () => { $('#irtTable tbody').append(irtRow({ up_to: null, fixed: 0, rate: 0, over: 0 })); testIrt(); refreshDirty(); });
    $('#btnIrtReset').on('click', async () => {
        if (!await confirm('Repor a tabela de IRT por omissão?', 'Só fica definitivo depois de "Gravar definições".', 'Repor')) return;
        $('#irtTable tbody').html(defaultIrt.map(irtRow).join('')); testIrt(); refreshDirty();
    });

    // ---------- carregar / gravar ----------
    function fill(s) {
        $('#formSettings [name]').each(function () {
            if (this.name in s && typeof s[this.name] !== 'object') this.value = this.name === 'work_start_time' ? String(s[this.name]).slice(0, 5) : (this.classList.contains('rs-num') ? fmt(s[this.name]) : s[this.name]);
        });
        $('#s_inss_allow').prop('checked', Number(s.inss_on_exempt_allowances) === 1);
        $('#irtTable tbody').html(s.irt_table.map(irtRow).join(''));
        testIrt();
    }
    async function load() {
        try {
            const resp = await get('rh/ajax/rh_settings.php', { year: $('#holYear').val() });
            defaults = resp.defaults || {}; defaultIrt = resp.default_irt_table;
            fill(resp.settings);
            saved = snapshot();
            renderHolidays(resp.holidays);
            refreshDirty();
        } catch (e) { fail(e); }
    }
    $('#btnDiscard').on('click', () => load());
    $('#btnSaveSettings').on('click', async () => {
        const data = snapshot();
        Object.keys(data).forEach(k => { if (!['irt_table', 'payslip_email_message', 'work_start_time', 'thirteenth_month'].includes(k) && data[k] !== '') data[k] = num(data[k]); });
        data.action = 'save';
        const $b = $('#btnSaveSettings').prop('disabled', true);
        try { toast((await post('rh/ajax/rh_settings.php', data)).message); await load(); } catch (e) { fail(e); }
        finally { $b.prop('disabled', false); }
    });

    // ---------- feriados ----------
    function renderHolidays(list) {
        const dow = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        $('#navHolCount').text(`${list.length} em ${$('#holYear').val()}`);
        if (!list.length) return $('#holList').html(`<div class="rh-empty"><i class="bi bi-calendar-x"></i>Sem feriados em ${esc($('#holYear').val())}. Use "Importar feriados nacionais".</div>`);
        let html = '', month = -1;
        for (const h of list) {
            const d = new Date(h.date + 'T00:00'), m = d.getMonth(), we = d.getDay() === 0 || d.getDay() === 6;
            if (m !== month) { month = m; html += `<div class="rs-hol-month">${monthsPt[m]}</div>`; }
            html += `<div class="rs-hol-item"><span class="rs-hol-day"><strong>${d.getDate()}</strong><small>${dow[d.getDay()]}</small></span>
                <span class="flex-grow-1">${esc(h.name)}${we ? ' <span class="rh-pill muted">fim-de-semana</span>' : ''}</span>
                <button type="button" class="btn btn-sm btn-light border hol-del" data-id="${h.id}" data-name="${esc(h.name)}" title="Remover"><i class="bi bi-trash text-danger"></i></button></div>`;
        }
        $('#holList').html(html);
    }
    async function reloadHolidays() {
        try { renderHolidays((await get('rh/ajax/rh_settings.php', { year: $('#holYear').val() })).holidays); } catch (e) { fail(e); }
    }
    $('#holYear').on('change', reloadHolidays);
    $('#btnHolImport').on('click', async () => {
        try { toast((await post('rh/ajax/rh_settings.php', { action: 'holidays_import', year: $('#holYear').val() })).message); reloadHolidays(); } catch (e) { fail(e); }
    });
    $('#btnHolAdd').on('click', async () => {
        try { toast((await post('rh/ajax/rh_settings.php', { action: 'holiday_add', date: $('#holDate').val(), name: $('#holName').val() })).message); $('#holDate, #holName').val(''); reloadHolidays(); } catch (e) { fail(e); }
    });
    $('#holName').on('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); $('#btnHolAdd').click(); } });
    $('#holList').on('click', '.hol-del', async function () {
        if (!await confirm('Remover feriado?', esc(this.dataset.name), 'Remover', 'warning')) return;
        try { toast((await post('rh/ajax/rh_settings.php', { action: 'holiday_delete', id: this.dataset.id })).message); reloadHolidays(); } catch (e) { fail(e); }
    });

    // ---------- e-mail ----------
    $('#mailVars').on('click', '[data-var]', function () {
        const ta = document.getElementById('s_mail'), v = this.dataset.var, a = ta.selectionStart ?? ta.value.length;
        ta.value = ta.value.slice(0, a) + v + ta.value.slice(ta.selectionEnd ?? a);
        ta.focus(); ta.selectionStart = ta.selectionEnd = a + v.length; refreshDirty();
    });

    load();
});
</script>
<?php require_once '../app/views/footer.php'; ?>
