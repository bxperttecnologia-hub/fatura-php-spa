
/* =============================
🔹 INIT VARS
============================= */

const currentYear = new Date().getFullYear();
let selectedYear = new Date().getFullYear();

const userEl = document.getElementById("user_id");
const companyEl = document.getElementById("company_id");

const user_id = userEl?.value || null;
const company_id = companyEl?.value || null;

let chartInstance = null;
let rhAbsencesChartInstance = null;
let carousel = null;
let currentData = null;

/* =============================
   🔹 HELPERS
============================= */
function formatCurrency(value, currency = "AOA", locale = "pt-AO") {
    return new Intl.NumberFormat(locale, {
        style: "currency",
        currency
    }).format(Number(value) || 0);
}

function getBadge(status) {
    switch (status) {
        case 'pago':
            return '<span class="badge bg-success">Pago</span>';
        case 'parcial':
            return '<span class="badge bg-info">Parcial</span>';
        default:
            return '<span class="badge bg-warning text-dark">Pendente</span>';
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('pt-PT', {
        day: '2-digit',
        month: 'short'
    });
}

function getInitials(name = "") {
    if (!name) return "?";
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function formatDateRange(start, end) {
    if (!start || !end) return 'Datas não definidas';

    const s = new Date(start);
    const e = new Date(end);

    const opt = {
        day: '2-digit',
        month: 'short'
    };

    return `${s.toLocaleDateString('pt-PT', opt)}–${e.toLocaleDateString('pt-PT', opt)}`;
}

const splitData = (data) => {
    if (!data) return "";

    let splited = data.split(" ")[0];
    let year = data.split("-")[0];
    let month = data.split("-")[1];

    const months = [{
        name: "Janeiro",
        index: 1
    },
    {
        name: "Fevereiro",
        index: 2
    },
    {
        name: "Março",
        index: 3
    },
    {
        name: "Abril",
        index: 4
    },
    {
        name: "Maio",
        index: 5
    },
    {
        name: "Junho",
        index: 6
    },
    {
        name: "Julho",
        index: 7
    },
    {
        name: "Agosto",
        index: 8
    },
    {
        name: "Setembro",
        index: 9
    },
    {
        name: "Outubro",
        index: 10
    },
    {
        name: "Novembro",
        index: 11
    },
    {
        name: "Dezembro",
        index: 12
    }
    ]

    let match = months.find(m => m.index == month);
    let mOnth = match?.name;
    return {
        year,
        mOnth
    };
}

/* =============================
   🔹 NOVO: setDif genérico
   Trata valores null/undefined como "sem dados
   anteriores" em vez de forçar 0% ou -100%
   enganosos.
============================= */
function setDif(id, value, opts = {}) {
    const el = document.getElementById(id);
    if (!el) return;

    const suffix = opts.suffix ?? '%';
    const noSuffixOnPositive = opts.noSuffixOnPositive ?? false;

    if (value === null || value === undefined) {
        el.innerHTML = `Sem dados anteriores`;
        el.className = "small text-muted-dif";
        return;
    }

    const numeric = Number(value);

    if (numeric > 0) {
        el.innerHTML = `<i class="bi bi-arrow-up-right"></i>${noSuffixOnPositive ? '+' : ''}${numeric}${noSuffixOnPositive ? '' : suffix}`;
        el.className = "small text-success";
    } else if (numeric < 0) {
        el.innerHTML = `<i class="bi bi-arrow-down-right"></i>${numeric}${suffix}`;
        el.className = "small text-danger";
    } else {
        el.innerHTML = `<i class="bi bi-dash"></i>0${suffix}`;
        el.className = "small text-muted-dif";
    }
}

/* =============================
   🔹 TABS
============================= */
const tagsLink = document.querySelectorAll(".tag-link");
const tagsContent = document.querySelectorAll(".tag-content");

tagsLink.forEach(t => {
    t.addEventListener("click", () => {
        tagsLink.forEach(x => x.classList.remove("active"));
        t.classList.add("active");

        showAndHideTag(t.getAttribute("data-tag"));
    });
});

function showAndHideTag(tag) {
    tagsContent.forEach(tgc => {
        const attr = tgc.getAttribute("data-tag-content");

        if (attr === tag) {
            tgc.classList.add("active");
            tgc.classList.remove("exit");
        } else {
            if (tgc.classList.contains("active")) {
                tgc.classList.add("exit");

                setTimeout(() => {
                    tgc.classList.remove("active", "exit");
                }, 300);
            }
        }
    });
}

/* =============================
   🔹 LOAD INVOICES
============================= */
function loadInvoices() {
    $.ajax({
        url: 'invoices/ajax/get_invoices_feed.php',
        method: 'GET',
        dataType: 'json',
        data: {
            year: selectedYear
        },

        success: function (res) {

            if (!res?.success || !Array.isArray(res.data)) return;

            let data = res.data?.filter(d => d.final_total > 0);

            let html = '';

            if (data.length === 0) {
                html = `<div class="p-3 text-center text-muted small">Sem faturas neste período</div>`;
            }

            data.forEach(inv => {
                // Trunca nomes longos com título completo no hover
                const clientName = inv.client_name ?? '';
                const displayName = clientName.length > 22 ?
                    clientName.slice(0, 22) + '…' :
                    clientName;

                html += `
                    <a href="invoice.php?id=${inv.id}">
                        <div class="item">
                            <div class="card p-2 hover-row">
                                <div class="d-flex justify-content-between">
                                    <small class="text-primary">${inv.reference ?? ''}</small>
                                    ${getBadge(inv.status)}
                                </div>
                                <strong title="${clientName}">${displayName}</strong>
                                <div class="d-flex justify-content-between">
                                    <small>${formatDate(inv.issue_date)}</small>
                                    <span class="small text-black">
                                        ${formatCurrency(inv.final_total)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </a>
                    `;
            });

            if (carousel) {
                try {
                    carousel.trigger('destroy.owl.carousel');
                } catch (e) { }
                $('#invoiceCarousel').html('');
            }

            $('#invoiceCarousel').html(html);

            carousel = $('#invoiceCarousel').owlCarousel({
                items: 1.2,
                margin: 10,
                loop: res.data.length > 1,
                autoplay: res.data.length > 1,
                autoplayTimeout: 3000,
                autoplayHoverPause: true,
                dots: false,
                nav: false
            });
        },
        error: function (xhr) {
            console.error("AJAX ERROR invoices:", xhr.responseText);
        }
    });

    $(document).ready(function () {
        $('.dashboard-carousel').owlCarousel({
            loop: true,
            margin: 4,
            nav: false,
            dots: false,
            autoplay: true,
            autoplayTimeout: 3000,
            autoplayHoverPause: true,
            smartSpeed: 800,

            responsive: {
                0: {
                    items: 1.2
                },
                600: {
                    items: 2.2
                },
                1000: {
                    items: 3.2
                }
            }
        });
    });
}

/* =============================
🔹 KPI DATA (VENDAS)
============================= */
/* =============================
🔹 SPARKLINES (mini-gráficos dos cards)
============================= */
const sparkInstances = {};

function renderSparkline(canvasId, data, color = "#28a745") {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    if (!Array.isArray(data) || data.length === 0) return;

    if (sparkInstances[canvasId]) {
        sparkInstances[canvasId].destroy();
    }

    sparkInstances[canvasId] = new Chart(canvas.getContext("2d"), {
        type: "line",
        data: {
            labels: data.map((_, i) => i),
            datasets: [{
                data,
                borderColor: color,
                backgroundColor: color + "22",
                fill: true,
                tension: 0.35,
                pointRadius: 0,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            },
            scales: {
                x: {
                    display: false
                },
                y: {
                    display: false
                }
            },
            elements: {
                line: {
                    borderJoinStyle: "round"
                }
            }
        }
    });
}

// Escolhe a cor do sparkline com base no sinal do crescimento
// (mesma leitura usada em setDif): positivo -> verde,
// negativo -> vermelho, neutro/sem dados -> azul padrão.
function sparkColorFromGrowth(value) {
    if (value === null || value === undefined) return "#28a745";

    const numeric = Number(value);

    if (numeric > 0) return "#28a745";
    if (numeric < 0) return "#dc3545";

    return "#28a745";
}

/* =============================
🔹 KPI DATA (VENDAS)
============================= */
function getInsightsNumber() {
    if (!company_id || !user_id) return;

    $.ajax({
        url: `index/ajax/get_sell_insights.php`,
        method: 'GET',
        data: {
            company_id,
            user_id,
            year: selectedYear
        },
        dataType: 'json',

        success: function (res) {
            if (res?.success) renderNumbers(res.data);
        },
        error: function (xhr) {
            console.error("AJAX ERROR KPI:", xhr.responseText);
        }
    });
}

function renderNumbers(data) {
    const kpis = data?.kpis || {};
    const sparks = data?.sparklines || {};

    $("#trimestral_volume").text(formatCurrency(kpis.volume_global));
    $("#month_average").text(formatCurrency(kpis?.media_mensal));
    $("#month_sell").text(formatCurrency(kpis.venda_periodo));
    $("#total_docs").text(formatCurrency(kpis.volume_liquid || 0));
    $("#total_g_month").text(formatCurrency(kpis.volume_liquid_mensal || 0));

    // CORRIGIDO: agora trata null como "sem dados anteriores"
    // em vez de mostrar -100% quando não há histórico real
    setDif("trimestral_volume_dif", kpis.crescimento);
    setDif("month_average_dif", kpis.media_mensal_dif);
    setDif("month_sell_dif", kpis.venda_periodo_growth);
    setDif("total_doc_dif", kpis.crescimento_documentos, {
        noSuffixOnPositive: true,
        suffix: ''
    });

    // NOVO: sparklines dos 4 cards, cor dinâmica conforme
    // a tendência de cada KPI
    renderSparkline(
        "spark1",
        sparks.volume_global,
        sparkColorFromGrowth(kpis.crescimento)
    );

    renderSparkline(
        "spark2",
        sparks.media_mensal,
        sparkColorFromGrowth(kpis.media_mensal_dif)
    );

    renderSparkline(
        "spark3",
        sparks.venda_periodo,
        sparkColorFromGrowth(kpis.venda_periodo_growth)
    );

    renderSparkline(
        "spark4",
        sparks.total_docs,
        sparkColorFromGrowth(kpis.crescimento_documentos)
    );

    renderGraphics(data);
    renderTopClients(data);
}


/* =============================
🔹 CHART
============================= */

function renderGraphics(apiData) {

    currentData = apiData;

    const yearsInvoices = apiData?.yearsInvoices || [];

    const yearSelect = document.getElementById("yearSelect");

    const availableYears = yearsInvoices.map(y => Number(y.ano));

    if (!availableYears.includes(selectedYear)) {
        selectedYear =
            availableYears.includes(new Date().getFullYear()) ?
                new Date().getFullYear() :
                availableYears[0];
    }

    yearSelect.innerHTML = yearsInvoices.map(y => `
        <option
            value="${y.ano}"
            ${Number(y.ano) === selectedYear ? "selected" : ""}
        >
            ${y.ano}
        </option>
    `).join("");

    yearSelect.onchange = () => {

        selectedYear = Number(yearSelect.value);

        updateChart(selectedYear);

        setTimeout(() => {
            loadInvoices();
            getInsightsNumber();
            loadRHData();
            loadStockDashboard();
        }, 1000)
    };

    updateChart(selectedYear);
}

function updateChart(selectedYear) {

    const evolucao = currentData?.evolucao || [];
    const evolucaoAnterior = currentData?.evolucao_anterior || [];

    const nomesMeses = [
        'Jan', 'Fev', 'Mar',
        'Abr', 'Mai', 'Jun',
        'Jul', 'Ago', 'Set',
        'Out', 'Nov', 'Dez'
    ];

    const map = {};

    evolucao.forEach(item => {
        const [year] = item.mes.split("-");
        if (Number(year) === selectedYear) {
            map[item.mes] = Number(item.total || 0);
        }
    });

    // NOVO: mapa do ano anterior, para comparação lado a lado
    const mapAnterior = {};

    evolucaoAnterior.forEach(item => {
        mapAnterior[item.mes] = Number(item.total || 0);
    });

    const canvas = document.getElementById("chart");
    if (!canvas) return;

    const labels = [];
    const valores = [];
    const valoresAnteriores = [];

    for (let mes = 1; mes <= 12; mes++) {

        const mesFormatado =
            `${selectedYear}-${String(mes).padStart(2, '0')}`;

        const mesAnteriorFormatado =
            `${selectedYear - 1}-${String(mes).padStart(2, '0')}`;

        labels.push(nomesMeses[mes - 1]);
        valores.push(map[mesFormatado] || 0);
        valoresAnteriores.push(mapAnterior[mesAnteriorFormatado] || 0);
    }

    if (chartInstance) {
        chartInstance.destroy();
    }

    const ctx = canvas.getContext("2d");

    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "#007abd");
    gradient.addColorStop(1, "#007bbd30");

    chartInstance = new Chart(ctx, {
        type: "bar",

        data: {
            labels,
            datasets: [{
                label: `Receita ${selectedYear}`,
                data: valores,
                backgroundColor: gradient,
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: "#007abd",
                barThickness: 20
            },
            {
                label: `Receita ${selectedYear - 1}`,
                data: valoresAnteriores,
                backgroundColor: "#c3c2b7",
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: "#a3a29c",
                barThickness: 20
            }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: false
                    }
                },

                tooltip: {
                    callbacks: {
                        label: (context) =>
                            `${context.dataset.label}: ${context.raw.toLocaleString()}`
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: "#f1f5f9"
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/* =============================
🔹 TOP CLIENTS
============================= */
function renderTopClients(apiData) {
    const el = document.getElementById("topClients");
    if (!el) return;

    const list = apiData?.top_clients || [];

    if (list.length === 0) {
        el.innerHTML = `<div class="p-3 text-center text-muted small">Sem clientes registados</div>`;
        return;
    }

    el.innerHTML = list.map(c => {
        const nome = c.cliente ?? 'Cliente sem nome';
        const nomeCurto = nome.length > 28 ? nome.slice(0, 28) + '…' : nome;

        return `
        <div class="hover-row d-flex justify-content-between p-2 rounded">
            <div title="${nome}">
                <strong>${nomeCurto}</strong><br>
                <small class="text-muted">${c.total_faturas || 0} faturas</small>
            </div>
            <span class="text-black small">
                ${formatCurrency(c.total_faturado)}
            </span>
        </div>
        `;
    }).join('');
}

/* =============================
🔹 RH MODULE
============================= */
let rhSalaryChartInstance = null;

// Guarda as instâncias dos sparklines
const rhSparkCharts = {};

function renderSparkline(canvasId, values, color) {

    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    // Destrói o gráfico anterior para evitar sobreposição
    if (rhSparkCharts[canvasId]) {
        rhSparkCharts[canvasId].destroy();
    }

    rhSparkCharts[canvasId] = new Chart(canvas, {
        type: "line",
        data: {
            labels: values.map((_, i) => i + 1),
            datasets: [{
                data: values,
                borderColor: color,
                backgroundColor: color + "20",
                fill: true,
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            },
            scales: {
                x: {
                    display: false
                },
                y: {
                    display: false
                }
            }
        }
    });
}

function loadRHData() {

    $.ajax({
        url: "index/ajax/get_hr_insights.php",
        method: "GET",
        data: {
            company_id,
            year: selectedYear
        },
        dataType: "json",

        success: function (res) {

            if (!res.success) return;

            const data = res.data || {};
            const k = data.kpis || {};

            // ================= KPIs =================

            $("#rh_total_employees").text(k.total_employes || 0);
            $("#rh_total_salary").text(formatCurrency(k.total_salary));
            $("#rh_pending_payroll").text(k.pending_payroll || 0);
            $("#rh_paid_month").text(formatCurrency(k.paid_this_month));

            setDif("rh_total_employees_dif", k.increase_employes, {
                noSuffixOnPositive: true
            });

            setDif("rh_total_salary_dif", k.increase_salary);

            setDif("rh_pending_payroll_dif", k.increase_pending, {
                noSuffixOnPositive: true
            });

            setDif("rh_paid_month_dif", k.paid_growth);

            // ================= SPARKLINES =================

            const s = data.sparklines || {};

            renderSparkline(
                "spark5",
                s.total_employes || [],
                "#0d6efd"
            );

            renderSparkline(
                "spark6",
                s.total_salary || [],
                "#198754"
            );

            renderSparkline(
                "spark7",
                s.pending_payroll || [],
                "#ffc107"
            );

            renderSparkline(
                "spark8",
                s.paid_this_month || [],
                "#6f42c1"
            );

            // ================= FOLHAS PENDENTES =================

            const pendingList = data.pending_payroll_list || [];

            if (pendingList.length === 0) {

                $("#rh_pending_payroll_list").html(
                    `<div class="p-3 text-center text-muted small">
                        Sem folhas pendentes
                    </div>`
                );

            } else {

                const html = pendingList.map(p => {

                    const nome = p.name || "Funcionário não identificado";

                    return `
                        <div class="list-item d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar bg-warning-subtle text-warning">
                                    ${getInitials(nome)}
                                </div>

                                <div>
                                    <div class="fw-medium">${nome}</div>
                                    <small class="text-muted">${splitData(p.reference_month)?.mOnth + " de " + splitData(p.reference_month)?.year}</small>
                                </div>
                            </div>

                            <span class="fw-bold">
                                ${formatCurrency(p.net_salary)}
                            </span>
                        </div>
                    `;

                }).join("");

                $("#rh_pending_payroll_list").html(html);
            }

            // ================= ÚLTIMOS PAGAMENTOS =================

            const paymentsList = data.recent_payments || [];
            const absenteeismList = data.absenteeism_list || [];

            console.log(data)

            if (absenteeismList.length === 0) {

                $("#rh_recent_absenteeism_list").html(
                    `<div class="p-3 text-center text-muted small">
                        Sem faltas registados
                    </div>`
                );

            } else {

                const html = absenteeismList.map(a => {

                    const nome = a.name || "Funcionário não identificado";


                    return `
                        <div class="list-item d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar bg-warning-subtle text-warning">
                                    ${getInitials(nome)}
                                </div>

                                <div>
                                    <div class="fw-medium">${nome}</div>
                                    <small class="text-muted">${splitData(a.created_at)?.mOnth + " de " + splitData(a.created_at)?.year}</small>
                                </div>
                            </div>

                            <span class="fw-bold">
                                ${a.total}
                            </span>
                        </div>
                    `;

                }).join("");

                $("#rh_recent_absenteeism_list").html(html);
            }

            // ================= GRÁFICO PRINCIPAL =================

            renderRHSalaryChart(data.salary_evolution || []);
            rhAbsenteeismChart(data.absenteeism_data || []);

        },

        error: function (xhr) {

            console.error("RH AJAX ERROR:");
            console.error(xhr.responseText);

        }

    });

}

/* =============================
🔹 GRÁFICO: custo salarial mensal (RH), filtrado por ano
============================= */
function renderRHSalaryChart(evolution) {
    const canvas = document.getElementById("rhSalaryChart");
    if (!canvas) return;

    const nomesMeses = [
        'Jan', 'Fev', 'Mar',
        'Abr', 'Mai', 'Jun',
        'Jul', 'Ago', 'Set',
        'Out', 'Nov', 'Dez'
    ];

    const map = {};
    evolution.forEach(item => {
        map[item.mes] = Number(item.total || 0);
    });

    const labels = [];
    const valores = [];

    for (let mes = 1; mes <= 12; mes++) {
        const mesFormatado = `${selectedYear}-${String(mes).padStart(2, '0')}`;
        labels.push(nomesMeses[mes - 1]);
        valores.push(map[mesFormatado] || 0);
    }

    if (rhSalaryChartInstance) {
        rhSalaryChartInstance.destroy();
    }

    const ctx = canvas.getContext("2d");

    const gradient = ctx.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, "#0f6e56");
    gradient.addColorStop(1, "#0f6e5630");

    rhSalaryChartInstance = new Chart(ctx, {
        type: "bar",
        data: {
            labels,
            datasets: [{
                label: 'Custo salarial',
                data: valores,
                backgroundColor: gradient,
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 28
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: (context) => `Custo: ${formatCurrency(context.raw)}`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    grid: {
                        color: "#f1f5f9"
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

/* =============================
🔹 GRÁFICO: custo salarial mensal (RH), filtrado por ano
============================= */

function rhAbsenteeismChart(data) {

    const labels = data.map(d => d.status);
    const values = data.map(d => d.total);

    new Chart(document.getElementById("rhAbsenteeismChart"), {
        type: "pie",
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    "#198754",
                    "#007abd",
                    "#ffc107",
                    "#0dcaf0"
                ],
                borderWidth: 0
            }]
        },
        options: {
            cutout: "70%",
            plugins: {
                legend: {
                    position: "bottom"
                }
            }
        }
    });

}

/* ==========================================
STOCK DASHBOARD
========================================== */

function loadStockDashboard() {

    if (!company_id) return;

    $.ajax({
        url: "index/ajax/get_stock_dashboard.php",
        method: "GET",
        data: {
            company_id,
            year: currentYear
        },
        dataType: "json",

        success: function (res) {

            if (!res || !res.success) {
                console.warn("Sem dados de stock");
                return;
            }

            const data = res.data || {};
            const kpis = data.kpis || {};

            document.getElementById("kpi-depots").textContent = kpis.depots ?? 0;
            document.getElementById("kpi-products").textContent = kpis.products ?? 0;
            document.getElementById("kpi-total-value").textContent = formatCurrency(kpis.total_value);
            document.getElementById("kpi-low-stock").textContent = kpis.low_stock ?? 0;

            setDif('kpi-depots-growth', kpis.increase_depots, {
                noSuffixOnPositive: true,
                suffix: ''
            });
            setDif('kpi-products-growth', kpis.increase_products, {
                noSuffixOnPositive: true,
                suffix: ''
            });
            setDif('kpi-total-value-growth', kpis.increase_total_value != null ?
                Number(kpis.increase_total_value).toFixed(2) :
                null
            );

            /* DEPÓSITOS */
            const depositsContainer = document.getElementById("depots_list");

            if (depositsContainer) {
                let html = "";

                (data.depots || []).forEach(dep => {
                    html += `
                            <div class="col-12 col-sm-4">
                                <div class="p-3 border rounded-3 depo-item">
                                    <h6 class="fw-semibold">${dep.name}</h6>
                                    <small class="text-muted">${dep.city ?? ''}</small>

                                    <div class="d-flex justify-content-between mt-2">
                                        <small class="text-muted">${dep.total_items || 0} itens</small>
                                        <span class="fw-bold text-success">
                                            ${formatCurrency(dep.total_value)}
                                        </span>
                                    </div>

                                    <a href="stock-depot.php?depot_id=${dep.id}" class="stretched-link">
                                        <small class="text-primary d-block mt-2">
                                            Ver detalhes <i class="bi bi-chevron-right"></i>
                                        </small>
                                    </a>
                                </div>
                            </div>`;
                });

                depositsContainer.innerHTML = html || `<small class="text-muted">Sem depósitos</small>`;
            }

            /* STOCK BAIXO */
            const lowStockContainer = document.getElementById("low_stock_list");

            if (lowStockContainer) {
                let html = "";

                (data.low_stock || []).forEach(item => {
                    html += `
                            <div class="list-item">
                                <div>
                                    <div class="fw-medium">${item.name}</div>
                                    <small class="text-muted">
                                        ${item.quantity}/${item.min_quantity}
                                    </small>
                                </div>
                                <button class="btn btn-sm text-primary">
                                    <i class="bi bi-plus"></i> Lista
                                </button>
                            </div>`;
                });

                lowStockContainer.innerHTML = html || `<small class="text-muted">Sem alertas</small>`;
            }

            /* COMPRAS (SUGESTÃO) */
            const purchaseContainer = document.getElementById("purchase_list");

            if (purchaseContainer) {
                let html = "";

                (data.purchases || []).forEach(p => {
                    html += `
                            <div class="list-item">
                                <div>
                                    <div class="fw-medium">${p.name}</div>
                                    <small class="text-muted">
                                        Sugerido: ${p.suggested_qty}
                                    </small>
                                </div>
                                <small class="text-muted">Auto</small>
                            </div>`;
                });

                purchaseContainer.innerHTML = html || `<small class="text-muted">Sem sugestões</small>`;
            }
        },

        error: function (xhr) {
            console.error("Erro STOCK AJAX:", xhr.responseText);
        }
    });
}

/* =============================
🔹 INIT
============================= */
const REFRESH_INTERVAL = 30000;

let dashboardTimer = null;
let isRefreshing = false;

async function refreshDashboard() {
    if (isRefreshing) return;

    try {
        isRefreshing = true;

        await Promise.all([
            getInsightsNumber(),
            loadInvoices(),
            loadRHData(),
            loadStockDashboard()
        ]);

    } catch (error) {
        console.error("Erro ao atualizar dashboard:", error);
    } finally {
        isRefreshing = false;
    }
}

function startDashboardRefresh() {

    refreshDashboard();

    dashboardTimer = setInterval(() => {

        if (document.hidden) return;

        refreshDashboard();

    }, REFRESH_INTERVAL);
}

function stopDashboardRefresh() {

    if (dashboardTimer) {
        clearInterval(dashboardTimer);
        dashboardTimer = null;
    }
}

$(document).ready(() => {

    startDashboardRefresh();

    document.addEventListener("visibilitychange", () => {

        if (document.hidden) {
            stopDashboardRefresh();
        } else {
            startDashboardRefresh();
        }

    });
});
