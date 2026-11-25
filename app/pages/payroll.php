<?php
require_once '../app/config/db.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

try {
    subscription_require_feature($pdo, (int)($_SESSION['user']['company_id'] ?? 0), 'rh');
} catch (Exception $e) {
    $cid = (int)($_SESSION['user']['company_id'] ?? 0);
    header('Location: subscription.php?company_id=' . $cid . '&upgrade=rh');
    exit;
}

require_once '../app/views/layout_creation.php';
?>
<link rel="stylesheet" href="/assets/css/rh.css?v=1.5">

<main class="main-content rh-page">
    <div class="container-fluid">

        <div class="rh-head">
            <div>
                <h1>Folha de Pagamento</h1>
                <p>Processamento mensal de salários: cálculo, aprovação, pagamento e mapas legais.</p>
            </div>
            <div class="rh-actions">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download"></i> Mapas e exportações
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header">Folha do mês</h6></li>
                        <li><a class="dropdown-item" href="#" data-export="folha" data-format="pdf"><i class="bi bi-filetype-pdf me-2"></i>Folha de salários (PDF)</a></li>
                        <li><a class="dropdown-item" href="#" data-export="folha" data-format="xlsx"><i class="bi bi-file-earmark-excel me-2"></i>Mapa salarial (Excel)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Obrigações legais</h6></li>
                        <li><a class="dropdown-item" href="#" data-export="inss" data-format="pdf"><i class="bi bi-shield-check me-2"></i>Mapa INSS (PDF)</a></li>
                        <li><a class="dropdown-item" href="#" data-export="inss" data-format="xlsx"><i class="bi bi-shield-check me-2"></i>Mapa INSS (Excel)</a></li>
                        <li><a class="dropdown-item" href="#" data-export="irt" data-format="pdf"><i class="bi bi-bank me-2"></i>Mapa IRT (PDF)</a></li>
                        <li><a class="dropdown-item" href="#" data-export="irt" data-format="xlsx"><i class="bi bi-bank me-2"></i>Mapa IRT (Excel)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header">Pagamento</h6></li>
                        <li><a class="dropdown-item" href="#" data-export="banco" data-format="xlsx"><i class="bi bi-cash-coin me-2"></i>Transferências bancárias (Excel)</a></li>
                        <li><a class="dropdown-item" href="#" data-export="banco" data-format="csv"><i class="bi bi-filetype-csv me-2"></i>Transferências bancárias (CSV)</a></li>
                    </ul>
                </div>
                <a href="/adiantamentos" data-spa class="btn btn-sm btn-outline-secondary"><i class="bi bi-wallet2"></i> Adiantamentos</a>
                <button type="button" class="btn btn-sm btn-primary" id="btnNewPayroll"><i class="bi bi-plus-lg"></i> Nova folha</button>
            </div>
        </div>

        <!-- Mês + estado do período -->
        <div class="rh-period">
            <div class="info">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light border" id="prevMonth" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></button>
                    <input type="month" id="inputMesReferencia" class="form-control form-control-sm" style="width:auto" aria-label="Mês de referência">
                    <button type="button" class="btn btn-sm btn-light border" id="nextMonth" aria-label="Mês seguinte"><i class="bi bi-chevron-right"></i></button>
                </div>
                <strong id="periodLabel">—</strong>
                <span id="periodStatus" class="rh-pill open">Aberto</span>
                <span class="text-muted small" id="periodCoverage"></span>
            </div>
            <div class="rh-actions">
                <button type="button" class="btn btn-sm btn-dark" id="btnGerarFolhaMes"><i class="bi bi-magic"></i> Gerar folha do mês</button>
                <button type="button" class="btn btn-sm btn-outline-dark" id="btnClosePeriod"><i class="bi bi-lock"></i> Fechar mês</button>
                <button type="button" class="btn btn-sm btn-outline-dark d-none" id="btnReopenPeriod"><i class="bi bi-unlock"></i> Reabrir mês</button>
            </div>
        </div>

        <!-- Indicadores -->
        <div class="rh-kpis">
            <div class="rh-kpi"><div class="label">Folhas</div><div class="value" id="kpiCount">0</div><div class="hint" id="kpiStates">—</div></div>
            <div class="rh-kpi"><div class="label">Total bruto</div><div class="value" id="kpiGross">0,00 Kz</div><div class="hint">abonos antes de descontos</div></div>
            <div class="rh-kpi"><div class="label">INSS (3% + 8%)</div><div class="value" id="kpiInss">0,00 Kz</div><div class="hint" id="kpiInssHint">—</div></div>
            <div class="rh-kpi"><div class="label">IRT retido</div><div class="value" id="kpiIrt">0,00 Kz</div><div class="hint">a entregar à AGT</div></div>
            <div class="rh-kpi"><div class="label">Custo para a empresa</div><div class="value" id="kpiCost">0,00 Kz</div><div class="hint">bruto − faltas + INSS patronal</div></div>
            <div class="rh-kpi accent"><div class="label">Líquido a pagar</div><div class="value" id="kpiNet">0,00 Kz</div><div class="hint">transferência aos funcionários</div></div>
        </div>

        <div class="rh-card p-0">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center px-3 pt-3">
                <div class="rh-filters">
                    <input type="search" class="form-control form-control-sm" id="payrollSearch" placeholder="Pesquisar funcionário…" aria-label="Pesquisar funcionário">
                    <select class="form-select form-select-sm" id="filterStatus" aria-label="Filtrar por estado">
                        <option value="">Todos os estados</option>
                        <option value="Pendente">Pendentes</option>
                        <option value="Aprovado">Aprovadas</option>
                        <option value="Pago">Pagas</option>
                    </select>
                </div>
                <div id="payrollTableControls" class="mb-0"></div>
            </div>

            <div class="px-3 pt-2">
                <div class="rh-bulkbar" id="bulkBar">
                    <strong><span id="bulkCount">0</span> selecionada(s)</strong>
                    <span class="text-muted">·</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bulk="approve"><i class="bi bi-check2-circle"></i> Aprovar</button>
                    <button type="button" class="btn btn-sm btn-outline-success" data-bulk="pay"><i class="bi bi-cash-stack"></i> Marcar como pago</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bulk="email"><i class="bi bi-envelope"></i> Enviar recibos</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bulk="recalc"><i class="bi bi-arrow-repeat"></i> Recalcular</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bulk="revert"><i class="bi bi-arrow-counterclockwise"></i> Voltar a pendente</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bulk="delete"><i class="bi bi-trash"></i> Eliminar</button>
                    <button type="button" class="btn btn-sm btn-link text-muted ms-auto" id="bulkClear">Limpar seleção</button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="payrollTable" class="table rh-table">
                    <thead>
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="checkAll" aria-label="Selecionar todas"></th>
                            <th>Funcionário</th>
                            <th>Mês</th>
                            <th class="num">Base</th>
                            <th class="num">Bruto</th>
                            <th class="num">Descontos</th>
                            <th class="num">Líquido</th>
                            <th>Estado</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="px-3 pb-3"></div>
        </div>
    </div>
</main>

<!-- Modal: folha individual com recibo em tempo real -->
<div class="modal fade rh-modal" id="modalPayroll" tabindex="-1" aria-labelledby="modalPayrollLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="formPayroll" autocomplete="off" novalidate>
                <input type="hidden" name="id" id="payrollId">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalPayrollLabel">Nova folha</h5>
                        <small class="opacity-75">Os campos vazios usam os valores automáticos (cargo, ponto, férias, adiantamentos).</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="btn-group w-100 mb-3" role="group" aria-label="Para quem" id="pfModeGroup">
                                <input type="radio" class="btn-check" name="pf_mode" id="pfModeOne" value="one" checked>
                                <label class="btn btn-outline-primary" for="pfModeOne"><i class="bi bi-person"></i> Um funcionário</label>
                                <input type="radio" class="btn-check" name="pf_mode" id="pfModeMany" value="many">
                                <label class="btn btn-outline-primary" for="pfModeMany"><i class="bi bi-people"></i> Vários / todos</label>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8 single-only">
                                    <label class="form-label" for="employee_id_payroll">Funcionário</label>
                                    <select name="employee_id" id="employee_id_payroll" class="form-select" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="pf_month">Mês de referência</label>
                                    <input type="month" name="reference_month" id="pf_month" class="form-control" required>
                                </div>
                            </div>

                            <div class="many-only d-none">
                                <div class="section-label d-flex justify-content-between align-items-center">
                                    <span>Funcionários <span class="badge text-bg-primary ms-1" id="pfManyCount">0</span></span>
                                    <span class="d-flex gap-2 align-items-center">
                                        <input type="search" class="form-control form-control-sm" id="pfManySearch" placeholder="Procurar…" style="width:170px" aria-label="Procurar funcionário">
                                        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="pfManyAll"><label class="form-check-label small text-body" style="text-transform:none;letter-spacing:0" for="pfManyAll">Selecionar todos</label></div>
                                    </span>
                                </div>
                                <div class="pf-many-list" id="pfManyList"><div class="rh-empty">A carregar…</div></div>
                                <div class="section-label">Valores iguais para todos (opcional)</div>
                                <div class="row g-3">
                                    <div class="col-md-4"><label class="form-label" for="pfm_bonus">Prémio / bónus</label><input type="number" step="0.01" min="0" id="pfm_bonus" class="form-control pfm-calc" placeholder="0"></div>
                                    <div class="col-md-4"><label class="form-label" for="pfm_other">Outros abonos</label><input type="number" step="0.01" min="0" id="pfm_other" class="form-control pfm-calc" placeholder="0"></div>
                                    <div class="col-md-4"><label class="form-label" for="pfm_disc">Outros descontos</label><input type="number" step="0.01" min="0" id="pfm_disc" class="form-control pfm-calc" placeholder="0"></div>
                                    <div class="col-12 small text-muted">Salário, subsídios, horas extra, faltas, férias e adiantamentos de cada um são calculados automaticamente (cargo, ponto e férias). Ajustes individuais fazem-se depois, editando a folha.</div>
                                </div>
                            </div>

                            <div class="single-only">
                            <div class="section-label">Abonos</div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label" for="pf_base">Salário base</label><input type="number" step="0.01" min="0" name="base_salary" id="pf_base" class="form-control rh-calc"></div>
                                <div class="col-md-4"><label class="form-label" for="pf_food">Subs. alimentação</label><input type="number" step="0.01" min="0" name="food_allowance" id="pf_food" class="form-control rh-calc"></div>
                                <div class="col-md-4"><label class="form-label" for="pf_transport">Subs. transporte</label><input type="number" step="0.01" min="0" name="transport_allowance" id="pf_transport" class="form-control rh-calc"></div>
                                <div class="col-md-4"><label class="form-label" for="pf_vac">Subs. férias (% base)</label><input type="number" step="0.01" min="0" max="100" name="vacation_subsidy_pct" id="pf_vac" class="form-control rh-calc"><div class="auto-hint" id="hintVac"></div></div>
                                <div class="col-md-4"><label class="form-label" for="pf_nat">Subs. Natal (% base)</label><input type="number" step="0.01" min="0" max="100" name="thirteenth_subsidy_pct" id="pf_nat" class="form-control rh-calc"><div class="auto-hint" id="hintNat"></div></div>
                                <div class="col-md-4"><label class="form-label" for="pf_ot">Horas extra (h)</label><input type="number" step="0.25" min="0" name="overtime_hours" id="pf_ot" class="form-control rh-calc"><div class="auto-hint">vazio = horas do ponto</div></div>
                                <div class="col-md-4"><label class="form-label" for="pf_bonus">Prémios / bónus</label><input type="number" step="0.01" min="0" name="bonuses" id="pf_bonus" class="form-control rh-calc" value="0"></div>
                                <div class="col-md-4"><label class="form-label" for="pf_comm">Comissões</label><input type="number" step="0.01" min="0" name="commissions" id="pf_comm" class="form-control rh-calc" value="0"></div>
                                <div class="col-md-4"><label class="form-label" for="pf_other">Outros abonos</label><input type="number" step="0.01" min="0" name="other_earnings" id="pf_other" class="form-control rh-calc" value="0"></div>
                            </div>

                            <div class="section-label">Descontos</div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label" for="pf_abs">Faltas injustificadas (dias)</label><input type="number" step="0.5" min="0" name="absence_days" id="pf_abs" class="form-control rh-calc"><div class="auto-hint">vazio = faltas do ponto</div></div>
                                <div class="col-md-4"><label class="form-label" for="pf_disc">Outros descontos</label><input type="number" step="0.01" min="0" name="discounts" id="pf_disc" class="form-control rh-calc" value="0"></div>
                                <div class="col-md-4"><label class="form-label" for="pf_sales">Vendas (informativo)</label><input type="number" step="0.01" min="0" name="sales" id="pf_sales" class="form-control" value="0"></div>
                            </div>

                            </div>

                            <div class="section-label">Estado</div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="pf_status">Estado</label>
                                    <select name="status" id="pf_status" class="form-select">
                                        <option value="Pendente">Pendente (em revisão)</option>
                                        <option value="Aprovado">Aprovado</option>
                                        <option value="Pago">Pago</option>
                                    </select>
                                </div>
                                <div class="col-md-4"><label class="form-label" for="pf_paydate">Data de pagamento</label><input type="date" name="payment_date" id="pf_paydate" class="form-control"></div>
                                <div class="col-md-12"><label class="form-label" for="pf_notes">Observações (aparecem no recibo)</label><textarea name="notes" id="pf_notes" rows="2" class="form-control" maxlength="1000"></textarea></div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="rh-slip single-only" id="slipPreview">
                                <h6><span>Recibo (simulação)</span><span class="rh-pill muted" id="slipMonth">—</span></h6>
                                <div id="slipBody" class="rh-empty"><i class="bi bi-receipt"></i>Escolha o funcionário e o mês para ver o cálculo.</div>
                            </div>
                            <div class="rh-slip many-only d-none" id="manyPreview">
                                <h6><span>Resumo (simulação)</span><span class="rh-pill muted" id="manyMonth">—</span></h6>
                                <div id="manyBody" class="rh-empty"><i class="bi bi-people"></i>Marque os funcionários para ver o cálculo.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold" id="btnSavePayroll"><i class="bi bi-check2"></i> Gravar folha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/js/rh.js?v=1.1"></script>
<script>
$(function () {
    const { esc, kz, money, monthLabel, currentMonth, post, get, toast, fail, report, confirm, debounce } = window.RH;
    const $month = $('#inputMesReferencia');
    const selected = new Set();
    let period = null;

    // Mês inicial: o da URL (?mes=) ou o actual
    const urlMonth = new URLSearchParams(location.search).get('mes');
    $month.val(/^\d{4}-\d{2}$/.test(urlMonth || '') ? urlMonth : currentMonth());

    const statusPill = s => `<span class="rh-pill ${esc(s)}">${esc(s === 'Pendente' ? 'Pendente' : s === 'Aprovado' ? 'Aprovada' : s === 'Pago' ? 'Paga' : s)}</span>`;

    const table = BootstrapTable.create({
        table: '#payrollTable',
        toolbar: '#payrollTableControls',
        searchInput: '#payrollSearch',
        pageSize: 25,
        pageSizes: [25, 50, 100],
        emptyText: 'Sem folhas neste mês. Use "Gerar folha do mês" para criar as folhas de todos os funcionários activos.',
        ajax: {
            url: 'rh/ajax/list_payroll.php',
            data: () => ({ mes: $month.val(), status: $('#filterStatus').val() })
        },
        dataSource: resp => { renderSummary(resp); return resp.data || []; },
        initialSort: { index: 1, direction: 'asc' },
        columns: [
            { data: 'id', sortable: false, searchable: false, render: (id) => `<input type="checkbox" class="row-check" value="${Number(id)}" ${selected.has(Number(id)) ? 'checked' : ''} aria-label="Selecionar">` },
            { data: 'employee_name', render: (v, r) => `<div class="who">${esc(v)}<small>${esc(r.position_name || '—')}${r.emailed_at ? ' · <i class="bi bi-envelope-check text-success" title="Recibo enviado por e-mail"></i>' : ''}</small></div>` },
            { data: 'reference_month', render: v => esc(monthLabel(v)) },
            { data: 'base_salary', render: v => `<span class="num d-block">${money(v)}</span>`, sortValue: r => Number(r.base_salary) },
            { data: 'gross_value', render: v => `<span class="num d-block">${money(v)}</span>`, sortValue: r => Number(r.gross_value) },
            { data: 'discounts_value', render: v => `<span class="num d-block text-danger">${money(v)}</span>`, sortValue: r => Number(r.discounts_value) },
            { data: 'net_salary', render: v => `<span class="num d-block fw-bold">${money(v)}</span>`, sortValue: r => Number(r.net_salary) },
            { data: 'status', render: statusPill },
            { data: null, sortable: false, searchable: false, render: (_, r) => `
                <div class="row-actions">
                    ${r.status !== 'Pago' && !(period && period.closed) ? `<button type="button" class="btn btn-sm btn-light border rh-edit" data-id="${r.id}" title="Editar"><i class="bi bi-pencil"></i></button>` : ''}
                    <button type="button" class="btn btn-sm btn-light border btn-receipt" data-id="${r.id}" title="Ver recibo"><i class="bi bi-file-earmark-pdf text-danger"></i></button>
                </div>` }
        ]
    });

    // o BootstrapTable limpa o cabeçalho das colunas sem ordenação: repõe a caixa "todas"
    $('#payrollTable thead th').first().html('<input type="checkbox" id="checkAll" aria-label="Selecionar todas">');

    function renderSummary(resp) {
        const t = resp.totals || {};
        period = resp.period || null;
        $('#kpiCount').text(t.count || 0);
        $('#kpiStates').html(`${t.pending || 0} pend. · ${t.approved || 0} aprov. · ${t.paid || 0} pagas`);
        $('#kpiGross').text(kz(t.gross));
        $('#kpiInss').text(kz((t.inss || 0) + (t.inss_employer || 0)));
        $('#kpiInssHint').text(`${money(t.inss)} trab. · ${money(t.inss_employer)} empresa`);
        $('#kpiIrt').text(kz(t.irt));
        $('#kpiCost').text(kz(t.employer_cost));
        $('#kpiNet').text(kz(t.net));
        if (period) {
            $('#periodLabel').text(period.label);
            $('#periodStatus').attr('class', `rh-pill ${period.closed ? 'closed' : 'open'}`).html(period.closed ? '<i class="bi bi-lock-fill"></i> Fechado' : 'Aberto');
            const missing = Math.max(0, period.active_employees - (t.count || 0));
            $('#periodCoverage').text(`${t.count || 0} de ${period.active_employees} funcionário(s) activo(s)${missing && !$('#filterStatus').val() ? ` · ${missing} sem folha` : ''}`);
            $('#btnClosePeriod').toggleClass('d-none', period.closed);
            $('#btnReopenPeriod').toggleClass('d-none', !period.closed);
            $('#btnGerarFolhaMes, #btnNewPayroll').prop('disabled', period.closed);
        }
        selected.clear();
        updateBulk();
    }

    function reload() {
        const url = new URL(location.href);
        url.searchParams.set('mes', $month.val());
        history.replaceState(history.state, '', url);
        table.reload();
    }

    $month.on('change', reload);
    $('#filterStatus').on('change', reload);
    $('#prevMonth, #nextMonth').on('click', function () {
        const [y, m] = ($month.val() || currentMonth()).split('-').map(Number);
        const d = new Date(y, m - 1 + (this.id === 'nextMonth' ? 1 : -1), 1);
        $month.val(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`).trigger('change');
    });

    // ---------- Seleção / acções em massa ----------
    function updateBulk() {
        $('#bulkCount').text(selected.size);
        $('#bulkBar').toggleClass('show', selected.size > 0);
        const visible = $('#payrollTable .row-check');
        $('#checkAll').prop('checked', visible.length > 0 && visible.toArray().every(el => el.checked));
    }
    $('#payrollTable').on('change', '.row-check', function () {
        this.checked ? selected.add(Number(this.value)) : selected.delete(Number(this.value));
        updateBulk();
    });
    $('#checkAll').on('change', function () {
        $('#payrollTable .row-check').each((_, el) => { el.checked = this.checked; this.checked ? selected.add(Number(el.value)) : selected.delete(Number(el.value)); });
        updateBulk();
    });
    $('#bulkClear').on('click', () => { selected.clear(); $('#payrollTable .row-check').prop('checked', false); updateBulk(); });

    $('[data-bulk]').on('click', async function () {
        const action = this.dataset.bulk;
        const ids = [...selected];
        if (!ids.length) return;
        try {
            if (action === 'email') {
                if (!await confirm('Enviar recibos por e-mail?', `Os recibos de ${ids.length} folha(s) aprovada(s) ou paga(s) são enviados em PDF para o e-mail de cada funcionário.`, 'Enviar')) return;
                Swal.fire({ title: 'A enviar…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                const resp = await post('rh/ajax/send_payslips.php', { ids });
                Swal.close(); report(resp); table.reload();
                return;
            }
            let data = { action, ids };
            if (action === 'pay') {
                const r = await Swal.fire({ title: 'Marcar como pago', html: 'Data de pagamento', input: 'date', inputValue: new Date().toISOString().slice(0, 10), showCancelButton: true, confirmButtonText: 'Marcar como pago', cancelButtonText: 'Cancelar' });
                if (!r.isConfirmed) return;
                data.payment_date = r.value;
            } else if (action === 'delete') {
                if (!await confirm('Eliminar folhas?', `${ids.length} folha(s) serão eliminadas. As folhas pagas não são eliminadas.`, 'Eliminar', 'warning')) return;
            } else if (action === 'recalc') {
                if (!await confirm('Recalcular folhas pendentes?', 'Recalcula faltas, horas extra, INSS, IRT e adiantamentos. Os valores escritos à mão (bónus, comissões, descontos, subsídios) mantêm-se.', 'Recalcular')) return;
            }
            const resp = await post('rh/ajax/payroll_action.php', data);
            report(resp); table.reload();
        } catch (e) { Swal.close(); fail(e); }
    });

    // ---------- Período ----------
    $('#btnGerarFolhaMes').on('click', async function () {
        const mes = $month.val();
        const r = await Swal.fire({
            title: `Gerar folha de ${monthLabel(mes)}?`,
            html: `<p class="mb-2">Cria a folha (pendente) de cada funcionário activo que ainda não tem folha neste mês, com faltas, horas extra, subsídios, INSS, IRT e adiantamentos calculados.</p>
                   <label class="form-check text-start d-inline-flex gap-2"><input type="checkbox" class="form-check-input" id="swRecalc"> Recalcular também as folhas pendentes existentes</label>`,
            icon: 'question', showCancelButton: true, confirmButtonText: 'Gerar', cancelButtonText: 'Cancelar',
            preConfirm: () => document.getElementById('swRecalc').checked
        });
        if (!r.isConfirmed) return;
        try { report(await post('rh/ajax/generate_monthly_payroll.php', { reference_month: mes, recalculate: r.value ? 1 : 0 })); table.reload(); }
        catch (e) { fail(e); }
    });

    async function closePeriod(force) {
        try {
            const resp = await post('rh/ajax/payroll_period.php', { action: 'close', reference_month: $month.val(), force: force ? 1 : 0 });
            toast(resp.message); table.reload();
        } catch (e) {
            if (!force && /não estão pagas/.test(String(e))) {
                if (await confirm('Fechar mesmo assim?', `${esc(e)}<br>Com o mês fechado ninguém pode alterar as folhas deste mês.`, 'Fechar mês', 'warning')) closePeriod(true);
            } else fail(e);
        }
    }
    $('#btnClosePeriod').on('click', async () => {
        if (await confirm(`Fechar ${monthLabel($month.val())}?`, 'Depois de fechado, as folhas deste mês ficam bloqueadas (não podem ser criadas, alteradas nem eliminadas) até o mês ser reaberto.', 'Fechar mês')) closePeriod(false);
    });
    $('#btnReopenPeriod').on('click', async () => {
        if (!await confirm(`Reabrir ${monthLabel($month.val())}?`, 'As folhas pendentes e aprovadas voltam a poder ser alteradas.', 'Reabrir')) return;
        try { toast((await post('rh/ajax/payroll_period.php', { action: 'reopen', reference_month: $month.val() })).message); table.reload(); } catch (e) { fail(e); }
    });

    // ---------- Exportações ----------
    $('[data-export]').on('click', function (e) {
        e.preventDefault();
        const mes = $month.val();
        if (!mes) return fail('Selecione o mês de referência.');
        window.open(`/rh/export/report.php?type=${this.dataset.export}&format=${this.dataset.format}&mes=${encodeURIComponent(mes)}`, '_blank');
    });

    // ---------- Recibo ----------
    $('#payrollTable').on('click', '.btn-receipt', function () {
        window.BXDocumentApi.openPdf(`/api/documents/payroll/${encodeURIComponent(this.dataset.id)}/pdf`)
            .catch(error => fail(error.message));
    });

    // ---------- Modal (nova / editar) com simulação ----------
    const $modal = $('#modalPayroll');
    const $form = $('#formPayroll');
    const $emp = $('#employee_id_payroll');
    window.RH.employeeSelect($emp, { dropdownParent: $modal });

    function resetForm() {
        $form[0].reset();
        $('#payrollId').val('');
        $emp.empty().trigger('change');
        $('#pf_month').val($month.val());
        $('#pf_bonus, #pf_comm, #pf_other, #pf_disc, #pf_sales').val('0');
        $('#hintVac, #hintNat').text('');
        $('#slipBody').attr('class', 'rh-empty').html('<i class="bi bi-receipt"></i>Escolha o funcionário e o mês para ver o cálculo.');
        $('#slipMonth').text('—');
    }

    // ---------- Vários funcionários ----------
    let manyRows = [];
    const manyChecked = () => $('#pfManyList .pf-many-cb:checked').map((_, el) => el.value).get();
    function setMode(mode) {
        const many = mode === 'many';
        $('.single-only').toggleClass('d-none', many);
        $('.many-only').toggleClass('d-none', !many);
        $emp.prop('required', !many);
        $('#btnSavePayroll').html(many ? '<i class="bi bi-check2-all"></i> Gravar folhas' : '<i class="bi bi-check2"></i> Gravar folha');
        if (many) loadMany();
    }
    $('input[name=pf_mode]').on('change', function () { setMode(this.value); });
    async function loadMany() {
        const month = $('#pf_month').val();
        if (!month) return;
        $('#pfManyList').html('<div class="rh-empty">A carregar…</div>');
        try {
            manyRows = (await get('rh/ajax/list_employees_for_payroll.php', { mes: month })).data || [];
            renderMany();
        } catch (e) { $('#pfManyList').html(`<div class="text-danger small p-2">${esc(e)}</div>`); }
    }
    function renderMany() {
        const q = ($('#pfManySearch').val() || '').toLowerCase();
        const prev = new Set(manyChecked());
        $('#pfManyList').html(manyRows.length ? manyRows.map(r => {
            const hidden = q && !`${r.name} ${r.position || ''} ${r.department || ''}`.toLowerCase().includes(q);
            return `<label class="pf-many-item ${r.already_issued ? 'issued' : ''} ${hidden ? 'd-none' : ''}">
                <input type="checkbox" class="form-check-input pf-many-cb" value="${r.id}" ${r.already_issued ? 'disabled' : ''} ${prev.has(String(r.id)) && !r.already_issued ? 'checked' : ''}>
                <span class="flex-grow-1"><strong>${esc(r.name)}</strong><small>${esc([r.position, r.department].filter(Boolean).join(' · ') || 'Sem cargo')}</small></span>
                <span class="text-end small">${r.already_issued ? `<span class="rh-pill ${esc(r.existing_status)}">já tem folha · ${esc(r.existing_status)}</span>` : `${money(r.salary_base)}`}</span>
            </label>`;
        }).join('') : '<div class="rh-empty">Sem funcionários activos neste mês.</div>');
        syncMany();
    }
    function syncMany() {
        const avail = $('#pfManyList .pf-many-cb:not(:disabled)'), on = avail.filter(':checked');
        $('#pfManyCount').text(on.length);
        $('#pfManyAll').prop('checked', avail.length > 0 && on.length === avail.length).prop('indeterminate', on.length > 0 && on.length < avail.length);
        previewMany();
    }
    $('#pfManyList').on('change', '.pf-many-cb', syncMany);
    $('#pfManyAll').on('change', function () { $('#pfManyList .pf-many-cb:not(:disabled)').filter((_, el) => !el.closest('.d-none')).prop('checked', this.checked); syncMany(); });
    $('#pfManySearch').on('input', debounce(renderMany, 200));
    $form.on('input', '.pfm-calc', debounce(() => previewMany(), 400));
    const manyData = () => ({ reference_month: $('#pf_month').val(), employee_ids: manyChecked(), bonuses: $('#pfm_bonus').val(), other_earnings: $('#pfm_other').val(), discounts: $('#pfm_disc').val() });
    let manyReq = 0;
    const previewMany = debounce(async () => {
        const d = manyData();
        $('#manyMonth').text(monthLabel(d.reference_month));
        if (!d.employee_ids.length) return $('#manyBody').attr('class', 'rh-empty').html('<i class="bi bi-people"></i>Marque os funcionários para ver o cálculo.');
        const req = ++manyReq;
        $('#manyPreview').addClass('loading');
        try {
            const r = await post('rh/ajax/calculate_payroll_batch.php', d);
            if (req !== manyReq) return;
            $('#manyBody').attr('class', '').html(`
                <div class="pf-many-sum">${r.items.map(i => `<div class="line"><span>${esc(i.employee_name)}</span><span>${money(i.net_salary)}</span></div>`).join('')}</div>
                <div class="line total"><span>Total bruto</span><span>${money(r.total_gross)}</span></div>
                <div class="line ded"><span>Total descontos</span><span>${money(r.total_discounts)}</span></div>
                <div class="net"><span>Líquido (${r.count})</span><span>${kz(r.total_net)}</span></div>
                <div class="meta">Custo para a empresa <strong>${kz(r.total_employer_cost)}</strong></div>
                ${r.errors.length ? `<div class="warn">${r.errors.map(w => `<div><i class="bi bi-exclamation-triangle"></i> ${esc(w)}</div>`).join('')}</div>` : ''}`);
        } catch (e) { if (req === manyReq) $('#manyBody').attr('class', 'text-danger small').text(e); }
        finally { if (req === manyReq) $('#manyPreview').removeClass('loading'); }
    }, 300);

    $('#btnNewPayroll').on('click', () => {
        resetForm();
        $('#pfModeGroup').removeClass('d-none');
        $('#pfModeOne').prop('checked', true); setMode('one');
        $('#pfm_bonus, #pfm_other, #pfm_disc, #pfManySearch').val('');
        $('#modalPayrollLabel').text('Nova folha');
        $('#pf_status').val('Pendente');
        $modal.modal('show');
    });

    $('#payrollTable').on('click', '.rh-edit', function () {
        const row = table.getRows().find(r => Number(r.id) === Number(this.dataset.id));
        if (!row) return;
        resetForm();
        $('#pfModeOne').prop('checked', true); setMode('one'); $('#pfModeGroup').addClass('d-none');
        $('#modalPayrollLabel').text(`Editar folha — ${row.employee_name}`);
        $('#payrollId').val(row.id);
        $emp.append(new Option(row.employee_name, row.employee_id, true, true)).trigger('change');
        $('#pf_month').val(row.reference_month);
        $('#pf_base').val(row.base_salary);
        $('#pf_food').val(row.food_allowance);
        $('#pf_transport').val(row.transport_allowance);
        $('#pf_vac').val(Number(row.vacation_subsidy_pct));
        $('#pf_nat').val(Number(row.thirteenth_subsidy_pct));
        $('#pf_ot').val(Number(row.overtime_hours || 0) ? row.overtime_hours : '');
        $('#pf_abs').val(row.absence_days !== undefined ? Number(row.absence_days) : '');
        $('#pf_bonus').val(row.bonuses);
        $('#pf_comm').val(row.commissions);
        $('#pf_other').val(row.other_earnings || 0);
        $('#pf_disc').val(row.discounts);
        $('#pf_sales').val(row.sales);
        $('#pf_status').val(row.status);
        $('#pf_paydate').val(row.payment_date || '');
        $('#pf_notes').val(row.notes || '');
        $modal.modal('show');
        preview();
    });

    // Ao escolher funcionário/mês: limpa os valores automáticos para virem do cargo/ponto
    $emp.on('select2:select', () => { $('#pf_base, #pf_food, #pf_transport, #pf_vac, #pf_nat, #pf_ot, #pf_abs').val(''); preview(true); });
    $('#pf_month').on('change', () => { if ($('#pfModeMany').is(':checked')) return loadMany(); if (!$('#payrollId').val()) $('#pf_vac, #pf_nat, #pf_ot, #pf_abs').val(''); preview(true); });
    $form.on('input', '.rh-calc', debounce(() => preview(false), 400));
    $('#pf_status').on('change', function () { if (this.value === 'Pago' && !$('#pf_paydate').val()) $('#pf_paydate').val(new Date().toISOString().slice(0, 10)); });

    let previewReq = 0;
    async function preview(fillDefaults) {
        const employee = $emp.val();
        const month = $('#pf_month').val();
        if (!employee || !month) return;
        const req = ++previewReq;
        $('#slipPreview').addClass('loading');
        try {
            const data = Object.fromEntries(new FormData($form[0]).entries());
            const resp = await get('rh/ajax/payroll_preview.php', data);
            if (req !== previewReq) return;
            const c = resp.calc;
            if (fillDefaults) {
                // mostra nos campos os valores automáticos usados no cálculo
                $('#pf_base').val(c.base_salary); $('#pf_food').val(c.food_allowance); $('#pf_transport').val(c.transport_allowance);
                $('#pf_vac').val(c.vacation_subsidy_pct); $('#pf_nat').val(c.thirteenth_subsidy_pct);
                $('#pf_ot').val(c.overtime_hours || ''); $('#pf_abs').val(c.absence_days || '');
            }
            $('#hintVac').text(c.suggestion.vacation_reason);
            $('#hintNat').text(c.suggestion.thirteenth_reason);
            renderSlip(c);
        } catch (e) {
            if (req === previewReq) $('#slipBody').attr('class', 'text-danger small').text(e);
        } finally {
            if (req === previewReq) $('#slipPreview').removeClass('loading');
        }
    }

    function renderSlip(c) {
        const line = (label, value, cls = '') => Number(value) ? `<div class="line ${cls}"><span>${esc(label)}</span><span>${money(value)}</span></div>` : '';
        const warn = [...(c.warnings || [])];
        if (c.existing) warn.unshift(`Este funcionário já tem folha neste mês (${c.existing.status}).`);
        if (c.period_closed) warn.unshift('Este mês está fechado — não é possível gravar.');
        $('#slipMonth').text(monthLabel(c.reference_month));
        $('#slipBody').attr('class', '').html(`
            ${line('Salário base', c.base_salary)}
            ${line('Subs. alimentação', c.food_allowance)}
            ${line('Subs. transporte', c.transport_allowance)}
            ${line(`Subs. férias (${c.vacation_subsidy_pct}%)`, c.vacation_subsidy)}
            ${line(`Subs. Natal (${c.thirteenth_subsidy_pct}%)`, c.thirteenth_subsidy)}
            ${line(`Horas extra (${c.overtime_hours} h)`, c.overtime_value)}
            ${line('Prémios / bónus', c.bonuses)}
            ${line('Comissões', c.commissions)}
            ${line('Outros abonos', c.other_earnings)}
            <div class="line total"><span>Total de abonos</span><span>${money(c.gross_salary)}</span></div>
            ${line(`Faltas (${c.absence_days} dia/s)`, c.absence_value, 'ded')}
            <div class="line ded"><span>INSS trabalhador</span><span>${money(c.inss_value)}</span></div>
            <div class="line ded"><span>IRT</span><span>${money(c.irt_value)}</span></div>
            ${(c.advances || []).map(a => line(a.description, a.amount, 'ded')).join('')}
            ${line('Outros descontos', c.manual_discounts, 'ded')}
            <div class="line total"><span>Total de descontos</span><span class="text-danger">${money(c.total_discounts)}</span></div>
            <div class="net"><span>Líquido</span><span>${kz(c.net_salary)}</span></div>
            <div class="meta">
                Base INSS ${money(c.inss_base)} · Matéria colectável IRT ${money(c.irt_base)} · Abonos isentos ${money(c.exempt_allowances)}<br>
                INSS patronal ${money(c.inss_employer_value)} · Custo para a empresa <strong>${kz(c.employer_cost)}</strong>
            </div>
            ${warn.length ? `<div class="warn">${warn.map(w => `<div><i class="bi bi-exclamation-triangle"></i> ${esc(w)}</div>`).join('')}</div>` : ''}
        `);
    }

    $form.on('submit', async function (e) {
        e.preventDefault();
        if ($('#pfModeMany').is(':checked')) {
            const d = manyData();
            if (!d.employee_ids.length) return fail('Marque pelo menos um funcionário.');
            if (!await confirm(`Gravar ${d.employee_ids.length} folha(s)?`, `${esc(monthLabel(d.reference_month))} · estado: <strong>${esc($('#pf_status').val())}</strong>`, 'Gravar folhas')) return;
            const $b = $('#btnSavePayroll').prop('disabled', true);
            try {
                const resp = await post('rh/ajax/issue_payroll_batch.php', { ...d, approved: 1, status: $('#pf_status').val(), payment_date: $('#pf_paydate').val(), notes: $('#pf_notes').val() });
                $modal.modal('hide'); report(resp); table.reload();
            } catch (err) { fail(err); }
            finally { $b.prop('disabled', false); }
            return;
        }
        if (!$emp.val() || !$('#pf_month').val()) return fail('Indique o funcionário e o mês de referência.');
        const $btn = $('#btnSavePayroll').prop('disabled', true);
        try {
            const resp = await post('rh/ajax/save_payroll.php', $form.serialize());
            $modal.modal('hide');
            toast(resp.message || 'Folha gravada.');
            table.reload();
        } catch (err) { fail(err); }
        finally { $btn.prop('disabled', false); }
    });
});
</script>

<?php require_once '../app/views/footer.php'; ?>
