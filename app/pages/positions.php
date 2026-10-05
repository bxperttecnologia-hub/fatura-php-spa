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
    /* ===== TABELA ESTILO ===== */
    #positionsTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    /* HEADER */
    #positionsTable thead th {
        border: none;
        font-size: 12px;
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 12px 16px;
        text-align: left;
        border-right: 1px solid #e5e7eb57;
    }

    #positionsTable thead th:last-child {
        border-right: none;
    }

    #positionsTable tbody tr td {
        border-right: 1px solid #e5e7eb57;
    }

    /* ROW */
    #positionsTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }


    /* HOVER PRO */
    #positionsTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    /* CELLS */
    #positionsTable tbody td {
        border: none;
        padding: 18px 16px;
        vertical-align: middle;
        font-size: 0.95rem;
        background: #fff !important;
        text-align: left !important;
    }

    #positionsTable thead td {
        background: #111 !important;
        display: none;
        max-width: 80px !important;
    }

    /* BORDAS ARREDONDADAS */
    #positionsTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: #fff !important;
    }

    #positionsTable tbody th {
        text-align: left !important;
    }

    #positionsTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    /* ===== NOME (PRINCIPAL) ===== */
    #positionsTable tbody td:first-child {
        font-weight: 600;
        color: #111;
    }

    /* SUBINFO */
    #positionsTable tbody td small {
        display: block;
        color: #6b7280;
    }
</style>

<main class="main-content">
    <div class="container-fluid mt-5">
        <div class="row">
            <div class="col-12">
                <div>
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 fw-bold mt-5">Cargos e Salários</h2>
                        <button class="btn btn-primary d-flex align-items-center justify-items-center align-content-center" data-bs-toggle="modal" data-bs-target="#modalPosition">
                            <i class="material-icons-round">add</i>
                            Adicionar Cargo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="positionsTable" class="table w-100">
                                <thead>
                                    <tr>
                                        <th>Nome do Cargo</th>
                                        <th>Salário Sugerido</th>
                                        <th>Subs. Alimentação</th>
                                        <th>Subs. Transporte</th>
                                        <th>Subs. Férias</th>
                                        <th>Subs. 13º</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Cargo -->
<div class="modal fade" id="modalPosition" tabindex="-1" aria-labelledby="modalPositionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formPosition">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPositionLabel">Cadastrar Cargo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nome do Cargo</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Departamento (opcional)</label>
                        <select name="department_id" class="form-control" id="selectPositionDepartment">
                            <option value="">— Sem departamento —</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Salário Sugerido</label>
                        <input type="number" step="0.01" name="suggested_salary" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label>Subsídio de alimentação</label>
                        <input type="number" step="0.01" name="food_allowance" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <label>Subsídio de transporte</label>
                        <input type="number" step="0.01" name="transport_allowance" class="form-control" value="0">
                    </div>

                    <div class="mb-3">
                        <label>Subsídio de férias</label>
                        <select name="vacation_subsidy_pct" class="form-control">
                            <option value="0">0%</option>
                            <option value="50">50%</option>
                            <option value="100">100%</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Subsídio de 13º</label>
                        <select name="thirteenth_subsidy_pct" class="form-control">
                            <option value="0">0%</option>
                            <option value="50">50%</option>
                            <option value="100">100%</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {

        // Fase 1: popula o select de Departamento no formulário de cargo
        $.getJSON('rh/ajax/list_departments.php', function(resp) {
            const select = $('#selectPositionDepartment');
            (resp.data || []).forEach(dep => {
                select.append(`<option value="${dep.id}">${dep.name}</option>`);
            });
        });

        const table = $('#positionsTable').DataTable({
            ajax: 'rh/ajax/list_positions.php',
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
            },
            columns: [{
                    data: 'name'
                },
                {
                    data: 'suggested_salary',
                    render: function(data) {
                        return `Kz ${parseFloat(data).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`;
                    }
                },
                {
                    data: 'food_allowance',
                    render: function(data) {
                        return `Kz ${parseFloat(data || 0).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`;
                    }
                },
                {
                    data: 'transport_allowance',
                    render: function(data) {
                        return `Kz ${parseFloat(data || 0).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`;
                    }
                },
                {
                    data: 'vacation_subsidy_pct',
                    render: function(data) {
                        return `${parseInt(data || 0)}%`;
                    }
                },
                {
                    data: 'thirteenth_subsidy_pct',
                    render: function(data) {
                        return `${parseInt(data || 0)}%`;
                    }
                },
                {
                    data: null,
                    render: function(row) {
                        return `
                          <button class='btn btn-sm text-warning editPosition'
                            data-id='${row.id}'
                            data-name='${row.name}'
                            data-salary='${row.suggested_salary}'
                            data-food_allowance='${row.food_allowance || 0}'
                            data-transport_allowance='${row.transport_allowance || 0}'
                            data-vacation_subsidy_pct='${row.vacation_subsidy_pct || 0}'
                            data-thirteenth_subsidy_pct='${row.thirteenth_subsidy_pct || 0}'
                            data-department_id='${row.department_id || ''}'
                          ><i class="bi bi-pencil"></i></button>
                          <button class='btn btn-sm text-danger deletePosition'
                            data-id='${row.id}'
                            data-name='${row.name}'
                          ><i class="bi bi-trash"></i></button>
                        `;
                    }
                }
            ]
        });

        $('#formPosition').on('submit', function(e) {
            e.preventDefault();
            $.post('rh/ajax/save_position.php', $(this).serialize())
                .done(function() {
                    $('#modalPosition').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Sucesso', 'Cargo salvo com sucesso!', 'success');
                    $('#editPositionId').remove();
                })
                .fail(function(xhr) {
                    Swal.fire('Erro', xhr.responseText || 'Não foi possível salvar o cargo.', 'error');
                });
        });

        $('#positionsTable').on('click', '.editPosition', function() {
            const btn = $(this);
            $('#formPosition input[name=name]').val(btn.data('name'));
            $('#formPosition input[name=suggested_salary]').val(btn.data('salary'));
            $('#formPosition input[name=food_allowance]').val(btn.data('food_allowance'));
            $('#formPosition input[name=transport_allowance]').val(btn.data('transport_allowance'));
            $('#formPosition select[name=vacation_subsidy_pct]').val(btn.data('vacation_subsidy_pct'));
            $('#formPosition select[name=thirteenth_subsidy_pct]').val(btn.data('thirteenth_subsidy_pct'));
            $('#formPosition select[name=department_id]').val(btn.data('department_id') || '');

            $('#formPosition').append(`<input type="hidden" name="id" value="${btn.data('id')}" id="editPositionId">`);
            $('#modalPosition').modal('show');
        });

        $('#positionsTable').on('click', '.deletePosition', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            Swal.fire({
                title: 'Eliminar cargo?',
                text: `Tem certeza que deseja eliminar o cargo "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.post('rh/ajax/delete_position.php', {
                    id
                }, function(resp) {
                    if (resp.success) {
                        table.ajax.reload();
                        Swal.fire('Ok', 'Cargo eliminado.', 'success');
                    } else {
                        Swal.fire('Erro', resp.message || 'Não foi possível eliminar.', 'error');
                    }
                }, 'json');
            });
        });
    });
</script>

<?php require_once '../app/views/footer.php'; ?>