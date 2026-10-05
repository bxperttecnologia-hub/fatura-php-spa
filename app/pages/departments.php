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
    /* ===== TABELA ESTILO (mesmo padrão de positions.php) ===== */
    #departmentsTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    #departmentsTable thead th {
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

    #departmentsTable thead th:last-child {
        border-right: none;
    }

    #departmentsTable tbody tr td {
        border-right: 1px solid #e5e7eb57;
    }

    #departmentsTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }

    #departmentsTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }
</style>

<main class="main-content">
<div class="container-fluid mt-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Departamentos</h4>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDepartment" id="btnNewDepartment">
            <i class="bi bi-plus-lg"></i> Novo Departamento
        </button>
    </div>

    <div class="table-responsive">
        <table id="departmentsTable" class="table align-middle" style="width:100%">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Departamento-pai</th>
                    <th>Funcionários</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
</main>

<!-- MODAL -->
<div class="modal fade" id="modalDepartment" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formDepartment">
                <div class="modal-header">
                    <h5 class="modal-title">Departamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nome do Departamento</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Departamento-pai (opcional)</label>
                        <select name="parent_department_id" class="form-control" id="selectParentDepartment">
                            <option value="">— Nenhum (topo da hierarquia) —</option>
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

        function loadParentOptions(excludeId) {
            $.getJSON('rh/ajax/list_departments.php', function(resp) {
                const select = $('#selectParentDepartment');
                select.find('option:not(:first)').remove();
                (resp.data || []).forEach(dep => {
                    if (excludeId && String(dep.id) === String(excludeId)) return;
                    select.append(`<option value="${dep.id}">${dep.name}</option>`);
                });
            });
        }

        const table = $('#departmentsTable').DataTable({
            ajax: 'rh/ajax/list_departments.php',
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json'
            },
            columns: [{
                    data: 'name'
                },
                {
                    data: 'parent_name',
                    render: data => data || '<span class="text-muted">—</span>'
                },
                {
                    data: 'employee_count',
                    render: data => `<span class="badge bg-light text-dark">${data || 0}</span>`
                },
                {
                    data: null,
                    render: row => `
                        <button class='btn btn-sm text-warning editDepartment'
                            data-id='${row.id}'
                            data-name='${row.name}'
                            data-parent='${row.parent_department_id || ''}'
                        ><i class="bi bi-pencil"></i></button>
                        <button class='btn btn-sm text-danger deleteDepartment'
                            data-id='${row.id}'
                            data-name='${row.name}'
                        ><i class="bi bi-trash"></i></button>
                    `
                }
            ]
        });

        $('#btnNewDepartment').on('click', function() {
            $('#formDepartment')[0].reset();
            $('#editDepartmentId').remove();
            loadParentOptions(null);
        });

        $('#formDepartment').on('submit', function(e) {
            e.preventDefault();
            $.post('rh/ajax/save_department.php', $(this).serialize())
                .done(function(resp) {
                    if (resp.success) {
                        $('#modalDepartment').modal('hide');
                        table.ajax.reload();
                        Swal.fire('Sucesso', 'Departamento salvo com sucesso!', 'success');
                        $('#editDepartmentId').remove();
                    } else {
                        Swal.fire('Erro', resp.message || 'Não foi possível salvar.', 'error');
                    }
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível salvar o departamento.', 'error');
                });
        });

        $('#departmentsTable').on('click', '.editDepartment', function() {
            const btn = $(this);
            loadParentOptions(btn.data('id'));
            $('#formDepartment input[name=name]').val(btn.data('name'));
            setTimeout(() => {
                $('#selectParentDepartment').val(btn.data('parent') || '');
            }, 300); // aguarda o loadParentOptions popular o select
            $('#formDepartment').append(`<input type="hidden" name="id" value="${btn.data('id')}" id="editDepartmentId">`);
            $('#modalDepartment').modal('show');
        });

        $('#departmentsTable').on('click', '.deleteDepartment', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            Swal.fire({
                title: 'Eliminar departamento?',
                text: `Tem certeza que deseja eliminar "${name}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (!result.isConfirmed) return;
                $.post('rh/ajax/delete_department.php', { id }, function(resp) {
                    if (resp.success) {
                        table.ajax.reload();
                        Swal.fire('Ok', 'Departamento eliminado.', 'success');
                    } else {
                        Swal.fire('Erro', resp.message || 'Não foi possível eliminar.', 'error');
                    }
                }, 'json');
            });
        });
    });
</script>

<?php require_once '../app/views/footer.php'; ?>