<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    /* ===== TABELA ESTILO ===== */
    #pontoTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    /* HEADER */
    #pontoTable thead th {
        border: none;
        font-size: 12px;
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 12px 16px;
        text-align: left;
    }

    /* ROW */
    #pontoTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }

    /* HOVER PRO */
    #pontoTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    /* CELLS */
    #pontoTable tbody td {
        border: none;
        padding: 18px 16px;
        vertical-align: middle;
        text-align: left;
        font-size: 0.95rem;
        background: #fff !important;
    }

    /* BORDAS ARREDONDADAS */
    #pontoTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: #fff !important;
    }

    #pontoTable tbody th {
        text-align: right !important;
    }

    #pontoTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    /* ===== NOME (PRINCIPAL) ===== */
    #pontoTable tbody td:first-child {
        font-weight: 600;
        color: #111;
    }

    /* SUBINFO */
    #pontoTable tbody td small {
        display: block;
        color: #6b7280;
    }


    /* ===== MOBILE ===== */
    @media (max-width: 768px) {

        #pontoTable thead {
            display: none;
        }

        #pontoTable,
        #pontoTable tbody,
        #pontoTable tr,
        #pontoTable td {
            display: block;
            width: 100%;
        }

        #pontoTable tbody tr {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 14px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        #pontoTable tbody td {
            padding: 6px 0;
            text-align: left;
        }

        #pontoTable tbody td:first-child {
            font-size: 1.1rem;
            font-weight: 600;
        }

        #pontoTable tbody td[data-label="Ações"] {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 10px;
        }
    }

    .modal-header {
        background: linear-gradient(135deg, #005a87, #007abd);
        color: #fff;
    }

    .modal-header button.btn-close {
        color: #fff !important;
    }

    form label{
        font-weight: bold;
    }
</style>

<main class="main-content">
    <div class="container-fluid mt-5">
        <div class="row">
            <div class="col-12">
                <div class="mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 fw-bold">Registro de Ponto e Faltas</h2>
                        <button class="btn btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalPonto">
                            <i class="bi bi-person-plus"></i>
                            Novo Registro
                        </button>
                    </div>
                    <div class="card-body mt-4">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label>Mês</label>
                                <input type="month" id="filtroMes" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label>Funcionário</label>
                                <select id="filtroFuncionario" class="form-control"></select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table id="pontoTable" class="table w-100">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Justificativa</th>
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

<!-- Modal de Registro -->
<div class="modal fade" id="modalPonto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formPonto">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Registro de Ponto</h5>
                    <button style="color: #fff !important;" type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 bg-white p-3 gap-2 rounded border-0 mb-3">
                        <label class="mb-2">Funcionário</label><br>
                        <select name="employee_id" class="form-control" required></select>
                    </div>

                    <div class="col-12 bg-white p-3 gap-2 rounded border-0 d-flex mb-3">
                        <div class="mb-3 col-12 col-md-6">
                            <label class="mb-2">Data</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <div class="mb-3 col-12 col-md-6">
                            <label class="mb-2">Tipo</label>
                            <select name="type" class="form-control">
                                <option value="presença">Presença</option>
                                <option value="falta">Falta</option>
                                <option value="atestado">Atestado</option>
                                <option value="folga">Folga</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 bg-white p-3 gap-2 rounded border-0 mb-3">
                        <label class="mb-2">Justificativa (opcional)</label>
                        <textarea name="justification" class="form-control"></textarea>
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

<?php require_once '../app/views/footer.php'; ?>

<script>
    $(document).ready(function() {
        const table = $('#pontoTable').DataTable({
            ajax: {
                url: 'rh/ajax/list_attendance.php',
                data: function(d) {
                    d.mes = $('#filtroMes').val();
                    d.funcionario = $('#filtroFuncionario').val();
                }
            },
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
            },
            columns: [{
                    data: 'employee_name'
                },
                {
                    data: 'date'
                },
                {
                    data: 'type'
                },
                {
                    data: 'justification'
                },
                {
                    data: null,
                    render: row => `<button class='btn btn-sm text-danger deleteRegistro' data-id='${row.id}'><i class="bi bi-trash"></i></button>`
                }
            ]
        });

        $('#filtroMes, #filtroFuncionario').on('change', function() {
            table.ajax.reload();
        });

        $('select[name=employee_id]').select2({
            dropdownParent: $('#modalPonto'),
            width: '100%',
            ajax: {
                url: 'rh/ajax/search_employees.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    term: params.term
                }),
                processResults: data => ({
                    results: data
                })
            }
        });

        $('#filtroFuncionario').select2({
            width: '100%',
            ajax: {
                url: 'rh/ajax/search_employees.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    term: params.term
                }),
                processResults: data => ({
                    results: data
                })
            }
        });

        $('#formPonto').on('submit', function(e) {
            e.preventDefault();
            $.post('rh/ajax/save_attendance.php', $(this).serialize())
                .done(function() {
                    $('#modalPonto').modal('hide');
                    table.ajax.reload();
                    Swal.fire('Sucesso', 'Registro salvo com sucesso', 'success');
                })
                .fail(function(xhr) {
                    const resp = xhr.responseJSON;
                    Swal.fire('Erro', (resp && resp.message) || 'Não foi possível salvar o registo.', 'error');
                });
        });

        $('#pontoTable').on('click', '.deleteRegistro', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Tem certeza?',
                text: 'O registro será removido!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, excluir'
            }).then(result => {
                if (result.isConfirmed) {
                    $.post('rh/ajax/delete_attendance.php', {
                        id
                    }, function() {
                        table.ajax.reload();
                        Swal.fire('Excluído', 'Registro removido com sucesso', 'success');
                    });
                }
            });
        });
    });
</script>