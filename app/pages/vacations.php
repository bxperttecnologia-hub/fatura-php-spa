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
    /* Estilos para transformar a tabela em cards no mobile */

    /* ===== TABELA ESTILO ===== */
    #vacationsTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    /* HEADER */
    #vacationsTable thead th {
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

    #vacationsTable thead th:last-child {
        border-right: none;
    }

    #vacationsTable tbody tr td {
        border-right: 1px solid #e5e7eb57;
    }

    /* ROW */
    #vacationsTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }


    /* HOVER PRO */
    #vacationsTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    /* CELLS */
    #vacationsTable tbody td {
        border: none;
        padding: 18px 16px;
        vertical-align: middle;
        font-size: 0.95rem;
        background: #fff !important;
        text-align: left !important;
    }

    #vacationsTable thead td {
        background: #111 !important;
        display: none;
        max-width: 80px !important;
    }

    /* BORDAS ARREDONDADAS */
    #vacationsTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: #fff !important;
    }

    #vacationsTable tbody th {
        text-align: left !important;
    }

    #vacationsTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    /* ===== NOME (PRINCIPAL) ===== */
    #vacationsTable tbody td:first-child {
        font-weight: 600;
        color: #111;
    }

    /* SUBINFO */
    #vacationsTable tbody td small {
        display: block;
        color: #6b7280;
    }
</style>

<main class="main-content">
    <div class="container-fluid mt-5">
        <div class="row">
            <div class="col-12">
                <div class="">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 fw-bold mt-4">Férias e Licenças</h2>
                        <button id="addVacationBtn" class="btn btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#modalVacation">
                            <i class="bi bi-calendar"></i>
                            Registrar Período
                        </button>
                    </div>
                    <div class="card-body mt-4">
                        <!-- Filtros -->
                        <div class="d-flex flex-wrap justify-content-between g-2 mb-3 align-items-end">
                            <div class="col-6 col-md-6 d-flex gap-2">
                                <div class="col-md-4">
                                    <label class="form-label mb-1">Mês de referência</label>
                                    <input type="month" id="filter_mes" class="form-control" value="<?= date('Y-m') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1">Funcionário</label>
                                    <select id="filter_funcionario" class="form-control"></select>
                                </div>
                            </div>
                            <div class="col-6 col-md-6 d-flex gap-2 justify-content-end">
                                <button id="btnFiltrarVac" class="btn btn-outline-primary rounded-pill d-flex gap-2">
                                    <i class="bi bi-filter"></i> Filtrar
                                </button>
                                <button id="btnLimparVac" class="btn btn-outline-secondary rounded-pill">Limpar</button>
                                <button id="btnExportVacPdf" class="btn btn-outline-danger rounded-pill">
                                    <i class="bi bi-file-pdf"></i> Exportar PDF
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="vacationsTable" class="table w-100">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Tipo</th>
                                        <th>Início</th>
                                        <th>Fim</th>
                                        <th>Status</th>
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

<!-- Modal Registro de Férias -->
<div class="modal fade" id="modalVacation" tabindex="-1" aria-labelledby="modalVacationLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formVacation">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVacationLabel">Registrar Período</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row p-3">
                        <div class="col-md-12 mb-3 card border-0 p-3">
                            <label class="fw-bold" style="margin-bottom: -10px;">Funcionário</label><br>
                            <select name="employee_id" id="employee_id" class="form-control"></select>
                        </div>

                        <div class="d-flex flex-wrap gap-2 bg-white border-0 p-3">
                            <div class="col-md-12">
                                <label class="fw-bold">Tipo</label>
                                <select name="type" class="form-control">
                                    <option value="Férias">Férias</option>
                                    <option value="Licença Médica">Licença Médica</option>
                                    <option value="Licença Maternidade">Licença Maternidade</option>
                                    <option value="Outros">Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Início</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-5">
                                <label class="fw-bold">Fim</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <labe class="fw-bold">Motivo</label>
                                    <textarea name="reason" class="form-control"></textarea>
                            </div>
                        </div>
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

        // Função genérica para lidar com erros de AJAX
        function handleAjaxError(xhr) {
            let errorMessage = 'Ocorreu um erro desconhecido.';
            if (xhr.status === 401 || xhr.status === 403) {
                errorMessage = 'Sua sessão expirou. Por favor, faça login novamente.';
                // Opcional: redirecionar para o login após um tempo
                setTimeout(() => window.location.href = 'login.php', 2000);
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    const error = JSON.parse(xhr.responseText);
                    errorMessage = error.message || 'Erro no servidor.';
                } catch (e) {
                    errorMessage = 'Erro ao processar a resposta do servidor.';
                }
            }
            Swal.fire('Erro', errorMessage, 'error');
        }

        const table = $('#vacationsTable').DataTable({
            ajax: {
                url: 'rh/ajax/list_vacations.php',
                type: 'GET',
                data: function(d) {
                    d.mes = $('#filter_mes').val() || null;
                    d.funcionario = $('#filter_funcionario').val() || null;
                },
                dataSrc: 'data',
                error: function(xhr) {
                    handleAjaxError(xhr);
                }
            },

            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-PT.json',
                errorLoading: '' // evita warning visual caso falhe o i18n
            },

            columns: [{
                    data: 'employee_name'
                },
                {
                    data: 'type'
                },

                {
                    data: 'start_date',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data + 'T00:00:00').toLocaleDateString('pt-PT');
                    }
                },

                {
                    data: 'end_date',
                    render: function(data) {
                        if (!data) return '-';
                        return new Date(data + 'T00:00:00').toLocaleDateString('pt-PT');
                    }
                },

                {
                    data: 'status',
                    render: function(data) {
                        let cor = 'secondary';

                        if (data === 'Aprovado') cor = 'success';
                        else if (data === 'Rejeitado') cor = 'danger';
                        else if (data === 'Pendente') cor = 'warning';

                        return `<span class="badge bg-${cor}">${data ?? '-'}</span>`;
                    }
                },

                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `
                    <button class="btn btn-sm btn-warning editVacation" data-id="${row.id}" title="Editar">
                        <i class="material-icons-round">edit</i>
                    </button>

                    <button class="btn btn-sm btn-danger deleteVacation" data-id="${row.id}" title="Excluir">
                        <i class="material-icons-round">delete</i>
                    </button>

                    <button class="btn btn-sm btn-outline-primary changeStatus" data-id="${row.id}" title="Alterar Status">
                        <i class="material-icons-round">key</i>
                    </button>
                `;
                    }
                }
            ]
        });

        // Select funcionário do modal
        $('#employee_id').select2({
            placeholder: 'Selecione o funcionário',
            dropdownParent: $('#modalVacation'),
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

        // Select funcionário do filtro
        $('#filter_funcionario').select2({
            placeholder: 'Todos os funcionários',
            allowClear: true,
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

        // Ações dos filtros
        $('#btnFiltrarVac').on('click', function() {
            table.ajax.reload();
        });

        $('#btnLimparVac').on('click', function() {
            $('#filter_mes').val('<?= date('Y-m') ?>');
            $('#filter_funcionario').val(null).trigger('change');
            table.ajax.reload();
        });

        $('#btnExportVacPdf').on('click', function() {
            const mes = $('#filter_mes').val();
            const funcionario = $('#filter_funcionario').val();

            // Exporta PDF do mês selecionado (período completo do mês)
            const url = `rh/ajax/export_vacations_pdf.php?mes=${encodeURIComponent(mes)}&employee_id=${encodeURIComponent(funcionario || '')}`;
            window.open(url, '_blank');
        });

        // Limpar modal ao clicar para adicionar
        $('#addVacationBtn').on('click', function() {
            $('#formVacation')[0].reset();
            $('#formVacation').find('input[name="id"]').remove();
            $('#modalVacationLabel').text('Registrar Período');
            $('#employee_id').val(null).trigger('change');
        });

        // Editar: buscar dados e preencher o modal
        $('#vacationsTable').on('click', '.editVacation', function() {
            const id = $(this).data('id');
            $.ajax({
                url: 'rh/ajax/get_vacation.php',
                type: 'GET',
                data: {
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#modalVacationLabel').text('Editar Período');
                        $('#formVacation').find('input[name="id"]').remove(); // Limpa ID antigo
                        $('#formVacation').append(`<input type="hidden" name="id" value="${data.id}">`);

                        $('#formVacation select[name=type]').val(data.type);
                        $('#formVacation input[name=start_date]').val(data.start_date);
                        $('#formVacation input[name=end_date]').val(data.end_date);
                        $('#formVacation textarea[name=reason]').val(data.reason);

                        // Preenche o select2 com o funcionário
                        if (data.employee) {
                            const option = new Option(data.employee.text, data.employee.id, true, true);
                            $('#employee_id').append(option).trigger('change');
                        }

                        $('#modalVacation').modal('show');
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                },
                error: handleAjaxError
            });
        });

        // Submeter formulário (Adicionar/Editar)
        $('#formVacation').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            $.ajax({
                url: 'rh/ajax/save_vacation.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#modalVacation').modal('hide');
                        table.ajax.reload();
                        Swal.fire('Sucesso', response.message, 'success');
                    } else {
                        Swal.fire('Erro', response.message, 'error');
                    }
                },
                error: handleAjaxError
            });
        });

        // Excluir
        $('#vacationsTable').on('click', '.deleteVacation', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Tem certeza?',
                text: "Esta ação não pode ser revertida!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, excluir!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'rh/ajax/delete_vacation.php',
                        type: 'POST',
                        data: {
                            id: id
                        },
                        success: function(response) {
                            if (response.success) {
                                table.ajax.reload();
                                Swal.fire('Excluído!', response.message, 'success');
                            } else {
                                Swal.fire('Erro', response.message, 'error');
                            }
                        },
                        error: handleAjaxError
                    });
                }
            });
        });

        // Alterar Status
        $('#vacationsTable').on('click', '.changeStatus', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Alterar status',
                input: 'select',
                inputOptions: {
                    'Aprovado': 'Aprovado',
                    'Pendente': 'Pendente',
                    'Rejeitado': 'Rejeitado'
                },
                inputPlaceholder: 'Selecione o novo status',
                showCancelButton: true,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    $.ajax({
                        url: 'rh/ajax/update_vacation_status.php',
                        type: 'POST',
                        data: {
                            id: id,
                            status: result.value
                        },
                        success: function(response) {
                            if (response.success) {
                                table.ajax.reload();
                                Swal.fire('Atualizado', response.message, 'success');
                            } else {
                                Swal.fire('Erro', response.message, 'error');
                            }
                        },
                        error: handleAjaxError
                    });
                }
            });
        });
    });
</script>
<?php require_once '../app/views/footer.php'; ?>