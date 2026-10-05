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
    .criterion-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .score-card {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        background: #fff;
    }
</style>

<main class="main-content">
<div class="container-fluid mt-5">
    <h4 class="mb-3">Avaliação de Desempenho</h4>

    <ul class="nav nav-tabs mb-3" id="evalTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabModelos">Modelos</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabCiclos">Ciclos</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMinhas">Minhas Avaliações</a></li>
    </ul>

    <div class="tab-content">

        <!-- ===================== MODELOS ===================== -->
        <div class="tab-pane fade show active" id="tabModelos">
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-primary btn-sm" id="btnNewTemplate"><i class="bi bi-plus-lg"></i> Novo Modelo</button>
            </div>
            <div id="templatesList"></div>
        </div>

        <!-- ===================== CICLOS ===================== -->
        <div class="tab-pane fade" id="tabCiclos">
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCycle"><i class="bi bi-plus-lg"></i> Novo Ciclo</button>
            </div>
            <div class="table-responsive">
                <table id="cyclesTable" class="table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>Ciclo</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th>Progresso</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- ===================== MINHAS AVALIAÇÕES ===================== -->
        <div class="tab-pane fade" id="tabMinhas">
            <div class="table-responsive">
                <table id="myEvaluationsTable" class="table align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Ciclo</th>
                            <th>Modelo</th>
                            <th>Status</th>
                            <th>Nota final</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>

<!-- MODAL: novo/editar modelo -->
<div class="modal fade" id="modalTemplate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formTemplate">
                <div class="modal-header">
                    <h5 class="modal-title">Modelo de Avaliação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nome do modelo</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <label>Critérios (com peso)</label>
                    <div id="criteriaList"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnAddCriterion">
                        <i class="bi bi-plus"></i> Adicionar critério
                    </button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: novo ciclo -->
<div class="modal fade" id="modalCycle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCycle">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Ciclo de Avaliação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nome do ciclo</label>
                        <input type="text" name="name" class="form-control" placeholder="Ex: Avaliação Semestral 2026-2" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Início</label>
                            <input type="date" name="period_start" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label>Fim</label>
                            <input type="date" name="period_end" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Ciclo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: gerar avaliações para um ciclo -->
<div class="modal fade" id="modalGenerate" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formGenerate">
                <input type="hidden" name="cycle_id">
                <div class="modal-header">
                    <h5 class="modal-title">Gerar Avaliações para o Ciclo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Modelo de avaliação</label>
                        <select name="template_id" class="form-control" id="selectGenerateTemplate" required></select>
                    </div>
                    <div class="mb-3">
                        <label>Departamento (opcional)</label>
                        <select name="department_id" class="form-control" id="selectGenerateDepartment">
                            <option value="">— Todos os departamentos —</option>
                        </select>
                    </div>
                    <p class="text-muted small">
                        Gera uma avaliação pendente para cada funcionário ativo do departamento
                        escolhido (ou de todos), atribuída à chefia direta (organograma) como avaliadora.
                        Quem não tiver chefia direta definida fica de fora.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Gerar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: pontuar avaliação -->
<div class="modal fade" id="modalScore" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formScore">
                <input type="hidden" name="evaluation_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="scoreModalTitle">Avaliar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="scoreCriteriaBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-outline-primary" data-finalizar="0">Gravar rascunho</button>
                    <button type="submit" class="btn btn-primary" data-finalizar="1">Concluir avaliação</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        /* ===================== MODELOS ===================== */

        function criterionRowHtml(label = '', weight = 1, id = '') {
            return `
                <div class="criterion-row" data-id="${id}">
                    <input type="text" class="form-control crit-label" placeholder="Critério (ex: Pontualidade)" value="${label}" required>
                    <input type="number" step="0.1" min="0" class="form-control crit-weight" style="max-width:100px" placeholder="Peso" value="${weight}" required>
                    <button type="button" class="btn btn-sm text-danger btnRemoveCriterion"><i class="bi bi-x-lg"></i></button>
                </div>
            `;
        }

        $('#btnAddCriterion').on('click', () => $('#criteriaList').append(criterionRowHtml()));
        $('#criteriaList').on('click', '.btnRemoveCriterion', function() { $(this).closest('.criterion-row').remove(); });

        function loadTemplates() {
            $.getJSON('rh/ajax/list_evaluation_templates.php', function(resp) {
                const list = $('#templatesList').empty();
                (resp.data || []).forEach(tpl => {
                    const critHtml = tpl.criteria.map(c => `<li>${c.label} <span class="text-muted">(peso ${c.weight})</span></li>`).join('');
                    list.append(`
                        <div class="score-card" data-id="${tpl.id}" data-name="${tpl.name}" data-criteria='${JSON.stringify(tpl.criteria)}'>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${tpl.name}</h6>
                                    <ul class="mb-0 small text-muted">${critHtml}</ul>
                                </div>
                                <div>
                                    <button class="btn btn-sm text-warning btnEditTemplate"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-sm text-danger btnDeleteTemplate"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    `);
                });
                if ((resp.data || []).length === 0) {
                    list.html('<p class="text-muted">Nenhum modelo criado ainda.</p>');
                }
                // Popula o select de modelos no modal de geração de avaliações
                const sel = $('#selectGenerateTemplate').empty();
                (resp.data || []).forEach(tpl => sel.append(`<option value="${tpl.id}">${tpl.name}</option>`));
            });
        }

        $('#btnNewTemplate').on('click', function() {
            $('#formTemplate')[0].reset();
            $('#criteriaList').empty().append(criterionRowHtml());
            $('#formTemplate').removeData('editId');
            $('#modalTemplate').modal('show');
        });

        $('#templatesList').on('click', '.btnEditTemplate', function() {
            const card = $(this).closest('.score-card');
            const criteria = JSON.parse(card.attr('data-criteria').replace(/&quot;/g, '"'));
            $('#formTemplate input[name=name]').val(card.data('name'));
            $('#criteriaList').empty();
            criteria.forEach(c => $('#criteriaList').append(criterionRowHtml(c.label, c.weight, c.id)));
            $('#formTemplate').data('editId', card.data('id'));
            $('#modalTemplate').modal('show');
        });

        $('#templatesList').on('click', '.btnDeleteTemplate', function() {
            const card = $(this).closest('.score-card');
            Swal.fire({
                title: `Eliminar modelo "${card.data('name')}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, eliminar'
            }).then(result => {
                if (!result.isConfirmed) return;
                $.post('rh/ajax/delete_evaluation_template.php', { id: card.data('id') }, function(resp) {
                    if (resp.success) { loadTemplates(); Swal.fire('Ok', 'Modelo eliminado.', 'success'); }
                    else Swal.fire('Erro', resp.message, 'error');
                }, 'json');
            });
        });

        $('#formTemplate').on('submit', function(e) {
            e.preventDefault();
            const criteria = [];
            $('#criteriaList .criterion-row').each(function() {
                criteria.push({
                    id: $(this).data('id') || null,
                    label: $(this).find('.crit-label').val(),
                    weight: $(this).find('.crit-weight').val()
                });
            });
            const payload = {
                name: $('#formTemplate input[name=name]').val(),
                criteria: JSON.stringify(criteria),
                id: $('#formTemplate').data('editId') || ''
            };
            $.post('rh/ajax/save_evaluation_template.php', payload)
                .done(function(resp) {
                    if (resp.success) {
                        $('#modalTemplate').modal('hide');
                        loadTemplates();
                        Swal.fire('Sucesso', 'Modelo salvo.', 'success');
                    } else {
                        Swal.fire('Erro', resp.message, 'error');
                    }
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível salvar o modelo.', 'error');
                });
        });

        /* ===================== CICLOS ===================== */

        const cyclesTable = $('#cyclesTable').DataTable({
            ajax: 'rh/ajax/list_evaluation_cycles.php',
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json' },
            columns: [
                { data: 'name' },
                { data: null, render: row => `${row.period_start} a ${row.period_end}` },
                { data: 'status', render: s => `<span class="badge ${s === 'aberto' ? 'bg-success' : 'bg-secondary'}">${s}</span>` },
                { data: null, render: row => `${row.concluidas}/${row.total_evaluations} concluídas` },
                {
                    data: null,
                    render: row => `<button class="btn btn-sm btn-outline-primary btnGenerate" data-id="${row.id}">Gerar avaliações</button>`
                }
            ]
        });

        $('#formCycle').on('submit', function(e) {
            e.preventDefault();
            $.post('rh/ajax/save_evaluation_cycle.php', $(this).serialize())
                .done(function(resp) {
                    if (resp.success) {
                        $('#modalCycle').modal('hide');
                        cyclesTable.ajax.reload();
                        Swal.fire('Sucesso', 'Ciclo criado.', 'success');
                    } else {
                        Swal.fire('Erro', resp.message, 'error');
                    }
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível criar o ciclo.', 'error');
                });
        });

        $.getJSON('rh/ajax/list_departments.php', function(resp) {
            const sel = $('#selectGenerateDepartment');
            (resp.data || []).forEach(dep => sel.append(`<option value="${dep.id}">${dep.name}</option>`));
        });

        $('#cyclesTable').on('click', '.btnGenerate', function() {
            $('#formGenerate input[name=cycle_id]').val($(this).data('id'));
            $('#modalGenerate').modal('show');
        });

        $('#formGenerate').on('submit', function(e) {
            e.preventDefault();
            $.post('rh/ajax/generate_evaluations.php', $(this).serialize())
                .done(function(resp) {
                    if (resp.success) {
                        $('#modalGenerate').modal('hide');
                        cyclesTable.ajax.reload();
                        myEvaluationsTable.ajax.reload();
                        Swal.fire('Ok', `${resp.created} avaliação(ões) gerada(s). ${resp.skipped_no_manager} funcionário(s) sem chefia direta ficaram de fora.`, 'success');
                    } else {
                        Swal.fire('Erro', resp.message, 'error');
                    }
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível gerar as avaliações.', 'error');
                });
        });

        /* ===================== MINHAS AVALIAÇÕES ===================== */

        const myEvaluationsTable = $('#myEvaluationsTable').DataTable({
            ajax: 'rh/ajax/list_evaluations.php?minhas=1',
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json' },
            columns: [
                { data: 'employee_name' },
                { data: 'cycle_name' },
                { data: 'template_name' },
                { data: 'status' },
                { data: 'final_score', render: d => d !== null ? d : '—' },
                {
                    data: null,
                    render: row => row.status === 'concluida'
                        ? `<span class="text-muted">Concluída</span>`
                        : `<button class="btn btn-sm btn-primary btnScore" data-id="${row.id}">Avaliar</button>`
                }
            ]
        });

        $('#myEvaluationsTable').on('click', '.btnScore', function() {
            const id = $(this).data('id');
            $.getJSON('rh/ajax/get_evaluation.php', { id })
                .done(function(resp) {
                    const ev = resp.data;
                    $('#scoreModalTitle').text(`Avaliar ${ev.employee_name} — ${ev.position || ''}`);
                    $('#formScore input[name=evaluation_id]').val(ev.id);
                    const body = $('#scoreCriteriaBody').empty();
                    ev.criteria.forEach(c => {
                        body.append(`
                            <div class="mb-3" data-criterion-id="${c.id}">
                                <label>${c.label} <span class="text-muted">(peso ${c.weight})</span></label>
                                <input type="number" step="0.1" min="0" max="100" class="form-control crit-score" value="${c.score ?? ''}" required>
                                <textarea class="form-control mt-1 crit-comment" placeholder="Comentário (opcional)">${c.comment || ''}</textarea>
                            </div>
                        `);
                    });
                    $('#modalScore').modal('show');
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível carregar a avaliação.', 'error');
                });
        });

        $('#formScore').on('submit', function(e) {
            e.preventDefault();
            const finalizar = $(document.activeElement).data('finalizar') || 0;
            const answers = [];
            $('#scoreCriteriaBody [data-criterion-id]').each(function() {
                answers.push({
                    criterion_id: $(this).data('criterion-id'),
                    score: $(this).find('.crit-score').val(),
                    comment: $(this).find('.crit-comment').val()
                });
            });
            $.post('rh/ajax/save_evaluation_answers.php', {
                evaluation_id: $('#formScore input[name=evaluation_id]').val(),
                answers: JSON.stringify(answers),
                finalizar
            })
                .done(function(resp) {
                    if (resp.success) {
                        $('#modalScore').modal('hide');
                        myEvaluationsTable.ajax.reload();
                        Swal.fire('Sucesso', finalizar == 1 ? `Avaliação concluída — nota final: ${resp.final_score}` : 'Rascunho gravado.', 'success');
                    } else {
                        Swal.fire('Erro', resp.message, 'error');
                    }
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível salvar a avaliação.', 'error');
                });
        });

        loadTemplates();
    });
</script>

<?php require_once '../app/views/footer.php'; ?>