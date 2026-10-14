<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    #companies{
        display: flex;
        align-items: center;
        align-content: center;
        justify-content: center;
        justify-items: center;
        width: 100%;
        /* height: 100vh; */
        margin-top: 10%;
    }

    .company-card {
        border-radius: 16px;
        transition: all 0.25s ease;
        overflow: hidden;
    }

    .company-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
    }

    /* HEADER */
    .company-card-header {
        background: linear-gradient(135deg, #007abd, #005a87);
        height: 110px;
        position: relative;
    }

    /* LOGO */
    .company-logo {
        width: 80px;
        height: 80px;
        object-fit: contain;
        background: #fff;
        border-radius: 50%;
        padding: 10px;
        position: absolute;
        bottom: -40px;
        left: 50%;
        transform: translateX(-50%);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* BODY AJUSTE */
    .company-card .card-body {
        padding-top: 50px;
    }

    /* NAME */
    .company-name {
        font-weight: 600;
        font-size: 16px;
    }

    /* INFO */
    .company-info {
        font-size: 13px;
        color: #555;
        display: grid;
        gap: 4px;
    }

    /* STATUS BADGE */
    .status-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 11px;
        padding: 5px 10px;
        border-radius: 20px;
        color: #fff;
    }

    .status-badge.active {
        background: #1cc88a;
    }

    .status-badge.expired {
        background: #e74a3b;
    }

    /* ACTION BUTTONS */
    .action-btn {
        border: none;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #f1f3f5;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
        cursor: pointer;
    }

    .action-btn i {
        font-size: 18px;
        color: #555;
    }

    .action-btn:hover {
        background: #007abd;
    }

    .action-btn:hover i {
        color: #fff;
    }

    .company-management-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        width: 100%;
    }
</style>

<body>

    <main>
        <div class="container">
            <div id="companies" class="row">
                <div class="company-management-header">
                    <h2 class="mb-0">Gestão de Empresas</h2>
                    <button type="button" class="btn btn-primary" id="btnAddCompany" data-bs-toggle="modal" data-bs-target="#addCompanyModal" disabled>
                        <i class="bi bi-plus-lg me-1"></i> Adicionar empresa
                    </button>
                    <div class="w-100 text-end small text-muted" id="companyPlanUsage" aria-live="polite"></div>
                </div>
                <div id="companies-list" class="row mt-4"></div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" id="addCompanyForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCompanyModalTitle">Adicionar empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">A empresa será adicionada ao plano e à validade da subscrição atual.</p>
                    <div class="mb-3">
                        <label for="newCompanyNif" class="form-label">NIF</label>
                        <input type="text" class="form-control" id="newCompanyNif" name="registration_number" minlength="5" maxlength="25" required autocomplete="off">
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="btnLookupCompanyNif">Consultar NIF na AGT</button>
                    <div class="small mb-3" id="newCompanyNifStatus" role="status" aria-live="polite"></div>
                    <div class="mb-3">
                        <label for="newCompanyName" class="form-label">Nome da empresa</label>
                        <input type="text" class="form-control" id="newCompanyName" name="name" minlength="2" maxlength="255" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveCompany">Adicionar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const regLabel = <?= json_encode(t('CNPJ')) ?>;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const publicPath = window.location.pathname.match(/^(.*\/public)(?:\/|$)/)?.[1] || '';
            const nifLookupUrl = `${publicPath}/contacts/ajax/consult_nif.php`;

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, (character) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[character]);
            }

            function formatDateAO(iso) {
                if (!iso) return '';
                const d = new Date(iso + 'T00:00:00');
                if (isNaN(d.getTime())) return iso;
                return d.toLocaleDateString('pt-PT');
            }

            function loadCompanies() {
                $.ajax({
                    url: `${publicPath}/assets/ajax/get_companies.php`,
                    method: "GET",
                    dataType: "json",
                    success: function(response) {
                        if (!Array.isArray(response)) {
                            $("#companies-list").html(`<p class="text-danger text-center">Não foi possível carregar a lista de empresas.</p>`);
                            $("#companyPlanUsage").text('Não foi possível obter o limite do plano.');
                            $("#btnAddCompany").prop('disabled', true);
                            return;
                        }
                        let companiesHtml = "";
                        if (response.length > 0) {
                            const companyLimit = response[0].company_limit;
                            const companyCount = Number(response[0].company_count) || 0;
                            const canAddCompany = response[0].can_add_company === true || response[0].can_add_company === 1 || response[0].can_add_company === '1';
                            const limitText = companyLimit === null ? 'ilimitado' : companyLimit;
                            $("#companyPlanUsage").text(`${escapeHtml(response[0].plan_name || 'Plano atual')}: ${companyCount} / ${limitText} empresas`);
                            $("#btnAddCompany").prop('disabled', !canAddCompany);
                            $("#btnAddCompany").attr('title', canAddCompany ? 'Adicionar empresa' : 'O limite de empresas do plano foi atingido ou a subscrição não está ativa.');
                            if (!canAddCompany) {
                                $("#companyPlanUsage").append(` · <a href="${publicPath}/subscription" data-spa>Faça upgrade para adicionar mais</a>`);
                            }

                            response.forEach(company => {
                                let logoUrl = company.logo_url
                                    ? (/^https?:\/\//i.test(company.logo_url)
                                        ? escapeHtml(company.logo_url)
                                        : `assets/img/companies/${encodeURIComponent(company.logo_url)}`)
                                    : "assets/img/companies/default.png";

                                companiesHtml += `
                                    <div class="col-md-4">
                                    <div class="card company-card h-100 border-0 shadow-sm">

                                        <!-- HEADER / LOGO -->
                                        <div class="company-card-header text-center position-relative">

                                            <img src="${logoUrl}"
                                                class="company-logo"
                                                alt="Logo da ${escapeHtml(company.name)}">

                                            <span class="status-badge ${company.is_active == 1 ? 'active' : 'expired'}">
                                                ${company.is_active == 1 ? 'Ativa' : 'Expirada'}
                                            </span>

                                        </div>

                                        <!-- BODY -->
                                        <div class="card-body text-center">

                                            <h5 class="company-name mb-1">${escapeHtml(company.name)}</h5>

                                            <p class="text-muted small mb-2">
                                                ${escapeHtml(regLabel)}: ${escapeHtml(company.registration_number)}
                                            </p>

                                            <div class="company-info">

                                                <div><strong>Email:</strong> ${escapeHtml(company.email || '-')}</div>
                                                <div><strong>Plano:</strong> ${escapeHtml(company.plan_code || '-')}</div>
                                                <div><strong>Vencimento:</strong> ${escapeHtml(formatDateAO(company.plan_expires_at) || '-')}</div>

                                            </div>

                                        </div>

                                        <!-- FOOTER ACTIONS -->
                                        <div class="card-footer bg-white border-0 text-center pb-3">

                                            <div class="d-flex justify-content-center gap-2">

                                                <button class="action-btn btn-manage" data-id="${escapeHtml(company.id)}" title="Usuários">
                                                    <i class="material-icons">people</i>
                                                </button>

                                                <button class="action-btn btn-renew" data-id="${escapeHtml(company.id)}" title="Renovar">
                                                    <i class="material-icons">autorenew</i>
                                                </button>

                                                <button class="action-btn btn-edit2" data-id="${escapeHtml(company.id)}" title="Editar">
                                                    <i class="material-icons">edit</i>
                                                </button>

                                            </div>

                                        </div>

                                    </div>
                                </div>

                                `;
                            });
                        } else {
                            companiesHtml = `<p class="text-center">Nenhuma empresa encontrada.</p>`;
                        }
                        $("#companies-list").html(companiesHtml);
                    },
                    error: function() {
                        $("#companies-list").html(`<p class="text-danger text-center">Erro ao carregar empresas.</p>`);
                        $("#companyPlanUsage").text('Não foi possível obter o limite do plano.');
                        $("#btnAddCompany").prop('disabled', true);
                    }
                });
            }

            loadCompanies();

            $('#btnLookupCompanyNif').on('click', function() {
                const nif = $('#newCompanyNif').val().trim();
                if (!/^[A-Za-z0-9]{5,25}$/.test(nif)) {
                    $('#newCompanyNifStatus').removeClass('text-success text-muted').addClass('text-danger').text('Indique um NIF válido (5 a 25 letras ou números).');
                    return;
                }
                const $button = $(this).prop('disabled', true).text('A consultar...');
                $('#newCompanyNifStatus').removeClass('text-success text-danger').addClass('text-muted').text('A consultar a AGT...');
                $.getJSON(nifLookupUrl, { tipoDocumento: 'NIF', numeroDocumento: nif })
                    .done(function(response) {
                        if (response.status === 'success' && response.data?.nome) {
                            $('#newCompanyName').val(response.data.nome);
                            $('#newCompanyNifStatus').removeClass('text-muted text-danger').addClass('text-success').text('NIF encontrado. Confirme o nome da empresa.');
                        } else {
                            $('#newCompanyNifStatus').removeClass('text-muted text-success').addClass('text-danger').text((response.message || 'NIF não encontrado.') + ' Pode preencher o nome manualmente.');
                        }
                    })
                    .fail(function(xhr) {
                        const message = xhr.responseJSON?.message || 'Falha ao consultar a AGT.';
                        $('#newCompanyNifStatus').removeClass('text-muted text-success').addClass('text-danger').text(message + ' Pode preencher o nome manualmente.');
                    })
                    .always(function() {
                        $button.prop('disabled', false).text('Consultar NIF na AGT');
                    });
            });

            $('#addCompanyForm').on('submit', function(event) {
                event.preventDefault();
                const $button = $('#btnSaveCompany').prop('disabled', true);
                $.ajax({
                    url: `${publicPath}/assets/ajax/create_company.php`,
                    method: 'POST',
                    data: $(this).serialize(),
                    headers: { 'X-CSRF-Token': csrfToken },
                    dataType: 'json'
                }).done(function(response) {
                    if (!response.success) {
                        Swal.fire('Não foi possível adicionar a empresa', response.message || 'Tente novamente.', 'error');
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('addCompanyModal')).hide();
                    $('#addCompanyForm')[0].reset();
                    Swal.fire('Empresa adicionada', 'A nova empresa já está disponível no seletor de empresas.', 'success');
                    loadCompanies();
                    document.dispatchEvent(new Event('companies:updated'));
                }).fail(function(xhr) {
                    const message = xhr.responseJSON?.message || 'Erro ao adicionar empresa. Verifique os dados e tente novamente.';
                    Swal.fire('Erro', message, 'error');
                }).always(function() {
                    $button.prop('disabled', false);
                });
            });

            $(document).on("click", ".btn-edit2", function() {
                const companyId = $(this).data("id");
                const editUrl = `${publicPath}/companies/edit?id=${encodeURIComponent(companyId)}`;
                if (typeof window.navigateSPA === "function") {
                    window.navigateSPA(editUrl);
                } else {
                    window.location.href = editUrl;
                }
            });
            $(document).on("click", ".btn-manage", function() {
                let companyId = $(this).data("id");
                window.location.href = `manage_users.php?company_id=${companyId}`;
            });

            $(document).on("click", ".btn-renew", function() {
                let companyId = $(this).data("id");
                window.location.href = `subscription.php?company_id=${companyId}`;
            });
        });
    </script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>