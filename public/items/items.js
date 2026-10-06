$(document).ready(function () {
  let items = []; // todos os itens vindos do servidor
  let filteredItems = []; // itens após pesquisa/ordenação
  let currentPage = 1;
  let pageSize = 25;
  let sortKey = null;
  let sortDir = "asc";

  const modalEl = document.getElementById("itemModal");
  const modalTitle = document.querySelector("#itemModal .modal-title");
  const defaultTitle = "Adicionar Novo Produto/Serviço";

  loadItems();

  function loadItems() {
    $.ajax({
      url: "items/ajax/get_items.php",
      method: "GET",
      dataType: "json",

      success: function (response) {
        items = response?.data || [];
        filteredItems = [...items];
        currentPage = 1;
        renderTable();
      },

      error: function (xhr, status, error) {
        console.error("Erro:", error);
      },
    });
  }

  // ==================================================
  // FILTRO + ORDENAÇÃO (substitui o "manipular" do DataTables)
  // ==================================================
  function applyFilterAndSort() {
    const term = ($("#searchInput").val() || "").toLowerCase().trim();

    filteredItems = items.filter((row) => {
      if (!term) return true;
      const haystack = [
        row.code,
        row.name,
        row.description,
        row.item_type,
        row.category,
      ]
        .join(" ")
        .toLowerCase();
      return haystack.indexOf(term) > -1;
    });

    if (sortKey) {
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

    if (!filteredItems.length) {
      $tbody.append(`
        <tr>
          <td colspan="9" class="text-center text-muted py-4">
            Nenhum produto ou serviço encontrado
          </td>
        </tr>
      `);
      $("#tableInfo").text("Sem dados");
      renderPagination(0);
      updateSortIcons();
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
      const description = row.name || row.description || "-";
      const price = row.unit_price || row.cost_price;
      const tax = row.tax || 0;

      rowsHtml += `
        <tr data-id="${row.id}">

          <td data-label="">
            <input type="checkbox" class="item-checkbox" value="${row.id}">
          </td>

          <td data-label="">
            <i class="${
              row.item_type === "service"
                ? "bi bi-tag fw-bold fs-5"
                : "bi bi-box-seam fw-bold fs-5"
            }"></i>
          </td>

          <td data-label="Código">${row.code || "-"}</td>

          <td data-label="Nome" class="text-truncate-custom" title="${description}">
            ${description}
          </td>

          <td data-label="Descrição" class="text-truncate-custom" title="${row.description || ""}">
            ${row.description || "-"}
          </td>

          <td data-label="Preço Unitário" class="text-success fw-bold">
            ${formatCurrency(price)}
          </td>

          <td data-label="Taxa/IVA">${tax}%</td>

          <td data-label="PVP" class="text-primary fw-bold">
            ${formatCurrency(row.pvp ?? 0)}
          </td>

          <td data-label="Ações">
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

    $("#tableInfo").text(`${start + 1}–${end} de ${totalItems}`);
    renderPagination(totalPages);
    updateSortIcons();

    $('[data-bs-toggle="tooltip"]').tooltip("dispose");
    $('[data-bs-toggle="tooltip"]').tooltip();
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

  $(document).on("click", "#tablePagination .page-link", function (e) {
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
  $(document).on("change", "#pageSizeSelect", function () {
    pageSize = parseInt($(this).val(), 10) || 25;
    currentPage = 1;
    renderTable();
  });

  // ==================================================
  // ORDENAÇÃO POR COLUNA (clique no cabeçalho)
  // ==================================================
  $(document).on("click", "#itemsTable thead th[data-key]", function () {
    const key = $(this).data("key");

    if (sortKey === key) {
      sortDir = sortDir === "asc" ? "desc" : "asc";
    } else {
      sortKey = key;
      sortDir = "asc";
    }

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
  $("#searchInput").on("keyup", function () {
    currentPage = 1;
    renderTable();
  });

  // ========================================
  // FORMATA MOEDA
  // ========================================
  function formatCurrency(value) {
    return new Intl.NumberFormat("pt-PT", {
      style: "currency",
      currency: "AOA",
    }).format(Number(value || 0));
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
  document
    .getElementById("downloadCSV")
    .addEventListener("click", () => exportCSV(items));
  document
    .getElementById("downloadExcel")
    .addEventListener("click", () => exportExcel(items));
  document
    .getElementById("downloadPDF")
    .addEventListener("click", () => exportPDF(items));

  $("#itemsTable").on("click", ".delete-btn", function () {
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
  $("#selectAll").on("change", function () {
    $(".item-checkbox").prop("checked", $(this).is(":checked"));
  });

  $("#deleteSelected").on("click", function () {
    const selected = $(".item-checkbox:checked")
      .map(function () {
        return String($(this).val());
      })
      .get();

    if (selected.length === 0) {
      return Swal.fire("Atenção", "Selecione pelo menos um item.", "warning");
    }

    Swal.fire({
      title: `Eliminar ${selected.length} item(s)?`,
      text: "Esta ação não pode ser desfeita!",
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
            items = items.filter((it) => !selected.includes(String(it.id)));
            renderTable();

            Swal.fire({
              toast: true,
              position: "top-end",
              icon: "success",
              title: res.message || `${selected.length} item(s) eliminados`,
              showConfirmButton: false,
              timer: 2500,
            });
          } else {
            Swal.fire({
              icon: "warning",
              title: "Atenção",
              text: res.error || "Não foi possível eliminar os itens.",
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

  $("#itemsTable").on("click", ".edit-btn", function () {
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

    form.unit_price.value = row.unit_price ?? 0;
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

  $("#saveEdit").on("click", function () {
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

  $(document).on("change", ".item-checkbox", function () {
    const selectedItems = $(".item-checkbox:checked");
    $("#deleteSelected").html(`
      <i class="bi bi-trash"></i> Eliminar ${selectedItems.length > 0 ? selectedItems.length + " item(s)" : ""}
    `);
  });
});
