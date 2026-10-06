document.addEventListener("DOMContentLoaded", () => {
  // =========================
  // ELEMENTOS
  // =========================
  const financeBlock = document.getElementById("financeBlock");
  const categorySelect = document.getElementById("category");
  const subcategorySelect = document.getElementById("subcategory");
  const depotCard = document.getElementById("depot");
  const codigoInput = document.getElementById("codigo");
  const stockSelect = document.getElementById("stock_id");

  // CAMPOS FISCAIS
  const taxField = document.getElementById("taxVat");

  const retentionField =
    document.getElementById("retention_tax") ||
    document.getElementById("retention");

  // PREÇOS
  const unitPriceInput = document.querySelector("[name='unit_price']");
  const costPriceInput = document.querySelector("[name='cost_price']");
  const salePriceInput = document.querySelector("[name='sale_price']");
  const pvpInput = document.querySelector("[name='pvp']");

  let ivaRegime = "geral";

  if (!categorySelect || !codigoInput) {
    console.error("Elementos essenciais não encontrados.");
    return;
  }

  // =========================
  // UI TOGGLE
  // =========================
  function toggleUI() {
    const isProduct = categorySelect.value === "product";

    if (depotCard) {
      depotCard.classList.toggle("active", isProduct);
    }

    // Gestão de stock é opcional: o usuário pode criar o item agora
    // e associá-lo a um depósito/stock mais tarde.
    if (stockSelect) {
      stockSelect.required = false;
    }

    if (!isProduct) {
      if (codigoInput) codigoInput.value = "";
      if (stockSelect) stockSelect.value = "";
    }
  }

  // =========================
  // BLOCO FINANCEIRO
  // =========================
  function toggleFinance() {
    if (!financeBlock) return;

    const hide = ["product", "service"].includes(categorySelect.value);

    financeBlock.style.display = hide ? "none" : "block";

    if (hide) {
      if (costPriceInput) costPriceInput.value = "";
      if (salePriceInput) salePriceInput.value = "";
      if (pvpInput) pvpInput.value = "";
    }
  }

  // =========================
  // CARREGAR EMPRESA
  // =========================
  async function loadCompany() {
    try {
      const companyId = document.querySelector(
        "input[name='id_company']",
      )?.value;

      if (!companyId) return;

      const res = await fetch(
        `assets/ajax/get_company.php?id=${encodeURIComponent(companyId)}`,
      );

      const data = await res.json();

      if (data?.success) {
        ivaRegime = data.data?.vat_regime || "geral";

        updateFiscal();
        calculatePrice();
      }
    } catch (error) {
      console.error("Erro ao carregar empresa:", error);
    }
  }

  // =========================
  // FISCAL
  // =========================
  function updateFiscal() {
    if (!taxField || !retentionField) return;

    const category = categorySelect.value;
    const subcategory = subcategorySelect?.value || "";

    const unitPrice = parseFloat(unitPriceInput?.value) || 0;

    let taxVal = 0;

    // =========================
    // IVA
    // =========================

    // Produtos essenciais/agricultura
    if (subcategory === "essential" || subcategory === "agriculture") {
      taxVal = 5;
    } else {
      switch (ivaRegime) {
        case "geral":
          taxVal = 14;
          break;

        case "simplificado":
          taxVal = 7;
          break;

        case "exempt":
        case "isento":
          taxVal = 0;
          break;

        default:
          taxVal = 14;
      }
    }

    taxField.value = taxVal;

    // =========================
    // RETENÇÃO
    // =========================
    let retentionVal = 0;

    // Serviços acima de 20.000
    if (category === "service" && unitPrice >= 20000) {
      retentionVal = 6.5;
    }

    retentionField.value = retentionVal;

    // RECALCULAR PVP
    calculatePrice();
  }

  // =========================
  // CALCULAR PREÇO FINAL
  // =========================
  function calculatePrice() {
    if (!pvpInput) return;

    const unitPrice = parseFloat(unitPriceInput?.value) || 0;
    const costPrice = parseFloat(costPriceInput?.value) || 0;
    const salePrice = parseFloat(salePriceInput?.value) || 0;

    const tax = parseFloat(taxField?.value) || 0;
    const retention = parseFloat(retentionField?.value) || 0;

    // BASE
    let base = 0;

    if (salePrice > 0) {
      base = costPrice + salePrice;
    } else {
      base = costPrice + unitPrice;
    }

    // IVA
    const ivaAmount = (base * tax) / 100;

    // SUBTOTAL
    const subtotal = base + ivaAmount;

    // RETENÇÃO
    const retentionAmount = (subtotal * retention) / 100;

    // TOTAL FINAL
    const total = subtotal - retentionAmount;

    pvpInput.value = total.toFixed(2);
  }

  // =========================
  // GERAR CÓDIGO
  // =========================
  async function generateCode() {
    try {
      const stockId = stockSelect?.value || "";
      const itemType = categorySelect.value;

      const res = await fetch(
        `items/ajax/generate_code.php?stock_id=${encodeURIComponent(
          stockId,
        )}&item_type=${encodeURIComponent(itemType)}`,
      );

      const data = await res.json();

      if (data?.success && data?.generated_code) {
        codigoInput.value = data.generated_code;
      }
    } catch (err) {
      console.error("Erro ao gerar código:", err);
    }
  }

  // =========================
  // VALIDAR CÓDIGO
  // =========================
  async function validateCode() {
    try {
      const codigo = codigoInput.value.trim();

      if (!codigo) return;

      const formData = new FormData();
      formData.append("codigo", codigo);

      const res = await fetch("items/ajax/check_code.php", {
        method: "POST",
        body: formData,
      });

      if (!res.ok) {
        throw new Error(`Erro HTTP: ${res.status}`);
      }

      const data = await res.json();

      if (data && data.exists) {
        alert("Este código já existe.");

        codigoInput.value = "";
        codigoInput.focus();
      }
    } catch (err) {
      console.error("Erro ao validar código:", err);
    }
  }

  // =========================
  // CARREGAR STOCKS
  // =========================
  async function fetchStocks() {
    if (!stockSelect) return;

    try {
      const res = await fetch("index/ajax/fetch_stocks.php");

      const data = await res.json();

      if (Array.isArray(data?.data)) {
        stockSelect.innerHTML =
          `<option value="">Selecione um stock</option>` +
          data.data
            .map(
              (stock) => `<option value="${stock.id}">${stock.name}</option>`,
            )
            .join("");
      }
    } catch (err) {
      console.error("Erro ao carregar stocks:", err);
    }
  }

  // =========================
  // SINCRONIZAÇÃO
  // =========================
  function syncUI() {
    toggleUI();
    toggleFinance();
    updateFiscal();
    generateCode();
  }

  // =========================
  // PROTEÇÃO IVA
  // =========================
  if (taxField) {
    ["keydown", "paste", "drop"].forEach((evt) => {
      taxField.addEventListener(evt, (e) => {
        e.preventDefault();
      });
    });
  }

  // =========================
  // EVENTS
  // =========================

  // Categoria
  categorySelect.addEventListener("change", syncUI);

  // Subcategoria
  if (subcategorySelect) {
    subcategorySelect.addEventListener("change", () => {
      updateFiscal();
    });
  }

  // Stock
  if (stockSelect) {
    stockSelect.addEventListener("change", generateCode);
  }

  // Código
  if (codigoInput) {
    codigoInput.addEventListener("blur", validateCode);
  }

  // Inputs de preço
  [unitPriceInput, costPriceInput, salePriceInput].forEach((input) => {
    if (!input) return;

    input.addEventListener("input", () => {
      updateFiscal();
      calculatePrice();
    });
  });

  // =========================
  // INIT
  // =========================
  syncUI();
  fetchStocks();
  loadCompany();
});

$(document).ready(function () {
  $("#saveItem").click(function (e) {
    e.preventDefault();

    const form = $("#itemForm")[0];
    const formData = new FormData(form);

    // =========================
    // ID
    // =========================
    const itemId =
      $("#item_id").val() || $("#product_item").val() || $("#id").val();

    if (itemId) {
      formData.append("id", itemId);
    }

    // =========================
    // TAX
    // =========================
    formData.append("tax_vat", $("#taxVat").val());

    for (let pair of formData.entries()) {
      console.log(pair[0], pair[1]);
    }

    console.log("tax:", formData.get("tax_vat"));

    // =========================
    // URL
    // =========================
    const url = "items/ajax/save_item.php";

    $.ajax({
      url: url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: "json",
      success: function (response) {
        Swal.fire({
          icon: response.type || "info",
          title: response.status || "",
          text: response.message || "",
        });

        const modalEl = document.getElementById("itemModal");
        const modal = bootstrap.Modal.getInstance(modalEl);

        if (modal) {
          modal.hide();
        }

        $("#itemForm")[0].reset();

        loadItems();
      },
      error: function () {
        Swal.fire(
          "Erro!",
          "Ocorreu um erro ao salvar o produto/serviço.",
          "error",
        );
      },
    });
  });

  const loadItems = () => {
    $.ajax({
      url: "items/ajax/get_items.php",
      method: "GET",
      dataType: "json",

      success: function (response) {
        console.log("RESPONSE:", response?.data);

        // garante array correto
        if (Array.isArray(response?.data)) {
          items = response?.data;
        } else if (Array.isArray(response)) {
          items = response;
        } else {
          items = [];
        }

        renderTable(items);
      },

      error: function (xhr, status, error) {
        console.error("Erro:", error);
      },
    });
  };

  function renderTable(data) {
    const $table = $("#itemsTable");
    const $tbody = $table.find("tbody");

    // destruir DataTable antes de mexer no DOM
    if ($.fn.DataTable.isDataTable($table)) {
      $table.DataTable().clear().destroy();
    }

    $tbody.empty();

    // =========================
    // SEM DADOS
    // =========================
    if (!data || !data.length) {
      $tbody.append(`
      <tr>
        <td class="text-center text-muted py-4">
          Nenhum produto ou serviço encontrado
        </td>
      </tr>
    `);
      return;
    }

    // =========================
    // BUILD ROWS (mais performático)
    // =========================
    let rowsHtml = "";

    data.forEach((row) => {
      const rowData = encodeURIComponent(JSON.stringify(row));

      const description = row.name || row.description || "-";

      const price = row.unit_price || row.cost_price;

      const tax = row.tax || 0;

      rowsHtml += `
      <tr data-id="${row.id}">
        
        <!-- CHECKBOX -->
        <td>
          <input type="checkbox" class="item-checkbox" value="${row.id}">
        </td>

        <!-- ICON -->
        <td>
          <i class="${
            row.item_type === "service"
              ? "bi bi-tag fw-bold fs-5"
              : "bi bi-box-seam fw-bold fs-5"
          }"></i>
        </td>

        <!-- CODE -->
        <td>${row.code || "-"}</td>

        <!-- NAME -->
        <td class="text-truncate-custom" title="${description}">
          ${description}
        </td>

        <!-- DESCRIPTION -->
        <td class="text-truncate-custom" title="${row.description || ""}">
          ${row.description || "-"}
        </td>

        <!-- PRICE -->
        <td class="text-success fw-bold">
          ${formatCurrency(price, row.currency, row.position)}
        </td>

        <!-- TAX -->
        <td>
          ${`${tax}%`}
        </td>

        <!-- PVP -->
        <td class="text-primary fw-bold">
          ${formatCurrency(row.pvp ?? 0, row.currency, row.position)}
        </td>

        <!-- ACTIONS -->
        <td>
          <div class="d-flex justify-content-center gap-2">

            <button class="btn btn-sm text-dark edit-btn"
              data-row="${rowData}"
              data-bs-toggle="tooltip"
              title="Editar">
              <i class="bi bi-pencil fs-6"></i>
            </button>

            <button class="btn btn-sm text-danger delete-btn"
              data-id="${row.id}"
              data-bs-toggle="tooltip"
              title="Excluir">
              <i class="bi bi-trash fs-6"></i>
            </button>

          </div>
        </td>

      </tr>
    `;
    });

    $tbody.html(rowsHtml);

    // =========================
    // REINICIAR DATATABLE
    // =========================
    $table.DataTable({
      pageLength: 25,
      lengthMenu: [10, 25, 50, 100],
      destroy: true,
      autoWidth: false,

      columnDefs: [
        { orderable: false, targets: [0, 1, 8] }, // checkbox, icon, actions
      ],

      language: {
        search: "",
        searchPlaceholder: "Pesquisar produtos...",
        lengthMenu: "Mostrar _MENU_",
        zeroRecords: "Nenhum registro encontrado",
        info: "_START_–_END_ de _TOTAL_",
        infoEmpty: "Sem dados",
        infoFiltered: "(filtrado de _MAX_)",
        paginate: {
          first: "«",
          last: "»",
          next: "›",
          previous: "‹",
        },
      },
    });

    // =========================
    // TOOLTIP (fix duplicação)
    // =========================
    $('[data-bs-toggle="tooltip"]').tooltip("dispose");
    $('[data-bs-toggle="tooltip"]').tooltip();
  }
});