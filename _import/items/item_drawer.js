/* Painéis laterais de Itens (criar / editar) — Produto / Serviço */
(function ($) {
  "use strict";

  // Este ficheiro é carregado na sidebar (todas as páginas) e no items.php: só inicia uma vez,
  // senão os handlers (abrir, guardar...) ficavam duplicados.
  if (window.__itemDrawerInit) return;
  window.__itemDrawerInit = true;

  // Só arranca quando o DOM estiver completo (os drawers podem vir do head/layout)
  $(function () {

  // Endpoint que devolve os depósitos [{id, name}] ou {data:[...]}. AJUSTE se for diferente.
  const STOCKS_ENDPOINT = "items/ajax/get_stocks.php";

  const $backdrop = $("#drawerBackdrop");
  const $create = $("#itemDrawer");
  const $edit = $("#editItemDrawer");
  const $createForm = $("#itemForm");
  const $editForm = $("#editItemForm");

  // Proteção: se os formulários não existirem no DOM, avisa em vez de rebentar
  if (!$createForm.length || !$editForm.length || !$create.length || !$edit.length) {
    console.error("item_drawer.js: elementos não encontrados no DOM", {
      itemForm: $createForm.length,
      editItemForm: $editForm.length,
      itemDrawer: $create.length,
      editItemDrawer: $edit.length,
    });
    return;
  }

  let codeTouched = false;
  let productFull = false; // Produto: false = lista rápida, true = formulário completo
  let saving = false;

  const TEXT = {
    product: { create: "Novo Produto", edit: "Editar Produto", btn: "Criar Produto", name: "Nome do produto", ph: "Ex: Cadeira de escritório" },
    service: { create: "Novo Serviço", edit: "Editar Serviço", btn: "Criar Serviço", name: "Nome do serviço", ph: "Ex: Consultoria técnica" },
  };

  // Todas as respostas (sucesso, aviso, erro) são toasts
  const toast = (icon, title) =>
    Swal.fire({
      toast: true,
      position: "top-end",
      icon,
      title,
      showConfirmButton: false,
      timer: icon === "success" ? 2500 : 4500,
      timerProgressBar: true,
    });

  // ================= abrir / fechar =================
  function openDrawer($d) {
    $d.addClass("show").attr("aria-hidden", "false");
    $backdrop.addClass("show");
    $("body").addClass("drawer-open");
  }
  function closeDrawers() {
    $(".item-drawer").removeClass("show").attr("aria-hidden", "true");
    $backdrop.removeClass("show");
    $("body").removeClass("drawer-open");
  }
  $(document).on("click", "[data-drawer-close]", closeDrawers);
  $backdrop.on("click", closeDrawers);
  $(document).on("keydown", function (e) {
    // Sem "return false": o jQuery bloquearia a escrita em todos os campos
    if (e.key === "Escape") closeDrawers();
  });

  // ================= tipo: Produto / Serviço =================
  const isService = ($f) => $f.find("[data-type-value]").val() === "service";

  function calcPvp($f) {
    const price = parseFloat($f.find("[name='unit_price']").val()) || 0;
    const tax = parseFloat($f.find("[data-tax]").val()) || 0;
    return Math.round(price * (1 + tax / 100) * 100) / 100;
  }

  function syncStock($f) {
    const svc = isService($f);
    const on = $f.find("[data-track-toggle]").is(":checked");
    $f.find("[data-track-value]").val(!svc && on ? 1 : 0);
    $f.find(".stock-toggle").toggleClass("is-on", on);
    const show = !svc && on;
    $f.find("[data-stock-block]").toggleClass("d-none", !show).find(":input").prop("disabled", !show);
  }

  function setType($f, type) {
    const svc = type === "service";
    const $drawer = $f.closest(".item-drawer");
    const isEdit = $f.is($editForm);

    $f.find(".type-tab").removeClass("active").filter(`[data-type="${type}"]`).addClass("active");

    // item_type real enviado ao servidor
    const kind = $f.find("[data-product-kind]").val() || "product";
    $f.find("[data-type-value]").val(svc ? "service" : kind);

    // blocos exclusivos de Produto
    $f.find("[data-only='product']").toggleClass("d-none", svc);
    $f.find("[data-only='product'][data-disable-hidden]").find(":input").prop("disabled", svc);

    // unidade: serviços usam sempre "service"
    const $unit = $f.find("[data-unit]");
    if (svc) $unit.val("service");
    else if ($unit.val() === "service") $unit.val("unit");

    // textos
    const t = TEXT[type];
    $drawer.find(".drawer-title").text(isEdit ? t.edit : t.create);
    $drawer.find("[data-save-label]").text(t.btn);
    $f.find("[data-name-label]").text(t.name);
    $f.find("[data-name-input]").attr("placeholder", t.ph);

    syncStock($f);
  }

  $(document).on("click", ".type-tab", function () {
    const $f = $(this).closest("form");
    setType($f, $(this).data("type"));
    if ($f.is($createForm)) {
      applyCreateView();
      generateCode();
    }
  });

  // "Tipo de produto" (produto, consumível, ...) alimenta o item_type
  $(document).on("change", "[data-product-kind]", function () {
    const $f = $(this).closest("form");
    if (!isService($f)) $f.find("[data-type-value]").val($(this).val() || "product");
    if ($f.is($createForm)) generateCode();
  });

  $(document).on("change", "[data-track-toggle]", function () {
    syncStock($(this).closest("form"));
  });

  // PVP automático (preço + IVA) enquanto o utilizador não o alterar à mão
  $(document).on("input change", "[name='unit_price'], [data-tax]", function () {
    const $f = $(this).closest("form");
    const $pvp = $f.find("[data-pvp]");
    if (!$pvp.data("touched")) $pvp.val(calcPvp($f).toFixed(2));
  });
  $(document).on("input", "[data-pvp]", function (e) {
    if (e.originalEvent) $(this).data("touched", true);
  });

  // Serviço: PVP = preço + IVA; sem custo/venda
  function prepareSubmit($f) {
    if (isService($f)) {
      $f.find("[name='pvp']").val(calcPvp($f).toFixed(2));
      $f.find("[name='cost_price'], [name='sale_price']").val("");
    }
  }

  // ================= depósitos =================
  function loadStocks($f) {
    const $sel = $f.find("[name='stock_id']");
    if ($sel.find("option").length > 1) return $.Deferred().resolve().promise();
    return $.getJSON(STOCKS_ENDPOINT)
      .done((res) => {
        const list = Array.isArray(res) ? res : res.data || res.stocks || [];
        list.forEach((s) => $sel.append(new Option(s.name, s.id)));
      })
      .fail(() => console.warn("Depósitos não carregados. Verifique STOCKS_ENDPOINT."));
  }

  // ================= código automático =================
  function generateCode() {
    if (codeTouched) return;
    $.getJSON("items/ajax/generate_code.php", {
      item_type: $createForm.find("[data-type-value]").val(),
      stock_id: $createForm.find("[name='stock_id']").val() || 0,
    }).done((r) => r.generated_code && $("#codigo").val(r.generated_code));
  }
  $("#codigo").on("input", () => (codeTouched = true));
  $createForm.on("change", "[name='stock_id']", generateCode);

  // ================= CRIAR =================
  function applyCreateView() {
    // Lista rápida só existe em Produto; Serviço vai direto ao formulário
    const showQuick = !isService($createForm) && !productFull;
    $("#quickAddPanel").toggle(showQuick);
    $("#fullFormWrapper").toggle(!showQuick);
    $("#itemModalFooter").toggle(!showQuick);
  }

  // ================= IVA conforme o regime (vat_regime) =================
  // Regime de Exclusão -> 0% | Simplificado -> 7% | Geral -> 14% (e taxas reduzidas)
  const VAT_RULES = {
    general: { def: 14, options: [[14, "14% - Taxa geral"], [7, "7%"], [5, "5%"], [0, "0% - Isento"]] },
    simplified: { def: 7, options: [[7, "7% - Regime simplificado"]] },
    exclusion: { def: 0, options: [[0, "0% - Exclusão de IVA"]] },
  };

  function getVatRegime() {
    let raw = null;
    try {
      raw = localStorage.getItem("vat_regime");
      if (raw == null) raw = sessionStorage.getItem("vat_regime");
    } catch (e) {}
    if (raw == null) {
      console.warn("vat_regime não encontrado no storage; a usar regime geral.");
      return "general";
    }
    try {
      const j = JSON.parse(raw);
      if (typeof j === "string") raw = j;
      else if (j && typeof j === "object") raw = j.regime || j.code || j.value || j.name || raw;
    } catch (e) {}
    const v = String(raw).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    if (/exclu/.test(v)) return "exclusion";
    if (/simplif/.test(v)) return "simplified";
    return "general";
  }

  function applyVatRegime($f) {
    const rule = VAT_RULES[getVatRegime()];
    const $sel = $f.find("[data-tax]");
    $sel.empty();
    rule.options.forEach(([v, label]) => $sel.append(new Option(label, String(v))));
    $sel.val(String(rule.def));
    $sel.toggleClass("vat-locked", rule.options.length === 1); // regime com taxa única
  }

  function resetCreateForm(keepType) {
    const type = keepType && isService($createForm) ? "service" : "product";
    $createForm[0].reset();
    $createForm.find("[name='item_id']").val(0);
    $createForm.find("[data-track-toggle]").prop("checked", true);
    $createForm.find("[data-pvp]").data("touched", false);
    applyVatRegime($createForm);
    setType($createForm, type);
    codeTouched = false;
    generateCode();
  }

  $(document).on("click", "[data-drawer-open='create']", function (e) {
    e.preventDefault(); // o link da sidebar tem href="#"
    productFull = false;
    resetCreateForm(false);
    applyCreateView();
    loadStocks($createForm);
    openDrawer($create);
  });
  $("#btnCustomItem").on("click", () => { productFull = true; applyCreateView(); generateCode(); });
  $("#btnBackToQuick").on("click", () => { productFull = false; applyCreateView(); });

  // Se o cadastro rápido preencher o formulário por conta própria, acompanhamos o estado
  new MutationObserver(() => {
    if ($("#fullFormWrapper").is(":visible") && !isService($createForm)) productFull = true;
  }).observe(document.getElementById("fullFormWrapper"), { attributes: true, attributeFilter: ["style"] });

  $("#saveItem").on("click", function (e) {
    e.stopImmediatePropagation(); // evita o handler antigo de #saveItem (script.js)
    if (saving) return;
    if (!$createForm[0].reportValidity()) return;
    prepareSubmit($createForm);

    const $btn = $(this).prop("disabled", true);
    saving = true;

    $.post("items/ajax/check_code.php", { codigo: $("#codigo").val() }, null, "json")
      .then((chk) => {
        if (chk && chk.exists) {
          toast("warning", "Já existe um item com este código.");
          return $.Deferred().reject("dup").promise();
        }
        return $.ajax({ url: "items/ajax/save_item.php", method: "POST", data: $createForm.serialize(), dataType: "json" });
      })
      .done((res) => {
        if (res.status === "success") {
          toast("success", res.message || "Item criado com sucesso");
          window.loadItems && window.loadItems();
          resetCreateForm(true); // limpa e mantém o painel aberto (no mesmo tipo)
          $createForm.find("[data-name-input]").trigger("focus");
        } else {
          toast("error", res.message || "Não foi possível guardar.");
        }
      })
      .fail((xhr) => {
        if (xhr === "dup") return;
        let msg = "Erro na requisição.";
        try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
        toast("error", msg);
      })
      .always(() => { saving = false; $btn.prop("disabled", false); });
  });

  // ================= EDITAR =================
  function setTax($f, value) {
    const $sel = $f.find("[data-tax]");
    const v = String(parseFloat(value) || 0);
    if (!$sel.find(`option[value='${v}']`).length) {
      $sel.append(new Option(`${v}%`, v));
      $sel.removeClass("vat-locked");
    }
    $sel.val(v);
  }

  $(document).on("click", ".edit-btn", function () {
    let row;
    try { row = JSON.parse(decodeURIComponent($(this).data("row"))); }
    catch (e) { return console.error("Dados inválidos para edição", e); }

    const set = (n, v) => $editForm.find(`[name='${n}']`).val(v);
    const svc = row.item_type === "service";

    set("id", row.id ?? "");
    if (row.company_id) set("company_id", row.company_id);
    set("codigo", row.code ?? "");
    $("#codigo_display").val(row.code ?? "");
    set("name", row.name ?? "");
    set("descricao", row.description ?? "");
    set("product_kind", svc ? "product" : row.item_type || "product");
    set("subcategory", row.subcategory ?? "");
    set("unit_measure", row.unit_measure ?? "unit");
    set("currency", row.currency ?? "AOA");
    set("quantidade", row.quantity ?? 0);
    set("min_stock", row.min_quantity ?? 1);
    set("unit_price", row.unit_price ?? 0);
    set("cost_price", row.cost_price ?? 0);
    set("sale_price", row.sale_price ?? 0);
    set("pvp", row.pvp ?? 0);
    set("retention", row.retention ?? 0);
    applyVatRegime($editForm);
    setTax($editForm, row.tax);
    $editForm.find("[data-pvp]").data("touched", true); // não sobrescrever o PVP existente

    $editForm.find("[data-track-toggle]").prop("checked", String(row.track_stock ?? 1) !== "0");
    setType($editForm, svc ? "service" : "product");

    loadStocks($editForm).always(() => set("stock_id", row.stock_id ?? ""));
    openDrawer($edit);
  });

  $("#saveEdit").on("click", function () {
    if (saving) return;
    if (!$editForm[0].reportValidity()) return;
    prepareSubmit($editForm);

    const $btn = $(this).prop("disabled", true);
    saving = true;

    $.ajax({ url: "items/ajax/edit_item.php", method: "POST", data: $editForm.serialize(), dataType: "json" })
      .done((res) => {
        if (res.success) {
          closeDrawers(); // na edição, fecha
          toast("success", "Item atualizado com sucesso!");
          window.loadItems && window.loadItems();
        } else {
          toast("error", res.message || "Erro ao atualizar");
        }
      })
      .fail((xhr) => {
        let msg = "Erro na requisição!";
        try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
        toast("error", msg);
      })
      .always(() => { saving = false; $btn.prop("disabled", false); });
  });

  // ================= categorias: scroll horizontal =================
  const cats = document.getElementById("quickCategories");
  if (cats) {
    // roda do rato (vertical) -> scroll horizontal
    cats.addEventListener("wheel", (e) => {
      if (cats.scrollWidth <= cats.clientWidth) return;
      if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
        cats.scrollLeft += e.deltaY;
        e.preventDefault();
      }
    }, { passive: false });

    // arrastar com o rato
    let down = false, startX = 0, startLeft = 0, moved = false;
    cats.addEventListener("mousedown", (e) => { down = true; moved = false; startX = e.pageX; startLeft = cats.scrollLeft; });
    window.addEventListener("mouseup", () => { down = false; cats.classList.remove("dragging"); });
    window.addEventListener("mousemove", (e) => {
      if (!down) return;
      const dx = e.pageX - startX;
      if (Math.abs(dx) > 4) { moved = true; cats.classList.add("dragging"); }
      cats.scrollLeft = startLeft - dx;
    });
    // evita "clique" acidental num botão depois de arrastar
    cats.addEventListener("click", (e) => { if (moved) { e.stopPropagation(); e.preventDefault(); moved = false; } }, true);
  }

  applyVatRegime($createForm);
  applyVatRegime($editForm);
  setType($createForm, "product");
  setType($editForm, "product");
  });
})(jQuery);
