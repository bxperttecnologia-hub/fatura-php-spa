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
</style>

<body>

    <main>
        <div class="container">
            <div id="companies" class="row">
                <h2 class="mb-4">Gestão de Empresas</h2>
                <div id="companies-list" class="row mt-4"></div>
            </div>
        </div>
    </main>


    <script>
        $(document).ready(function() {
            const regLabel = <?= json_encode(t('CNPJ')) ?>;

            function formatDateAO(iso) {
                if (!iso) return '';
                const d = new Date(iso + 'T00:00:00');
                if (isNaN(d.getTime())) return iso;
                return d.toLocaleDateString('pt-PT');
            }

            function loadCompanies() {
                $.ajax({
                    url: "assets/ajax/get_companies.php",
                    method: "GET",
                    dataType: "json",
                    success: function(response) {
                        let companiesHtml = "";
                        if (response.length > 0) {
                            response.forEach(company => {
                                let logoUrl = company.logo_url ? company.logo_url : "assets/img/companies/default.png";

                                companiesHtml += `
                                    <div class="col-md-4">
                                    <div class="card company-card h-100 border-0 shadow-sm">

                                        <!-- HEADER / LOGO -->
                                        <div class="company-card-header text-center position-relative">

                                            <img src="assets/img/companies/${logoUrl}"
                                                class="company-logo"
                                                alt="Logo da ${company.name}">

                                            <span class="status-badge ${company.is_active == 1 ? 'active' : 'expired'}">
                                                ${company.is_active == 1 ? 'Ativa' : 'Expirada'}
                                            </span>

                                        </div>

                                        <!-- BODY -->
                                        <div class="card-body text-center">

                                            <h5 class="company-name mb-1">${company.name}</h5>

                                            <p class="text-muted small mb-2">
                                                ${regLabel}: ${company.registration_number}
                                            </p>

                                            <div class="company-info">

                                                <div><strong>Email:</strong> ${company.email}</div>
                                                <div><strong>Plano:</strong> ${company.plan_code || '-'}</div>
                                                <div><strong>Vencimento:</strong> ${formatDateAO(company.plan_expires_at) || '-'}</div>

                                            </div>

                                        </div>

                                        <!-- FOOTER ACTIONS -->
                                        <div class="card-footer bg-white border-0 text-center pb-3">

                                            <div class="d-flex justify-content-center gap-2">

                                                <button class="action-btn btn-manage" data-id="${company.id}" title="Usuários">
                                                    <i class="material-icons">people</i>
                                                </button>

                                                <button class="action-btn btn-renew" data-id="${company.id}" title="Renovar">
                                                    <i class="material-icons">autorenew</i>
                                                </button>

                                                <button class="action-btn btn-edit2" data-id="${company.id}" title="Editar">
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
                    }
                });
            }

            loadCompanies();


            $(document).on("click", ".btn-edit2", function() {
                let companyId = $(this).data("id");
                window.location.href = `edit_company.php?id=${companyId}`;
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