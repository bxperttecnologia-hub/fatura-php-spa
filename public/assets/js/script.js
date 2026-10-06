if (!("fetch" in window)) {
  var script = document.createElement("script");
  script.src =
    "https://cdnjs.cloudflare.com/ajax/libs/core-js/3.29.1/minified.js";
  script.defer = true;
  document.head.appendChild(script);
}

$(document).ready(function () {
  $("#unidade, #retencao, #taxa").select2({
    width: "100%",
  });

  // =========================
  // VALIDATION HELPERS
  // =========================
  function showError(msg) {
    Swal.fire("Atenção", msg, "warning");
  }

  function validateForm() {
    const itemType = $("#category").val();
    const name = $("input[name='name']").val();
    const code = $("#codigo").val();

    if (!name) {
      showError("Nome é obrigatório");
      return false;
    }

    // Código continua obrigatório para produtos.
    // Stock/depósito é OPCIONAL: o item pode ser criado sem stock
    // e associado a um depósito mais tarde.
    if (itemType === "product" && !code) {
      showError("Código é obrigatório para produtos");
      return false;
    }

    return true;
  }

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

        setTimeout(() => {
          document
            .querySelectorAll(".modal-backdrop")
            .forEach((el) => el.remove());
          document.body.classList.remove("modal-open");
          document.body.style.removeProperty("padding-right");
          document.body.style.removeProperty("overflow");
        }, 300);

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

  // =========================
  // ACTIVE MENU
  // =========================
  let currentUrl = window.location.pathname.split("/").pop();

  $(".menu li, .submenu li").removeClass("active");

  let activeLink = $("a[href='" + currentUrl + "']");

  if (activeLink.length) {
    activeLink.closest("li").addClass("active");

    let parentDropdown = activeLink.closest(".dropdown");

    if (parentDropdown.length) {
      parentDropdown.addClass("open");
      parentDropdown.find(".submenu").slideDown(300);
    }
  }

  // =========================
  // DROPDOWN MENU
  // =========================
  $(".dropdown > a").on("click", function (event) {
    event.preventDefault();

    let parentLi = $(this).closest("li");
    let submenu = parentLi.find(".submenu");

    if (submenu.is(":visible")) {
      submenu.slideUp(300);
      parentLi.removeClass("open");
    } else {
      $(".submenu").slideUp(300);
      $(".dropdown").removeClass("open");

      submenu.slideDown(300);
      parentLi.addClass("open");
    }
  });

  // =========================
  // FORM DIRTY TRACKING
  // =========================
  let isFormDirty = false;

  $("#itemForm input, #itemForm select, #itemForm textarea").on(
    "input change",
    function () {
      isFormDirty = true;
    },
  );

  // =========================
  // TOOLTIP
  // =========================
  $("body").tooltip({
    selector: '[data-bs-toggle="tooltip"]',
  });

  // =========================
  // TIMEZONE
  // =========================
  let userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  $.ajax({
    url: "assets/ajax/set_timezone.php",
    method: "POST",
    data: { timezone: userTimezone },
    success: function (response) {
      console.log("Fuso horário atualizado:", response);
    },
  });
});

function formatDateTimeToBrazilian(dateTimeString) {
  if (!dateTimeString) return "Data inválida"; // Verifica se a string está vazia ou indefinida

  const [date, time] = dateTimeString.split(" "); // Separa a data da hora
  const [year, month, day] = date.split("-"); // Separa ano, mês e dia
  return `${day}/${month}/${year} ${time}`; // Retorna no formato brasileiro
}

// Formatar os valores com a moeda na posição correta
function formatCurrency(value, currencySymbol, currencyPosition) {
  let formattedValue = value.toLocaleString(undefined, {
    minimumFractionDigits: 2,
  });

  return currencyPosition === "left"
    ? `${currencySymbol} ${formattedValue}`
    : `${formattedValue} ${currencySymbol}`;
}

// btn.addEventListener("click", () => {
//   sidebar.classList.toggle("mobile-active");
//   overlay.classList.toggle("show");
//   document.body.classList.toggle("sidebar-open");
// });

// overlay.addEventListener("click", () => {
//   sidebar.classList.remove("mobile-active");
//   overlay.classList.remove("show");
//   document.body.classList.remove("sidebar-open");
// });
