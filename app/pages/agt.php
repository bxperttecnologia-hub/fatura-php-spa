<?php
require_once '../app/config/db.php';
require_once '../app/helpers/authentication.php';
require_once '../app/views/layout_creation.php';
?>

<style>
    #agtPage {
        --agt-ink: #172033;
        --agt-muted: #64748b;
        --agt-line: #e2e8f0;
        --agt-soft: #f8fafc;
        color: var(--agt-ink);
    }

    #agtPage .agt-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    #agtPage .agt-title {
        margin: 0;
        font-size: clamp(1.35rem, 2.2vw, 1.75rem);
        font-weight: 700;
        letter-spacing: -.025em;
    }

    #agtPage .agt-subtitle {
        margin: 5px 0 0;
        color: var(--agt-muted);
        font-size: .92rem;
        max-width: 720px;
    }

    #agtPage .agt-card {
        background: #fff;
        border: 1px solid var(--agt-line);
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
    }

    #agtPage .agt-card h2 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 4px;
    }

    #agtPage .agt-card .agt-hint {
        color: var(--agt-muted);
        font-size: .86rem;
        margin: 0 0 16px;
    }

    #agtPage .agt-env {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: .8rem;
        font-weight: 600;
    }

    #agtPage .agt-env.test { background: #fff7ed; color: #9a3412; }
    #agtPage .agt-env.production { background: #ecfdf5; color: #047857; }

    #agtPage .agt-key-status {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        background: var(--agt-soft);
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 16px;
    }

    #agtPage .agt-key-status span {
        display: block;
        color: var(--agt-muted);
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    #agtPage .agt-key-status strong {
        font-size: .95rem;
        word-break: break-all;
    }

    #agtPage .agt-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    #agtPage .agt-table th {
        color: var(--agt-muted);
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        white-space: nowrap;
    }

    #agtPage .agt-table td {
        vertical-align: middle;
        font-size: .9rem;
    }

    #agtPage .agt-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-weight: 600;
    }

    #agtPage .agt-empty {
        text-align: center;
        color: var(--agt-muted);
        padding: 28px 12px;
    }

    #agtPage .agt-types {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
        gap: 6px 12px;
        max-height: 280px;
        overflow-y: auto;
        border: 1px solid var(--agt-line);
        border-radius: 10px;
        padding: 12px;
    }
</style>

<main class="main-content">
    <div class="container-fluid mt-4" id="agtPage">
        <div class="agt-header">
            <div>
                <h1 class="agt-title">Faturação Eletrónica AGT</h1>
                <p class="agt-subtitle">
                    Carregue a chave privada da empresa (disponível no Portal do Contribuinte da AGT).
                    O BXpert cria logo as séries base e pode acrescentar outras quando precisar.
                </p>
            </div>
            <span class="agt-env" id="agtEnv">—</span>
        </div>

        <section class="agt-card">
            <h2>Chave privada da empresa</h2>
            <p class="agt-hint">
                A chave fica guardada cifrada e nunca volta a ser mostrada. Serve para assinar os pedidos à AGT
                em nome da empresa.
            </p>

            <div class="agt-key-status d-none" id="agtKeyStatus">
                <div><span>NIF</span><strong id="agtKeyNif">—</strong></div>
                <div><span>Estabelecimento</span><strong id="agtKeyEstablishment">—</strong></div>
                <div><span>Chave</span><strong id="agtKeyInfo">—</strong></div>
                <div><span>Atualizada em</span><strong id="agtKeyUpdated">—</strong></div>
            </div>

            <form id="agtKeyForm" class="row g-3" autocomplete="off">
                <div class="col-md-3">
                    <label class="form-label" for="agtTaxId">NIF da empresa</label>
                    <input type="text" class="form-control" id="agtTaxId" name="tax_id" maxlength="15" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="agtEstablishment">Estabelecimento</label>
                    <input type="text" class="form-control" id="agtEstablishment" name="establishment" value="SEDE" maxlength="200">
                    <div class="form-text">"SEDE" se a empresa só tem uma localização.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="agtKeyFile">Ficheiro da chave privada</label>
                    <input type="file" class="form-control" id="agtKeyFile" name="key" accept=".pem,.key,.txt">
                    <div class="form-text">
                        <a href="#" id="agtTogglePaste">Prefiro colar o texto da chave</a>
                    </div>
                </div>
                <div class="col-12 d-none" id="agtPasteWrap">
                    <label class="form-label" for="agtKeyText">Texto da chave privada</label>
                    <textarea class="form-control font-monospace" id="agtKeyText" name="key_text" rows="6"
                        placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary" id="agtKeySubmit">Guardar chave e criar séries</button>
                </div>
            </form>
        </section>

        <section class="agt-card">
            <div class="agt-toolbar">
                <div>
                    <h2>Séries de numeração</h2>
                    <p class="agt-hint mb-0">Séries atribuídas pela AGT. A série padrão de cada tipo é a usada na emissão.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary" id="agtSync" disabled>Sincronizar com a AGT</button>
                    <button type="button" class="btn btn-primary" id="agtNewSeries" disabled>Nova série</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table agt-table mb-0">
                    <thead>
                        <tr>
                            <th>Série</th>
                            <th>Tipo</th>
                            <th>Ano</th>
                            <th>Estabelecimento</th>
                            <th>Estado</th>
                            <th>Numeração autorizada</th>
                            <th class="text-end">Padrão</th>
                        </tr>
                    </thead>
                    <tbody id="agtSeriesBody">
                        <tr><td colspan="7" class="agt-empty">A carregar…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="agt-card d-none" id="agtOptionsCard">
            <h2>Opções de emissão</h2>
            <p class="agt-hint">
                As faturas FT e FR finalizadas passam a usar a série padrão e são enviadas automaticamente à AGT.
                Os artigos sem IVA levam o código de isenção abaixo (confirme o código correto com o contabilista).
            </p>
            <form id="agtOptionsForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="agtExemptionCode">Código de isenção de IVA</label>
                    <input type="text" class="form-control text-uppercase" id="agtExemptionCode" name="exemption_code"
                        maxlength="3" pattern="[Mm][0-9]{2}" placeholder="M00" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary" id="agtOptionsSubmit">Guardar</button>
                </div>
            </form>
        </section>

        <section class="agt-card d-none" id="agtDocsCard">
            <div class="agt-toolbar">
                <div>
                    <h2>Faturas enviadas à AGT</h2>
                    <p class="agt-hint mb-0">A AGT valida as faturas de forma diferida; o estado atualiza-se ao consultar.</p>
                </div>
                <button type="button" class="btn btn-outline-secondary" id="agtCheckPending">Atualizar estados</button>
            </div>
            <div class="table-responsive">
                <table class="table agt-table mb-0">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th class="text-end">Total</th>
                            <th>Estado na AGT</th>
                        </tr>
                    </thead>
                    <tbody id="agtDocsBody">
                        <tr><td colspan="5" class="agt-empty">A carregar…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="agtSeriesModal" tabindex="-1" aria-labelledby="agtSeriesModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" id="agtSeriesForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="agtSeriesModalTitle">Nova série na AGT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Tipos de documento</label>
                    <div class="agt-types mb-3" id="agtTypeList"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="agtSeriesYear">Ano</label>
                            <select class="form-select" id="agtSeriesYear" name="year"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="agtSeriesEstablishment">Estabelecimento</label>
                            <input type="text" class="form-control" id="agtSeriesEstablishment" name="establishment" maxlength="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="agtSeriesContingency">Regime</label>
                            <select class="form-select" id="agtSeriesContingency" name="contingency">
                                <option value="N">Normal</option>
                                <option value="C">Contingência (sem ligação à AGT)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="agtSeriesSubmit">Criar na AGT</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
(function () {
    const page = document.getElementById('agtPage');
    if (!page) return;

    const endpoint = '/agt/ajax/agt.php';
    const statusLabels = { A: 'Aberta', U: 'Em uso', F: 'Fechada' };
    let state = null;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function call(action, body) {
        const options = body
            ? { method: 'POST', body, headers: { 'X-CSRF-Token': csrf() }, credentials: 'same-origin' }
            : { credentials: 'same-origin' };
        const res = await fetch(`${endpoint}?action=${action}`, options);
        let data = {};
        try { data = await res.json(); } catch (_) { /* resposta não JSON */ }
        if (!res.ok || !data.success) {
            const error = new Error(data.message || 'Não foi possível comunicar com o servidor.');
            error.errors = data.errors || [];
            throw error;
        }
        return data;
    }

    function busy(button, on, text) {
        if (on) {
            button.dataset.label = button.innerHTML;
            button.disabled = true;
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${esc(text)}`;
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.label || button.innerHTML;
        }
    }

    function createdSummary(created) {
        if (!created || !created.length) return '';
        const types = state?.document_types || {};
        const rows = created.map((c) => c.success
            ? `<li><strong>${esc(c.document_type)}</strong> ${esc(types[c.document_type] || '')}: série <code>${esc(c.series_code)}</code></li>`
            : `<li class="text-danger"><strong>${esc(c.document_type)}</strong> ${esc(types[c.document_type] || '')}: ${esc(c.message)}</li>`);
        return `<ul class="text-start small mb-0">${rows.join('')}</ul>`;
    }

    function render(data) {
        state = data;
        const env = document.getElementById('agtEnv');
        env.className = `agt-env ${data.environment}`;
        env.textContent = data.environment === 'production' ? 'Produção' : 'Ambiente de testes (HML)';

        const settings = data.settings;
        document.getElementById('agtKeyStatus').classList.toggle('d-none', !settings);
        document.getElementById('agtKeySubmit').textContent = settings ? 'Substituir chave' : 'Guardar chave e criar séries';
        document.getElementById('agtTaxId').value = settings?.tax_id || data.company.nif || '';
        document.getElementById('agtEstablishment').value = settings?.establishment || 'SEDE';
        if (settings) {
            document.getElementById('agtKeyNif').textContent = settings.tax_id;
            document.getElementById('agtKeyEstablishment').textContent = settings.establishment;
            document.getElementById('agtKeyInfo').textContent = `RSA ${settings.key_bits} · ${settings.key_fingerprint}`;
            document.getElementById('agtKeyUpdated').textContent = new Date(settings.updated_at.replace(' ', 'T')).toLocaleString('pt-PT');
        }
        document.getElementById('agtSync').disabled = !settings;
        document.getElementById('agtNewSeries').disabled = !settings;
        document.getElementById('agtOptionsCard').classList.toggle('d-none', !settings);
        document.getElementById('agtDocsCard').classList.toggle('d-none', !settings);
        if (settings) {
            document.getElementById('agtExemptionCode').value = settings.exemption_code || 'M00';
            loadDocuments();
        }

        const body = document.getElementById('agtSeriesBody');
        if (!data.series.length) {
            body.innerHTML = `<tr><td colspan="7" class="agt-empty">${settings
                ? 'Ainda sem séries. Use "Nova série" ou "Sincronizar com a AGT".'
                : 'Carregue a chave privada da empresa para criar as séries.'}</td></tr>`;
            return;
        }
        body.innerHTML = data.series.map((s) => `
            <tr>
                <td><span class="agt-code">${esc(s.series_code)}</span>${s.contingency === 'C' ? ' <span class="badge text-bg-warning">Contingência</span>' : ''}</td>
                <td>${esc(s.document_type)} <span class="text-muted">${esc(s.document_label)}</span></td>
                <td>${esc(s.series_year)}</td>
                <td>${esc(s.establishment)}</td>
                <td>${esc(statusLabels[s.status] || s.status || '—')}</td>
                <td>${s.first_document_no ? `${esc(s.first_document_no)} – ${esc(s.last_document_no)}` : '—'}</td>
                <td class="text-end">${s.is_default
                    ? '<span class="badge text-bg-primary">Padrão</span>'
                    : `<button type="button" class="btn btn-sm btn-outline-primary" data-agt-default="${s.id}">Usar como padrão</button>`}</td>
            </tr>`).join('');
    }

    const docBadges = { sent: 'text-bg-warning', valid: 'text-bg-success', invalid: 'text-bg-danger', error: 'text-bg-danger' };
    const money = (value) => Number(value || 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function loadDocuments() {
        const body = document.getElementById('agtDocsBody');
        try {
            const { documents } = await call('documents');
            body.innerHTML = documents.length ? documents.map((d) => `
                <tr>
                    <td><a href="/invoices/view?id=${d.id}" data-spa class="agt-code">${esc(d.document_no)}</a></td>
                    <td>${esc(d.customer || '—')}</td>
                    <td>${esc(d.issue_date || '')}</td>
                    <td class="text-end">${money(d.final_total)}</td>
                    <td>
                        <span class="badge ${docBadges[d.status] || 'text-bg-secondary'}">${esc(d.label)}</span>
                        ${(d.errors || []).length ? `<div class="small text-danger mt-1">${d.errors.map(esc).join('<br>')}</div>` : ''}
                    </td>
                </tr>`).join('')
                : '<tr><td colspan="5" class="agt-empty">Ainda nenhuma fatura enviada. Finalize uma fatura (FT ou FR) para a enviar.</td></tr>';
        } catch (e) {
            body.innerHTML = `<tr><td colspan="5" class="agt-empty text-danger">${esc(e.message)}</td></tr>`;
        }
    }

    document.getElementById('agtCheckPending').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        busy(button, true, 'A consultar…');
        try {
            await call('check_pending', new FormData());
            await loadDocuments();
        } catch (e) {
            Swal.fire('Consulta falhou', e.message, 'error');
        } finally {
            busy(button, false);
        }
    });

    document.getElementById('agtOptionsForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = document.getElementById('agtOptionsSubmit');
        busy(button, true, 'A guardar…');
        try {
            const data = await call('save_options', new FormData(event.currentTarget));
            render(data);
            Swal.fire({ icon: 'success', title: data.message, timer: 1800, showConfirmButton: false });
        } catch (e) {
            Swal.fire('Erro', e.message, 'error');
        } finally {
            busy(button, false);
        }
    });

    async function load() {
        try {
            render(await call('status'));
        } catch (e) {
            document.getElementById('agtSeriesBody').innerHTML = `<tr><td colspan="7" class="agt-empty text-danger">${esc(e.message)}</td></tr>`;
        }
    }

    document.getElementById('agtTogglePaste').addEventListener('click', (event) => {
        event.preventDefault();
        document.getElementById('agtPasteWrap').classList.toggle('d-none');
    });

    document.getElementById('agtKeyForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const fd = new FormData(form);
        const hasFile = document.getElementById('agtKeyFile').files.length > 0;
        if (!hasFile && !String(fd.get('key_text') || '').trim()) {
            Swal.fire('Chave em falta', 'Escolha o ficheiro da chave privada ou cole o texto da chave.', 'warning');
            return;
        }
        if (!hasFile) fd.delete('key');
        const button = document.getElementById('agtKeySubmit');
        busy(button, true, 'A validar com a AGT…');
        try {
            const data = await call('save_key', fd);
            form.reset();
            render(data);
            const failed = (data.created || []).some((c) => !c.success);
            Swal.fire({
                icon: failed ? 'warning' : 'success',
                title: data.message,
                html: createdSummary(data.created) || 'As séries base já existiam na AGT.',
            });
        } catch (e) {
            const details = (e.errors || []).map((m) => `<li>${esc(m)}</li>`).join('');
            Swal.fire({
                icon: 'error',
                title: 'Chave não aceite',
                html: `${esc(e.message)}${details ? `<ul class="text-start small mt-3 mb-0">${details}</ul>` : ''}`,
            });
        } finally {
            busy(button, false);
        }
    });

    document.getElementById('agtSync').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        busy(button, true, 'A sincronizar…');
        try {
            const data = await call('sync', new FormData());
            render(data);
            Swal.fire({ icon: 'success', title: data.message, timer: 2200, showConfirmButton: false });
        } catch (e) {
            Swal.fire('Sincronização falhou', e.message, 'error');
        } finally {
            busy(button, false);
        }
    });

    const modalEl = document.getElementById('agtSeriesModal');
    document.getElementById('agtNewSeries').addEventListener('click', () => {
        const used = new Set(state.series.filter((s) => s.series_year === state.year && s.status !== 'F').map((s) => s.document_type));
        document.getElementById('agtTypeList').innerHTML = Object.entries(state.document_types).map(([code, label]) => `
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="types[]" value="${code}" id="agtType${code}">
                <label class="form-check-label" for="agtType${code}"><strong>${code}</strong> ${esc(label)}${used.has(code) ? ' <span class="text-muted small">(já tem série)</span>' : ''}</label>
            </div>`).join('');
        document.getElementById('agtSeriesYear').innerHTML = state.years.map((y) => `<option value="${y}">${y}</option>`).join('');
        document.getElementById('agtSeriesEstablishment').value = state.settings?.establishment || 'SEDE';
        document.getElementById('agtSeriesContingency').value = 'N';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.getElementById('agtSeriesForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const fd = new FormData(event.currentTarget);
        if (!fd.getAll('types[]').length) {
            Swal.fire('Tipo em falta', 'Escolha pelo menos um tipo de documento.', 'warning');
            return;
        }
        const button = document.getElementById('agtSeriesSubmit');
        busy(button, true, 'A pedir à AGT…');
        try {
            const data = await call('create_series', fd);
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            render(data);
            const failed = data.created.some((c) => !c.success);
            Swal.fire({ icon: failed ? 'warning' : 'success', title: data.message, html: createdSummary(data.created) });
        } catch (e) {
            Swal.fire('Não foi possível criar', e.message, 'error');
        } finally {
            busy(button, false);
        }
    });

    document.getElementById('agtSeriesBody').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-agt-default]');
        if (!button) return;
        const fd = new FormData();
        fd.append('id', button.dataset.agtDefault);
        busy(button, true, '');
        try {
            render(await call('set_default', fd));
        } catch (e) {
            busy(button, false);
            Swal.fire('Erro', e.message, 'error');
        }
    });

    load();
})();
</script>

<?php require_once '../app/views/footer.php'; ?>
