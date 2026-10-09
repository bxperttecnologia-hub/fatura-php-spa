function selectedProformas() {
  const ids = new Set(
    $('#invoicesTable tbody .invoice-check:checked')
      .map(function () {
        return String(this.value);
      })
      .get(),
  );
  return (window.proformasForExport || []).filter((row) =>
    ids.has(String(row.id)),
  );
}

function refreshSelectedExportGroup() {
  const selected = selectedProformas();
  $("#bxExportSelectedGroup").prop("hidden", selected.length === 0);
  $("#bxExportSelectedLabel").text(`Selecionados (${selected.length})`);
  $("#proformaSelectionCount").text(
    `${selected.length} ${selected.length === 1 ? "selecionada" : "selecionadas"}`,
  );
  $("#proformaSelectionBar").toggleClass("is-visible", selected.length > 0);
}

$(document).ready(function () {
  let proformas = []; // todos os dados vindos do servidor
  let filteredProformas = []; // após filtros/ordenação
  let currentPage = 1;
  let pageSize = 25;
  let sortKey = "codigo";
  let sortDir = "desc";
  const escapeHtml = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (char) =>
      ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[char],
    );
  const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString("pt-PT") : "-";
  const normalizeStatus = (value) =>
    String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .trim()
      .toLowerCase();

  function updateOverview() {
    const total = proformas.length;
    const drafts = proformas.filter(
      (row) => String(row.status_invoice || "").toLowerCase() === "rascunho",
    ).length;
    const paid = proformas.filter((row) =>
      ["pago", "convertida", "convertido"].includes(
        String(row.status_invoice || "").toLowerCase(),
      ),
    ).length;
    $("#proformaInsightTotal").text(total);
    $("#proformaInsightPending").text(Math.max(0, total - drafts - paid));
    $("#proformaInsightPaid").text(paid);
    $("#proformaInsightDrafts").text(drafts);
  }

  function formatCurrency(value, symbol = "", position = "left") {
    const formatted = Number(value || 0).toLocaleString("pt-PT", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    if (!symbol) return formatted;
    return position === "right"
      ? `${formatted} ${symbol}`
      : `${symbol} ${formatted}`;
  }

  loadProformas();

  $(document).off(".proformaList");
  $(document)
    .off("reload-proformas.proformaList")
    .on("reload-proformas.proformaList.spaPage", loadProformas);

  function loadProformas() {
    $.ajax({
      url: "proform/ajax/fetch_proforms.php",
      type: "GET",
      dataType: "json",

      success: function (json) {
        if (Array.isArray(json)) {
          proformas = json;
        } else if (json?.data && Array.isArray(json.data)) {
          proformas = json.data;
        } else if (json?.invoices && Array.isArray(json.invoices)) {
          proformas = json.invoices;
        } else {
          console.error("Formato inválido:", json);
          proformas = [];
        }

        window.proformasForExport = proformas;
        updateOverview();
        currentPage = 1;
        renderTable();
      },

      error: function () {
        console.error("Erro ao carregar proformas.");
      },
    });
  }

  // ==================================================
  // FILTROS + ORDENAÇÃO
  // ==================================================
  function applyFilterAndSort() {
    const clienteFiltro = ($("#filterClient").val() || "").toLowerCase().trim();
    const statusFiltro = normalizeStatus($("#filterStatus").val());
    const start = $("#filterStartDate").val();
    const end = $("#filterEndDate").val();

    filteredProformas = proformas.filter((row) => {
      const cliente = (row.cliente || "").toLowerCase();
      const status = normalizeStatus(row.status_invoice);

      if (clienteFiltro && !cliente.includes(clienteFiltro)) return false;
      if (statusFiltro && status !== statusFiltro) return false;

      if (row.issue_date) {
        const current = new Date(row.issue_date);

        if (start) {
          const startDate = new Date(start);
          if (current < startDate) return false;
        }

        if (end) {
          const endDate = new Date(end);
          endDate.setHours(23, 59, 59, 999);
          if (current > endDate) return false;
        }
      }

      return true;
    });

    if (sortKey) {
      filteredProformas.sort((a, b) => {
        let va = a[sortKey] ?? "";
        let vb = b[sortKey] ?? "";

        // datas
        if (sortKey === "issue_date" || sortKey === "due_date") {
          va = va ? new Date(va).getTime() : 0;
          vb = vb ? new Date(vb).getTime() : 0;
        } else if (sortKey === "final_total") {
          va = Number(va) || 0;
          vb = Number(vb) || 0;
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
  // RENDER TABELA
  // ==================================================
  function renderTable() {
    applyFilterAndSort();

    const $tbody = $("#invoicesTable tbody");
    const checkedProformaIds = new Set(
      selectedProformas().map((row) => String(row.id)),
    );
    $tbody.empty();

    if (!filteredProformas.length) {
      $tbody.append(`
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            Nenhuma proforma encontrada
          </td>
        </tr>
      `);
      $("#tableInfo").text("Sem dados");
      renderPagination(0);
      updateSortIcons();
      return;
    }

    const totalItems = filteredProformas.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, totalItems);
    const pageData = filteredProformas.slice(start, end);

    let rowsHtml = "";

    pageData.forEach((row) => {
      const status = row.status_invoice || "?";
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const due = row.due_date ? new Date(row.due_date) : null;
      if (due) due.setHours(0, 0, 0, 0);
      const isOverdue =
        due && due < today && String(status).toLowerCase() !== "pago";
      const normalizedStatus = String(status).toLowerCase();
      const statusSlug = normalizedStatus
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "");
      const statusKey = isOverdue
        ? "vencido"
        : ["pago", "pendente", "parcial", "rascunho", "convertida", "convertido"].includes(statusSlug)
          ? statusSlug
          : "outro";
      const rowId = escapeHtml(row.id);
      const code = escapeHtml(row.codigo || "-");
      const client = escapeHtml(row.cliente || "-");
      const isDraft = normalizedStatus === "rascunho";
      const isSelected = checkedProformaIds.has(String(row.id));
      const dueDateHtml = row.due_date
        ? `<span class="${isOverdue ? "bx-due--overdue" : ""}">${formatDate(row.due_date)}${isOverdue ? '<span class="bx-due__sub">Em atraso</span>' : ""}</span>`
        : "-";

      rowsHtml += `
        <tr class="document-row${isSelected ? " is-selected" : ""}" data-id="${rowId}">
          <td class="bx-td-check">
            <input type="checkbox" class="invoice-check form-check-input" data-id="${rowId}"
              value="${rowId}" aria-label="Selecionar proforma ${code}" ${isSelected ? "checked" : ""}>
            <span class="bx-badge bx-badge--${statusKey}">${escapeHtml(isOverdue ? "Vencido" : status)}</span>
          </td>
          <td class="bx-td-doc"><span class="bx-doc"><span class="bx-doc__type">PROFORMA</span><span class="bx-doc__num">${code}</span></span></td>
          <td class="bx-td-client" data-label="Cliente">${client}</td>
          <td data-label="Emissão">${formatDate(row.issue_date)}</td>
          <td data-label="Vencimento">${dueDateHtml}</td>
          <td data-label="Moeda">${escapeHtml(row.currency || "-")}</td>
          <td class="bx-num bx-money bx-td-total" data-label="Total">${escapeHtml(formatCurrency(Number(row.final_total || 0), row.symbol || "", row.position || "left"))}</td>
          <td class="bx-td-actions">
            <div class="bx-actions">
              <a class="bx-icon-btn" href="/proformas/view?id=${encodeURIComponent(row.id)}" data-spa
                aria-label="Ver proforma ${code}" title="Ver"><i class="bi bi-eye" aria-hidden="true"></i></a>
              ${isDraft ? `<button type="button" class="bx-icon-btn js-edit-proforma" data-id="${rowId}" aria-label="Editar proforma ${code}" title="Editar"><i class="bi bi-pencil" aria-hidden="true"></i></button>` : `<button type="button" class="bx-icon-btn js-pdf-proforma" data-id="${rowId}" aria-label="Descarregar proforma ${code} em PDF" title="PDF"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></button>`}
              <button type="button" class="bx-icon-btn js-more" data-id="${rowId}" aria-haspopup="menu" aria-expanded="false"
                aria-label="Mais ações da proforma ${code}" title="Mais ações"><i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
            </div>
          </td>
        </tr>
      `;
    });

    $tbody.html(rowsHtml);
    refreshSelectedExportGroup();

    $("#tableInfo").text(`${start + 1}–${end} de ${totalItems}`);
    renderPagination(totalPages);
    updateSortIcons();


    // reset "selecionar todos" ao re-renderizar
    $("#selectAll").prop("checked", false);
  }

  // ==================================================
  // PAGINAÇÃO
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

  $(document).on("click.proformaList.spaPage", "#tablePagination .page-link", function (e) {
    e.preventDefault();
    const page = parseInt($(this).data("page"), 10);
    const $li = $(this).closest("li");
    if (!page || $li.hasClass("disabled") || $li.hasClass("active")) return;
    currentPage = page;
    renderTable();
  });

  $(document).on("change.proformaList.spaPage", "#pageSizeSelect", function () {
    pageSize = parseInt($(this).val(), 10) || 25;
    currentPage = 1;
    renderTable();
  });

  // ==================================================
  // ORDENAÇÃO POR COLUNA
  // ==================================================
  $(document).on("click.proformaList.spaPage", "#invoicesTable thead th[data-key]", function () {
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
    $("#invoicesTable thead th[data-key]").each(function () {
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
  // FILTROS (eventos)
  // ==================================================
  $("#filterClient").on("input", function () {
    currentPage = 1;
    renderTable();
  });

  $("#filterStatus").on("change", function () {
    currentPage = 1;
    renderTable();
  });

  $("#filterStartDate").on("change", function () {
    currentPage = 1;
    renderTable();
  });

  $("#filterEndDate").on("change", function () {
    currentPage = 1;
    renderTable();
  });

  $("#btnClearFilters").on("click", function () {
    $("#filterClient").val("");
    $("#filterStatus").val("");
    $("#filterStartDate").val("");
    $("#filterEndDate").val("");
    currentPage = 1;
    renderTable();
  });

  // ==================================================
  // CLIQUE NA LINHA (navegar para a proforma)
  // ==================================================
  $("#invoicesTable tbody").on("click", "tr.document-row", function (e) {
    const $target = $(e.target);

    if ($target.closest("button").length || $target.closest("input").length) {
      return;
    }

    const id = $(this).data("id");
    const row = filteredProformas.find((r) => String(r.id) === String(id));
    if (!row) return;

    const url =
      `/proformas/view?id=${encodeURIComponent(row.id)}`;

    if (typeof window.navigateSPA === "function") window.navigateSPA(url);
    else window.location.href = url;
  });

  // ==================================================
  // SELECIONAR TODOS (apenas a página atual)
  // ==================================================
  $(document).on("click.proformaList.spaPage", "#selectAll", function () {
    const isChecked = $(this).is(":checked");
    $('#invoicesTable tbody .invoice-check').prop("checked", isChecked);
    $('#invoicesTable tbody tr.document-row').toggleClass("is-selected", isChecked);
    refreshSelectedExportGroup();
  });

  $("#invoicesTable tbody").on("change", 'input[type="checkbox"]', function () {
    $(this).closest("tr").toggleClass("is-selected", this.checked);
    const totalCheckboxes = $(
      '#invoicesTable tbody input[type="checkbox"]',
    ).length;
    const checkedCheckboxes = $(
      '#invoicesTable tbody input[type="checkbox"]:checked',
    ).length;

    $("#selectAll").prop(
      "checked",
      totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes,
    );
    refreshSelectedExportGroup();
  });

  $("#clearProformaSelection").on("click", function () {
    $("#selectAll").prop("checked", false);
    $('#invoicesTable tbody .invoice-check')
      .prop("checked", false)
      .trigger("change");
  });

  $(document)
    .off(".proformaRowMenu")
    .on("click.proformaRowMenu.spaPage", "#invoicesTable .js-edit-proforma", function (event) {
      event.stopPropagation();
      const id = encodeURIComponent(this.dataset.id);
      const url = `/proformas/create?edit_id=${id}`;
      if (typeof window.navigateSPA === "function") window.navigateSPA(url);
      else window.location.assign(url);
    })
    .on("click.proformaRowMenu.spaPage", "#invoicesTable .js-pdf-proforma", function (event) {
      event.stopPropagation();
      downloadPDF(this.dataset.id);
    })
    .on("click.proformaRowMenu.spaPage", "#invoicesTable .js-more", function (event) {
      event.stopPropagation();
      const row = proformas.find((item) => String(item.id) === String(this.dataset.id));
      if (!row) return;
      let menu = document.getElementById("bxRowMenu");
      if (!menu) {
        menu = document.createElement("div");
        menu.id = "bxRowMenu";
        menu.className = "bx-menu bxRowMenu";
        menu.setAttribute("role", "menu");
        document.body.appendChild(menu);
      }
      const isDraft = String(row.status_invoice || "").toLowerCase() === "rascunho";
      const isConverted = ["convertida", "convertido"].includes(normalizeStatus(row.status_invoice));
      menu.innerHTML = `
        <button type="button" class="bx-menu__item" role="menuitem" data-row-action="view"><i class="bi bi-eye" aria-hidden="true"></i> Visualizar</button>
        ${isDraft ? `<button type="button" class="bx-menu__item" role="menuitem" data-row-action="edit"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</button>` : ""}
        <button type="button" class="bx-menu__item" role="menuitem" data-row-action="pdf"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Descarregar PDF</button>
        <button type="button" class="bx-menu__item" role="menuitem" data-row-action="send"><i class="bi bi-envelope" aria-hidden="true"></i> Enviar por e-mail</button>
        ${isConverted ? "" : `<button type="button" class="bx-menu__item" role="menuitem" data-row-action="convert"><i class="bi bi-receipt" aria-hidden="true"></i> Converter em fatura</button>`}
        ${isDraft ? `<button type="button" class="bx-menu__item bx-menu__item--danger" role="menuitem" data-row-action="delete"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>` : ""}
      `;
      menu.dataset.rowId = row.id;
      menu.dataset.companyId = row.company_id || "";
      const rect = this.getBoundingClientRect();
      menu.classList.add("is-open");
      const menuRect = menu.getBoundingClientRect();
      menu.style.left = `${Math.max(8, Math.min(rect.right - menuRect.width, window.innerWidth - menuRect.width - 8))}px`;
      menu.style.top = `${Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menuRect.height - 8))}px`;
      this.setAttribute("aria-expanded", "true");
      menu.querySelector("[role='menuitem']")?.focus();
    })
    .on("click.proformaRowMenu.spaPage", "#bxRowMenu [data-row-action]", function (event) {
      event.stopPropagation();
      const menu = document.getElementById("bxRowMenu");
      const id = menu?.dataset.rowId;
      if (!id) return;
      if (this.dataset.rowAction === "edit") {
        const url = `/proformas/create?edit_id=${encodeURIComponent(id)}`;
        if (typeof window.navigateSPA === "function") window.navigateSPA(url);
        else window.location.assign(url);
      } else if (this.dataset.rowAction === "view") {
        const url = `/proformas/view?id=${encodeURIComponent(id)}`;
        if (typeof window.navigateSPA === "function") window.navigateSPA(url);
        else window.location.assign(url);
      } else if (this.dataset.rowAction === "send" || this.dataset.rowAction === "convert") {
        const action = this.dataset.rowAction === "send" ? "send=1" : "convert=1";
        const url = `/proformas/view?id=${encodeURIComponent(id)}&${action}`;
        if (typeof window.navigateSPA === "function") window.navigateSPA(url);
        else window.location.assign(url);
      } else if (this.dataset.rowAction === "pdf") {
        downloadPDF(id);
      } else if (this.dataset.rowAction === "delete") {
        deleteProforma(id, menu.dataset.companyId);
      }
      menu.classList.remove("is-open");
      $("#invoicesTable .js-more").attr("aria-expanded", "false");
    })
    .on("click.proformaRowMenu.spaPage", function (event) {
      if (!event.target.closest("#bxRowMenu, #invoicesTable .js-more")) {
        $("#bxRowMenu").removeClass("is-open");
        $("#invoicesTable .js-more").attr("aria-expanded", "false");
      }
    })
    .on("keydown.proformaRowMenu.spaPage", function (event) {
      if (event.key === "Escape") {
        $("#bxRowMenu").removeClass("is-open");
        $("#invoicesTable .js-more").attr("aria-expanded", "false").first().trigger("focus");
      }
    });
});

// ==================================================
// DOWNLOAD PDF DA PROFORMA
// ==================================================
function downloadPDF(invoiceId) {
  $.ajax({
    url: "proform/ajax/get_proform.php",
    type: "GET",
    data: { id: invoiceId },
    dataType: "json",
    success: function (response) {
      if (response?.success === false || response?.error) {
        alert(response.error || "Não foi possível carregar os dados da proforma.");
        return;
      }

      response = response?.data || response;
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();
      const taxSummary = window.BXDocumentTax.summarize(
        response.items,
        response.vat_regime,
        Number(response.retention_value || 0),
      );

      if (response.logo_url) {
        const img = new Image();
        img.src = /^https?:\/\//i.test(response.logo_url)
          ? response.logo_url
          : `/assets/img/companies/${encodeURIComponent(response.logo_url)}`;
        doc.addImage(img, "PNG", 10, 10, 50, 15);
      }

      let currentY = 10;
      doc.setFontSize(12);
      doc.setFont("helvetica", "bold");
      doc.text(response.company_name, 70, currentY);
      doc.setFont("helvetica", "normal");
      doc.setFontSize(10);
      currentY += 5;
      doc.text(response.company_address.replace(/\n/g, " "), 70, currentY);
      currentY += 5;
      doc.text(`Tel: ${response.company_phone}`, 70, currentY);
      currentY += 5;
      doc.text(`E-mail: ${response.company_email}`, 70, currentY);
      currentY += 5;
      doc.text(`Contribuinte: ${response.registration_number}`, 70, currentY);

      function generateRandomHash(length = 70) {
        const characters =
          "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
        let hash = "";
        for (let i = 0; i < length; i++) {
          hash += characters.charAt(
            Math.floor(Math.random() * characters.length),
          );
        }
        return hash;
      }

      const qrSize = 40;
      const qrX = 150;
      const qrY = Math.max(currentY - 15, 35);
      const qrBase64 = generateQRCode(
        "../public/proform_public.php?id=" +
          generateRandomHash() +
          "_" +
          response.company_id +
          "/" +
          response.id,
      );
      doc.addImage(qrBase64, "PNG", qrX, qrY, qrSize, qrSize);

      currentY = Math.max(currentY + 10, qrY - 50);
      doc.setFont("helvetica", "bold");
      doc.setFontSize(12);
      doc.text(`Exmo.(s) Sr.(s):`, 10, currentY);
      doc.setFont("helvetica", "normal");
      currentY += 5;
      doc.text(response.client_name, 10, currentY);
      currentY += 5;
      doc.text(response.client_address.replace(/\n/g, " "), 10, currentY);
      currentY += 5;
      doc.text(`Contribuinte: ${response.client_contributor}`, 10, currentY);

      const formatDate = (date) => {
        return new Intl.DateTimeFormat("pt-BR", {
          day: "2-digit",
          month: "short",
          year: "numeric",
        })
          .format(date)
          .replace(/ de /g, " ")
          .replace(/\.$/, "")
          .replace(/\b[a-z]/, (char) => char.toUpperCase());
      };

      const issueDate = new Date(response.issue_date);
      const dueDateObj = new Date(issueDate);
      dueDateObj.setDate(issueDate.getDate() + response.due_date);

      const issueDateFormatted = formatDate(issueDate);
      const dueDateFormatted = formatDate(dueDateObj);

      currentY += 10;
      doc.setFont("helvetica", "bold");
      doc.text(`Proforma n.º ${response.codigo}`, 10, currentY);
      doc.setFont("helvetica", "normal");
      currentY += 5;
      doc.text(`Data de emissão: ${issueDateFormatted}`, 10, currentY);
      currentY += 5;
      doc.text(`Vencimento: ${dueDateFormatted}`, 10, currentY);
      currentY += 5;
      doc.text(
        `Referência: ${response.reference || "Não especificada"}`,
        10,
        currentY,
      );

      doc.setDrawColor(400, 200, 200);
      doc.line(10, currentY + 5, 200, currentY + 5);

      doc.autoTable({
        startY: currentY + 10,
        margin: { left: 10 },
        pageBreak: "auto",
        head: [
          [
            "Código",
            "Descrição",
            "Preço Unitário",
            "Qtd",
            "Taxa/IVA %",
            "Desc. %",
            "Total",
          ],
        ],
        body: response.items.map((item) => {
          const calculated = window.BXDocumentTax.calculateItem(
            item,
            response.vat_regime,
          );
          return [
            item.code,
            item.description,
            formatCurrency(
              item.unit_price,
              response.company_symbol,
              response.company_position,
            ),
            item.quantity,
            calculated.taxRate,
            item.discount,
            formatCurrency(
              calculated.total,
              response.company_symbol,
              response.company_position,
            ),
          ];
        }),
        theme: "striped",
        styles: { fontSize: 10, halign: "center" },
        headStyles: { fillColor: [100, 100, 255], textColor: 255 },
        alternateRowStyles: { fillColor: [240, 240, 240] },
        didDrawPage: function (data) {
          currentY = data.cursor.y;
        },
      });

      const taxDetails = taxSummary.rows.map((tax) => [
        `${tax.rate}%`,
        formatCurrency(
          tax.base,
          response.company_symbol,
          response.company_position,
        ),
        formatCurrency(
          tax.iva,
          response.company_symbol,
          response.company_position,
        ),
        formatCurrency(
          tax.retention,
          response.company_symbol,
          response.company_position,
        ),
        formatCurrency(
          tax.net,
          response.company_symbol,
          response.company_position,
        ),
      ]);

      doc.autoTable({
        startY: doc.lastAutoTable.finalY + 10,
        margin: { left: 10 },
        pageBreak: "auto",
        head: [["Taxa", "Base", "Valor (IVA)", "Retenção", "Líquido"]],
        body: taxDetails,
        theme: "grid",
        styles: { fontSize: 10, halign: "center" },
        headStyles: { fillColor: [100, 100, 255], textColor: 255 },
      });

      const resumoBody = [
        [
          "Total líquido",
          formatCurrency(
            taxSummary.totalSum,
            response.company_symbol,
            response.company_position,
          ),
        ],
        [
          "Desconto",
          formatCurrency(
            taxSummary.totalDiscount,
            response.company_symbol,
            response.company_position,
          ),
        ],
        [
          "Sem Impostos/IVA c/ Desc.",
          formatCurrency(
            taxSummary.subtotal,
            response.company_symbol,
            response.company_position,
          ),
        ],
        [
          "Imposto/IVA:",
          formatCurrency(
            taxSummary.totalTax,
            response.company_symbol,
            response.company_position,
          ),
        ],
      ];

      resumoBody.push([
        "Retenção",
        formatCurrency(
          taxSummary.retention,
          response.company_symbol,
          response.company_position,
        ),
      ]);

      resumoBody.push([
        "Total Geral:",
        formatCurrency(
          taxSummary.finalTotal,
          response.company_symbol,
          response.company_position,
        ),
      ]);

      if (response.currency_company !== response.currency_items) {
        resumoBody.push([
          "Total Convertido:",
          `${formatCurrency(response.converted_total, response.symbol, response.position)} (${response.currency_items})`,
        ]);
      }

      doc.autoTable({
        startY: doc.lastAutoTable.finalY + 10,
        margin: { left: 10 },
        pageBreak: "auto",
        head: [["Descrição", "Valor"]],
        body: resumoBody,
        theme: "grid",
        styles: { fontSize: 10, halign: "center" },
        headStyles: { fillColor: [100, 100, 255], textColor: 255 },
      });

      if (currentY + 30 > doc.internal.pageSize.height) {
        doc.addPage();
        currentY = 20;
      }

      doc.setFontSize(10);
      doc.setFont("helvetica", "bold");
      doc.text("Observações:", 10, doc.lastAutoTable.finalY + 20);

      const pageHeight = doc.internal.pageSize.height;
      currentY = doc.lastAutoTable.finalY + 25;
      const textHeight =
        doc.splitTextToSize(
          response.observation || "Nenhuma observação adicionada.",
          180,
        ).length * 10;

      if (currentY + textHeight > pageHeight) {
        doc.addPage();
        currentY = 20;
        doc.text("Observações (continuação):", 10, currentY);
        currentY += 5;
      }

      doc.setFont("helvetica", "normal");
      doc.text(
        doc.splitTextToSize(
          response.observation || "Nenhuma observação adicionada.",
          180,
        ),
        10,
        currentY,
      );

      doc.save(`Proforma_${response.company_name}_${response.codigo}.pdf`);
    },
    error: function () {
      alert("Erro ao carregar os dados da proforma.");
    },
  });

  function generateQRCode(text) {
    const qr = qrcode(0, "L");
    qr.addData(text);
    qr.make();
    const qrCodeImgTag = qr.createImgTag(5);
    const base64Image = qrCodeImgTag.match(/src="([^"]*)"/)[1];
    return base64Image;
  }
}

// ==================================================
// ELIMINAR PROFORMA
// ==================================================
var proformaToDelete = null;
var proformaCompanyId = null;

var deleteProforma = (id, companyId) => {
  proformaToDelete = id;
  proformaCompanyId = companyId;

  const modal = new bootstrap.Modal(document.getElementById("deleteModal"));
  modal.show();
};

$("#confirmDelete")
  .off("click")
  .on("click", function () {
    if (!proformaToDelete) return;

    $.ajax({
      url: "proform/ajax/delete_invoice.php",
      type: "POST",
      data: { invoice_id: proformaToDelete, company_id: proformaCompanyId },

      success: function (response) {
        const modalEl = document.getElementById("deleteModal");
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();

        if (response.success) {
          Swal.fire({
            icon: "success",
            title: "Proforma eliminada!",
            timer: 1500,
            showConfirmButton: false,
          });

          // Recarrega os dados mantendo o fluxo atual da listagem.
          $(document).trigger("reload-proformas");
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro ao eliminar!",
            timer: 1500,
            showConfirmButton: false,
          });
        }
      },

      error: function () {
        alert("Erro na requisição. Tente novamente.");
      },
    });
  });

// HTML Proforma (mantido, caso usado noutro ponto)
function renderInvoiceHTML(data) {
  const html = `
    <div>
      <h2>${data.company_name}</h2>
      <p>${data.company_address}</p>
      <p>Tel: ${data.company_phone}</p>

      <hr>

      <h3>Proforma nº ${data.codigo}</h3>
      <p>Cliente: ${data.client_name}</p>

      <table border="1" width="100%" cellspacing="0" cellpadding="5">
        <thead>
          <tr>
            <th>Descrição</th>
            <th>Qtd</th>
            <th>Preço</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          ${data.items
            .map(
              (item) => `
              <tr>
                <td>${item.description}</td>
                <td>${item.quantity}</td>
                <td>${item.unit_price}</td>
                <td>${item.unit_price * item.quantity}</td>
              </tr>
            `,
            )
            .join("")}
        </tbody>
      </table>

      <h3>Total: ${data.final_total}</h3>
    </div>
  `;

  $("#proforma-container").html(html);
}

// ==================================================
// EXPORTAÇÃO (Excel/PDF/CSV)
// ==================================================
//
// Mapa: cada tipo de documento do menu "Exportar" -> endpoint
// PHP responsável por gerar Excel/CSV; PDF é gerado no navegador.
//
var EXPORT_CONFIG = {
  invoices: {
    url: "proform/ajax/proformas_export.php",
    buildParams: (format) => ({ formato: format || "excel" }),
  },

  sales_report: {
    url: "proform/ajax/export_sales_report.php",
    buildParams: () => ({}),
    direct: true,
  },
};

/**
 * @param {string} docType - chave em EXPORT_CONFIG (ex.: "invoices", "credit_notes", "sales_report"...)
 * @param {string} [format] - "excel" | "pdf" | "csv"
 */
async function exportFile(docType, format, options = {}) {
  const config = EXPORT_CONFIG[docType];

  if (!config) {
    console.error("Tipo de exportação desconhecido:", docType);
    Swal?.fire?.({
      icon: "error",
      title: "Exportação indisponível",
      text: "Este tipo de exportação ainda não foi configurado.",
      timer: 2000,
      showConfirmButton: false,
    });
    return;
  }

  if (docType === "invoices" && format === "pdf") {
    const rows = options.rows || window.proformasForExport || [];
    if (!rows.length) {
      Swal?.fire?.({
        icon: "info",
        title: "Sem proformas",
        text: "Não há proformas para exportar.",
      });
      return;
    }
    if (!window.jspdf?.jsPDF) {
      Swal?.fire?.({
        icon: "error",
        title: "Exportação indisponível",
        text: "Não foi possível carregar o gerador de PDF. Atualize a página e tente novamente.",
      });
      return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape" });
    const money = (value, symbol, position) => {
      const amount = Number(value || 0).toLocaleString("pt-PT", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
      return position === "right" ? `${amount} ${symbol || ""}` : `${symbol || ""} ${amount}`;
    };
    const date = (value) =>
      value ? new Intl.DateTimeFormat("pt-PT").format(new Date(value)) : "-";

    doc.setFontSize(16);
    doc.text(options.title || "Lista de Proformas", 14, 16);
    doc.autoTable({
      startY: 23,
      head: [["Número", "Emissão", "Vencimento", "Cliente", "Estado", "Moeda", "Total"]],
      body: rows.map((row) => [
        row.codigo || row.reference || row.id,
        date(row.issue_date),
        date(row.due_date),
        row.cliente || "-",
        row.status_invoice || "-",
        row.currency || "-",
        money(row.final_total, row.symbol, row.position),
      ]),
      styles: { fontSize: 8 },
      headStyles: { fillColor: [15, 23, 42] },
    });
    doc.save(options.filename || "proformas.pdf");
    return;
  }

  const query = new URLSearchParams(config.buildParams(format)).toString();
  const finalUrl = query ? `${config.url}?${query}` : config.url;
  const preloader = document.getElementById("preloader");
  const progressBar = document.getElementById("progressBar");
  if (preloader) preloader.style.display = "block";
  if (progressBar) progressBar.style.width = "35%";

  try {
    const response = await fetch(finalUrl, { credentials: "same-origin" });
    const contentType = response.headers.get("content-type") || "";
    if (!response.ok || contentType.includes("application/json")) {
      let message = `Falha ao exportar proformas (HTTP ${response.status}).`;
      try {
        const payload = await response.json();
        message = payload.error || payload.message || message;
      } catch (_) {
        // Mantém o erro HTTP se a resposta não for JSON válido.
      }
      throw new Error(message);
    }

    const blob = await response.blob();
    const disposition = response.headers.get("content-disposition") || "";
    const filename =
      disposition.match(/filename="?([^";]+)"?/i)?.[1] ||
      `proformas.${format === "csv" ? "csv" : "xlsx"}`;
    const downloadUrl = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(downloadUrl);
    if (progressBar) progressBar.style.width = "100%";
  } catch (error) {
    console.error("Erro ao exportar proformas:", error);
    Swal?.fire?.({
      icon: "error",
      title: "Erro na exportação",
      text: error.message || "Não foi possível exportar as proformas.",
    });
  } finally {
    if (preloader) preloader.style.display = "none";
    if (progressBar) progressBar.style.width = "0%";
  }
}

function closeProformaExportMenu() {
  const menu = document.getElementById("exportMenuList");
  const button = document.getElementById("exportMenuBtn");
  menu?.classList.remove("is-open");
  button?.setAttribute("aria-expanded", "false");
}

function exportSelectedProformasCsv() {
  const rows = selectedProformas();
  if (!rows.length) return;

  const cell = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
  const columns = [
    ["Número", (row) => row.codigo || row.id],
    ["Data de emissão", (row) => row.issue_date],
    ["Data de vencimento", (row) => row.due_date],
    ["Cliente", (row) => row.cliente],
    ["Estado", (row) => row.status_invoice],
    ["Moeda", (row) => row.currency],
    ["Total", (row) => row.final_total],
  ];
  const content = [
    columns.map(([label]) => cell(label)).join(";"),
    ...rows.map((row) => columns.map(([, value]) => cell(value(row))).join(";")),
  ].join("\r\n");
  const url = URL.createObjectURL(
    new Blob(["\uFEFF", content], { type: "text/csv;charset=utf-8" }),
  );
  const link = document.createElement("a");
  link.href = url;
  link.download = "proformas_selecionadas.csv";
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 4000);
}

function runProformaExportAction(action) {
  if (action === "selected-pdf") {
    return exportFile("invoices", "pdf", {
      rows: selectedProformas(),
      title: "Proformas selecionadas",
      filename: "proformas_selecionadas.pdf",
    });
  }
  if (action === "selected-csv") return exportSelectedProformasCsv();
  if (action === "list-pdf") return exportFile("invoices", "pdf");
  if (action === "list-excel") return exportFile("invoices", "excel");
  if (action === "list-csv") return exportFile("invoices", "csv");
}

$(document)
  .off(".proformaExport")
  .on("click.proformaExport.spaPage", "#exportMenuBtn", function (event) {
    event.stopPropagation();
    const menu = document.getElementById("exportMenuList");
    const isOpen = menu?.classList.toggle("is-open") ?? false;
    this.setAttribute("aria-expanded", String(isOpen));
    if (!isOpen) closeProformaExportMenu();
  })
  .on("click.proformaExport.spaPage", "#exportMenu [data-export-action], #proformaSelectionBar [data-export-action]", function (event) {
    event.stopPropagation();
    runProformaExportAction(this.dataset.exportAction);
    closeProformaExportMenu();
  })
  .on("change.proformaExport.spaPage", "#selectAll, #invoicesTable .invoice-check", refreshSelectedExportGroup)
  .on("click.proformaExport.spaPage", function (event) {
    if (!event.target.closest("#exportMenu")) closeProformaExportMenu();
  })
  .on("keydown.proformaExport.spaPage", function (event) {
    if (event.key === "Escape") closeProformaExportMenu();
  });
