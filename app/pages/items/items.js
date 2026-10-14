$(document).ready(function () {
  const $page = $("#itemsPage");
  if (!$page.length) return;

  $page.off(".itemsPage");

  let items = []; // todos os itens vindos do servidor
  let filteredItems = []; // itens após pesquisa/ordenação
  const selectedIds = new Set();
  let currentPage = 1;
  let pageSize = 25;
  let sortKey = null;
  let sortDir = "asc";
  let loadState = "loading";
  let itemsRequest = null;

  const modalEl = document.getElementById("itemModal");
  const modalTitle = document.querySelector("#itemModal .modal-title");
  const defaultTitle = "Adicionar Novo Produto/Serviço";

  renderTable();
  loadItems();
  $(document).off("items:reload.spaPage").on("items:reload.spaPage", loadItems);

  function loadItems() {
    if (itemsRequest) itemsRequest.abort();

    loadState = "loading";
    renderTable();

    const request = $.ajax({
      url: "items/ajax/get_items.php",
      method: "GET",
      dataType: "json",

      success: function (response) {
        if (itemsRequest !== request) return;
        if (response?.status !== true || !Array.isArray(response.data)) {
          loadState = "error";
          renderTable();
          console.error("Resposta inválida ao carregar produtos:", response);
          return;
        }

        items = response.data;
        populateFilters(items);
        const validIds = new Set(items.map((item) => String(item.id)));
        selectedIds.forEach((id) => {
          if (!validIds.has(id)) selectedIds.delete(id);
        });
        loadState = "ready";
        currentPage = 1;
        renderTable();
      },

      error: function (xhr, status, error) {
        if (itemsRequest !== request || status === "abort") return;
        loadState = "error";
        console.error(
          "Não foi possível carregar os produtos:",
          error || xhr.statusText,
        );
        renderTable();
      },
      complete: function () {
        if (itemsRequest === request) itemsRequest = null;
      },
    });
    itemsRequest = request;
  }

  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>"']/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[char],
    );

  function canonicalPrice(value) {
    let normalized = String(value ?? "")
      .trim()
      .replace(/\s/g, "");
    if (!normalized) return "";

    if (normalized.includes(",")) {
      normalized = normalized.replace(/\./g, "").replace(",", ".");
    } else if ((normalized.match(/\./g) || []).length > 1) {
      normalized = normalized.replace(/\./g, "");
    }

    if (!/^-?\d+(?:\.\d{0,2})?$/.test(normalized)) return "";
    const number = Number(normalized);
    return Number.isFinite(number) ? String(number) : "";
  }

  function displayPrice(value) {
    const canonical = canonicalPrice(value);
    if (!canonical) return "";
    const [integer, decimal] = canonical.split(".");
    const sign = integer.startsWith("-") ? "-" : "";
    const digits = sign ? integer.slice(1) : integer;
    const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    return `${sign}${grouped}${decimal ? `,${decimal}` : ""}`;
  }

  function normalizeTax(value) {
    const raw = String(value ?? "0")
      .replace("%", "")
      .trim()
      .replace(",", ".");
    const numeric = Number(raw);
    return Number.isFinite(numeric) ? String(numeric) : raw.toLowerCase();
  }

  function displayTax(value) {
    const raw = String(value ?? "0").trim();
    return raw.endsWith("%") ? raw : `${raw}%`;
  }

  function populateFilters(data) {
    const taxes = [
      ...new Map(
        data.map((item) => {
          const value = String(item.tax ?? "0");
          return [normalizeTax(value), value];
        }),
      ).values(),
    ].sort((a, b) => {
      const na = Number(normalizeTax(a));
      const nb = Number(normalizeTax(b));
      return Number.isFinite(na) && Number.isFinite(nb)
        ? na - nb
        : a.localeCompare(b, "pt");
    });

    $("#filterTax").html(
      '<option value="">Todas as taxas</option>' +
        taxes
          .map(
            (tax) =>
              `<option value="${escapeHtml(normalizeTax(tax))}">${escapeHtml(displayTax(tax))}</option>`,
          )
          .join(""),
    );

    $("#filterItemType").html(
      '<option value="">Produtos e serviços</option>' +
        '<option value="product">Produto</option>' +
        '<option value="service">Serviço</option>',
    );

    const categories = [
      ...new Set(data.map((item) => String(item.category ?? "").trim())),
    ].sort((a, b) => a.localeCompare(b, "pt", { sensitivity: "base" }));
    $("#filterCategory").html(
      '<option value="">Todas as categorias</option>' +
        categories
          .map((category) => {
            const value = category || "__uncategorized__";
            const label = category || "Sem categoria";
            return `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`;
          })
          .join(""),
    );
  }

  function clearSelection() {
    selectedIds.clear();
    refreshSelectionUI();
  }

  // ==================================================
  // FILTRO + ORDENAÇÃO
  // ==================================================
  function applyFilterAndSort() {
    const term = ($("#searchInput").val() || "").toLowerCase().trim();
    const taxFilter = $("#filterTax").val();
    const typeFilter = $("#filterItemType").val();
    const categoryFilter = $("#filterCategory").val();

    filteredItems = items.filter((row) => {
      const haystack = [
        row.code,
        row.name,
        row.description,
        row.item_type,
        row.category,
      ]
        .join(" ")
        .toLowerCase();
      if (term && !haystack.includes(term)) return false;
      if (taxFilter && normalizeTax(row.tax) !== taxFilter) return false;
      if (
        typeFilter &&
        String(row.item_type || "").toLowerCase() !== typeFilter
      )
        return false;
      if (categoryFilter) {
        const rowCategory =
          String(row.category ?? "").trim() || "__uncategorized__";
        if (rowCategory !== categoryFilter) return false;
      }
      return true;
    });

    const order = $("#filterOrder").val() || "newest";
    if (sortKey && order === "column") {
      filteredItems.sort((a, b) => {
        let va = a[sortKey] ?? "";
        let vb = b[sortKey] ?? "";

        const na = parseFloat(va);
        const nb = parseFloat(vb);

        if (!isNaN(na) && !isNaN(nb)) {
          va = na;
          vb = nb;
        } else {
          va = String(va).toLowerCase();
          vb = String(vb).toLowerCase();
        }

        if (va < vb) return sortDir === "asc" ? -1 : 1;
        if (va > vb) return sortDir === "asc" ? 1 : -1;
        return 0;
      });
    } else {
      filteredItems.sort((a, b) => {
        if (order === "name_asc" || order === "name_desc") {
          const comparison = String(a.name ?? "").localeCompare(
            String(b.name ?? ""),
            "pt",
            { sensitivity: "base" },
          );
          return order === "name_asc" ? comparison : -comparison;
        }

        const comparison = (Number(a.id) || 0) - (Number(b.id) || 0);
        return order === "oldest" ? comparison : -comparison;
      });
    }
  }

  // ==================================================
  // RENDER TABELA (HTML + BOOTSTRAP, SEM DATATABLES)
  // ==================================================
  function renderTable() {
    applyFilterAndSort();

    const $table = $("#itemsTable");
    const $tbody = $table.find("tbody");
    $tbody.empty();

    if (loadState === "loading") {
      $tbody.html(
        '<tr><td colspan="8" class="items-empty" aria-live="polite"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>A carregar produtos...</td></tr>',
      );
      $("#tableInfo").text("A carregar...");
      renderPagination(0);
      $("#selectAll").prop({ checked: false, indeterminate: false });
      return;
    }

    if (loadState === "error") {
      $tbody.html(`
        <tr>
          <td colspan="8" class="items-empty" role="alert">
            <div>Não foi possível carregar os produtos.</div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="retryItems">Tentar novamente</button>
          </td>
        </tr>
      `);
      $("#tableInfo").text("Erro ao carregar");
      renderPagination(0);
      $("#selectAll").prop({ checked: false, indeterminate: false });
      return;
    }

    if (!filteredItems.length) {
      const hasSearch = Boolean(($("#searchInput").val() || "").trim());
      const emptyContent = hasSearch
        ? `Nenhum produto corresponde à sua pesquisa.<br><button type="button" class="btn btn-sm btn-link" id="clearItemsSearch">Limpar pesquisa</button>`
        : `Nenhum produto ou serviço encontrado.<br><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Novo Produto</button>`;
      $tbody.html(
        `<tr><td colspan="8" class="items-empty">${emptyContent}</td></tr>`,
      );
      $("#tableInfo").text("Sem dados");
      renderPagination(0);
      updateSortIcons();
      refreshSelectionUI();
      return;
    }

    const totalItems = filteredItems.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, totalItems);
    const pageData = filteredItems.slice(start, end);

    let rowsHtml = "";

    pageData.forEach((row) => {
      const rowData = encodeURIComponent(JSON.stringify(row));
      const name = row.name || row.description || "-";
      const description = row.description || "-";
      const price = row.unit_price ?? row.cost_price ?? 0;
      const tax = row.tax || 0;
      const itemType = row.item_type === "service" ? "Serviço" : "Produto";
      const isSelected = selectedIds.has(String(row.id));
      const itemIcon = row.item_type === "service" ? "bi-tag" : "bi-box-seam";

      rowsHtml += `
        <tr data-id="${escapeHtml(row.id)}" class="${isSelected ? "is-selected" : ""}">
          <td data-label="Selecionar" class="item-select-cell">
            <input type="checkbox" class="item-checkbox form-check-input" value="${escapeHtml(row.id)}"
              aria-label="Selecionar ${itemType.toLowerCase()} ${escapeHtml(name)}" ${isSelected ? "checked" : ""}>
          </td>
          <td data-label="Tipo" class="item-icon-cell">
            <i class="bi ${itemIcon} me-1 text-secondary" aria-hidden="true"></i>
          </td>
          <td data-label="Nome" class="item-name" title="${escapeHtml(name)}">
            ${escapeHtml(name)}
            <small>${itemType}${row.code ? ` · ${escapeHtml(row.code)}` : ""}</small>
          </td>
          <td data-label="Descrição" class="item-description" title="${escapeHtml(description)}">
            ${escapeHtml(description)}
          </td>
          <td data-label="Preço Unitário" class="item-price">
            ${formatCurrency(price)}
          </td>
          <td data-label="Taxa/IVA" class="item-tax">${escapeHtml(tax)}%</td>
          <td data-label="PVP" class="item-pvp">
            ${formatCurrency(row.pvp ?? 0)}
          </td>
          <td data-label="Ações" class="item-actions">
            <div class="d-inline-flex align-items-center gap-1">
              <button type="button" class="items-icon-btn edit-btn"
                data-row="${rowData}"
                data-bs-toggle="tooltip"
                title="Editar produto" aria-label="Editar produto ${escapeHtml(name)}">
                <i class="bi bi-pencil" aria-hidden="true"></i>
              </button>
              <button type="button" class="items-icon-btn delete-btn"
                data-id="${escapeHtml(row.id)}"
                data-bs-toggle="tooltip"
                title="Eliminar produto" aria-label="Eliminar produto ${escapeHtml(name)}">
                <i class="bi bi-trash" aria-hidden="true"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    });

    $tbody.html(rowsHtml);

    $("#tableInfo").text(`${start + 1}–${end} de ${totalItems}`);
    renderPagination(totalPages);
    updateSortIcons();

    $page.find('[data-bs-toggle="tooltip"]').each(function () {
      bootstrap.Tooltip.getOrCreateInstance(this);
    });
    refreshSelectionUI();
  }

  // ==================================================
  // PAGINAÇÃO (BOOTSTRAP)
  // ==================================================
  function renderPagination(totalPages) {
    const $pagination = $("#tablePagination");
    $pagination.empty();

    if (totalPages <= 1) return;

    const addItem = (label, page, disabled = false, active = false) => {
      $pagination.append(`
        <li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
          <a href="#" class="page-link" data-page="${page}">${label}</a>
        </li>
      `);
    };

    addItem("«", currentPage - 1, currentPage === 1);

    const maxButtons = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    let endPage = Math.min(totalPages, startPage + maxButtons - 1);
    startPage = Math.max(1, endPage - maxButtons + 1);

    for (let p = startPage; p <= endPage; p++) {
      addItem(p, p, false, p === currentPage);
    }

    addItem("»", currentPage + 1, currentPage === totalPages);
  }

  $page.on("click.itemsPage", "#tablePagination .page-link", function (e) {
    e.preventDefault();
    const page = parseInt($(this).data("page"), 10);
    const $li = $(this).closest("li");
    if (!page || $li.hasClass("disabled") || $li.hasClass("active")) return;
    currentPage = page;
    renderTable();
  });

  // ==================================================
  // TAMANHO DA PÁGINA
  // ==================================================
  $page.on("change.itemsPage", "#pageSizeSelect", function () {
    pageSize = parseInt($(this).val(), 10) || 25;
    currentPage = 1;
    renderTable();
  });

  // ==================================================
  // ORDENAÇÃO POR COLUNA (clique no cabeçalho)
  // ==================================================
  $page.on("click.itemsPage", "#itemsTable thead th[data-key]", function () {
    const key = $(this).data("key");

    if (sortKey === key) {
      sortDir = sortDir === "asc" ? "desc" : "asc";
    } else {
      sortKey = key;
      sortDir = "asc";
    }

    $("#filterOrder").val("column");
    currentPage = 1;
    renderTable();
  });

  function updateSortIcons() {
    $("#itemsTable thead th[data-key]").each(function () {
      const key = $(this).data("key");
      const $icon = $(this).find(".sort-icon");
      if (!$icon.length) return;

      if (key !== sortKey) {
        $icon.attr("class", "sort-icon bi bi-arrow-down-up text-muted ms-1");
      } else {
        $icon.attr(
          "class",
          sortDir === "asc"
            ? "sort-icon bi bi-arrow-up ms-1"
            : "sort-icon bi bi-arrow-down ms-1",
        );
      }
    });
  }

  // ==================================================
  // PESQUISA
  // ==================================================
  $page.on("input.itemsPage", "#searchInput", function () {
    clearSelection();
    currentPage = 1;
    renderTable();
  });

  $page.on(
    "change.itemsPage",
    "#filterTax, #filterItemType, #filterCategory",
    function () {
      clearSelection();
      currentPage = 1;
      renderTable();
    },
  );

  $page.on("change.itemsPage", "#filterOrder", function () {
    const order = this.value;
    if (order === "name_asc" || order === "name_desc") {
      sortKey = "name";
      sortDir = order === "name_asc" ? "asc" : "desc";
    } else if (order === "newest" || order === "oldest") {
      sortKey = null;
      sortDir = order === "newest" ? "desc" : "asc";
    }
    currentPage = 1;
    renderTable();
  });

  $page.on("click.itemsPage", "#clearItemsFilters", function () {
    $("#searchInput").val("");
    $("#filterTax, #filterItemType, #filterCategory").val("");
    $("#filterOrder").val("newest");
    sortKey = null;
    sortDir = "desc";
    clearSelection();
    currentPage = 1;
    renderTable();
  });

  // ========================================
  // FORMATA MOEDA
  // ========================================
  function formatCurrency(value) {
    const formatted = new Intl.NumberFormat("pt-PT", {
      useGrouping: false,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(Number(value) || 0);
    const [integer, decimals] = formatted.split(",");
    const groupedInteger = integer.replace(/\B(?=(\d{3})+(?!\d))/g, "\u00a0");
    return `${groupedInteger},${decimals}\u00a0AOA`;
  }

  // ========================================
  // PREPARA DADOS (para exportação)
  // ========================================
  function prepareData(data = []) {
    return data.map((item) => ({
      ID: item.id,
      Código: item.code || "-",
      Nome: item.name || "-",
      Tipo: item.item_type || "-",
      Categoria: item.category || "-",
      Unidade: item.unit_measure || "-",
      Preço: formatCurrency(item.unit_price),
      PVP: formatCurrency(item.pvp),
      Quantidade: item.quantity || 0,
      Stock: item.stock_name || "-",
      Estado: item.status || "-",
      Moeda: item.currency || "AOA",
    }));
  }

  function generateFileName(type) {
    const date = new Date().toISOString().split("T")[0];
    return `produtos_${date}.${type}`;
  }

  function exportCSV(data = []) {
    if (!data.length) return alert("Nenhum dado encontrado");

    const rows = prepareData(data);
    const headers = Object.keys(rows[0]).join(";");

    const csvContent = rows.map((row) =>
      Object.values(row)
        .map((value) => `"${String(value).replace(/"/g, '""')}"`)
        .join(";"),
    );

    const csv = [headers, ...csvContent].join("\n");
    const blob = new Blob(["\uFEFF" + csv], {
      type: "text/csv;charset=utf-8;",
    });

    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = generateFileName("csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  function exportExcel(data = []) {
    if (!data.length) return alert("Nenhum dado encontrado");

    const rows = prepareData(data);
    const worksheet = XLSX.utils.json_to_sheet(rows);

    worksheet["!cols"] = [
      { wch: 8 },
      { wch: 20 },
      { wch: 35 },
      { wch: 15 },
      { wch: 15 },
      { wch: 12 },
      { wch: 18 },
      { wch: 18 },
      { wch: 12 },
      { wch: 30 },
      { wch: 12 },
      { wch: 10 },
    ];

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Produtos");
    XLSX.writeFile(workbook, generateFileName("xlsx"));
  }

  function exportPDF(data = []) {
    if (!data.length) return alert("Nenhum dado encontrado");

    const rows = prepareData(data);
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape" });

    doc.setFontSize(16);
    doc.text("Relatório de Produtos", 14, 15);

    const tableColumn = Object.keys(rows[0]);
    const tableRows = rows.map((row) => Object.values(row));

    doc.autoTable({
      head: [tableColumn],
      body: tableRows,
      startY: 25,
      styles: { fontSize: 8, cellPadding: 2 },
      headStyles: {
        fillColor: [41, 128, 185],
        textColor: 255,
        fontStyle: "bold",
      },
      alternateRowStyles: { fillColor: [245, 245, 245] },
    });

    doc.save(generateFileName("pdf"));
  }

  // ========================================
  // EVENTOS
  // ========================================
  function closeExportMenu() {
    $("#itemsExportMenu").prop("hidden", true);
    $("#itemsExportToggle").attr("aria-expanded", "false");
  }

  $page.on("click.itemsPage", "#itemsExportToggle", function () {
    const menu = $("#itemsExportMenu");
    const opening = menu.prop("hidden");
    menu.prop("hidden", !opening);
    $(this).attr("aria-expanded", String(opening));
  });

  $page.on("click.itemsPage", "#downloadCSV", function () {
    closeExportMenu();
    exportCSV(items);
  });
  $page.on("click.itemsPage", "#downloadExcel", function () {
    closeExportMenu();
    exportExcel(items);
  });
  $page.on("click.itemsPage", "#downloadPDF", function () {
    closeExportMenu();
    exportPDF(items);
  });
  $page.on("click.itemsPage", function (event) {
    if (!$(event.target).closest(".items-export").length) closeExportMenu();
  });
  $page.on("keydown.itemsPage", function (event) {
    if (event.key === "Escape") closeExportMenu();
  });

  $page.on("click.itemsPage", "#retryItems", loadItems);
  $page.on("click.itemsPage", "#clearItemsSearch", function () {
    $("#searchInput").val("");
    currentPage = 1;
    renderTable();
    $("#searchInput").trigger("focus");
  });

  $page.on("click.itemsPage", "#itemsTable .delete-btn", function () {
    const itemId = $(this).data("id");
    const $row = $(this).closest("tr");
    deleteWithUndo(itemId, $row);
  });

  function deleteWithUndo(itemId, $row) {
    let timeout;
    let cancelled = false;

    $row.fadeOut(200);

    Swal.fire({
      toast: true,
      position: "top-end",
      showConfirmButton: false,
      timer: 5000,
      html: `
        <div class="d-flex align-items-center gap-2">
          <span>Item removido</span>
          <button id="undoBtn" class="btn btn-sm btn-light">Desfazer</button>
        </div>
      `,
      didOpen: () => {
        const undoBtn = document.getElementById("undoBtn");
        undoBtn.addEventListener("click", () => {
          cancelled = true;
          clearTimeout(timeout);
          $row.fadeIn(200);

          Swal.fire({
            toast: true,
            icon: "info",
            position: "top-end",
            title: "Ação cancelada",
            showConfirmButton: false,
            timer: 2000,
          });
        });
      },
    });

    timeout = setTimeout(() => {
      if (cancelled) return;

      $.ajax({
        url: "items/ajax/delete_item.php",
        method: "POST",
        data: { id: itemId },
        dataType: "json",
      })
        .done(function (res) {
          if (res.success) {
            items = items.filter((it) => String(it.id) !== String(itemId));
            selectedIds.delete(String(itemId));

            Swal.fire({
              icon: "success",
              title: "Eliminado",
              text: res.message || "Item eliminado com sucesso",
            });

            renderTable();
          } else {
            $row.fadeIn(200);
            Swal.fire({
              icon: "warning",
              title: "Não eliminado",
              text: res.message || "Não foi possível eliminar o item",
            });
          }
        })
        .fail(function (xhr) {
          $row.fadeIn(200);
          let msg = "Erro ao eliminar item";
          try {
            msg = JSON.parse(xhr.responseText)?.message || msg;
          } catch (e) {}

          Swal.fire({ icon: "error", title: "Erro", text: msg });
        });
    }, 5000);
  }

  // selecionar todos (apenas os visíveis na página atual)
  $page.on("change.itemsPage", "#selectAll", function () {
    const select = this.checked;
    $("#tableBody .item-checkbox").each(function () {
      const id = String(this.value);
      this.checked = select;
      if (select) selectedIds.add(id);
      else selectedIds.delete(id);
      $(this).closest("tr").toggleClass("is-selected", select);
    });
    refreshSelectionUI();
  });

  function refreshSelectionUI() {
    const selectedCount = selectedIds.size;
    $("#itemsSelectionBar").prop("hidden", selectedCount === 0);
    $("#itemsSelectionCount").text(
      `${selectedCount} ${selectedCount === 1 ? "selecionado" : "selecionados"}`,
    );

    const visible = $("#tableBody .item-checkbox");
    const checkedCount = visible.filter(":checked").length;
    $("#selectAll")
      .prop("checked", visible.length > 0 && checkedCount === visible.length)
      .prop("indeterminate", checkedCount > 0 && checkedCount < visible.length);
  }

  $page.on("change.itemsPage", "#tableBody .item-checkbox", function () {
    const id = String(this.value);
    if (this.checked) selectedIds.add(id);
    else selectedIds.delete(id);
    $(this).closest("tr").toggleClass("is-selected", this.checked);
    refreshSelectionUI();
  });

  $page.on("click.itemsPage", "#deleteSelected", function () {
    const selected = [...selectedIds];

    if (selected.length === 0) {
      return Swal.fire("Atenção", "Selecione pelo menos um item.", "warning");
    }

    Swal.fire({
      title: selected.length === 1 ? "Eliminar produto?" : "Eliminar produtos?",
      text:
        selected.length === 1
          ? `Tem certeza que deseja eliminar "${items.find((item) => String(item.id) === selected[0])?.name || "este produto"}"?`
          : `Tem certeza que deseja eliminar ${selected.length} produtos?`,
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#d33",
      confirmButtonText: "Sim, eliminar",
      cancelButtonText: "Cancelar",
    }).then((result) => {
      if (!result.isConfirmed) return;

      Swal.fire({
        title: "A eliminar...",
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      $.ajax({
        url: "items/ajax/delete_items_bulk.php",
        method: "POST",
        data: { ids: selected },
        dataType: "json",
      })
        .done(function (res) {
          if (res.success) {
            const deletedIds = (
              Array.isArray(res.deleted) ? res.deleted : []
            ).map(String);
            items = items.filter((it) => !deletedIds.includes(String(it.id)));
            deletedIds.forEach((id) => selectedIds.delete(id));
            renderTable();

            Swal.fire({
              toast: true,
              position: "top-end",
              icon: "success",
              title: res.blocked?.length
                ? `${deletedIds.length} eliminados; ${res.blocked.length} não puderam ser removidos.`
                : res.message || `${deletedIds.length} item(s) eliminados`,
              showConfirmButton: false,
              timer: 2500,
            });
          } else {
            Swal.fire({
              icon: "warning",
              title: "Atenção",
              text:
                res.error ||
                res.message ||
                "Não foi possível eliminar os itens.",
            });
          }
        })
        .fail(function (xhr) {
          let msg = "Erro inesperado.";
          try {
            const res = JSON.parse(xhr.responseText);
            msg = res.error || msg;
          } catch (e) {}

          Swal.fire({ icon: "error", title: "Erro", text: msg });
        });
    });
  });

  $page.on("click.itemsPage", "#itemsTable .edit-btn", function () {
    const rawData = $(this).data("row");
    if (!rawData) {
      console.error("Dados não encontrados para edição.");
      return;
    }

    let row;
    try {
      row = JSON.parse(decodeURIComponent(rawData));
    } catch (e) {
      console.error("Erro ao parse JSON:", e, rawData);
      return;
    }

    const form = document.getElementById("itemForm");
    if (!form) return;

    if (!form.product_id) {
      const hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = "product_id";
      form.appendChild(hidden);
    }
    form.product_id.value = row.id ?? "";

    form.codigo.value = row.code ?? "";
    form.name.value = row.name ?? "";
    form.descricao.value = row.description ?? "";

    $("[name='item_type']", form).val(row.item_type ?? "product");
    $("[name='subcategory']", form).val(row.subcategory ?? "");
    $("[name='unit_measure']", form).val(row.unit_measure ?? "unit");
    $("[name='currency']", form).val(row.currency ?? "AOA");

    $("[name='stock_id']", form).val(row.stock_id ?? "");

    form.quantidade.value = row.quantity ?? 0;
    form.min_stock.value = row.min_quantity ?? 1;

    form.querySelector("[data-price-value]").value = row.unit_price ?? 0;
    form.querySelector("[data-price-display]").value = displayPrice(
      row.unit_price ?? 0,
    );
    form.cost_price.value = row.cost_price ?? 0;
    form.sale_price.value = row.sale_price ?? 0;
    form.pvp.value = row.pvp ?? 0;

    form.taxVat.value = row.tax ?? "";
    $("[name='retention']", form).val(row.retention ?? 0);

    form.id_company.value = row.company_id ?? "";

    const modal = new bootstrap.Modal(modalEl, {
      backdrop: true,
      keyboard: true,
    });
    modalTitle.innerHTML = "Editar Produto/Serviço";
    modal.show();

    const isProduct = row.item_type === "product";
    setTimeout(() => {
      !isProduct
        ? $("#depot").removeClass("active")
        : $("#depot").addClass("active");
    }, 200);
  });

  function resetItemForm() {
    const form = document.getElementById("itemForm");
    if (!form) return;

    form.reset();
    const priceDisplay = form.querySelector("[data-price-display]");
    if (priceDisplay) priceDisplay.value = "";

    const idField = form.querySelector("[name='product_id']");
    if (idField) idField.remove();

    $("[name='item_type']", form).val("product").trigger("change");
    $("[name='subcategory']", form).val("").trigger("change");
    $("[name='stock_id']", form).val("").trigger("change");
    $("[name='currency']", form).val("AOA").trigger("change");

    const tax = form.querySelector("#tax");
    if (tax) tax.value = "";

    const retention = form.querySelector("#retention_tax");
    if (retention) retention.value = "0";

    modalTitle.innerHTML = defaultTitle;

    if (typeof syncUI === "function") {
      syncUI();
    }
  }

  modalEl.addEventListener("hidden.bs.modal", () => {
    resetItemForm();
  });

  $page.on("click.itemsPage", "#saveEdit", function () {
    const form = $("#editItemForm");
    let formData = form.serialize();

    $.ajax({
      url: "items/ajax/edit_item.php",
      method: "POST",
      data: formData,
      dataType: "json",

      success: function (response) {
        if (response.success) {
          $("#editItemModal").modal("hide");

          Swal.fire({
            toast: true,
            position: "top-end",
            icon: "success",
            title: "Item atualizado com sucesso!",
            showConfirmButton: false,
            timer: 3000,
          });

          loadItems();
        } else {
          Swal.fire("Erro", response.message || "Erro ao atualizar", "error");
        }
      },

      error: function () {
        Swal.fire("Erro!", "Erro na requisição!", "error");
      },
    });
  });
});
