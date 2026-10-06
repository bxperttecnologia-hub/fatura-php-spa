$(document).ready(function () {
  carregarItens();

  $("#addRow").on("click", function () {
    $("#formNovoItem")[0].reset();
    carregarMoedas("AOA").then((options) => {
      $("#selectMoeda").html(options);
    });
    const modal = new bootstrap.Modal(document.getElementById("modalNovoItem"));
    modal.show();
  });

  $("#btnSalvarItem").on("click", function () {
    const $btn = $(this);
    if ($btn.prop("disabled")) return;

    $btn.prop("disabled", true).text("Salvando...");

    const form = $("#formNovoItem");
    const dados = {
      id: null,
      code: form.find('[name="code"]').val().trim(),
      name: form.find('[name="name"]').val().trim(),
      category: form.find('[name="category"]').val().trim(),
      quantity: parseInt(form.find('[name="quantity"]').val()) || 0,
      min_quantity: parseInt(form.find('[name="min_quantity"]').val()) || 0,
      unit_price: parseFloat(form.find('[name="unit_price"]').val()) || 0,
      currency: form.find('[name="currency"]').val() || "AOA",
    };

    if (!dados.name) {
      Swal.fire("Preencha o nome do produto/serviço.", "", "warning");
      $btn.prop("disabled", false).text("Salvar Produto/Serviço");
      return;
    }

    mostrarStatusSalvando();

    $.post(
      "stock/ajax/stock_items.php",
      {
        action: "save",
        stock_id: STOCK_ID,
        items: JSON.stringify([dados]),
      },
      function (res) {
        if (res.success) {
          $("#modalNovoItem").modal("hide");
          carregarItens(() => {
            const ultimaLinha = $("#stockTable tbody tr").last();
            ultimaLinha.addClass("tr-recente");
            $("html, body").animate(
              { scrollTop: ultimaLinha.offset().top - 100 },
              600,
            );
            setTimeout(() => ultimaLinha.removeClass("tr-recente"), 2000);
          });
          mostrarStatusSalvo();
        } else {
          Swal.fire("Erro ao salvar o produto/serviço.", "", "error");
        }

        $btn.prop("disabled", false).text("Salvar Produto/Serviço");
      },
      "json",
    );
  });

  $("#saveAll").on("click", function () {
    saveAutomaticamente();
  });
});

function carregarItens(callback = null) {
  $.get(
    "stock/ajax/stock_items.php",
    { action: "list", stock_id: STOCK_ID },
    function (res) {
      $("#stockName")
        .text(res.stock?.name || "")
        .css("color", res.stock?.color || "#007abd"); // aplica a cor no texto
      $(".cor-estoque").each(function () {
        this.style.setProperty(
          "background-color",
          res.stock?.color,
          "important",
        );
        this.style.setProperty("border-color", res.stock?.color, "important");
      });

      // Atualiza info de modificação no cabeçalho
      if (res.stock?.updated_at) {
        const d = new Date(res.stock.updated_at);
        $("#lastUpdate").text(
          d.toLocaleDateString("pt-BR") +
            " " +
            d.toLocaleTimeString("pt-BR", {
              hour: "2-digit",
              minute: "2-digit",
            }),
        );
      }
      if (res.stock?.updated_by) {
        $("#lastUser").text(res.stock.updated_by);
      }

      $(" #stockTable thead th").each(function () {
        this.style.setProperty(
          "background-color",
          res.stock?.color,
          "important",
        );
        this.style.setProperty("border-color", res.stock?.color, "important");
      });

      const tbody = $("#stockTable tbody");
      tbody.empty();

      res.items.forEach((item) => {
        const row = $(gerarLinha(item));
        tbody.append(row);

        carregarMoedas(item?.iso_code || "").then((options) => {
          row.find(".currency-select").html(options);
        });
      });

      updateGrandTotal();

      if (callback) callback();
    },
    "json",
  );
}

function gerarLinha(item) {
  const id = item?.id || "";
  const code = item?.code || "";
  const name = item?.name || "";
  const category = item?.item_type || "";
  const tax =
    String(item?.vat_regime || "").toLowerCase() === "geral"
      ? parseFloat(item?.tax) || 0
      : 0;
  const quantity = item?.quantity || 0;
  const min_quantity = item?.min_quantity ?? 1;
  const price = item?.unit_price || 0;
  const iso_code = item?.iso_code || "AOA";
  const symbol = item?.symbol || "Kz";
  const position = item?.position || "left";
  const total = formatCurrency(quantity * price, symbol, position);

  console.log(item.tax);

  return `
    <tr data-id="${id}">

  <td>
    <span class="view">${code}</span>
    <input type="text" disabled class="edit form-control d-none item-code" value="${code}">
  </td>

  <td>
    <span class="view">${name}</span>
    <input type="text" class="edit form-control d-none item-name" value="${name}">
  </td>

  <td>
    <span class="view">${category == "product" ? "Produto" : ""}</span>
    <input type="text" disabled class="edit form-control d-none item-category" value="${category == "product" ? "Produto" : ""}">
  </td>

  <td>
  <span class="view">${quantity}</span>
  <input type="number" class="edit form-control d-none item-quantity" value="${quantity}">
  </td>
  
  <td>
  <span class="view">${min_quantity}</span>
    <input type="number" class="edit form-control d-none item-min-quantity" value="${min_quantity}">
  </td>
  
  <td>
  <span class="view">${tax}</span>
    <select 
      class="edit form-select sm-select d-none item-tax"
      name="tax"
      id="tax"
    >
      <option value="5" ${tax === 5 ? "selected" : ""}>5%</option>

      <option value="14" ${tax === 14 ? "selected" : ""}>14%</option>

      <option value="0" ${tax === 0 ? "selected" : ""}>0%</option>
    </select>
  </td>

  <td>
    <span class="view">${formatCurrency(price, symbol, position)}</span>
    <input type="number" class="edit form-control d-none item-price" value="${price}">
  </td>

  <td class="item-total fw-bold text-success">
    ${total}
  </td>

  <td>
    <button class="btn btn-sm btn-edit">
      <i class="bi bi-pencil"></i>
    </button>

    <button class="btn text-success btn-sm btn-save d-none">
      <i class="bi bi-check-lg"></i>
    </button>

    <button class="btn text-danger btn-sm btn-cancel d-none">
      <i class="bi bi-x-lg"></i>
    </button>

    <button class="btn btn-sm btn-delete">
      <i class="bi bi-trash"></i>
    </button>
  </td>

</tr>
  `;
}

$(document).on("click", ".btn-edit", function () {
  const row = $(this).closest("tr");

  // esconder textos
  row.find(".view").addClass("d-none");

  // mostrar inputs
  row.find(".edit").removeClass("d-none");

  // trocar botões
  row.find(".btn-edit").addClass("d-none");
  row.find(".btn-save, .btn-cancel").removeClass("d-none");
});

$(document).on("click", ".btn-cancel", function () {
  const row = $(this).closest("tr");

  // voltar valores originais
  row.find(".edit").each(function () {
    const input = $(this);
    const span = input.siblings(".view");

    input.val(span.text());
  });

  // restaurar UI
  row.find(".view").removeClass("d-none");
  row.find(".edit").addClass("d-none");

  row.find(".btn-edit").removeClass("d-none");
  row.find(".btn-save, .btn-cancel").addClass("d-none");
});

$(document).on("click", ".btn-save", function () {
  saveAutomaticamente();
});

$(document).on("click", ".btn-mover", function () {
  const itemId = $(this).data("id");
  const $linha = $(this).closest("tr");

  if (!itemId) {
    Swal.fire("Produto/Serviço ainda não foi salvo.", "", "info");
    return;
  }

  const nomeItem = $linha.find(".item-name").val().trim();
  const qtdDisponivel = parseInt($linha.find(".item-quantity").val()) || 0;

  $.getJSON(
    "stock/ajax/stock_controller.php",
    { action: "list_transfer", current_stock: STOCK_ID },
    function (res) {
      if (!res.success || !res.estoques.length) {
        Swal.fire("Nenhum stock disponível para transferência.", "", "info");
        return;
      }

      const options = res.estoques
        .map((e) => `<option value="${e.id}">${e.name}</option>`)
        .join("");

      Swal.fire({
        title: "Transferir Produto/Serviço",
        html: `
        <p>Produto/Serviço: <b>"${nomeItem}"</b><br>Quantidade disponível: <b>${qtdDisponivel}</b></p>
        <label for="quantidadeMover">Quantas unidades deseja mover?</label>
        <input type="number" id="quantidadeMover" class="form-control mb-3" value="${qtdDisponivel}" min="1" max="${qtdDisponivel}">
        
        <label for="destinoEstoque">Stock de destino:</label>
        <select id="destinoEstoque" class="form-select mt-1">${options}</select>
      `,
        showCancelButton: true,
        confirmButtonText: "Mover",
        cancelButtonText: "Cancelar",
        preConfirm: () => {
          const destino = $("#destinoEstoque").val();
          const qtdMover = parseInt($("#quantidadeMover").val());

          if (!destino) {
            Swal.showValidationMessage("Escolha um stock de destino.");
            return false;
          }

          if (!qtdMover || qtdMover < 1 || qtdMover > qtdDisponivel) {
            Swal.showValidationMessage(
              `Quantidade deve ser entre 1 e ${qtdDisponivel}`,
            );
            return false;
          }

          return { destino, qtdMover };
        },
      }).then((result) => {
        if (!result.isConfirmed) return;

        const destino = result.value.destino;
        const qtdMover = result.value.qtdMover;

        $.post(
          "stock/ajax/move_item.php",
          {
            item_id: itemId,
            from_stock: STOCK_ID,
            to_stock: destino,
            quantidade: qtdMover,
          },
          function (res) {
            if (res.success) {
              if (parseInt(destino) === parseInt(STOCK_ID)) {
                // Atualiza a tabela porque o estoque de destino é o atual
                carregarItens(() => {
                  const ultimaLinha = $("#stockTable tbody tr").last();
                  ultimaLinha.addClass("tr-recente");
                  $("html, body").animate(
                    { scrollTop: ultimaLinha.offset().top - 100 },
                    600,
                  );
                  setTimeout(() => ultimaLinha.removeClass("tr-recente"), 2000);
                });
              } else {
                // Remove visualmente do estoque de origem
                $linha.fadeOut(300, () => {
                  $linha.remove();
                  updateGrandTotal();
                });
              }

              mostrarStatusSalvo();
            } else {
              Swal.fire(
                "Erro ao mover o produto/serviço.",
                res.message || "",
                "error",
              );
            }
          },
          "json",
        );
      });
    },
  );
});

$(document).on("click", ".btn-deletar", function () {
  const $tr = $(this).closest("tr");
  const id = $(this).data("id");

  if (!id) {
    // Se não tem ID ainda, só remove visualmente
    $tr.remove();
    updateGrandTotal();
    return;
  }

  Swal.fire({
    title: "Tem certeza?",
    text: "Você quer remover este item do stock?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sim, remover",
    cancelButtonText: "Cancelar",
  }).then((result) => {
    if (result.isConfirmed) {
      $.post(
        "stock/ajax/delete_item.php",
        {
          stock_id: STOCK_ID,
          id: id,
        },
        function (res) {
          if (res.success) {
            $tr.fadeOut(300, () => {
              $tr.remove();
              updateGrandTotal();
            });
            mostrarStatusSalvo();
          } else {
            Swal.fire("Erro ao remover o produto/serviço.", "", "error");
          }
        },
        "json",
      );
    }
  });
});

function alterarQtd(btn, delta) {
  const input = $(btn).siblings("input");
  let val = parseInt(input.val()) || 0;
  val = Math.max(0, val + delta);
  input.val(val).trigger("input");
}

function atualizarTotal(input, symbol, position) {
  const row = $(input).closest("tr");
  const quantity = parseInt(row.find(".item-quantity").val()) || 0;
  const price = parseFloat(row.find(".item-price").val()) || 0;
  row
    .find(".item-total")
    .text(formatCurrency(quantity * price, symbol, position));
  updateGrandTotal();
}

function removerLinha(btn) {
  $(btn).closest("tr").remove();
  updateGrandTotal();
}

function carregarMoedas(selectedIso = "AOA") {
  const cacheKey = "moedasCache";

  if (sessionStorage.getItem(cacheKey)) {
    const data = JSON.parse(sessionStorage.getItem(cacheKey));
    return Promise.resolve(gerarOpcoesMoeda(data, selectedIso));
  }

  return $.getJSON("assets/ajax/get_currencies.php").then((data) => {
    sessionStorage.setItem(cacheKey, JSON.stringify(data));
    return gerarOpcoesMoeda(data, selectedIso);
  });
}

function gerarOpcoesMoeda(data, selectedIso) {
  return data
    .map((row) => {
      const selected = row.iso_code === selectedIso ? "selected" : "";
      const label = `(${row.iso_code}) ${row.currency}`;
      return `<option value="${row.iso_code}" ${selected}>${label}</option>`;
    })
    .join("");
}

let saveTimeout = null;
let statusAtual = "salvo";
let mostrarBotaoTimeout = null;

/**
 * UI BASE (reutilizável)
 */
function atualizarStatusUI({ icon, text, loading = false, type = "muted" }) {
  const html = `
    <i class="bi ${icon} ${loading ? "spinner-border spinner-border-sm" : ""}"></i>
    <span class="status-text text-${type}">
      ${text}
      ${loading ? '<span class="dots"></span>' : ""}
    </span>
  `;

  $("#saveStatus").html(html);
}

/**
 * NÃO SALVO
 */
function marcarComoNaoSalvo(showAlert = false) {
  if (statusAtual === "nao-salvo") return;

  statusAtual = "nao-salvo";

  atualizarStatusUI({
    icon: "bi-cloud-slash",
    text: "Não salvo",
    type: "danger",
  });

  $("#saveAllManual").removeClass("d-none").addClass("d-inline-flex");

  if (showAlert) {
    Swal.fire({
      icon: "warning",
      title: "Alterações não salvas",
      text: "Existem alterações pendentes.",
      timer: 1500,
      showConfirmButton: false,
    });
  }
}

/**
 * SALVANDO (LOADING REAL)
 */
function mostrarStatusSalvando(text = "Salvando") {
  statusAtual = "salvando";

  atualizarStatusUI({
    icon: "bi-cloud-upload",
    text: `${text}...`,
    loading: true,
    type: "primary",
  });

  $("#saveAllManual").addClass("d-none").removeClass("d-inline-flex");

  Swal.fire({
    title: "A salvar...",
    text: "Por favor aguarde",
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });
}

/**
 * SALVO COM SUCESSO
 */
function mostrarStatusSalvo(message = "Salvo com sucesso") {
  statusAtual = "salvo";

  Swal.close();

  atualizarStatusUI({
    icon: "bi-cloud-check",
    text: "Salvo",
    type: "success",
  });

  Swal.fire({
    icon: "success",
    title: message,
    timer: 1200,
    showConfirmButton: false,
    toast: true,
    position: "top-end",
  });

  clearTimeout(mostrarBotaoTimeout);

  mostrarBotaoTimeout = setTimeout(() => {
    if (statusAtual === "nao-salvo") {
      $("#saveAllManual").removeClass("d-none").addClass("d-inline-flex");
    }
  }, 3000);
}

/**
 * ERRO AO SALVAR
 */
function mostrarErroAoSalvar(message = "Erro ao salvar dados") {
  statusAtual = "nao-salvo";

  Swal.close();

  atualizarStatusUI({
    icon: "bi-cloud-slash",
    text: "Erro ao salvar",
    type: "danger",
  });

  $("#saveAllManual").removeClass("d-none").addClass("d-inline-flex");

  Swal.fire({
    icon: "error",
    title: "Falha",
    text: message,
  });
}

let isSaving = false;
let hasPendingChanges = false;
let refreshTimeout = null;

function triggerAutoSave() {
  clearTimeout(saveTimeout);

  saveTimeout = setTimeout(() => {
    saveAutomaticamente();
  }, 1000);
}

function safeVal(el) {
  return (el?.val?.() || "").toString().trim();
}

/**
 * AUTO SAVE
 */
function saveAutomaticamente() {
  if (isSaving) {
    hasPendingChanges = true;
    return;
  }

  isSaving = true;

  const dados = [];

  $("#stockTable tbody tr").each(function () {
    const $tr = $(this);

    const name = safeVal($tr.find(".item-name"));
    if (!name) return;

    const price = parseFloat($tr.find(".item-price").val()) || 0;
    const tax = parseFloat($tr.find("select[name='tax']").val()) || 0;

    const quantity =
      parseInt(
        $tr.find("input[name='quantity'], .item-quantity").first().val(),
      ) || 0;

    const min_quantity =
      parseInt(
        $tr
          .find("input[name='min_quantity'], .item-min-quantity")
          .first()
          .val(),
      ) || 1;

    const pvp = Number((price + (price * tax) / 100).toFixed(2));

    dados.push({
      id: $tr.data("id") || null,
      name,
      tax,
      quantity,
      min_quantity,
      price,
      pvp,
    });
  });

  if (!dados.length) {
    isSaving = false;
    return;
  }

  //  UI feedback imediato
  if (typeof mostrarStatusSalvando === "function") {
    mostrarStatusSalvando();
  }

  $.ajax({
    url: "items/ajax/update_item_inline.php",
    method: "POST",
    data: {
      items: JSON.stringify(dados),
    },
    dataType: "json",

    success: function (res) {
      if (res?.success) {
        Swal.fire({
          icon: "success",
          title: "Atualizado com sucesso",
          text: `${res.updated || 0} item(s) atualizado(s)`,
          timer: 1500,
          showConfirmButton: false,
          toast: true,
          position: "top-end",
        });

        // ❌ cancela refresh anterior (evita spam)
        clearTimeout(refreshTimeout);

        // ⏳ refresh só depois de 10 segundos
        refreshTimeout = setTimeout(() => {
          if (typeof loadStockItems === "function") {
            loadStockItems();
          }
        }, 10000);

        mostrarStatusSalvo();
      } else {
        Swal.fire({
          icon: "error",
          title: "Erro ao salvar",
          text: res?.error || "Erro desconhecido",
        });

        mostrarErroAoSalvar();
      }
    },

    error: function (xhr) {
      Swal.fire({
        icon: "error",
        title: "Erro de conexão",
        text: "Não foi possível salvar os dados",
      });

      if (typeof mostrarErroAoSalvar === "function") {
        mostrarErroAoSalvar();
      }

      console.error("AJAX ERROR:", xhr.responseText);
    },

    complete: function () {
      isSaving = false;

      // 🔁 evita loop infinito com autosave
      if (hasPendingChanges) {
        hasPendingChanges = false;

        setTimeout(() => {
          saveAutomaticamente();
        }, 500);
      }
    },
  });
}

// auto-save
$(document).on(
  "input change",
  ".item-code, .item-name, .item-category, .item-min-quantity, .item-tax, .item-quantity, .item-price, .item-currency",
  function () {
    triggerAutoSave();
  },
);

// botão manual
$("#saveAllManual").on("click", function () {
  saveAutomaticamente();
});

// Ctrl + S
$(document).on("keydown", function (e) {
  if (e.ctrlKey && e.key.toLowerCase() === "s") {
    e.preventDefault();
    saveAutomaticamente();
  }
});

function updateGrandTotal() {
  const tbody = $("#stockTable tbody");
  let totalQuantidade = 0;
  let totalGeral = 0;

  let symbol = "Kz";
  let position = "left";

  const firstRow = tbody.find("tr:first");
  if (firstRow.length) {
    const oninputAttr = firstRow.find(".item-quantity").attr("oninput");
    if (oninputAttr) {
      const matches = oninputAttr.match(/'(.*?)',\s*'(.*?)'/);
      if (matches && matches.length > 2) {
        symbol = matches[1];
        position = matches[2];
      }
    }
  }

  tbody.find("tr").each(function () {
    const row = $(this);
    const quantity = parseInt(row.find(".item-quantity").val()) || 0;
    const price = parseFloat(row.find(".item-price").val()) || 0;

    totalQuantidade += quantity;
    totalGeral += quantity * price;
  });

  const tfoot = $("#stockTable tfoot");
  tfoot.empty();

  const totalRow = `
        <tr style="font-weight: bold;">
            <td colspan="2" class="text-end"><strong>Total Geral</strong></td>
            <td><strong>${totalQuantidade}</strong></td>
            <td></td>
            <td><strong>${formatCurrency(totalGeral, symbol, position)}</strong></td>
            <td></td>
        </tr>
    `;
  tfoot.append(totalRow);
}
