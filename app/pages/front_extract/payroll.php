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
    #payrollTable {
        border-collapse: separate;
        border-spacing: 0 12px;
        width: 100%;
    }

    /* HEADER */
    #payrollTable thead th {
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

    #payrollTable thead th:last-child {
        border-right: none;
    }

    #payrollTable tbody tr td {
        border-right: 1px solid #e5e7eb57;
    }

    /* ROW */
    #payrollTable tbody tr {
        background: #fff !important;
        border-radius: 14px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        text-align: left !important;
    }


    /* HOVER PRO */
    #payrollTable tbody tr:hover {
        transform: translateY(-4px) scale(1.01);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    /* CELLS */
    #payrollTable tbody td {
        border: none;
        padding: 18px 16px;
        vertical-align: middle;
        font-size: 0.95rem;
        background: #fff !important;
        text-align: left !important;
    }

    #payrollTable thead td {
        background: #111 !important;
        display: none;
        max-width: 80px !important;
    }

    /* BORDAS ARREDONDADAS */
    #payrollTable tbody td:first-child {
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
        background: #fff !important;
    }

    #payrollTable tbody th {
        text-align: left !important;
    }

    #payrollTable tbody td:last-child {
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
        text-align: right;
        padding-right: 24px;
    }

    /* ===== NOME (PRINCIPAL) ===== */
    #payrollTable tbody td:first-child {
        font-weight: 600;
        color: #111;
    }

    /* SUBINFO */
    #payrollTable tbody td small {
        display: block;
        color: #6b7280;
    }

    .modal-content {
        backdrop-filter: blur(10px);
    }

    .form-floating>.form-control,
    .form-floating>.form-select {
        border-radius: 12px;
        background: #fff;
        transition: all 0.2s ease;
    }

    .form-floating>.form-control:focus,
    .form-floating>.form-select:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, .15);
        border-color: #0d6efd;
        transform: translateY(-1px);
    }

    .shadow-sm {
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05) !important;
    }

    .modal-header.bg-gradient-primary {
        background: linear-gradient(135deg, #0d6efd, #6610f2);
    }
</style>

<main class="main-content">
    <div class="container-fluid mt-5">
        <div class="mb-3 d-flex align-items-center">
            <input type="month" id="inputMesReferencia" class="form-control w-auto me-2" />
            <button class="btn btn-sm d-flex gap-1 btn-outline-danger rounded-pill" id="btnExportarPDF">
                <i class="material-icons-round">picture_as_pdf</i> Exportar PDF Geral
            </button>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Folha de Pagamento</h5>
                        <div class="d-flex gap-2 mb-3">
                            <button class="btn btn-sm rounded-pill d-flex gap-1 align-items-center btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPayroll">
                                <i class="material-icons-round">attach_money</i>
                                Registrar Pagamento
                            </button>
                            <a href="rh/export/folha_export.php" class="btn btn-sm rounded-pill d-flex gap-1 align-items-center btn-outline-success">
                                <i class="material-icons-round">download</i> Exportar Folha
                            </a>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm rounded-pill dropdown-toggle btn-outline-info" data-bs-toggle="dropdown">
                                    <i class="material-icons-round">summarize</i> Mapa INSS
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" id="linkMapaInssPdf">PDF</a></li>
                                    <li><a class="dropdown-item" href="#" id="linkMapaInssXlsx">Excel</a></li>
                                </ul>
                            </div>
                            <button class="btn btn-sm rounded-pill d-flex gap-1 align-items-center btn-outline-dark" id="btnGerarFolhaMes">
                                <i class="material-icons-round">auto_awesome</i> Gerar Folha do Mês
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="payrollTable" class="table w-100">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Referente a</th>
                                        <th>Salário Base</th>
                                        <th>Bônus</th>
                                        <th>Adicionais</th>
                                        <th>Descontos</th>
                                        <th>Salário Líquido</th>
                                        <th>IBAN</th>
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

<!-- Modal Registro de Pagamento -->
<div class="modal fade" id="modalPayroll" tabindex="-1" aria-labelledby="modalPayrollLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

            <form id="formPayroll">

                <!-- HEADER -->
                <div class="modal-header bg-gradient-primary text-white py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalPayrollLabel">
                            <i class="bi bi-cash-stack"></i> Registrar Pagamento
                        </h5>
                        <small class="opacity-75">Preencha os dados do processamento salarial</small>
                    </div>

                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- BODY -->
                <div class="modal-body p-4 bg-light">

                    <div class="row g-3">

                        <!-- Employee -->
                        <div class="col-md-12">
                            <div class="form-floating shadow-sm col-12">
                                <select name="employee_id" id="employee_id_payroll" class="form-select border-0 col-12"></select>
                                <label><i class="bi bi-person"></i> Funcionário</label>
                            </div>
                        </div>

                        <!-- Month -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="month" name="reference_month" class="form-control border-0" required>
                                <label><i class="bi bi-calendar2"></i> Referente a</label>
                            </div>
                        </div>

                        <!-- Base Salary -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="base_salary" step="0.01" class="form-control border-0" required>
                                <label><i class="bi bi-cash"></i> Salário Base</label>
                            </div>
                        </div>

                        <!-- Bonuses -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="bonuses" step="0.01" class="form-control border-0" value="0">
                                <label> Bônus</label>
                            </div>
                        </div>

                        <!-- Allowances -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="food_allowance" step="0.01" class="form-control border-0" value="0">
                                <label> Subs. alimentação</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="transport_allowance" step="0.01" class="form-control border-0" value="0">
                                <label><i class="bi bi-car"></i> Subs. transporte</label>
                            </div>
                        </div>

                        <!-- Percent Selects -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <select name="vacation_subsidy_pct" class="form-select border-0">
                                    <option value="0">0%</option>
                                    <option value="50">50%</option>
                                    <option value="100">100%</option>
                                </select>
                                <label> Férias</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <select name="thirteenth_subsidy_pct" class="form-select border-0">
                                    <option value="0">0%</option>
                                    <option value="50">50%</option>
                                    <option value="100">100%</option>
                                </select>
                                <label> 13º Subsídio</label>
                            </div>
                        </div>

                        <!-- Sales / Commission -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="commissions" step="0.01" class="form-control border-0" value="0">
                                <label> Comissões</label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="sales" step="0.01" class="form-control border-0" value="0">
                                <label> Vendas</label>
                            </div>
                        </div>

                        <!-- Discounts -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="number" name="discounts" step="0.01" class="form-control border-0" value="0">
                                <label><i class="bi bi-percent"></i> Descontos</label>
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <input type="date" name="payment_date" class="form-control border-0">
                                <label><i class="bi bi-calendar2"></i> Data Pagamento</label>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-4">
                            <div class="form-floating shadow-sm">
                                <select name="status" class="form-select border-0">
                                    <option value="Pendente">🟡 Pendente</option>
                                    <option value="Pago">🟢 Pago</option>
                                </select>
                                <label>Status</label>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-white border-0 px-4 py-3">

                    <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm fw-bold">
                        Salvar Pagamento →
                    </button>

                </div>

            </form>
        </div>
    </div>
</div>


<!-- jsPDF PRIMEIRO -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>


<!-- AutoTable DEPOIS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>

<script>
    $(document).ready(function() {
        const table = $('#payrollTable').DataTable({
            ajax: {
                url: 'rh/ajax/list_payroll.php',
                data: function(d) {
                    d.mes = $('#inputMesReferencia').val();
                }
            },
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
            },
            columns: [{
                    data: 'employee_name'
                },
                {
                    data: 'reference_month'
                },
                {
                    data: 'base_salary',
                    render: data => `Kz ${parseFloat(data).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`
                },
                {
                    data: 'bonuses',
                    render: data => `Kz ${parseFloat(data).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`
                },
                {
                    data: null,
                    render: row => {
                        const totalAdd = (
                            parseFloat(row.food_allowance || 0) +
                            parseFloat(row.transport_allowance || 0) +
                            (parseFloat(row.base_salary || 0) * (parseInt(row.vacation_subsidy_pct || 0) / 100)) +
                            (parseFloat(row.base_salary || 0) * (parseInt(row.thirteenth_subsidy_pct || 0) / 100)) +
                            parseFloat(row.commissions || 0) +
                            parseFloat(row.sales || 0)
                        );
                        return `Kz ${totalAdd.toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`;
                    }
                },
                {
                    // Coluna da listagem mostra o TOTAL de descontos (manual + faltas + INSS + IRT).
                    // O campo `discounts` isolado (só o valor manual) é usado no formulário de edição.
                    data: 'total_discounts',
                    render: data => `Kz ${parseFloat(data || 0).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`
                },
                {
                    data: 'net_salary',
                    render: data => `Kz ${parseFloat(data).toLocaleString('pt-AO', { minimumFractionDigits: 2 })}`
                },
                {
                    data: 'iban',
                    // render: data => 'iban'
                },
                {
                    data: 'status',
                    render: status => `<span class="badge bg-${status === 'Pago' ? 'success' : 'warning'}">${status}</span>`
                },
                {
                    data: null,
                    render: row => `
                        <button class='btn btn-sm btn-edit-employee text-warning' data-id='${row.id}'><i class="bi bi-pencil"></i></button>
                        <button class='btn btn-sm text-danger openReceipt'
                            data-id='${row.id}'><i class="bi bi-file-pdf"></i></button>`
                }
            ]
        });

        $('#inputMesReferencia').on('change', function() {
            table.ajax.reload();
        });

        $('#employee_id_payroll').select2({
            placeholder: 'Selecione o funcionário',
            ajax: {
                url: 'rh/ajax/search_employees.php',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    term: params.term
                }),
                processResults: data => ({
                    results: data,
                }),
                templateResult: function(item) {
                    console.log(data)
                    if (!item.id) return item.text;
                    return `${item.text} - ${item.salary ? 'Salário: Kz ' + parseFloat(item.salary).toLocaleString('pt-AO') : ''}`;
                }
            }
        });

        $('#employee_id_payroll').on('select2:select', function(e) {

            const data = e.params.data;

            const salario = parseFloat(data.salary || 0);
            const subAlim = parseFloat(data.sub_alim || 0);
            const subTrans = parseFloat(data.sub_trans || 0);
            const subDecimo = parseFloat(data.sub_decimo || 0);
            const subFerias = parseFloat(data.sub_ferias || 0);

            console.log(data);

            // INPUTS
            $('#formPayroll [name="base_salary"]')
                .val(salario.toFixed(2));

            $('#formPayroll [name="food_allowance"]')
                .val(subAlim.toFixed(2));

            $('#formPayroll [name="transport_allowance"]')
                .val(subTrans.toFixed(2));

            // SELECTS — valores por defeito do cargo
            $('#formPayroll select[name="vacation_subsidy_pct"]')
                .val(subFerias)
                .trigger('change');

            $('#formPayroll select[name="thirteenth_subsidy_pct"]')
                .val(subDecimo)
                .trigger('change');

            // Fase 2, item 4: refina a sugestão com base em férias aprovadas
            // este mês e nos meses trabalhados no ano (13º proporcional).
            refreshSuggestedSubsidies();

        });

        // Fase 2, item 4 — sugestão automática de subsídio de férias / 13º mês.
        // Nunca grava sozinho: só pré-preenche os selects, que continuam editáveis.
        function refreshSuggestedSubsidies() {
            const employeeId = $('#employee_id_payroll').val();
            const referenceMonth = $('#formPayroll input[name="reference_month"]').val();
            if (!employeeId || !referenceMonth) return;

            $.getJSON('rh/ajax/get_suggested_subsidies.php', {
                employee_id: employeeId,
                reference_month: referenceMonth
            }, function(resp) {
                if (!resp.success) return;

                $('#formPayroll select[name="vacation_subsidy_pct"]')
                    .val(resp.suggested_vacation_subsidy_pct)
                    .trigger('change');
                $('#formPayroll select[name="thirteenth_subsidy_pct"]')
                    .val(resp.suggested_thirteenth_subsidy_pct)
                    .trigger('change');

                $('#subsidyHint').remove();
                $('#formPayroll select[name="thirteenth_subsidy_pct"]').closest('.mb-3')
                    .after(`<div id="subsidyHint" class="form-text text-muted mb-2">
                        <i class="bi bi-info-circle"></i> Férias: ${resp.vacation_reason}<br>
                        <i class="bi bi-info-circle"></i> 13º: ${resp.thirteenth_reason}
                    </div>`);
            });
        }

        $('#formPayroll input[name="reference_month"]').on('change', function() {
            refreshSuggestedSubsidies();
        });

        $('#formPayroll').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const base = parseFloat(form.find('[name=base_salary]').val());
            const bonus = parseFloat(form.find('[name=bonuses]').val());
            const discount = parseFloat(form.find('[name=discounts]').val());
            const food = parseFloat(form.find('[name=food_allowance]').val() || 0);
            const transport = parseFloat(form.find('[name=transport_allowance]').val() || 0);
            const vacPct = parseInt(form.find('[name=vacation_subsidy_pct]').val() || 0);
            const thirteenthPct = parseInt(form.find('[name=thirteenth_subsidy_pct]').val() || 0);
            const commissions = parseFloat(form.find('[name=commissions]').val() || 0);

            const vacationSub = base * (vacPct / 100);
            const thirteenthSub = base * (thirteenthPct / 100);
            const net = (base + bonus + food + transport + vacationSub + thirteenthSub + commissions - discount).toFixed(2);
            form.append(`<input type='hidden' name='net_salary' value='${net}' id='temp_net'>`);

            $.post('rh/ajax/save_payroll.php', form.serialize(), function() {
                $('#modalPayroll').modal('hide');
                table.ajax.reload();
                Swal.fire('Sucesso', 'Pagamento registrado com sucesso!', 'success');
                resetPayrollForm();
            });
        });

        $('#payrollTable').on('click', '.btn-edit-employee', function() {
            const row = table.row($(this).parents('tr')).data();
            $('#formPayroll select[name=employee_id]').html(`<option value="${row.employee_id}" selected>${row.employee_name}</option>`);
            $('#formPayroll input[name=reference_month]').val(row.reference_month);
            $('#formPayroll input[name=base_salary]').val(row.base_salary);
            $('#formPayroll input[name=bonuses]').val(row.bonuses);
            $('#formPayroll input[name=food_allowance]').val(row.food_allowance);
            $('#formPayroll input[name=transport_allowance]').val(row.transport_allowance);
            $('#formPayroll select[name=vacation_subsidy_pct]').val(row.vacation_subsidy_pct);
            $('#formPayroll select[name=thirteenth_subsidy_pct]').val(row.thirteenth_subsidy_pct);
            $('#formPayroll input[name=commissions]').val(row.commissions);
            $('#formPayroll input[name=sales]').val(row.sales);
            // row.discounts agora é só o valor manual (não o total) — é o que se edita aqui.
            $('#formPayroll input[name=discounts]').val(row.discounts);
            $('#formPayroll input[name=payment_date]').val(row.payment_date);
            $('#formPayroll select[name=status]').val(row.status);
            $('#formPayroll').append(`<input type="hidden" name="id" value="${row.id}" id="editPayrollId">`);
            $('#modalPayroll').modal('show');
        });

        $('#modalPayroll').on('hidden.bs.modal', function() {
            resetPayrollForm();
        });

        function resetPayrollForm() {
            $('#formPayroll')[0].reset();
            $('#formPayroll select[name=employee_id]').val(null).trigger('change');
            $('#editPayrollId').remove();
            $('#temp_net').remove();
        }

        // Recibo: abre o PDF server-side (com proventos e descontos detalhados)
        $('#payrollTable').on('click', '.openReceipt', function() {
            const id = $(this).data('id');
            window.open(`rh/pdf/recibo.php?id=${id}`, '_blank');
        });

        /* (removido) Gerador client-side antigo via jsPDF, pois não refletia INSS/IRT corretamente */

        // $('#payrollTable').on('click', '.generatePdf', function() { ... })

        // --- (resto do código continua) ---


    });

    $('#btnExportarPDF').on('click', function() {
        const mes = $('#inputMesReferencia').val();
        if (!mes) {
            return Swal.fire('Atenção', 'Selecione o mês de referência!', 'warning');
        }

        window.open(`rh/ajax/export_all_payroll_pdf.php?mes=${mes}`, '_blank');
    });

    // Fase 2: Mapa de Remunerações INSS (PDF / Excel) do mês selecionado
    $('#linkMapaInssPdf, #linkMapaInssXlsx').on('click', function(e) {
        e.preventDefault();
        const mes = $('#inputMesReferencia').val();
        if (!mes) {
            return Swal.fire('Atenção', 'Selecione o mês de referência!', 'warning');
        }
        const format = this.id === 'linkMapaInssXlsx' ? 'xlsx' : 'pdf';
        window.open(`rh/export/mapa_inss.php?mes=${mes}&format=${format}`, '_blank');
    });

    // Fase 5, item 1: "Gerar Folha do Mês" — cria folhas Pendente para quem
    // ainda não tem folha nesse mês, pré-preenchidas a partir do cargo e da
    // sugestão automática de subsídios. O RH revê e marca como Pago depois.
    $('#btnGerarFolhaMes').on('click', function() {
        const mes = $('#inputMesReferencia').val();
        if (!mes) {
            return Swal.fire('Atenção', 'Selecione o mês de referência antes de gerar a folha!', 'warning');
        }
        Swal.fire({
            title: `Gerar folha de ${mes}?`,
            text: 'Cria uma folha "Pendente" para cada funcionário ativo que ainda não tenha folha neste mês.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, gerar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.post('rh/ajax/generate_monthly_payroll.php', { reference_month: mes }, function(resp) {
                if (resp.success) {
                    table.ajax.reload();
                    Swal.fire('Ok', resp.message, 'success');
                } else {
                    Swal.fire('Erro', resp.message || 'Não foi possível gerar a folha.', 'error');
                }
            }, 'json').fail(function(xhr) {
                const resp = xhr.responseJSON;
                Swal.fire('Erro', (resp && resp.message) || 'Não foi possível gerar a folha.', 'error');
            });
        });
    });
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-mask/1.14.16/jquery.mask.min.js"></script>


<?php require_once '../app/views/footer.php'; ?>