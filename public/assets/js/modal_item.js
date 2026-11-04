document.addEventListener("DOMContentLoaded", () => {
  // =========================
  // ELEMENTOS
  // =========================
  const financeBlock = document.getElementById("financeBlock");
  const categorySelect = document.getElementById("category");
  const subcategorySelect = document.getElementById("subcategory");
  const codigoInput = document.getElementById("codigo");

  // CAMPOS FISCAIS
  const taxField = document.getElementById("taxVat");

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
    if (categorySelect.value !== "product" && codigoInput) codigoInput.value = "";
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
    if (!taxField) return;

    const subcategory = subcategorySelect?.value || "";

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

    pvpInput.value = subtotal.toFixed(2);
  }

  // =========================
  // GERAR CÓDIGO
  // =========================
  async function generateCode() {
    try {
      const itemType = categorySelect.value;

      const res = await fetch(
        `items/ajax/generate_code.php?stock_id=0&item_type=${encodeURIComponent(itemType)}`,
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
        if (response.status !== "success") {
          Swal.fire({
            icon: "error",
            title: "Não foi possível guardar",
            text: response.message || "O servidor não conseguiu guardar o produto/serviço.",
          });
          return;
        }

        Swal.fire({
          icon: "success",
          title: "Guardado",
          text: response.message || "Produto/serviço guardado com sucesso.",
        });

        const modalEl = document.getElementById("itemModal");
        const modal = bootstrap.Modal.getInstance(modalEl);

        if (modal) {
          modal.hide();
        }

        $("#itemForm")[0].reset();

        $(document).trigger("items:reload");
      },
      error: function (xhr) {
        const message = xhr.responseJSON?.message
          || "Ocorreu um erro ao salvar o produto/serviço.";
        Swal.fire(
          "Erro!",
          message,
          "error",
        );
      },
    });
  });

});