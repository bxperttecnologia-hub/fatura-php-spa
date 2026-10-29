<?php
require_once '../app/config/db.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

try {
    subscription_require_feature($pdo, (int)($_SESSION['user']['company_id'] ?? 0), 'stock');
} catch (Exception $e) {
    $cid = (int)($_SESSION['user']['company_id'] ?? 0);
    header('Location: subscription.php?company_id=' . $cid . '&upgrade=stock');
    exit;
}

require_once '../app/views/layout_creation.php';
?>

<body>
    <main>

        <!-- <link rel="stylesheet" href="stock/style.css"> -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

        <!-- Lucide -->
        <script src="https://unpkg.com/lucide@latest"></script>

        <style>
            .card-custom {
                border-radius: 12px;
                transition: 0.3s;
            }

            .card-custom:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            }

            .icon-box {
                background: rgba(13, 110, 253, 0.1);
                padding: 5px;
                border-radius: 10px;
                font-weight: bolder;
                font-size: x-large;
                height: 45px !important;
                width: 50px !important;
                display: flex;
                align-items: center;
                align-content: center;
                justify-items: center;
                justify-content: center;
            }

            #pieChart {
                width: 200px !important;
            }

            /* CARD */
            .form-card {
                background: #ffffff;
                border-radius: 14px;
                padding: 18px;
                border: 1px solid #eef2f7;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
                transition: 0.2s;
            }

            .form-card:hover {
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            }

            /* HEADER */
            .form-card-header {
                margin-bottom: 15px;
            }

            .form-card-header h6 {
                font-weight: 600;
                margin: 0;
            }

            .form-card-header span {
                font-size: 0.8rem;
                color: #6b7280;
            }

            /* INPUT */
            .pro-input {
                border-radius: 10px;
                height: 44px;
                border: 1px solid #e5e7eb;
            }

            .pro-input:focus {
                border-color: #635bff;
                box-shadow: 0 0 0 2px rgba(99, 91, 255, 0.15);
            }

            /* BUTTON */
            .pro-btn {
                border-radius: 10px;
                height: 44px;
                font-weight: 500;
            }

            .pro-btn:hover {
                background: #f9fafb;
            }

            .stripe-form {
                font-family: Inter, system-ui;
            }

            .stripe-form label {
                font-size: 0.85rem;
                margin-bottom: 5px;
                display: block;
                align-self: flex-start;
                text-align: left;
                font-weight: bold;
                margin-bottom: 5px !important;
            }

            .stripe-header h5 {
                font-weight: 600;
                margin-bottom: 2px;
            }

            .stripe-header p {
                font-size: 0.85rem;
                color: #6b7280;
                margin-bottom: 15px;
            }

            /* CARD */
            .stripe-card {
                background: #fff;
                border: 1px solid #e6e8eb;
                border-radius: 12px;
                padding: 16px;
            }

            /* INPUT */
            .stripe-input {
                width: 100%;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                height: 40px;
                padding: 0 10px;
                margin-bottom: 10px;
                font-size: 0.9rem;
            }

            .stripe-input:focus {
                border-color: #635bff;
                box-shadow: 0 0 0 2px rgba(99, 91, 255, 0.15);
                outline: none;
            }

            /* COLOR */
            .stripe-color {
                width: 100%;
                height: 40px;
                border-radius: 8px;
                border: none;
            }

            /* BUTTON */
            .stripe-btn {
                width: 100%;
                background: #635bff;
                color: #fff;
                border: none;
                height: 40px;
                border-radius: 8px;
                font-weight: 500;
                margin-top: 8px;
            }

            .stripe-btn:hover {
                background: #5147e5;
            }

            /* MAP */
            .stripe-map {
                height: 180px;
                border-radius: 10px;
            }

            /* DROPDOWN */
            .icon-dropdown {
                position: relative;
            }

            .icon-selected {
                display: flex;
                align-items: center;
                gap: 10px;
                border: 1px solid #e5e7eb;
                padding: 10px;
                border-radius: 8px;
                cursor: pointer;
            }

            .icon-dropdown-menu {
                display: none;
                position: absolute;
                width: 100%;
                max-height: 200px;
                overflow: auto;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                margin-top: 5px;
                z-index: 10;
            }

            .icon-item {
                padding: 8px 10px;
                display: flex;
                gap: 10px;
                cursor: pointer;
            }

            .icon-item:hover {
                background: #f3f4f6;
            }
        </style>

        <div class="container py-4 mt-5">

            <!-- HEADER -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h3 class="fw-bold">Inventário / Armazém</h3>
                    <small class="text-muted">Gerir armazém e inventário</small>
                </div>

                <button class="btn btn-primary" id="createStock">
                    <i data-lucide="plus"></i> Novo Depósito
                </button>
            </div>

            <!-- GRID -->
            <div class="row g-4" id="estoquesContainer"></div>

        </div>

        <!-- ================= MODAIS ================= -->

        <!-- NOVO -->
        <!-- <div class="modal fade" id="createStock">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    
                </div>
            </div>
        </div> -->

        <!-- VER -->
        <div class="modal fade" id="modalVer">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Produtos</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        Lista de produtos aqui...
                    </div>
                </div>
            </div>
        </div>

        <!-- INSIGHTS -->
        <div class="modal fade" id="modalInsights">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Insights</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <canvas id="pieChart" class="mb-4"></canvas>
                        <canvas id="barChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- EDITAR -->
        <div class="modal fade" id="modalEditar">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Editar</h5>
                        <button class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input class="form-control mb-2" id="editNome">
                        <input class="form-control mb-2" id="editDesc">
                        <input class="form-control" id="editLocal">
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary">Salvar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DELETE -->
        <div class="modal fade" id="modalDelete">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Confirmar</h5>
                    </div>
                    <div class="modal-body">
                        Deseja eliminar este depósito?
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-danger">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= JS ================= -->

        <script>
            // abrir dropdown
            $(document).on("click", "#selectedIcon", function() {
                $("#iconOptions").toggle();
            });

            // selecionar
            $(document).on("click", ".icon-item", function() {
                const icon = $(this).data("icon");

                $("#selectedIcon").html(`
    <i class="bi bi-${icon}"></i>
    <span>${icon}</span>
    <i class="bi bi-chevron-down ms-auto"></i>
  `);

                $("#selectedIcon").data("icon", icon);
                $("#iconOptions").hide();
            });

            // fechar fora
            $(document).on("click", function(e) {
                if (!$(e.target).closest("#iconDropdown").length) {
                    $("#iconOptions").hide();
                }
            });


            // let pieChart, barChart;

            // function carregarGraficos() {

            //     const labels = data.map(d => d.nome);
            //     const valores = data.map(d => parseFloat(d.valor.replace('.', '')));
            //     const itens = data.map(d => d.itens);

            //     // DESTROI gráficos antigos (evita bug ao reabrir modal)
            //     if (pieChart) pieChart.destroy();
            //     if (barChart) barChart.destroy();

            //     // PIZZA
            //     pieChart = new Chart(document.getElementById('pieChart'), {
            //         type: 'pie',
            //         data: {
            //             labels: labels,
            //             datasets: [{
            //                 data: valores
            //             }]
            //         }
            //     });

            //     // BARRA
            //     barChart = new Chart(document.getElementById('barChart'), {
            //         type: 'bar',
            //         data: {
            //             labels: labels,
            //             datasets: [{
            //                 label: 'Itens',
            //                 data: itens
            //             }]
            //         },
            //         options: {
            //             scales: {
            //                 y: {
            //                     beginAtZero: true
            //                 }
            //             }
            //         }
            //     });
            // }

            // const modalInsights = document.getElementById('modalInsights');

            // modalInsights.addEventListener('shown.bs.modal', () => {
            //     carregarGraficos();
            // });
        </script>

        <script src="stock/stock.js"></script>


        <?php require_once '../app/views/footer.php'; ?>
</body>

</html>