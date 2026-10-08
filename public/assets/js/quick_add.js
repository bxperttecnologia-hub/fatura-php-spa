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
  if (window.__quickAddInitialized) return;

  // =========================
  // ELEMENTOS
  // =========================
  const modalEl = document.getElementById("itemModal");
  const quickPanel = document.getElementById("quickAddPanel");
  const fullFormWrapper = document.getElementById("fullFormWrapper");
  const footer = document.getElementById("itemModalFooter");

  const quickSearch = document.getElementById("quickSearch");
  const quickCategoriesEl = document.getElementById("quickCategories");
  const categoryToggle = document.getElementById("quickCategoryToggle");
  const quickItemsListEl = document.getElementById("quickItemsList");
  const btnCustomItem = document.getElementById("btnCustomItem");
  const btnBackToQuick = document.getElementById("btnBackToQuick");
  const quickFooter = document.getElementById("itemQuickFooter");

  const itemForm = document.getElementById("itemForm");
  const itemIdField = document.getElementById("item_id");
  const categorySelect = document.getElementById("category");
  const subcategorySelect = document.getElementById("subcategory");
  const nameInput = document.getElementById("name");
  const descricaoInput = document.getElementById("descricao");
  const unitMeasureSelect = document.querySelector("[name='unit_measure']");
  const unitPriceInput = document.querySelector("[name='unit_price']");
  const searchBox = quickSearch?.closest(".selector-search");
  const heading = document.getElementById("quickResultsHeading");

  // Se o modal/painel não existir nesta página, não faz nada.
  if (!quickPanel || !fullFormWrapper || !itemForm) return;
  if (document.getElementById("item_select")) return;

  if (
    typeof PRESET_ITEMS === "undefined" ||
    typeof PRESET_CATEGORIES === "undefined"
  ) {
    console.error("preset_items.js não foi carregado antes de quick_add.js.");
    return;
  }

  window.__quickAddInitialized = true;
  let activeCategory = "all";
  let activeResult = -1;
  let searchTimer = null;

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
    quickPanel.hidden = false;
    if (searchBox) searchBox.hidden = false;
    if (quickCategoriesEl) quickCategoriesEl.hidden = true;
    if (categoryToggle) {
      categoryToggle.hidden = false;
      categoryToggle.setAttribute("aria-expanded", "false");
    }
    if (heading) heading.hidden = false;
    if (quickItemsListEl) quickItemsListEl.hidden = false;
    fullFormWrapper.style.display = "none";
    if (footer) footer.style.display = "none";
    if (quickFooter) quickFooter.hidden = false;
  }

  function showFullForm() {
    quickPanel.style.display = "none";
    fullFormWrapper.style.display = "block";
    if (footer) footer.style.display = "flex";
    if (quickFooter) quickFooter.hidden = true;
  }

  function fireChange(el) {
    if (!el) return;
    el.dispatchEvent(new Event("change", { bubbles: true }));
  }

  // =========================
  // RENDER: CATEGORIAS
  // =========================
  function renderCategories() {
    if (!quickCategoriesEl) return;
    const chips = [{ id: "all", label: "Todas as categorias" }, ...PRESET_CATEGORIES];
    quickCategoriesEl.replaceChildren(...chips.map((cat) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "selector-category" + (activeCategory === cat.id ? " active" : "");
      button.dataset.cat = cat.id;
      button.textContent = cat.label;
      button.setAttribute("aria-pressed", String(activeCategory === cat.id));
      return button;
    }));

    quickCategoriesEl.querySelectorAll(".selector-category").forEach((btn) => {
      btn.addEventListener("click", () => {
      if (document.getElementById("item_select")) return;
      activeCategory = btn.dataset.cat;
        if (categoryToggle) {
          categoryToggle.innerHTML = activeCategory === "all"
            ? 'Categoria <span aria-hidden="true">▾</span>'
            : `Categoria · ${btn.textContent} <span aria-hidden="true">▾</span>`;
          categoryToggle.setAttribute("aria-expanded", "false");
        }
        quickCategoriesEl.hidden = true;
        renderCategories();
        renderItems();
      });
    });
  }

  // =========================
  // RENDER: LISTA DE ITENS
  // =========================
  function renderItems() {
    const term = (quickSearch?.value || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

    const filtered = PRESET_ITEMS.filter((item) => {
      const matchesCategory =
        activeCategory === "all" || item.category === activeCategory;
      const matchesSearch = !term || `${item.name} ${item.description || ""}`.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().includes(term);
      return matchesCategory && matchesSearch;
    });
    activeResult = -1;
    if (heading) heading.textContent = term ? `Resultados para “${quickSearch.value.trim()}”` : "Itens sugeridos";
    if (btnCustomItem) btnCustomItem.querySelector("span").textContent =
      term ? `Criar “${quickSearch.value.trim()}”` : "Criar produto ou serviço";

    if (!filtered.length) {
      quickItemsListEl.innerHTML = '<div class="selector-status" role="status">Nenhum produto ou serviço encontrado.</div>';
      return;
    }

    const visible = !term && activeCategory === "all" ? filtered.slice(0, 8) : filtered;
    quickItemsListEl.replaceChildren(...visible.map((item, index) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "selector-result";
      button.dataset.id = item.id;
      button.dataset.resultIndex = String(index);
      button.setAttribute("role", "option");
      button.setAttribute("aria-selected", "false");
      button.setAttribute("aria-label", `Adicionar ${item.name}`);

      const text = document.createElement("span");
      text.className = "min-w-0";
      const name = document.createElement("span");
      name.className = "selector-result-name d-block";
      name.textContent = item.name;
      const meta = document.createElement("span");
      meta.className = "selector-result-meta";
      meta.textContent = `${ITEM_TYPE_LABELS[item.item_type] || item.item_type} · ${UNIT_LABELS[item.unit_measure] || item.unit_measure}`;
      text.append(name, meta);

      const plus = document.createElement("span");
      plus.className = "selector-result-plus";
      plus.setAttribute("aria-hidden", "true");
      plus.textContent = "+";
      button.append(text, plus);
      button.addEventListener("click", () => applyPreset(item));
      return button;
    }));
    if (!term && activeCategory === "all" && filtered.length > visible.length) {
      const hint = document.createElement("div");
      hint.className = "selector-status";
      hint.textContent = "Pesquise para encontrar outros produtos e serviços.";
      quickItemsListEl.append(hint);
    }

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
    if (document.getElementById("item_select")) return;
    const query = quickSearch?.value.trim() || "";
    itemForm.reset();
    if (itemIdField) itemIdField.value = "0";
    fireChange(categorySelect);
    if (nameInput) nameInput.value = query;

    showFullForm();
    const title = document.getElementById("itemModalLabel");
    if (title) title.textContent = "Criar produto ou serviço";
    const subtitle = document.getElementById("itemModalSubtitle");
    if (subtitle) subtitle.textContent = "Preencha os dados do produto ou serviço.";
    setTimeout(() => nameInput?.focus(), 150);
  });

  // =========================
  // VOLTAR AO CADASTRO RÁPIDO
  // =========================
  btnBackToQuick?.addEventListener("click", () => {
    if (document.getElementById("item_select")) return;
    const title = document.getElementById("itemModalLabel");
    if (title) title.textContent = "Adicionar Novo Produto/Serviço";
    const subtitle = document.getElementById("itemModalSubtitle");
    if (subtitle) subtitle.textContent = "Pesquise e escolha um item.";
    showQuickPanel();
    setTimeout(() => quickSearch?.focus(), 0);
  });

  // =========================
  // PESQUISA
  // =========================
  quickSearch?.addEventListener("input", () => {
    if (document.getElementById("item_select")) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(renderItems, 120);
  });

  quickSearch?.addEventListener("keydown", (event) => {
    if (document.getElementById("item_select")) return;
    const options = Array.from(quickItemsListEl.querySelectorAll(".selector-result"));
    if (!options.length || !["ArrowDown", "ArrowUp", "Enter"].includes(event.key)) return;
    event.preventDefault();
    if (event.key === "Enter") {
      const selected = activeResult >= 0 ? options[activeResult] : options[0];
      selected?.click();
      return;
    }
    activeResult = event.key === "ArrowDown"
      ? (activeResult + 1) % options.length
      : (activeResult <= 0 ? options.length - 1 : activeResult - 1);
    options.forEach((option, index) => option.setAttribute("aria-selected", String(index === activeResult)));
    options[activeResult].focus();
  });

  quickItemsListEl?.addEventListener("keydown", (event) => {
    if (document.getElementById("item_select")) return;
    const options = Array.from(quickItemsListEl.querySelectorAll(".selector-result"));
    if (event.key === "Enter" && event.target.matches(".selector-result")) {
      event.preventDefault();
      event.target.click();
      return;
    }
    if (!options.length || !["ArrowDown", "ArrowUp"].includes(event.key)) return;
    event.preventDefault();
    const current = options.indexOf(document.activeElement);
    activeResult = event.key === "ArrowDown"
      ? (current + 1) % options.length
      : (current <= 0 ? options.length - 1 : current - 1);
    options.forEach((option, index) => option.setAttribute("aria-selected", String(index === activeResult)));
    options[activeResult].focus();
  });

  categoryToggle?.addEventListener("click", () => {
    if (document.getElementById("item_select")) return;
    const expanded = categoryToggle.getAttribute("aria-expanded") === "true";
    categoryToggle.setAttribute("aria-expanded", String(!expanded));
    quickCategoriesEl.hidden = expanded;
    if (!expanded) quickCategoriesEl.querySelector(".selector-category.active")?.focus();
  });

  modalEl?.addEventListener("hidden.bs.modal", () => {
    clearTimeout(searchTimer);
    activeCategory = "all";
    if (quickSearch) quickSearch.value = "";
    if (categoryToggle) {
      categoryToggle.setAttribute("aria-expanded", "false");
      categoryToggle.innerHTML = 'Categoria <span aria-hidden="true">▾</span>';
    }
    const title = document.getElementById("itemModalLabel");
    if (title) title.textContent = "Adicionar Novo Produto/Serviço";
    const subtitle = document.getElementById("itemModalSubtitle");
    if (subtitle) subtitle.textContent = "Pesquise e escolha um item.";
    if (quickFooter) quickFooter.hidden = true;
  });

  // =========================
  // RESET AO ABRIR O MODAL
  // Só mostra o cadastro rápido quando é um item NOVO (item_id = 0).
  // Se o modal for reaproveitado para edição, vai direto ao formulário.
  // =========================
  modalEl?.addEventListener("show.bs.modal", () => {
    if (document.getElementById("item_select")) return;
    if (document.getElementById("item_select")) return;
    const isNewItem =
      !itemIdField || itemIdField.value === "0" || itemIdField.value === "";

    if (isNewItem) {
      activeCategory = "all";
      if (quickSearch) quickSearch.value = "";
      renderCategories();
      renderItems();
      showQuickPanel();
      setTimeout(() => {
        if (modalEl.classList.contains("show") && !quickPanel.hidden) quickSearch?.focus();
      }, 350);
    } else {
      showFullForm();
    }
  });

  modalEl?.addEventListener("shown.bs.modal", () => {
    if (document.getElementById("item_select")) return;
    if (!quickPanel.hidden && quickPanel.style.display !== "none") quickSearch?.focus();
  });

  // Render inicial
  renderCategories();
  renderItems();
});
