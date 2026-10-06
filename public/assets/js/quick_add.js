/**
 * ==========================================================================
 * CADASTRO RÁPIDO DE ITENS
 * ==========================================================================
 * Controla o painel inicial do #itemModal:
 *  - lista categorias e itens pré-configurados (PRESET_ITEMS)
 *  - ao escolher um item, pré-preenche o #itemForm e mostra o formulário
 *    completo (usando os próprios eventos "change" já existentes em
 *    modal_item.js, para reaproveitar toda a lógica fiscal/UI já criada)
 *  - permite criar um item personalizado quando não existe na lista
 *
 * Depende de: preset_items.js (deve ser carregado ANTES deste ficheiro)
 * ==========================================================================
 */

document.addEventListener("DOMContentLoaded", () => {
  // =========================
  // ELEMENTOS
  // =========================
  const modalEl = document.getElementById("itemModal");
  const quickPanel = document.getElementById("quickAddPanel");
  const fullFormWrapper = document.getElementById("fullFormWrapper");
  const footer = document.getElementById("itemModalFooter");

  const quickSearch = document.getElementById("quickSearch");
  const quickCategoriesEl = document.getElementById("quickCategories");
  const quickItemsListEl = document.getElementById("quickItemsList");
  const btnCustomItem = document.getElementById("btnCustomItem");
  const btnBackToQuick = document.getElementById("btnBackToQuick");

  const itemForm = document.getElementById("itemForm");
  const itemIdField = document.getElementById("item_id");
  const categorySelect = document.getElementById("category");
  const subcategorySelect = document.getElementById("subcategory");
  const nameInput = document.getElementById("name");
  const descricaoInput = document.getElementById("descricao");
  const unitMeasureSelect = document.querySelector("[name='unit_measure']");
  const unitPriceInput = document.querySelector("[name='unit_price']");

  // Se o modal/painel não existir nesta página, não faz nada.
  if (!quickPanel || !fullFormWrapper || !itemForm) return;

  if (
    typeof PRESET_ITEMS === "undefined" ||
    typeof PRESET_CATEGORIES === "undefined"
  ) {
    console.error("preset_items.js não foi carregado antes de quick_add.js.");
    return;
  }

  let activeCategory = "all";

  // =========================
  // LABELS AUXILIARES
  // =========================
  const ITEM_TYPE_LABELS = {
    product: "Produto",
    service: "Serviço",
    consumable: "Consumível",
    raw_material: "Matéria-prima",
    finished_good: "Produto final",
  };

  const UNIT_LABELS = {
    unit: "Unidade",
    kg: "Kg",
    liter: "Litro",
    meter: "Metro",
    service: "Serviço",
  };

  // =========================
  // NAVEGAÇÃO ENTRE PAINÉIS
  // =========================
  function showQuickPanel() {
    quickPanel.style.display = "block";
    fullFormWrapper.style.display = "none";
    if (footer) footer.style.display = "none";
  }

  function showFullForm() {
    quickPanel.style.display = "none";
    fullFormWrapper.style.display = "block";
    if (footer) footer.style.display = "flex";
  }

  function fireChange(el) {
    if (!el) return;
    el.dispatchEvent(new Event("change", { bubbles: true }));
  }

  // =========================
  // RENDER: CATEGORIAS
  // =========================
  function renderCategories() {
    const chips = [{ id: "all", label: "Todos" }, ...PRESET_CATEGORIES];

    quickCategoriesEl.innerHTML = chips
      .map(
        (cat) => `
        <button type="button"
          class="btn btn-sm ${activeCategory === cat.id ? "btn-primary" : "btn-outline-secondary"} rounded-pill quick-cat-btn"
          data-cat="${cat.id}">
          ${cat.icon ? `<i class="${cat.icon} me-1"></i>` : ""}${cat.label}
        </button>`,
      )
      .join("");

    quickCategoriesEl.querySelectorAll(".quick-cat-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        activeCategory = btn.dataset.cat;
        renderCategories();
        renderItems();
      });
    });
  }

  // =========================
  // RENDER: LISTA DE ITENS
  // =========================
  function renderItems() {
    const term = (quickSearch?.value || "").trim().toLowerCase();

    const filtered = PRESET_ITEMS.filter((item) => {
      const matchesCategory =
        activeCategory === "all" || item.category === activeCategory;
      const matchesSearch = !term || item.name.toLowerCase().includes(term);
      return matchesCategory && matchesSearch;
    });

    if (!filtered.length) {
      quickItemsListEl.innerHTML = `
        <div class="col-12 text-center text-muted py-3">
          Nenhum item pré-configurado encontrado.
        </div>`;
      return;
    }

    quickItemsListEl.innerHTML = filtered
      .map(
        (item) => `
        <div class="col-md-6">
          <button type="button"
            class="btn w-100 text-start soft-card quick-item-btn d-flex justify-content-between align-items-center"
            data-id="${item.id}">
            <span>
              <strong>${item.name}</strong><br>
              <small class="text-muted">${ITEM_TYPE_LABELS[item.item_type] || item.item_type} · ${UNIT_LABELS[item.unit_measure] || item.unit_measure}</small>
            </span>
            <i class="bi bi-plus-circle text-primary fs-5"></i>
          </button>
        </div>`,
      )
      .join("");

    quickItemsListEl.querySelectorAll(".quick-item-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const preset = PRESET_ITEMS.find((i) => i.id === btn.dataset.id);
        if (preset) applyPreset(preset);
      });
    });
  }

  // =========================
  // APLICAR PRESET AO FORMULÁRIO
  // =========================
  function applyPreset(preset) {
    itemForm.reset();
    if (itemIdField) itemIdField.value = "0";

    if (nameInput) nameInput.value = preset.name;
    if (descricaoInput) descricaoInput.value = preset.description || "";

    if (categorySelect) {
      categorySelect.value = preset.item_type;
      fireChange(categorySelect); // aciona toggleUI/toggleFinance/generateCode em modal_item.js
    }

    if (subcategorySelect) {
      subcategorySelect.value = preset.subcategory || "";
      fireChange(subcategorySelect); // aciona updateFiscal (recalcula IVA) em modal_item.js
    }

    if (unitMeasureSelect) {
      unitMeasureSelect.value = preset.unit_measure || "unit";
    }

    showFullForm();

    setTimeout(() => unitPriceInput?.focus(), 150);
  }

  // =========================
  // CRIAR ITEM PERSONALIZADO
  // (item não existe na lista pré-configurada)
  // =========================
  btnCustomItem?.addEventListener("click", () => {
    itemForm.reset();
    if (itemIdField) itemIdField.value = "0";
    fireChange(categorySelect);

    showFullForm();
    setTimeout(() => nameInput?.focus(), 150);
  });

  // =========================
  // VOLTAR AO CADASTRO RÁPIDO
  // =========================
  btnBackToQuick?.addEventListener("click", () => {
    showQuickPanel();
  });

  // =========================
  // PESQUISA
  // =========================
  quickSearch?.addEventListener("input", renderItems);

  // =========================
  // RESET AO ABRIR O MODAL
  // Só mostra o cadastro rápido quando é um item NOVO (item_id = 0).
  // Se o modal for reaproveitado para edição, vai direto ao formulário.
  // =========================
  modalEl?.addEventListener("show.bs.modal", () => {
    const isNewItem =
      !itemIdField || itemIdField.value === "0" || itemIdField.value === "";

    if (isNewItem) {
      activeCategory = "all";
      if (quickSearch) quickSearch.value = "";
      renderCategories();
      renderItems();
      showQuickPanel();
    } else {
      showFullForm();
    }
  });

  // Render inicial
  renderCategories();
  renderItems();
});
