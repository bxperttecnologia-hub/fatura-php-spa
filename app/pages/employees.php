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

<style>
    /* ===== TABELA — mesmo padrão de linhas soltas com sombra usado em
       positions.php/payroll.php, com o toque do layout de referência
       (avatar circular + nome, badges de status) ===== */
    #employeesTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    #employeesTable thead th {
        border: none;
        font-size: 12px;
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 12px 16px;
        text-align: left;
    }

    #employeesTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        transition: all 0.2s ease;
    }

    #employeesTable tbody tr:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
    }

    #employeesTable tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 0.9rem;
        background: #fff !important;
    }

    #employeesTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
    }

    #employeesTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    .emp-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
        background: #eef2ff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #4f46e5;
        margin-right: 10px;
        flex-shrink: 0;
    }

    .emp-name-cell {
        display: flex;
        align-items: center;
    }

    .emp-name-cell .emp-meta {
        font-weight: 600;
        color: #111827;
        line-height: 1.2;
    }

    .emp-name-cell .emp-meta small {
        display: block;
        font-weight: 400;
        color: #9ca3af;
    }

    .badge-status-ativo {
        background: #dcfce7;
        color: #16a34a;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
    }

    .badge-status-inativo {
        background: #fee2e2;
        color: #dc2626;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
    }

    /* ===== TOPO: busca + botão, inspirado no HumanHub ===== */
    .employees-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .employees-search {
        position: relative;
        max-width: 320px;
        width: 100%;
    }

    .employees-search input {
        border-radius: 10px;
        padding-left: 38px;
        border: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .employees-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    /* ===== MODAL — inspirado no Arion: coluna de foto + navegação vertical,
       conteúdo do lado direito em abas ===== */
    #modalEmployee .modal-dialog {
        max-width: 820px;
    }

    #modalEmployee .emp-modal-side {
        background: #f8fafc;
        border-right: 1px solid #eef0f3;
        padding: 28px 22px;
        width: 240px;
        flex-shrink: 0;
    }

    #modalEmployee .emp-photo-tile {
        position: relative;
        width: 120px;
        height: 120px;
        border-radius: 16px;
        margin: 0 auto 16px auto;
        background: #1f2937;
        overflow: hidden;
        cursor: pointer;
    }

    #modalEmployee .emp-photo-tile img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        opacity: 0.9;
    }

    #modalEmployee .emp-photo-tile .emp-photo-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 34px;
        font-weight: 700;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
    }

    #modalEmployee .emp-photo-tile .emp-photo-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        color: #fff;
        font-size: 11px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        opacity: 0;
        transition: opacity 0.15s ease;
    }

    #modalEmployee .emp-photo-tile:hover .emp-photo-overlay {
        opacity: 1;
    }

    #modalEmployee .emp-side-nav {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    #modalEmployee .emp-side-nav li {
        padding: 10px 12px;
        border-radius: 10px;
        font-size: 13px;
        color: #4b5563;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
    }

    #modalEmployee .emp-side-nav li.active {
        background: #eef2ff;
        color: #4f46e5;
        font-weight: 600;
    }

    #modalEmployee .emp-side-nav li.disabled {
        opacity: 0.4;
        pointer-events: none;
    }

    #modalEmployee .modal-body {
        display: flex;
        padding: 0;
    }

    #modalEmployee .emp-modal-main {
        flex: 1;
        padding: 26px 28px;
        max-height: 68vh;
        overflow-y: auto;
    }

    #modalEmployee .emp-tab-pane {
        display: none;
    }

    #modalEmployee .emp-tab-pane.active {
        display: block;
    }

    .eval-history-item, .termination-summary {
        border: 1px solid #eef0f3;
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 8px;
        font-size: 13px;
    }
</style>

<main class="main-content">
<div class="container-fluid mt-5">

    <div class="employees-toolbar">
        <div>
            <h2 class="mb-0 fw-bold">Funcionários</h2>
            <p class="text-muted mb-0">Gestão de funcionários da empresa</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="employees-search">
                <i class="bi bi-search"></i>
                <input type="text" id="inputSearchEmployee" class="form-control" placeholder="Pesquisar funcionário...">
            </div>
            <select id="filterDepartment" class="form-select" style="max-width:180px">
                <option value="">Todos os departamentos</option>
            </select>
            <select id="filterStatus" class="form-select" style="max-width:140px">
                <option value="">Todos os status</option>
                <option value="ativo">Ativo</option>
                <option value="inativo">Inativo</option>
            </select>
            <button class="btn btn-primary rounded-pill px-4" id="btnNewEmployee">
                <i class="bi bi-plus-lg"></i> Novo Funcionário
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table id="employeesTable" class="table align-middle" style="width:100%">
            <thead>
                <tr>
                    <th>Funcionário</th>
                    <th>Admitido em</th>
                    <th>Status</th>
                    <th>Cargo</th>
                    <th>Departamento</th>
                    <th>Salário</th>
                    <th>Total pago este ano</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
</main>

<!-- ===================== MODAL: FICHA DO FUNCIONÁRIO ===================== -->
<div class="modal fade" id="modalEmployee" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px; overflow:hidden;">
            <form id="formEmployee" enctype="multipart/form-data">
                <input type="hidden" name="id" id="empId">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="employeeModalTitle">Novo Funcionário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- COLUNA ESQUERDA: foto + navegação -->
                    <div class="emp-modal-side">
                        <div class="emp-photo-tile" id="empPhotoTile">
                            <div class="emp-photo-placeholder" id="empPhotoPlaceholder">?</div>
                            <img src="" id="empPhotoPreview" style="display:none">
                            <div class="emp-photo-overlay">
                                <i class="bi bi-camera"></i>
                                <span>Alterar foto</span>
                            </div>
                            <input type="file" name="photo" id="empPhotoInput" accept="image/png,image/jpeg,image/webp,image/gif" class="d-none">
                        </div>

                        <ul class="emp-side-nav">
                            <li class="active" data-tab="dados"><i class="bi bi-person"></i> Dados Pessoais</li>
                            <li data-tab="cargo"><i class="bi bi-briefcase"></i> Cargo &amp; Contrato</li>
                            <li data-tab="documentos"><i class="bi bi-file-earmark"></i> Documentos</li>
                            <li data-tab="avaliacoes" id="navAvaliacoes" class="disabled"><i class="bi bi-clipboard-check"></i> Avaliações</li>
                            <li data-tab="situacao" id="navSituacao" class="disabled"><i class="bi bi-shield-exclamation"></i> Situação</li>
                        </ul>
                    </div>

                    <!-- COLUNA DIREITA: conteúdo das abas -->
                    <div class="emp-modal-main">

                        <!-- ---------- DADOS PESSOAIS ---------- -->
                        <div class="emp-tab-pane active" data-pane="dados">
                            <div class="mb-3">
                                <label class="form-label">Nome completo *</label>
                                <input type="text" name="employee_name" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Tipo de documento</label>
                                    <select name="document_type" class="form-control">
                                        <option value="BI">BI</option>
                                        <option value="Passaporte">Passaporte</option>
                                        <option value="Outro">Outro</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Nº do documento</label>
                                    <input type="text" name="bi" class="form-control">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Data de nascimento</label>
                                    <input type="date" name="birth_date" class="form-control">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Estado civil</label>
                                    <select name="marital_status" class="form-control">
                                        <option value="">—</option>
                                        <option value="Solteiro(a)">Solteiro(a)</option>
                                        <option value="Casado(a)">Casado(a)</option>
                                        <option value="Divorciado(a)">Divorciado(a)</option>
                                        <option value="Viúvo(a)">Viúvo(a)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nível académico</label>
                                <select name="academic_level" class="form-control">
                                    <option value="">—</option>
                                    <option value="Ensino primário">Ensino primário</option>
                                    <option value="Ensino médio">Ensino médio</option>
                                    <option value="Técnico médio">Técnico médio</option>
                                    <option value="Superior">Superior</option>
                                    <option value="Pós-graduação">Pós-graduação</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label class="form-label">DDI</label>
                                    <input type="text" name="phone_ddi" class="form-control" placeholder="+244">
                                </div>
                                <div class="col-8 mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- ---------- CARGO & CONTRATO ---------- -->
                        <div class="emp-tab-pane" data-pane="cargo">
                            <div class="mb-3">
                                <label class="form-label">Cargo *</label>
                                <select name="position_id" id="selectEmployeePosition" class="form-control" required>
                                    <option value="">— Selecione —</option>
                                </select>
                                <div class="form-text">Não encontra o cargo? <a href="positions.php" target="_blank">Cria um novo</a>.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Departamento</label>
                                <select name="department_id" id="selectEmployeeDepartment" class="form-control">
                                    <option value="">— Sem departamento —</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Chefia direta</label>
                                <select name="manager_id" id="selectEmployeeManager" class="form-control">
                                    <option value="">— Sem chefia direta —</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Salário base (Kz) *</label>
                                <input type="number" step="0.01" name="salary" id="inputEmployeeSalary" class="form-control" required>
                                <div class="form-text text-warning d-none" id="salaryWarningHint">
                                    <i class="bi bi-exclamation-triangle"></i> <span></span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Tipo de contrato</label>
                                    <select name="contract_type" class="form-control">
                                        <option value="">—</option>
                                        <option value="período experimental">Período experimental</option>
                                        <option value="tempo determinado">A termo certo (tempo determinado)</option>
                                        <option value="efetivo">Efetivo (sem termo)</option>
                                        <option value="Prestação de Serviço">Prestação de Serviço</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Data de admissão</label>
                                    <input type="date" name="admission_date" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Fim do contrato (se a termo certo)</label>
                                <input type="date" name="contract_end_date" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">IBAN</label>
                                <input type="text" name="iban" id="inputEmployeeIban" class="form-control text-uppercase"
                                       placeholder="AO06 0006 0000 0000 0000 0000 0" maxlength="30" autocomplete="off">
                                <div class="form-text">Formato de Angola: AO + 23 dígitos (25 caracteres no total).</div>
                            </div>
                        </div>


                        <!-- ---------- DOCUMENTOS ---------- -->
                        <div class="emp-tab-pane" data-pane="documentos">
                            <div class="mb-3">
                                <label class="form-label">Documento 1 (ex: BI digitalizado)</label>
                                <input type="file" name="doc1" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                                <div class="form-text" id="doc1CurrentLink"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Documento 2 (ex: contrato assinado)</label>
                                <input type="file" name="doc2" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp">
                                <div class="form-text" id="doc2CurrentLink"></div>
                            </div>
                        </div>

                        <!-- ---------- AVALIAÇÕES ---------- -->
                        <div class="emp-tab-pane" data-pane="avaliacoes">
                            <div id="evalHistoryList"><p class="text-muted">Guarda o funcionário primeiro para ver o histórico.</p></div>
                            <a href="avaliacoes.php" target="_blank" class="btn btn-sm btn-outline-primary mt-2">Gerir avaliações</a>
                        </div>

                        <!-- ---------- SITUAÇÃO / RESCISÃO ---------- -->
                        <div class="emp-tab-pane" data-pane="situacao">
                            <div id="situacaoAtivoBlock">
                                <p class="text-muted">Funcionário ativo. Se saiu da empresa, regista o desligamento abaixo.</p>
                                <div class="mb-3">
                                    <label class="form-label">Tipo de cessação</label>
                                    <select id="selectTipoCessacao" class="form-control">
                                        <option value="">— Selecione —</option>
                                        <option value="periodo_experimental">Período experimental</option>
                                        <option value="termo_certo_nao_renovado">Contrato a termo certo não renovado</option>
                                        <option value="causas_objetivas">Despedimento por causas objetivas / coletivo</option>
                                        <option value="sem_justa_causa">Despedimento sem justa causa / rescisão com justa causa (trabalhador)</option>
                                        <option value="demissao_trabalhador">Demissão pelo trabalhador (sem justa causa)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Data de cessação</label>
                                    <input type="date" id="inputDataCessacao" class="form-control">
                                </div>
                                <div class="form-check mb-3" id="avisoPrevioDadoWrapper" style="display:none">
                                    <input type="checkbox" class="form-check-input" id="checkAvisoPrevioDado">
                                    <label class="form-check-label" for="checkAvisoPrevioDado">Aviso prévio de 30 dias foi dado a tempo</label>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSimularRescisao">Simular cálculo</button>
                                <div id="simulacaoRescisaoResult" class="mt-3"></div>
                                <hr>
                                <div class="mb-3">
                                    <label class="form-label">Indemnização final (ajustável)</label>
                                    <input type="number" step="0.01" id="inputIndemnizacaoFinal" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notas</label>
                                    <textarea id="inputNotasRescisao" class="form-control"></textarea>
                                </div>
                                <button type="button" class="btn btn-danger" id="btnDesligarFuncionario">
                                    <i class="bi bi-box-arrow-right"></i> Desligar Funcionário
                                </button>
                            </div>
                            <div id="situacaoInativoBlock" style="display:none">
                                <div class="termination-summary" id="terminationSummary"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        /* ===================== IBAN (formato Angola: AO + 23 dígitos = 25) ===================== */

        function formatIban(raw) {
            // Mantém só letras/dígitos, maiúsculas, no máximo 25 caracteres (AO + 23 dígitos)
            const clean = (raw || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 25);
            // Agrupa de 4 em 4 para leitura, como qualquer IBAN
            return clean.replace(/(.{4})/g, '$1 ').trim();
        }

        $('#inputEmployeeIban').on('input', function() {
            const cursorAtEnd = this.selectionStart === this.value.length;
            this.value = formatIban(this.value);
            if (cursorAtEnd) {
                this.setSelectionRange(this.value.length, this.value.length);
            }
        });

        /* ===================== SELECTS AUXILIARES ===================== */

        function loadPositionsSelect() {
            $.getJSON('rh/ajax/list_positions.php', function(resp) {
                const sel = $('#selectEmployeePosition');
                sel.find('option:not(:first)').remove();
                (resp.data || []).forEach(p => sel.append(`<option value="${p.id}">${p.name}</option>`));
            });
        }

        function loadDepartmentsSelects() {
            $.getJSON('rh/ajax/list_departments.php', function(resp) {
                const filterSel = $('#filterDepartment');
                const modalSel = $('#selectEmployeeDepartment');
                filterSel.find('option:not(:first)').remove();
                modalSel.find('option:not(:first)').remove();
                (resp.data || []).forEach(d => {
                    filterSel.append(`<option value="${d.id}">${d.name}</option>`);
                    modalSel.append(`<option value="${d.id}">${d.name}</option>`);
                });
            });
        }

        function loadManagersSelect(excludeId) {
            const url = 'rh/ajax/list_managers.php' + (excludeId ? `?exclude_id=${excludeId}` : '');
            $.getJSON(url, function(resp) {
                const sel = $('#selectEmployeeManager');
                sel.find('option:not(:first)').remove();
                (resp.data || []).forEach(e => sel.append(`<option value="${e.id}">${e.name}${e.position ? ' — ' + e.position : ''}</option>`));
            });
        }

        loadPositionsSelect();
        loadDepartmentsSelects();
        loadManagersSelect(null);

        /* ===================== TABELA ===================== */

        function initials(name) {
            return (name || '?').trim().split(' ').slice(0, 2).map(w => w.charAt(0).toUpperCase()).join('');
        }

        const table = $('#employeesTable').DataTable({
            ajax: {
                url: 'rh/ajax/list_employees.php',
                dataSrc: function(json) {
                    if (json.error) {
                        Swal.fire('Erro ao carregar funcionários', json.error, 'error');
                    }
                    return json.data || [];
                },
                error: function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro ao carregar funcionários', (resp && resp.error) || 'Não foi possível contactar o servidor.', 'error');
                }
            },
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json' },
            order: [],
            columns: [
                {
                    data: null,
                    render: row => `
                        <div class="emp-name-cell">
                            ${row.photo_url
                                ? `<img src="assets/img/employees/${row.photo_url}" class="emp-avatar">`
                                : `<span class="emp-avatar">${initials(row.name)}</span>`}
                            <div class="emp-meta">${row.name}<small>${row.email || row.bi || ''}</small></div>
                        </div>
                    `
                },
                { data: 'admission_date', render: d => d || '—' },
                {
                    data: 'status',
                    render: s => `<span class="badge-status-${s}">${s === 'ativo' ? 'Ativo' : 'Inativo'}</span>`
                },
                { data: 'position', render: d => d || '—' },
                { data: 'department_name', render: d => d || '—' },
                {
                    data: 'salary',
                    render: d => 'Kz ' + parseFloat(d || 0).toLocaleString('pt-AO', { minimumFractionDigits: 2 })
                },
                {
                    data: 'total_pago_ano',
                    render: d => 'Kz ' + parseFloat(d || 0).toLocaleString('pt-AO', { minimumFractionDigits: 2 })
                },
                {
                    data: null,
                    render: row => `
                        <button class="btn btn-sm text-warning btnEditEmployee" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm text-danger btnDeleteEmployee" data-id="${row.id}" data-name="${row.name}"><i class="bi bi-trash"></i></button>
                    `
                }
            ]
        });

        $('#inputSearchEmployee').on('keyup', function() { table.search(this.value).draw(); });
        $('#filterDepartment, #filterStatus').on('change', function() { table.draw(); });

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            const row = table.row(dataIndex).data();
            const dep = $('#filterDepartment').val();
            const status = $('#filterStatus').val();
            if (dep && String(row.department_id) !== String(dep)) return false;
            if (status && row.status !== status) return false;
            return true;
        });

        /* ===================== ABAS DO MODAL ===================== */

        $('.emp-side-nav').on('click', 'li:not(.disabled)', function() {
            const tab = $(this).data('tab');
            $('.emp-side-nav li').removeClass('active');
            $(this).addClass('active');
            $('.emp-tab-pane').removeClass('active');
            $(`.emp-tab-pane[data-pane="${tab}"]`).addClass('active');
        });

        function resetToFirstTab() {
            $('.emp-side-nav li').removeClass('active');
            $('.emp-side-nav li[data-tab="dados"]').addClass('active');
            $('.emp-tab-pane').removeClass('active');
            $('.emp-tab-pane[data-pane="dados"]').addClass('active');
        }

        /* ===================== FOTO ===================== */

        $('#empPhotoTile').on('click', () => $('#empPhotoInput').trigger('click'));
        $('#empPhotoInput').on('change', function() {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                $('#empPhotoPreview').attr('src', e.target.result).show();
                $('#empPhotoPlaceholder').hide();
            };
            reader.readAsDataURL(file);
        });

        /* ===================== NOVO FUNCIONÁRIO ===================== */

        $('#btnNewEmployee').on('click', function() {
            $('#formEmployee')[0].reset();
            $('#empId').val('');
            $('#employeeModalTitle').text('Novo Funcionário');
            $('#empPhotoPreview').hide().attr('src', '');
            $('#empPhotoPlaceholder').show().text('?');
            $('#salaryWarningHint').addClass('d-none');
            $('#navAvaliacoes, #navSituacao').addClass('disabled');
            $('#doc1CurrentLink, #doc2CurrentLink').empty();
            loadManagersSelect(null);
            resetToFirstTab();
            $('#modalEmployee').modal('show');
        });

        /* ===================== EDITAR FUNCIONÁRIO ===================== */

        $('#employeesTable').on('click', '.btnEditEmployee', function() {
            const id = $(this).data('id');
            const row = table.rows().data().toArray().find(r => String(r.id) === String(id));
            if (!row) return;

            $('#formEmployee')[0].reset();
            $('#empId').val(row.id);
            $('#employeeModalTitle').text(row.name);

            $('#formEmployee input[name=employee_name]').val(row.name);
            $('#formEmployee select[name=document_type]').val(row.document_type || 'BI');
            $('#formEmployee input[name=bi]').val(row.bi);
            $('#formEmployee input[name=birth_date]').val(row.birth_date);
            $('#formEmployee select[name=marital_status]').val(row.marital_status || '');
            $('#formEmployee select[name=academic_level]').val(row.academic_level || '');
            $('#formEmployee input[name=email]').val(row.email);
            $('#formEmployee input[name=phone_ddi]').val(row.phone_ddi);
            $('#formEmployee input[name=phone]').val(row.phone);

            loadManagersSelect(row.id);
            setTimeout(() => {
                $('#selectEmployeePosition').val(row.position_id || '');
                $('#selectEmployeeDepartment').val(row.department_id || '');
                $('#selectEmployeeManager').val(row.manager_id || '');
            }, 250);

            $('#inputEmployeeSalary').val(row.salary);
            $('#formEmployee select[name=contract_type]').val(row.contract_type || '');
            $('#formEmployee input[name=admission_date]').val(row.admission_date);
            $('#formEmployee input[name=contract_end_date]').val(row.contract_end_date);
            $('#formEmployee input[name=iban]').val(formatIban(row.iban || ''));
            $('#salaryWarningHint').addClass('d-none');

            if (row.photo_url) {
                $('#empPhotoPreview').attr('src', 'assets/img/employees/' + row.photo_url).show();
                $('#empPhotoPlaceholder').hide();
            } else {
                $('#empPhotoPreview').hide();
                $('#empPhotoPlaceholder').show().text((row.name || '?').charAt(0).toUpperCase());
            }

            $('#doc1CurrentLink').html(row.doc1_url ? `<a href="assets/docs/employees/${row.doc1_url}" target="_blank">Ver documento atual</a>` : '');
            $('#doc2CurrentLink').html(row.doc2_url ? `<a href="assets/docs/employees/${row.doc2_url}" target="_blank">Ver documento atual</a>` : '');

            $('#navAvaliacoes, #navSituacao').removeClass('disabled');

            // Histórico de avaliações
            $.getJSON('rh/ajax/get_employee_evaluation_history.php', { employee_id: row.id }, function(resp) {
                const list = $('#evalHistoryList').empty();
                const items = resp.data || [];
                if (items.length === 0) {
                    list.html('<p class="text-muted">Sem avaliações registadas.</p>');
                    return;
                }
                items.forEach(ev => {
                    list.append(`
                        <div class="eval-history-item">
                            <strong>${ev.cycle_name}</strong> (${ev.period_start} a ${ev.period_end})<br>
                            Modelo: ${ev.template_name} · Avaliador: ${ev.evaluator_name || '—'}<br>
                            Status: ${ev.status} ${ev.final_score !== null ? '· Nota final: ' + ev.final_score : ''}
                        </div>
                    `);
                });
            });

            // Situação / rescisão
            if (row.status === 'inativo') {
                $('#situacaoAtivoBlock').hide();
                $('#situacaoInativoBlock').show();
                $('#terminationSummary').html(`
                    <strong>Funcionário inativo.</strong><br>
                    Data de saída: ${row.end_date || '—'}<br>
                    Tipo de cessação: ${row.termination_type || '—'}<br>
                    Indemnização registada: ${row.termination_amount !== null && row.termination_amount !== undefined ? 'Kz ' + parseFloat(row.termination_amount).toLocaleString('pt-AO', {minimumFractionDigits:2}) : '—'}<br>
                    Notas: ${row.termination_notes || '—'}
                `);
            } else {
                $('#situacaoAtivoBlock').show();
                $('#situacaoInativoBlock').hide();
                $('#simulacaoRescisaoResult').empty();
            }

            resetToFirstTab();
            $('#modalEmployee').modal('show');
        });

        /* ===================== SALVAR ===================== */

        $('#formEmployee').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            $.ajax({
                url: 'rh/ajax/save_employee.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function(resp) {
                if (resp.success) {
                    $('#modalEmployee').modal('hide');
                    table.ajax.reload();
                    if (resp.warning) {
                        Swal.fire('Funcionário salvo', resp.warning, 'warning');
                    } else {
                        Swal.fire('Sucesso', 'Funcionário salvo com sucesso!', 'success');
                    }
                } else {
                    Swal.fire('Erro', resp.message || 'Não foi possível salvar.', 'error');
                }
            }).fail(function(xhr) {
                Swal.fire('Erro', xhr.responseText || 'Não foi possível salvar.', 'error');
            });
        });

        /* ===================== ELIMINAR ===================== */

        $('#employeesTable').on('click', '.btnDeleteEmployee', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            Swal.fire({
                title: `Eliminar ${name}?`,
                text: 'Isto só é possível se não houver folha, férias ou ponto associados.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, eliminar'
            }).then(result => {
                if (!result.isConfirmed) return;
                $.post('rh/ajax/delete_employee.php', { employee_id: id })
                    .done(function(resp) {
                        if (resp.success) {
                            table.ajax.reload();
                            Swal.fire('Ok', 'Funcionário eliminado.', 'success');
                        } else {
                            Swal.fire('Não foi possível eliminar', resp.error || resp.message || '', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        const resp = xhr.responseJSON;
                        Swal.fire('Não foi possível eliminar', (resp && (resp.error || resp.message)) || 'Erro ao eliminar.', 'error');
                    });
            });
        });

        /* ===================== ALERTA DE SALÁRIO MÍNIMO (visual, além do warning pós-save) ===================== */
        // O aviso definitivo vem do backend (save_employee.php) após gravar;
        // isto é só feedback imediato enquanto o utilizador digita.

        /* ===================== RESCISÃO ===================== */

        $('#selectTipoCessacao').on('change', function() {
            $('#avisoPrevioDadoWrapper').toggle($(this).val() === 'termo_certo_nao_renovado');
        });

        $('#btnSimularRescisao').on('click', function() {
            const employeeId = $('#empId').val();
            const tipo = $('#selectTipoCessacao').val();
            const dataFim = $('#inputDataCessacao').val();
            if (!employeeId || !tipo || !dataFim) {
                return Swal.fire('Atenção', 'Escolhe o tipo de cessação e a data.', 'warning');
            }
            $.getJSON('rh/ajax/simular_rescisao.php', {
                employee_id: employeeId,
                tipo_cessacao: tipo,
                data_fim: dataFim,
                aviso_previo_dado: $('#checkAvisoPrevioDado').is(':checked') ? 1 : 0
            }).done(function(resp) {
                $('#inputIndemnizacaoFinal').val(resp.indemnizacao);
                let avisosHtml = resp.avisos.map(a => `<li>${a}</li>`).join('');
                $('#simulacaoRescisaoResult').html(`
                    <div class="alert alert-info">
                        <strong>Indemnização estimada: Kz ${parseFloat(resp.indemnizacao).toLocaleString('pt-AO', {minimumFractionDigits:2})}</strong><br>
                        ${resp.detalhe}
                        <ul class="mb-0 mt-2 small">${avisosHtml}</ul>
                    </div>
                `);
            }).fail(function(xhr) {
                const resp = xhr.responseJSON;
                Swal.fire('Erro', (resp && resp.message) || 'Não foi possível simular o cálculo.', 'error');
            });
        });

        $('#btnDesligarFuncionario').on('click', function() {
            const employeeId = $('#empId').val();
            const tipo = $('#selectTipoCessacao').val();
            const dataFim = $('#inputDataCessacao').val();
            if (!employeeId || !tipo || !dataFim) {
                return Swal.fire('Atenção', 'Escolhe o tipo de cessação e a data antes de desligar.', 'warning');
            }
            Swal.fire({
                title: 'Confirmar desligamento?',
                text: 'Esta ação marca o funcionário como inativo. Confirma que já reviste o cálculo com o contabilista?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, desligar'
            }).then(result => {
                if (!result.isConfirmed) return;
                $.post('rh/ajax/desligar_funcionario.php', {
                    employee_id: employeeId,
                    tipo_cessacao: tipo,
                    data_fim: dataFim,
                    aviso_previo_dado: $('#checkAvisoPrevioDado').is(':checked') ? 1 : 0,
                    indemnizacao_final: $('#inputIndemnizacaoFinal').val(),
                    notas: $('#inputNotasRescisao').val()
                }).done(function(resp) {
                    if (resp.success) {
                        $('#modalEmployee').modal('hide');
                        table.ajax.reload();
                        Swal.fire('Ok', resp.message, 'success');
                    } else {
                        Swal.fire('Erro', resp.message, 'error');
                    }
                }).fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível desligar o funcionário.', 'error');
                });
            });
        });
    });
</script>

<?php require_once '../app/views/footer.php'; ?>
