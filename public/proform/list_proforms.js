$(document).ready(function () {
  let proformas = []; // todos os dados vindos do servidor
  let filteredProformas = []; // após filtros/ordenação
  let currentPage = 1;
  let pageSize = 25;
  let sortKey = "codigo";
  let sortDir = "desc";

  loadProformas();

  $(document).on("reload-proformas", loadProformas);

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
    const statusFiltro = ($("#filterStatus").val() || "").toLowerCase().trim();
    const start = $("#filterStartDate").val();
    const end = $("#filterEndDate").val();

    filteredProformas = proformas.filter((row) => {
      const cliente = (row.cliente || "").toLowerCase();
      const status = (row.status_invoice || "").toLowerCase();

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

      const proformaUrl =
        `proform.php?id=` +
        `${String(row.issue_date || "").replaceAll("-", "")}` +
        `/${row.company_id}/${row.id}`;

      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const due = row.due_date ? new Date(row.due_date) : null;
      if (due) due.setHours(0, 0, 0, 0);
      const isOverdue = due && due < today;

      let actionsHtml = `
        <button
          class="btn btn-action text-primary"
          title="Ver"
          onclick="event.stopPropagation();window.location.href='${proformaUrl}'"
        >
          <i class="bi bi-card-list"></i>
        </button>
      `;

      if ((status || "").toLowerCase() === "rascunho") {
        actionsHtml += `
          <button
            class="btn btn-sm text-warning ms-1"
            title="Editar"
            onclick="event.stopPropagation(); window.location.href='create_proform.php?edit_id=${row.id}'"
          >
            <i class="bi bi-pencil"></i>
          </button>

          <button
            class="btn btn-sm text-danger ms-1"
            title="Eliminar"
            onclick="event.stopPropagation(); deleteInvoice(${row.id}, ${row.company_id})"
          >
            <i class="bi bi-trash"></i>
          </button>
        `;
      } else {
        actionsHtml += `
          <button
            class="btn btn-sm text-success ms-1"
            title="PDF"
            onclick="event.stopPropagation(); downloadPDF(${row.id})"
          >
            <i class="bi bi-file-earmark-pdf"></i>
          </button>
        `;
      }

      rowsHtml += `
        <tr class="invoice-row"
            data-id="${row.id}"
            data-cliente="${row.cliente || ""}"
            data-status="${row.status_invoice || ""}"
            data-issue-date="${row.issue_date || ""}">

          <td>
            <div class="d-flex align-items-center gap-3">
              <input
                type="checkbox"
                class="invoice-check"
                data-id="${row.id}"
                value="${row.id}"
              >

              <div
                class="icon-statusFatura p-2 py-1"
                data-status="${status}"
                style="background:${row.color || "#000"}; color:${row.text_color || "#fff"};"
                data-bs-toggle="tooltip"
                data-bs-title="${status}"
              >
                ${status.charAt(0).toUpperCase()}
              </div>
            </div>
          </td>

          <td>${row.codigo || "-"}</td>
          <td>${row.cliente || "-"}</td>
          <td>${row.issue_date ? new Date(row.issue_date).toLocaleDateString("pt-BR") : "-"}</td>

          <td>
            <span class="${isOverdue ? "text-danger" : ""}">
              ${row.due_date ? new Date(row.due_date).toLocaleDateString("pt-BR") : "-"}
            </span>
          </td>

          <td>${row.currency || "-"}</td>

          <td>${formatCurrency(Number(row.final_total || 0), row.symbol || "", row.position || "left")}</td>

          <td>
            <div class="d-flex justify-content-end gap-2">
              ${actionsHtml}
            </div>
          </td>
        </tr>
      `;
    });

    $tbody.html(rowsHtml);

    $("#tableInfo").text(`${start + 1}–${end} de ${totalItems}`);
    renderPagination(totalPages);
    updateSortIcons();

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      bootstrap.Tooltip.getOrCreateInstance(el);
    });

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

  $(document).on("click", "#tablePagination .page-link", function (e) {
    e.preventDefault();
    const page = parseInt($(this).data("page"), 10);
    const $li = $(this).closest("li");
    if (!page || $li.hasClass("disabled") || $li.hasClass("active")) return;
    currentPage = page;
    renderTable();
  });

  $(document).on("change", "#pageSizeSelect", function () {
    pageSize = parseInt($(this).val(), 10) || 25;
    currentPage = 1;
    renderTable();
  });

  // ==================================================
  // ORDENAÇÃO POR COLUNA
  // ==================================================
  $(document).on("click", "#invoicesTable thead th[data-key]", function () {
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
  $("#invoicesTable tbody").on("click", "tr.invoice-row", function (e) {
    const $target = $(e.target);

    if ($target.closest("button").length || $target.closest("input").length) {
      return;
    }

    const id = $(this).data("id");
    const row = filteredProformas.find((r) => String(r.id) === String(id));
    if (!row) return;

    const url =
      `proform.php?id=` +
      `${String(row.issue_date || "").replaceAll("-", "")}` +
      `/${row.company_id}/${row.id}`;

    window.location.href = url;
  });

  // ==================================================
  // SELECIONAR TODOS (apenas a página atual)
  // ==================================================
  $(document).on("click", "#selectAll", function () {
    const isChecked = $(this).is(":checked");
    $('#invoicesTable tbody input[type="checkbox"]').prop("checked", isChecked);
  });

  $("#invoicesTable tbody").on("change", 'input[type="checkbox"]', function () {
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
      if (response.error) {
        alert(response.error);
        return;
      }

      const { jsPDF } = window.jspdf;
      const doc = new jsPDF();

      if (response.logo_url) {
        const img = new Image();
        img.src = `assets/img/companies/${response.logo_url}`;
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
        body: response.items.map((item) => [
          item.code,
          item.description,
          formatCurrency(
            item.unit_price,
            response.company_symbol,
            response.company_position,
          ),
          item.quantity,
          item.tax,
          item.discount,
          formatCurrency(
            item.unit_price * item.quantity,
            response.company_symbol,
            response.company_position,
          ),
        ]),
        theme: "striped",
        styles: { fontSize: 10, halign: "center" },
        headStyles: { fillColor: [100, 100, 255], textColor: 255 },
        alternateRowStyles: { fillColor: [240, 240, 240] },
        didDrawPage: function (data) {
          currentY = data.cursor.y;
        },
      });

      const taxDetails = response.tax_details.map((tax) => [
        `${tax.tax_rate}%`,
        formatCurrency(
          tax.tax_base,
          response.company_symbol,
          response.company_position,
        ),
        formatCurrency(
          tax.tax_value,
          response.company_symbol,
          response.company_position,
        ),
      ]);

      if (response.tax_details[0]?.retention_rate) {
        taxDetails.push([
          `Retenção (${response.tax_details[0].retention_rate}%)`,
          formatCurrency(
            response.tax_details[0].total_sum,
            response.company_symbol,
            response.company_position,
          ),
          formatCurrency(
            response.tax_details[0].retention_value,
            response.company_symbol,
            response.company_position,
          ),
        ]);
      }

      doc.autoTable({
        startY: doc.lastAutoTable.finalY + 10,
        margin: { left: 10 },
        pageBreak: "auto",
        head: [["Taxa/Imposto", "Base", "Valor"]],
        body: taxDetails,
        theme: "grid",
        styles: { fontSize: 10, halign: "center" },
        headStyles: { fillColor: [100, 100, 255], textColor: 255 },
      });

      const resumoBody = [
        [
          "Total líquido",
          formatCurrency(
            response.total_sum,
            response.company_symbol,
            response.company_position,
          ),
        ],
        [
          "Desconto",
          formatCurrency(
            response.total_discount,
            response.company_symbol,
            response.company_position,
          ),
        ],
        [
          "Sem Impostos/IVA c/ Desc.",
          formatCurrency(
            response.total_sum - response.total_discount,
            response.company_symbol,
            response.company_position,
          ),
        ],
        [
          "Imposto/IVA:",
          formatCurrency(
            response.total_tax,
            response.company_symbol,
            response.company_position,
          ),
        ],
      ];

      resumoBody.push([
        "Retenção",
        formatCurrency(
          response.retention_value,
          response.company_symbol,
          response.company_position,
        ),
      ]);

      resumoBody.push([
        "Total Geral:",
        formatCurrency(
          response.final_total,
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
let invoiceToDelete = null;
let invoice_companyId = null;

const deleteInvoice = (id, companyId) => {
  invoiceToDelete = id;
  invoice_companyId = companyId;

  const modal = new bootstrap.Modal(document.getElementById("deleteModal"));
  modal.show();
};

$("#confirmDelete")
  .off("click")
  .on("click", function () {
    if (!invoiceToDelete) return;

    $.ajax({
      url: "proform/ajax/delete_invoice.php",
      type: "POST",
      data: { invoice_id: invoiceToDelete, company_id: invoice_companyId },

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

          // recarrega os dados sem DataTables
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
// EXPORTAÇÃO (Excel/PDF/CSV) COM PROGRESSO
// ==================================================
//
// Mapa: cada tipo de documento do menu "Exportar" -> endpoint
// PHP responsável por gerar o ficheiro, e (quando aplicável)
// o endpoint que reporta o progresso da geração.
//
// "direct: true" = o próprio endpoint já faz o download completo
// numa única chamada (ex.: relatório de vendas), pelo que não
// faz sentido mostrar a barra de progresso a "fingir" 0% a 100%.
//
const EXPORT_CONFIG = {
  invoices: {
    url: "proform/ajax/proformas_export.php",
    progressUrl: "proform/ajax/proformas_export.php?status=1",
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
 * @param {string} [format] - "excel" | "pdf" | "csv" (só é usado pelos tipos que suportam formato)
 */
function exportFile(docType, format) {
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

  const query = new URLSearchParams(config.buildParams(format)).toString();
  const finalUrl = query ? `${config.url}?${query}` : config.url;

  // Download direto, sem barra de progresso (ex.: relatório de vendas,
  // ou tipos cujo endpoint ainda não tem geração assíncrona)
  if (config.direct || !config.progressUrl) {
    window.location.href = finalUrl;
    return;
  }

  const preloader = document.getElementById("preloader");
  const progressBar = document.getElementById("progressBar");

  preloader.style.display = "block";
  progressBar.style.width = "0%";

  let checkProgress = setInterval(() => {
    fetch(config.progressUrl)
      .then((res) => res.json())
      .then((data) => {
        progressBar.style.width = data.progress + "%";

        if (data.progress >= 100) {
          clearInterval(checkProgress);
          setTimeout(() => {
            preloader.style.display = "none";
          }, 500);
        }
      })
      .catch(() => {
        clearInterval(checkProgress);
        preloader.style.display = "none";
      });
  }, 1000);

  setTimeout(() => {
    window.location.href = finalUrl;
  }, 2000);
}
