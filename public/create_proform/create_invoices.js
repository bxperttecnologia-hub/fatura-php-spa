$(document).ready(function () {
  const vat_regime = JSON.parse(localStorage.getItem("vat_regime"));
  const company_id = document.querySelector("input[id=company_id]").value;

  let countryMap = {};
  let invoiceId = null;

  initializeTooltips();

  // ----- Cliente (modal em blocos) e Cliente X (anónimo) -----
  const ANON_VALUE = "anon"; // valor do <option> virtual "Cliente X"
  const ANON_EMAIL = "cliente-x@anonimo.local"; // igual a ANON_CONTACT_EMAIL em anonymous_contact.php
  const CONTACT_LOGO_BASE = ""; // prefixo do caminho, se a BD só guardar o nome do ficheiro (ex.: "uploads/contacts/")
  let contactsCache = [];
  let anonContactId = null;
  let contactsLoaded = false;

  // Rascunho tal como estava ao abrir a página: repor as linhas grava o rascunho
  // antes de os clientes carregarem e apagaria o cliente escolhido.
  let bootDraft = null;
  try {
    bootDraft = JSON.parse(localStorage.getItem("proformaDraft") || "null");
  } catch (e) {}

  loadSelect2Items();
  fetchCurrencySymbol();
  fetchExchangeRate(userCurrency, $("#manual_exchange_rate").val());

  // Carrega contatos antes de verificar edição para garantir que o select esteja preenchido
  const contactsReadyPromise = loadContacts().then(() => {
    const editId = new URLSearchParams(window.location.search).get("edit_id");
    if (editId) {
      loadInvoiceForEdit(editId);
    }
  });

  console.log("JS carregado, iniciando requisição AJAX...");

  // Carregar países ao iniciar a página
  selectCountry();

  // Quando um contato é selecionado, carrega os dados no formulário
  function syncContactIdValue() {
    // Cliente X: não há contact_id; o servidor resolve o contacto anónimo
    if ($("#contact-select").val() === ANON_VALUE) {
      $("#contact_id").val("");
      return "";
    }

    const selectedId =
      $("#contact-select").val() || $("#contact_id").val() || "";
    if (selectedId) {
      $("#contact_id").val(selectedId);
    }
    return selectedId;
  }

  $("#contact-select").on("change", function () {
    let contatoId = $(this).val();

    // Cliente X (anónimo): sem ficha nem contact_id
    if (contatoId === ANON_VALUE) {
      $("#anonymous_client").val("1");
      $("#contact_id").val("");
      $("#contact-form").hide();
      renderClientTrigger();
      return;
    }

    $("#anonymous_client").val("0");
    $("#contact_id").val(contatoId || "");
    renderClientTrigger();

    if (contatoId) {
      $.ajax({
        url: "contacts/ajax/get_contact.php",
        type: "POST",
        data: { id: contatoId },
        dataType: "json",
        success: function (contato) {
          const finalContactId = contato.id || contatoId;
          $("#contact_id").val(finalContactId);
          $("#contact_name").val(contato.name).prop("disabled", true);
          $("#email").val(contato.email).prop("disabled", true);
          $("#contributor").val(contato.contributor).prop("disabled", true);
          $("#po_box")
            .val(contato.po_box || contato.telephone)
            .prop("disabled", true);
          $("#address").val(contato.address).prop("disabled", true);
          selectCountry(contato.country, contato.city);

          $("#country").prop("disabled", true);
          $("#city").prop("disabled", true);

          $("#contact-form").show();

          console.log(" Formulário agora está visível.");
        },
        error: function (xhr, status, error) {
          console.error("❌ Erro ao buscar dados do contato:", status, error);
        },
      });
    } else {
      $("#contact-form").fadeOut();
    }
  });

  $('[data-bs-toggle="tooltip"]').tooltip();

  $("#toggle-contact-form").on("click", function () {
    if ($("#contact-form").is(":visible")) {
      $("#contact-form").hide();
      $("#select-contact-container").show();

      $("#spanIconCreateInvoices").text("person_add");
      $("#toggle-contact-form")
        .attr("data-bs-title", "Inserir novo Contato")
        .tooltip("dispose")
        .tooltip(); // Atualiza tooltip
    } else {
      $("#contact-form").show();
      $("#select-contact-container").hide();
      $(
        "#contact-form input, #contact-form textarea, #contact-form select",
      ).prop("disabled", false);
      $(
        "#contact-form input:not(#contact_id), #contact-form textarea, #contact-form select",
      ).val("");
      $("#contact_id").val("");
      $("#spanIconCreateInvoices").text("close");
      $("#toggle-contact-form")
        .attr("data-bs-title", "Fechar formulário")
        .tooltip("dispose")
        .tooltip(); // Atualiza tooltip
    }
  });

  $("#country").on("change", function () {
    if ($(this).data("ignore-change")) return;
    loadCities($(this).val());
  });

  // =====================================================================
  // ESCOLHER CLIENTE — modal em blocos (logo + nome) e Cliente X (anónimo)
  // O <select id="contact-select"> continua a ser a fonte de verdade (escondido):
  // o modal só escolhe o valor e dispara "change".
  // =====================================================================
  const $clientModal = $("#clientModal").appendTo("body"); // fora de qualquer contentor do layout

  function toSelectValue(contactId) {
    return anonContactId && String(contactId) === String(anonContactId)
      ? ANON_VALUE
      : contactId;
  }

  function contactLogo(contact) {
    const raw = String(
      contact?.logo ||
        contact?.logo_url ||
        contact?.avatar ||
        contact?.photo ||
        contact?.image ||
        "",
    ).trim();

    if (!raw) return "";
    return /^(https?:)?\/\/|^\/|^data:/i.test(raw)
      ? raw
      : CONTACT_LOGO_BASE + raw;
  }

  function initialsOf(name) {
    const parts = String(name || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    if (!parts.length) return "?";
    return (
      parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : "")
    ).toUpperCase();
  }

  function hueOf(name) {
    let hue = 0;
    for (const ch of String(name || ""))
      hue = (hue * 31 + ch.charCodeAt(0)) % 360;
    return hue;
  }

  // Logo do cliente; se não houver (ou falhar a carregar) mostra as iniciais
  function avatarHtml(contact, size) {
    if (contact.anon) {
      return `<span class="inv-avatar inv-avatar--${size} inv-avatar--anon" aria-hidden="true">X</span>`;
    }

    const name = contact.name || "";
    const hue = hueOf(name);
    const initials = escapeAttr(initialsOf(name));
    const logo = contactLogo(contact);

    if (logo) {
      return `<span class="inv-avatar inv-avatar--${size}" data-initials="${initials}" data-hue="${hue}">
        <img src="${escapeAttr(logo)}" alt="" loading="lazy"></span>`;
    }

    return `<span class="inv-avatar inv-avatar--${size}" style="background:hsl(${hue} 55% 42%)" aria-hidden="true">${initials}</span>`;
  }

  function bindAvatarFallbacks($root) {
    $root
      .find(".inv-avatar img")
      .off("error")
      .on("error", function () {
        const $avatar = $(this).parent();
        $avatar
          .css("background", `hsl(${$avatar.data("hue")} 55% 42%)`)
          .text($avatar.data("initials"));
      });
  }

  function renderClientTrigger() {
    const value = String($("#contact-select").val() || "");
    let html = `<span class="inv-client-placeholder">Selecione um cliente...</span>`;

    if (value === ANON_VALUE) {
      html = `${avatarHtml({ anon: true }, "sm")}<span class="inv-client-name">Cliente X</span>`;
    } else if (value) {
      const contact = contactsCache.find((c) => String(c.id) === value);
      const name =
        contact?.name ||
        $("#contact-select option:selected").text().split(" - ")[0].trim();

      html = `${avatarHtml(contact || { name }, "sm")}<span class="inv-client-name">${escapeAttr(name)}</span>`;
    }

    bindAvatarFallbacks(
      $("#clientPickerBtn .inv-client-trigger-main").html(html),
    );
  }

  function pickCardHtml(value, name, avatar, selected, extraClass) {
    return `<button type="button" class="inv-pick-card ${extraClass || ""} ${selected ? "is-selected" : ""}"
        data-id="${escapeAttr(value)}" title="${escapeAttr(name)}" aria-pressed="${selected}">
        ${avatar}<span class="inv-pick-name">${escapeAttr(name)}</span></button>`;
  }

  function renderClientGrid(filter) {
    const query = String(filter || "")
      .trim()
      .toLowerCase();
    const current = String($("#contact-select").val() || "");
    const cards = [];

    // Cliente X é sempre o primeiro bloco
    const anonWords = "cliente x anónimo anonimo consumidor final";
    if (!query || anonWords.includes(query)) {
      cards.push(
        pickCardHtml(
          ANON_VALUE,
          "Cliente X",
          avatarHtml({ anon: true }, "lg"),
          current === ANON_VALUE,
          "inv-pick-card--anon",
        ),
      );
    }

    contactsCache
      .filter(
        (c) =>
          !query ||
          `${c.name || ""} ${c.email || ""}`.toLowerCase().includes(query),
      )
      .forEach((c) => {
        cards.push(
          pickCardHtml(
            c.id,
            c.name || "Sem nome",
            avatarHtml(c, "lg"),
            String(c.id) === current,
          ),
        );
      });

    if (!contactsLoaded) {
      cards.push(`<p class="inv-pick-empty">A carregar clientes…</p>`);
    } else if (!cards.length) {
      cards.push(`<p class="inv-pick-empty">Nenhum cliente encontrado.</p>`);
    }

    bindAvatarFallbacks($("#clientGrid").html(cards.join("")));
  }

  function openClientModal() {
    $clientModal.addClass("is-open").attr("aria-hidden", "false");
    $("html").addClass("inv-modal-open");
    $("#clientSearch").val("");
    renderClientGrid("");
    setTimeout(() => $("#clientSearch").trigger("focus"), 30);
  }

  function closeClientModal() {
    $clientModal.removeClass("is-open").attr("aria-hidden", "true");
    $("html").removeClass("inv-modal-open");
    $("#clientPickerBtn").trigger("focus");
  }

  $("#clientPickerBtn").on("click", openClientModal);

  $clientModal.on("click", function (event) {
    if (
      event.target === this ||
      $(event.target).closest("[data-inv-close]").length
    ) {
      closeClientModal();
    }
  });

  $(document).on("keydown", function (event) {
    if (event.key === "Escape" && $clientModal.hasClass("is-open"))
      closeClientModal();
  });

  $("#clientSearch").on("input", function () {
    renderClientGrid($(this).val());
  });

  // Enter escolhe o primeiro resultado
  $("#clientSearch").on("keydown", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      $("#clientGrid .inv-pick-card").first().trigger("click");
    }
  });

  $("#clientGrid").on("click", ".inv-pick-card", function () {
    $("#contact-select")
      .val(String($(this).data("id")))
      .trigger("change");
    if (typeof saveDraft === "function") saveDraft(); // .trigger() do jQuery não chega ao listener nativo do form
    closeClientModal();
  });

  renderClientTrigger();

  // =====================================================================
  // PRAZO DE PAGAMENTO
  // O backend guarda `due_date` como nº de dias. O campo escondido #due_date
  // mantém esse valor; o date picker e os chips são só interface.
  // =====================================================================
  const MS_DAY = 86400000;

  const parseISO = (value) => {
    const [y, m, d] = String(value || "")
      .split("-")
      .map(Number);
    return y ? new Date(y, m - 1, d) : null;
  };

  const toISO = (dt) =>
    `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(dt.getDate()).padStart(2, "0")}`;

  function syncDueUI() {
    const days = Math.max(0, parseInt($("#due_date").val(), 10) || 0);
    const issue = parseISO($("#issue_date").val()) || new Date();
    const due = new Date(
      issue.getFullYear(),
      issue.getMonth(),
      issue.getDate() + days,
    );

    $("#due_date_picker").val(toISO(due)).attr("min", $("#issue_date").val());

    $("#due_chips .inv-chip").each(function () {
      $(this).toggleClass("active", Number($(this).data("days")) === days);
    });
  }

  function setDueDays(days) {
    $("#due_date").val(Math.max(0, parseInt(days, 10) || 0));
    syncDueUI();
    if (typeof saveDraft === "function") saveDraft();
  }

  $("#due_chips").on("click", ".inv-chip", function () {
    setDueDays($(this).data("days"));
  });

  $("#due_date_picker").on("change", function () {
    const issue = parseISO($("#issue_date").val());
    const due = parseISO($(this).val());

    if (!issue || !due) return syncDueUI();
    setDueDays(Math.round((due - issue) / MS_DAY));
  });

  // Ao mudar a emissão, o vencimento acompanha (mesmo nº de dias)
  $("#issue_date").on("change", syncDueUI);

  syncDueUI();

  // =====================================================================
  // RETENÇÃO NA FONTE (caixa de selecção -> campo escondido #retention)
  // =====================================================================
  const RETENTION_RATE = parseFloat($("#apply_retention").data("rate")) || 6.5;

  function syncRetentionUI() {
    $("#apply_retention").prop(
      "checked",
      (parseFloat($("#retention").val()) || 0) > 0,
    );
  }

  $("#apply_retention").on("change", function () {
    $("#retention").val(this.checked ? RETENTION_RATE.toFixed(2) : "0.00");
    updateInvoiceSummary();
    if (typeof saveDraft === "function") saveDraft();
  });

  syncRetentionUI();

  // =====================================================================
  // LINHAS: estado vazio, "Adicionar Linha", cancelar, modo edição
  // =====================================================================
  function refreshLinesState() {
    const count = $("#items_list .item-list").length;
    $("#lines_card").toggleClass("has-lines", count > 0);
    $("#lines_count").text(count);
  }

  function escapeAttr(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function setEditMode() {
    $("#saveInvoiceBtn .btn-label").text("Guardar Alterações");
    $("#inv_crumb_current").text("Editar Documento");
  }

  $("#addLineBtn").on("click", function () {
    const $select = $("#item_select");
    if ($select.hasClass("select2-hidden-accessible")) {
      $select.select2("open");
    } else {
      $select.trigger("focus");
    }
  });

  $("#cancelInvoiceBtn").on("click", function () {
    const backUrl = $(this).data("href") || "proforms.php";
    const hasWork =
      $("#items_list .item-list").length > 0 ||
      Boolean($("#contact-select").val());

    const leave = () => {
      if (typeof clearDraft === "function") clearDraft();
      window.location.href = backUrl;
    };

    if (!hasWork) return leave();

    Swal.fire({
      icon: "warning",
      title: "Descartar este documento?",
      text: "O rascunho guardado neste navegador será apagado.",
      showCancelButton: true,
      confirmButtonText: "Descartar",
      cancelButtonText: "Continuar a editar",
    }).then((result) => {
      if (result.isConfirmed) leave();
    });
  });

  function cleanCurrencyValue(value) {
    if (!value) return 0; // Caso o valor esteja vazio, retorna 0

    // Remove o símbolo da moeda (letras e espaços) e converte vírgula para ponto
    return parseFloat(value.replace(/[^\d,.-]/g, "").replace(",", "."));
  }

  function initializeTooltips() {
    document
      .querySelectorAll('[data-bs-toggle="tooltip"]')
      .forEach((el) => new bootstrap.Tooltip(el));
  }

  const DRAFT_KEY = "proformaDraft";

  function getStoredDraft() {
    if (!contactsLoaded && bootDraft) return bootDraft;

    try {
      const raw = localStorage.getItem(DRAFT_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      console.warn("Erro ao ler rascunho localStorage", e);
      return null;
    }
  }

  function hydrateSavedContactFromDraft() {
    const savedDraft = getStoredDraft();
    const draftForm = savedDraft?.form || {};
    const savedContactId =
      savedDraft?.meta?.contact_select || draftForm.contact_id || "";

    if (savedContactId) {
      $("#contact_id").val(savedContactId);
      $("#contact-select").val(savedContactId).trigger("change");
      return true;
    }

    const hasFilledSavedContact = [
      draftForm.name,
      draftForm.contributor,
      draftForm.address,
      draftForm.email,
    ].some((value) => String(value || "").trim());

    if (hasFilledSavedContact) {
      $("#contact_name").val(draftForm.name || "");
      $("#email").val(draftForm.email || "");
      $("#contributor").val(draftForm.contributor || "");
      $("#po_box").val(draftForm.po_box || draftForm.telephone || "");
      $("#address").val(draftForm.address || "");
      $("#country").val(draftForm.country || "");
      $("#city").val(draftForm.city || "");
      $("#contact-form").show();
      return true;
    }

    return false;
  }

  function resolveContactValidationState() {
    if ($("#contact-select").val() === ANON_VALUE) {
      return {
        selectedId: ANON_VALUE,
        hasSelectedContact: true,
        hasFilledNewContact: false,
      };
    }

    const draft = getStoredDraft();
    const currentSelected = $("#contact-select").val()?.trim() || "";
    const currentContactId = $("#contact_id").val()?.trim() || "";

    const currentFieldValues = {
      name: $("#contact-form input[name='name']").val()?.trim() || "",
      contributor:
        $("#contact-form input[name='contributor']").val()?.trim() || "",
      address: $("#contact-form textarea[name='address']").val()?.trim() || "",
      email: $("#contact-form input[name='email']").val()?.trim() || "",
    };

    const draftFieldValues = {
      name: draft?.form?.name || "",
      contributor: draft?.form?.contributor || "",
      address: draft?.form?.address || "",
      email: draft?.form?.email || "",
    };

    const selectedId =
      currentSelected ||
      currentContactId ||
      draft?.meta?.contact_select ||
      draft?.form?.contact_id ||
      "";

    if (selectedId) {
      $("#contact_id").val(selectedId);
    }

    const hasRequiredNewContact = (values) =>
      Boolean(
        values.name && values.contributor && values.address && values.email,
      );

    const hasFilledNewContact =
      hasRequiredNewContact(currentFieldValues) ||
      hasRequiredNewContact(draftFieldValues);

    return {
      selectedId,
      hasSelectedContact: Boolean(selectedId),
      hasFilledNewContact,
    };
  }

  function loadSelect2Items() {
    $.ajax({
      url: "create_invoices/ajax/get_items.php",
      dataType: "json",
      success: function (data) {
        let options =
          '<option value="">Selecione um produto/serviço...</option>';
        $.each(data?.data, function (index, item) {
          options += `<option value="${item.id}" data-item='${JSON.stringify(
            item,
          )}'>
            ${item.item_type !== "service" ? `<b><i class="bi bi-box"></i> ${item.code}</b> -` : "<b><i class='bi bi-gear'></i></b>"} ${item.description || item.name}
            </option>`;
        });
        $("#item_select").html(options);
        $(".select2").select2({ width: "100%", dropdownParent: $("#invPage") });
      },
    });
  }

  async function loadContacts() {
    const select = $("#contact-select");

    try {
      // loading
      select
        .prop("disabled", true)
        .html('<option value="">Carregando contatos...</option>');

      const response = await $.ajax({
        url: "contacts/ajax/fetch_contacts.php",
        type: "GET",
        dataType: "json",
      });

      // limpa select
      select.empty();
      contactsCache = [];

      // opção padrão + Cliente X (anónimo)
      select.append('<option value="">Selecione um cliente...</option>');
      select.append(`<option value="${ANON_VALUE}">Cliente X</option>`);

      // valida retorno
      if (
        response.success &&
        Array.isArray(response.data) &&
        response.data.length > 0
      ) {
        response.data.forEach((contato) => {
          // O contacto técnico do Cliente X não aparece como cliente normal
          if (
            String(contato.email || "")
              .trim()
              .toLowerCase() === ANON_EMAIL
          ) {
            anonContactId = contato.id;
            return;
          }

          contactsCache.push(contato);
          select.append(
            `<option value="${contato.id}">${escapeAttr(contato.name)} - ${escapeAttr(contato.email)}</option>`,
          );
        });
      } else {
        select.append(`
        <option value="">
          Nenhum contato encontrado
        </option>
      `);
      }

      const savedDraft = getStoredDraft();

      const savedContactId =
        savedDraft?.meta?.contact_select || savedDraft?.form?.contact_id || "";
      if (savedContactId) {
        $("#contact_id").val(savedContactId);
        select.val(savedContactId).trigger("change");
      } else {
        hydrateSavedContactFromDraft();
      }

      // atualiza select2
      if (select.hasClass("select2-hidden-accessible")) {
        select.trigger("change");
      }

      return response.data;
    } catch (xhr) {
      console.error("❌ Erro ao carregar contatos:", xhr);

      Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Não foi possível carregar os contatos.",
      });

      throw xhr;
    } finally {
      select.prop("disabled", false);
      contactsLoaded = true;
      renderClientTrigger();
      if (bootDraft && typeof saveDraft === "function") saveDraft();
      if ($("#clientModal").hasClass("is-open"))
        renderClientGrid($("#clientSearch").val());
    }
  }

  function showTableItemsList() {
    let element = document.querySelector("#items_list");
    if (element) {
      element.classList.remove("d-none");
    }
  }

  function calculateRowTotal(row) {
    let price = parseFloat(row.find(".field_price").val()) || 0;
    let qtd = parseFloat(row.find(".field_qtd").val()) || 0;
    let discount = parseFloat(row.find(".field_desc").val()) || 0;
    let tax = parseFloat(row.find(".field_tax").val()) || 0;

    // =====================================
    // SUBTOTAL
    // =====================================

    let subtotal = price * qtd;

    // =====================================
    // IVA
    // =====================================

    let taxValue = 0;

    if (vat_regime === "geral") {
      taxValue = (subtotal * tax) / 100;
    } else {
      taxValue = 0.0;
    }

    // total com IVA
    let totalWithTax = subtotal + taxValue;

    // =====================================
    // DESCONTO (%)
    // =====================================

    let totalDiscount = (discount / 100) * totalWithTax;

    // =====================================
    // TOTAL FINAL
    // =====================================

    let totalFinal = totalWithTax - totalDiscount;

    row
      .find(".row-total")
      .text(formatCurrency(totalFinal, currencySymbol, currencyPosition));
  }

  function calculateGrandTotal() {
    let grandTotal = 0;

    $(".row-item").each(function () {
      let value = $(this).find(".row-total").text().trim();

      // remove separadores de milhares
      value = value.replace(/\./g, "");

      // troca vírgula por ponto
      value = value.replace(",", ".");

      let rowTotal = parseFloat(value) || 0;

      grandTotal += rowTotal;
    });

    $("#grand_total").text(
      grandTotal.toLocaleString("pt-PT", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }),
    );
  }

  // Adicionar item ao selecionar
  $("#item_select").on("change", function () {
    var selected = $(this).find(":selected").data("item");
    if (selected) {
      addItemRow(selected);
      $(this).val("").trigger("change"); // Opcional: limpar seleção
    }
  });

  function addItemRow(item) {
    const itemId = item?.id || item?.item_id;

    if (!itemId) return;

    // Evitar duplicação
    if ($(`#item-${itemId}`).length) {
      return;
    }

    // Dados do item
    const description = item?.name || item?.description || "";
    const code = item?.code || item?.codigo || "";
    const retention = item?.retention || 0;

    // IVA
    const itemTax = Number(item?.tax ?? 0);
    const taxValue = String(vat_regime).toLowerCase() === "geral" ? itemTax : 0;

    console.log({
      vat_regime,
      itemTax,
      taxValue,
    });

    // Serviço ou Produto
    const isService =
      item?.item_type === "service" ||
      String(code).toUpperCase().startsWith("SERV");

    // Quantidade da linha (ao editar/repor rascunho) e quantidade máxima (stock)
    const lineQty =
      Number(item?.line_quantity) > 0 ? Number(item.line_quantity) : 1;

    const maxQty = isService
      ? 999999
      : Math.max(
          Number(item?.quantity ?? item?.stock_quantity ?? 9999),
          lineQty,
        );

    // Preço unitário
    const unitPrice = Number(
      item?.unit_price ?? item?.sale_price ?? item?.cost_price ?? 0,
    );

    // Desconto
    const discount = Number(item?.discount ?? 0);

    const itemHtml = `
    <div class="inv-line row-item item-list" id="item-${itemId}" data-id="${itemId}">

        <div class="inv-cell inv-cell-desc">
            <input type="text" class="inv-line-desc field_description"
                value="${escapeAttr(description)}" title="${escapeAttr(description)}" readonly>
            <div class="inv-line-meta">
                <input type="text" class="inv-line-code field_code"
                    value="${escapeAttr(code)}" readonly tabindex="-1">
                ${isService ? '<span class="inv-tag">Serviço</span>' : ""}
            </div>
            <input type="hidden" class="field_retention" value="${retention}">
        </div>

        <div class="inv-cell" data-label="Qtd">
            <input type="number" class="inv-cell-input field_qtd"
                value="${lineQty}" min="1" max="${maxQty}" aria-label="Quantidade">
        </div>

        <div class="inv-cell" data-label="P. Unitário">
            <input type="number" class="inv-cell-input field_price"
                value="${unitPrice.toFixed(2)}" step="0.01" min="0" aria-label="Preço unitário">
        </div>

        <div class="inv-cell" data-label="Desc (%)">
            <input type="number" class="inv-cell-input field_desc"
                value="${discount}" step="0.01" min="0" aria-label="Desconto em percentagem">
        </div>

        <div class="inv-cell" data-label="IVA (%)">
            <span class="inv-pill">
                <input type="number" class="field_tax" value="${taxValue}" step="0.01" readonly tabindex="-1" aria-label="IVA">%
            </span>
        </div>

        <div class="inv-cell inv-cell-total" data-label="Total Linha">
            <span class="row-total">0,00</span>
        </div>

        <div class="inv-cell inv-cell-act">
            <button type="button" class="inv-trash remove-item" data-id="${itemId}"
                title="Remover linha" aria-label="Remover linha">
                <i class="bi bi-trash3" aria-hidden="true"></i>
            </button>
        </div>

    </div>
    `;

    $("#items_list").append(itemHtml);

    const newRow = $(`#item-${itemId}`);

    newRow.find(".field_code").on("keydown paste", function (e) {
      e.preventDefault();
    });

    newRow
      .find(".field_price, .field_qtd, .field_desc")
      .on("input change", function () {
        calculateRowTotal(newRow);

        if (typeof updateInvoiceSummary === "function") {
          updateInvoiceSummary();
        } else if (typeof calculateGrandTotal === "function") {
          calculateGrandTotal();
        }
      });

    newRow.find(".remove-item").on("click", function () {
      newRow.remove();
      refreshLinesState();

      if (typeof updateInvoiceSummary === "function") {
        updateInvoiceSummary();
      } else if (typeof calculateGrandTotal === "function") {
        calculateGrandTotal();
      }

      if (
        $("#items_list .item-list").length === 0 &&
        typeof hideTableItemsList === "function"
      ) {
        hideTableItemsList();
      }
    });

    calculateRowTotal(newRow);

    if (typeof updateInvoiceSummary === "function") {
      updateInvoiceSummary();
    } else if (typeof calculateGrandTotal === "function") {
      calculateGrandTotal();
    }

    if (typeof showTableItemsList === "function") {
      showTableItemsList();
    }

    refreshLinesState();

    if (typeof saveDraft === "function") saveDraft();
  }

  // Remover item da lista
  $(document).on("click", ".remove-item", function () {
    let itemId = $(this).data("id");
    $(`#item-${itemId}`).remove();
    refreshLinesState();
    if (typeof saveDraft === "function") saveDraft();
  });

  $(document).on("input", ".field_price, .field_qtd, .field_desc", function () {
    let row = $(this).closest(".row-item");
    calculateRowTotal(row);
    calculateGrandTotal();
    updateInvoiceSummary();
    if (typeof saveDraft === "function") saveDraft();
  });

  // Buscar moeda, símbolo e posição via AJAX
  // OBS: Para emissão/edição de faturas, usamos como padrão AOA (Kz) quando possível,
  // porque em Angola a moeda padrão do sistema deve ser Kwanza.
  function fetchCurrencySymbol(selectedIso = "AOA") {
    $.ajax({
      url: "assets/ajax/get_currency.php",
      type: "GET",
      dataType: "json",
      success: function (data) {
        // tenta usar a moeda pedida; se o endpoint retornar outra, cai pra ela
        const iso = selectedIso || data.currency || "AOA";
        userCurrency = iso;

        // se o endpoint retornou símbolo/posição, usa; senão mantém defaults
        if (data.symbol && data.position && data.currency === iso) {
          currencySymbol = data.symbol;
          currencyPosition = data.position;
        }

        updateInvoiceSummary();
      },
      error: function () {
        console.error("Erro ao buscar a moeda do usuário.");
      },
    });
  }

  function resetInvoiceSummary() {
    // =========================
    // VALORES ZERO PADRÃO
    // =========================
    const zeroValue = 0;
    const zeroFormatted = formatCurrency(0, currencySymbol, currencyPosition);

    // =========================
    // UI PRINCIPAL
    // =========================
    $("#total_sum").text(zeroFormatted);
    $("#total_discount").text(zeroFormatted);
    $("#subtotal_without_tax").text(zeroFormatted);
    $("#total_tax").text(zeroFormatted);
    $("#retention_value").text(zeroFormatted);
    $("#final_total").text(zeroFormatted);

    // =========================
    // TABELA IMPOSTOS
    // =========================
    $("#tax_summary").html(`
        <tr>
            <td colspan="5" class="inv-tax-empty">
                Nenhum item adicionado
            </td>
        </tr>
    `);

    // =========================
    // INPUTS HIDDEN (IDs)
    // =========================
    $("#total_sumInput").val(zeroValue.toFixed(2));
    $("#total_discountInput").val(zeroValue.toFixed(2));
    $("#subtotal_without_taxInput").val(zeroValue.toFixed(2));
    $("#total_taxInput").val(zeroValue.toFixed(2));
    $("#retention_valueInput").val(zeroValue.toFixed(2));
    $("#final_totalInput").val(zeroValue.toFixed(2));

    // =========================
    // INPUTS HIDDEN (NAME)
    // =========================
    $("input[name='total_sum']").val(zeroValue.toFixed(2));
    $("input[name='total_discount']").val(zeroValue.toFixed(2));
    $("input[name='subtotal_without_tax']").val(zeroValue.toFixed(2));
    $("input[name='total_tax']").val(zeroValue.toFixed(2));
    $("input[name='retention_value']").val(zeroValue.toFixed(2));
    $("input[name='final_total']").val(zeroValue.toFixed(2));

    // =========================
    // RETENÇÃO (IMPORTANTE)
    // =========================
    // NÃO forçar recalculo interno
    if (typeof updateRetention === "function") {
      updateRetention(zeroValue, zeroValue, zeroValue);
    }
  }

  let finalTotal = 0;

  // ao mudar a moeda, recalcula com o símbolo correto
  $(document).on("change", "#currency", function () {
    const iso = $(this).val() || "AOA";
    // não consulta API externa aqui; só ajusta símbolo/posição via backend padrão
    fetchCurrencySymbol(iso);
  });

  function updateInvoiceSummary() {
    let totalSum = 0;
    let totalDiscount = 0;
    let taxExemptIncidence = 0;
    let tax14Incidence = 0;
    let totalTax = 0;
    let totalRetention = 0;

    const itemList = document.querySelectorAll(".item-list");

    if (!itemList.length) {
      resetInvoiceSummary();
      return;
    }

    let grouped = {};

    itemList.forEach((row) => {
      const $row = $(row);

      const toNumber = (value) =>
        parseFloat(
          String(value || "0")
            .replace(",", ".")
            .replace("%", "")
            .trim(),
        ) || 0;

      const price = toNumber($row.find(".field_price").val());
      const qtd = toNumber($row.find(".field_qtd").val());
      const discountPercent = toNumber($row.find(".field_desc").val());
      const taxNum = toNumber($row.find(".field_tax").val());
      const retentionRate = toNumber($row.find(".field_retention").val());

      const lineTotal = price * qtd;

      const discountValue = (lineTotal * discountPercent) / 100;
      const safeDiscount = Math.min(discountValue, lineTotal);

      const lineSubtotal = lineTotal - safeDiscount;

      totalSum += lineTotal;
      totalDiscount += safeDiscount;

      // ==================================================
      // IVA
      // Regra: taxa 7 = Isento = IVA 0%
      // ==================================================
      const effectiveTaxRate = taxNum === 7 ? 0 : taxNum;
      const ivaValue = (lineSubtotal * effectiveTaxRate) / 100;

      // ==================================================
      // RETENÇÃO
      // ==================================================
      const retentionValue = (lineSubtotal * retentionRate) / 100;

      totalRetention += retentionValue;

      // ==================================================
      // TOTAL DA LINHA
      // ==================================================
      const net = lineSubtotal + ivaValue - retentionValue;

      // ==================================================
      // INCIDÊNCIA IVA
      // ==================================================
      if (taxNum === 14) {
        tax14Incidence += lineSubtotal;
      } else {
        taxExemptIncidence += lineSubtotal;
      }

      totalTax += ivaValue;

      // ==================================================
      // AGRUPAMENTO
      // Mostra 0% quando a taxa original é 7
      // ==================================================
      const groupTax = taxNum === 7 ? 0 : taxNum;

      if (!grouped[groupTax]) {
        grouped[groupTax] = {
          base: 0,
          iva: 0,
          retention: 0,
          net: 0,
        };
      }

      grouped[groupTax].base += lineSubtotal;
      grouped[groupTax].iva += ivaValue;
      grouped[groupTax].retention += retentionValue;
      grouped[groupTax].net += net;
    });

    totalSum = +totalSum.toFixed(2);
    totalDiscount = +totalDiscount.toFixed(2);
    totalTax = +totalTax.toFixed(2);
    totalRetention = +totalRetention.toFixed(2);

    const subtotalWithoutTax = +(totalSum - totalDiscount).toFixed(2);

    let finalTotal = +(subtotalWithoutTax + totalTax - totalRetention).toFixed(
      2,
    );

    if (finalTotal < 0) {
      finalTotal = 0;
    }

    $("#total_sum").text(
      formatCurrency(totalSum, currencySymbol, currencyPosition),
    );

    $("#total_discount").text(
      formatCurrency(totalDiscount, currencySymbol, currencyPosition),
    );

    $("#subtotal_without_tax").text(
      formatCurrency(subtotalWithoutTax, currencySymbol, currencyPosition),
    );

    $("#total_tax").text(
      formatCurrency(totalTax, currencySymbol, currencyPosition),
    );

    $("#retention_value").text(
      formatCurrency(totalRetention, currencySymbol, currencyPosition),
    );

    $("#final_total").text(
      formatCurrency(finalTotal, currencySymbol, currencyPosition),
    );

    // ==================================================
    // RESUMO DE IMPOSTOS
    // ==================================================
    const tax_summary = $("#tax_summary");
    let html = "";

    Object.keys(grouped).forEach((tax) => {
      const g = grouped[tax];

      html += `
      <tr>
        <td>${tax}%</td>
        <td>${formatCurrency(g.base, currencySymbol, currencyPosition)}</td>
        <td>${formatCurrency(g.iva, currencySymbol, currencyPosition)}</td>
        <td>${formatCurrency(
          g.retention,
          currencySymbol,
          currencyPosition,
        )}</td>
        <td>${formatCurrency(g.net, currencySymbol, currencyPosition)}</td>
      </tr>
    `;
    });

    tax_summary.html(html);

    const setVal = (name, value) => {
      $(`input[name='${name}']`).val(value.toFixed(2));
    };

    setVal("total_sum", totalSum);
    setVal("total_discount", totalDiscount);
    setVal("subtotal_without_tax", subtotalWithoutTax);
    setVal("total_tax", totalTax);
    setVal("retention_value", totalRetention);
    setVal("final_total", finalTotal);

    $("#total_sumInput").val(totalSum.toFixed(2));
    $("#total_discountInput").val(totalDiscount.toFixed(2));
    $("#subtotal_without_taxInput").val(subtotalWithoutTax.toFixed(2));
    $("#total_taxInput").val(totalTax.toFixed(2));
    $("#retention_valueInput").val(totalRetention.toFixed(2));
    $("#final_totalInput").val(finalTotal.toFixed(2));

    updateRetention(subtotalWithoutTax, totalTax, totalRetention);
  }

  function updateRetention(subtotal, totalTax, totalRetentionItems) {
    let perc = parseFloat($("#retention").val()) || 0;

    // base já calculada corretamente
    let base = subtotal + totalTax - totalRetentionItems;

    let extraRetention = 0;

    // retenção adicional global (opcional)
    if (perc > 0) {
      extraRetention = (base * perc) / 100;
    }

    let final = base - extraRetention;

    // O que é gravado tem de coincidir com o que o ecrã mostra
    $("input[name='retention_value']").val(
      (totalRetentionItems + extraRetention).toFixed(2),
    );
    $("input[name='final_total']").val(Math.max(final, 0).toFixed(2));

    // UI
    if (extraRetention > 0 || totalRetentionItems > 0) {
      $("#retention_sumary").removeClass("d-none");

      $("#retention_value").text(
        formatCurrency(
          totalRetentionItems + extraRetention,
          currencySymbol,
          currencyPosition,
        ),
      );
    } else {
      $("#retention_sumary").addClass("d-none");
    }

    $("#final_total").text(
      formatCurrency(final, currencySymbol, currencyPosition),
    );

    // $("#final_totalInput").val(final.toFixed(2));

    updateConvertedTotal?.();
  }

  let exchangeRate = 1; // Padrão: 1 para mesma moeda

  // Buscar taxa de câmbio via API
  function fetchExchangeRate(baseCurrency, targetCurrency) {
    if (baseCurrency === targetCurrency) {
      $("#exchange_rate_container").hide();
      $("#conversion_row, #exchange_rate_row").hide();
      return;
    }

    const apiUrl = `https://api.exchangerate-api.com/v4/latest/${baseCurrency}`;

    $.ajax({
      url: apiUrl,
      type: "GET",
      dataType: "json",
      success: function (data) {
        if (data.rates && data.rates[targetCurrency]) {
          exchangeRate = data.rates[targetCurrency];

          $("#manual_exchange_rate").val(exchangeRate.toFixed(6));
          $("#exchange_rate").text(exchangeRate.toFixed(6));
          $("#currency_pair").text(`${baseCurrency}/${targetCurrency}`);

          updateConvertedTotal();
          $(
            "#exchange_rate_container, #conversion_row, #exchange_rate_row",
          ).show();
        }
      },
      error: function () {
        console.error("Erro ao buscar a taxa de câmbio.");
        $("#exchange_rate").text("Erro ao buscar taxa.");
      },
    });
  }

  // Atualizar o total convertido para a moeda selecionada
  function updateConvertedTotal(finalTotal) {
    let totalValue =
      parseFloat(
        $("#final_total")
          .text()
          .replace(/[^\d,.-]/g, "") // Remove caracteres não numéricos
          .replace(/\./g, "") // Remove pontos separadores de milhar
          .replace(",", "."), // Substitui vírgula decimal por ponto
      ) || 0;

    let convertedTotal = parseFloat(totalValue) * parseFloat(exchangeRate);

    $("#converted_total").text(
      formatCurrency(convertedTotal, $("#currency").val()),
    );
    if ($("#manual_exchange_rate").val() > 0) {
      $("#converted_totalInput").val(
        cleanCurrencyValue(
          formatCurrency(convertedTotal, $("#currency").val()),
        ),
      );
    }
  }

  $("#currency").on("change", function () {
    let selectedCurrency = $(this).val();
    $("#selected_currency").text(selectedCurrency);

    fetchExchangeRate(userCurrency, selectedCurrency);
    updateExchangeRateText();
  });

  $("#manual_exchange_rate").on("input", function () {
    exchangeRate = parseFloat($(this).val()) || 1;
    updateConvertedTotal();
  });

  $("#currency").on("change", function () {
    let selectedCurrency = $(this).val();
    $("#selected_currency").text(selectedCurrency);

    fetchExchangeRate(userCurrency, selectedCurrency);
  });

  $("#manual_exchange_rate").on("input", function () {
    exchangeRate = parseFloat($(this).val()) || 1;
    updateConvertedTotal();
    updateExchangeRateText();
  });

  // Atualizar o texto da taxa de câmbio no sumário
  function updateExchangeRateText() {
    let selectedCurrency = $("#currency").val();
    $("#exchange_rate").text(exchangeRate.toFixed(6));
    $("#currency_pair").text(`AOA/${selectedCurrency}`);
  }

  function selectCountry(selectedCountry = "", selectedCity = "") {
    const username = "israelsouza";
    const countrySelect = $("#country");
    const citySelect = $("#city");

    countrySelect
      .html('<option value="">Carregando lista de países...</option>')
      .trigger("change");
    citySelect
      .html('<option value="">Selecione um país primeiro</option>')
      .trigger("change");

    fetch(`https://secure.geonames.org/countryInfoJSON?username=${username}`)
      .then((response) => response.json())
      .then((data) => {
        if (!data.geonames) throw new Error("API retornou dados inválidos");

        countryMap = {};
        let options = '<option value="">Selecione um país</option>';

        data.geonames.forEach((country) => {
          countryMap[country.countryName] = country.geonameId;
          options += `<option value="${country.countryName}">${country.countryName}</option>`;
        });

        countrySelect.html(options).trigger("change");
        countrySelect.select2({
          width: "100%",
          placeholder: "Selecione um país",
          allowClear: false,
          dropdownParent: countrySelect.parent(),
        });

        if (selectedCountry) {
          countrySelect.val(selectedCountry).trigger("change");
          loadCities(selectedCountry, selectedCity);
        }
      })
      .catch((error) => {
        console.error("❌ Erro ao carregar países:", error);
        countrySelect
          .html('<option value="">Erro ao carregar</option>')
          .trigger("change");
      });
  }

  function loadCities(countryName, selectedCity = "") {
    const citySelect = $("#city");
    const countryId = countryMap[countryName];

    if (!countryId) {
      citySelect
        .html('<option value="">Selecione um país primeiro</option>')
        .trigger("change");
      return;
    }

    citySelect
      .html('<option value="">Carregando cidades...</option>')
      .trigger("change");

    fetch(
      `https://secure.geonames.org/childrenJSON?geonameId=${countryId}&username=israelsouza`,
    )
      .then((response) => response.json())
      .then((data) => {
        if (!data.geonames) throw new Error("API retornou dados inválidos");

        let options = '<option value="">Selecione uma cidade</option>';
        data.geonames.forEach((city) => {
          let isSelected = city.name === selectedCity ? "selected" : "";
          options += `<option value="${city.name}" ${isSelected}>${city.name}</option>`;
        });

        citySelect.html(options).trigger("change");
        citySelect.select2({
          width: "100%",
          placeholder: "Selecione uma cidade",
          allowClear: false,
          dropdownParent: citySelect.parent(),
        });

        if (selectedCity) {
          citySelect.val(selectedCity).trigger("change");
        }
      })
      .catch((error) => {
        console.error("❌ Erro ao carregar cidades:", error);
        citySelect
          .html('<option value="">Erro ao carregar</do not get translated>')
          .trigger("change");
      });
  }

  $("#saveInvoiceBtn").on("click", function (event) {
    event.preventDefault();

    const draft = getStoredDraft();
    if (draft) {
      const draftForm = draft.form || {};
      const draftContactId =
        draft?.meta?.contact_select || draftForm.contact_id || "";

      if (draftContactId && !$("#contact-select").val()) {
        $("#contact-select").val(draftContactId).trigger("change");
      }

      const draftFields = [
        "contact_id",
        "name",
        "contributor",
        "address",
        "email",
        "po_box",
        "country",
        "city",
        "issue_date",
        "reference",
        "observation",
        "retention",
        "currency",
        "manual_exchange_rate",
      ];

      draftFields.forEach((fieldName) => {
        const field = $("#" + fieldName);
        const draftValue = draftForm[fieldName];
        if (draftValue !== undefined && draftValue !== null && !field.val()) {
          field.val(draftValue);
        }
      });

      hydrateSavedContactFromDraft();
    }

    let missingFields = [];
    let isContactFormVisible = $("#contact-form").is(":visible");
    const contactState = resolveContactValidationState();

    // =========================
    // VALIDAR CONTACTO
    // =========================
    if (isContactFormVisible) {
      $("#contact-form .form-control[required]").each(function () {
        if (!String($(this).val() || "").trim()) {
          let label =
            $(this).closest("div").find("label").text() || "Campo obrigatório";

          missingFields.push(label);
        }
      });
    }

    if (!contactState.hasSelectedContact && !contactState.hasFilledNewContact) {
      Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Por favor, selecione um contato ou preencha os dados de um novo contato.",
      });
      return;
    }

    if (missingFields.length > 0) {
      Swal.fire({
        icon: "error",
        title: "Campos obrigatórios faltando:",
        html: missingFields.join("<br>"),
      });
      return;
    }

    // =========================
    // VALIDAR ITENS
    // =========================
    if ($("#items_list .item-list").length === 0) {
      Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Adicione ao menos um produto/serviço antes de salvar.",
      });
      return;
    }

    // =========================
    // DADOS DA PROFORMA
    // =========================
    let invoiceData = $("#formFatura").serializeArray();
    const selectedContactId = syncContactIdValue();

    if (selectedContactId) {
      const hasContactIdField = invoiceData.some(
        (field) => field.name === "contact_id",
      );
      if (hasContactIdField) {
        invoiceData = invoiceData.map((field) =>
          field.name === "contact_id"
            ? { ...field, value: selectedContactId }
            : field,
        );
      } else {
        invoiceData.push({ name: "contact_id", value: selectedContactId });
      }
    }

    let items = [];

    $("#items_list .item-list").each(function () {
      items.push({
        id: $(this).attr("id").replace("item-", ""),
        code: parseFloat($(this).find(".field_code").val()) || 1,
        quantity: parseFloat($(this).find(".field_qtd").val()) || 1,
        unit_price: parseFloat($(this).find(".field_price").val()) || 0,
        discount: parseFloat($(this).find(".field_desc").val()) || 0,
        tax: parseFloat($(this).find(".field_tax").val()) || 0,
      });
    });

    // =========================
    // AJAX
    // =========================
    const $saveBtn = $("#saveInvoiceBtn").prop("disabled", true);

    $.ajax({
      // Criar e editar passam pelo mesmo endpoint: o servidor distingue pelo edit_invoice_id
      url: "create_proform/ajax/save_invoices.php",
      method: "POST",
      dataType: "json",
      data: {
        invoice: invoiceData,
        items: items,
        company_id: company_id,
      },
      success: function (response) {
        if (!response.success) {
          $saveBtn.prop("disabled", false);
          return Swal.fire({
            icon: "error",
            title: "Erro",
            text:
              response.message ||
              response.error ||
              "Erro ao salvar a proforma.",
          });
        }

        if (typeof clearDraft === "function") clearDraft();

        console.log("✅ Proforma salva com sucesso:", response);

        Swal.fire({
          icon: "success",
          title: invoiceId
            ? "Proforma atualizada com sucesso!"
            : "Proforma criada com sucesso!",
          text: "Clique abaixo para visualizar.",
          confirmButtonText: "Ver proforma",
          confirmButtonColor: "#007abd",
        }).then((result) => {
          if (result.isConfirmed) {
            //  usar ID dinâmico vindo do backend
            window.location.href = `proform.php?id=${response.proform_id}`;
          }
        });
      },
      error: function (xhr, status, error) {
        $saveBtn.prop("disabled", false);

        Swal.fire({
          icon: "error",
          title: "Erro",
          text: xhr.responseJSON?.error || "Erro ao conectar ao servidor.",
        });

        console.log("STATUS:", status);
        console.log("ERROR:", error);
        console.log("RESPONSE:", xhr.responseText);
      },
    });
  });

  function loadInvoiceForEdit(id) {
    const draft = getStoredDraft();

    if (draft && draft.form && Object.keys(draft.form).length > 0) {
      $("#edit_invoice_id").val(id);

      const form = draft.form;
      const contactSelect = document.getElementById("contact-select");

      if (draft.meta?.contact_select) {
        $(contactSelect).val(draft.meta.contact_select).trigger("change");
      }

      $("#issue_date").val(form.issue_date || "");
      $("#reference").val(form.reference || "");
      $("#observation").val(form.observation || "");
      $("#retention").val(form.retention || 0);
      $("#currency")
        .val(form.currency || "AOA")
        .trigger("change");
      $("#manual_exchange_rate").val(form.manual_exchange_rate || 1);

      $("#items_list .item-list").remove();

      if (Array.isArray(draft.items) && draft.items.length > 0) {
        draft.items.forEach((item) => {
          addItemRow({
            id: item.id || item.item_id,
            code: item.code || "",
            description: item.description || item.name || "",
            unit_price: item.unit_price || 0,
            line_quantity: item.quantity || 1,
            tax: item.tax || 0,
            discount: item.discount || 0,
          });
        });
      }

      syncDueUI();
      syncRetentionUI();
      updateInvoiceSummary();
      setEditMode();
      invoiceId = id;
      return;
    }

    $.getJSON("proform/ajax/get_proform.php", { id: id }, function (response) {
      if (response.error) {
        Swal.fire("Erro", response.error, "error");
        return;
      }

      const data = response?.data;

      $("#edit_invoice_id").val(id);

      $("#contact-select")
        .val(toSelectValue(data.contact_id))
        .trigger("change");
      $("#issue_date").val(data.issue_date);

      setDueDays(data.due_date);

      $("#reference").val(data.reference);
      $("#observation").val(data.observation);

      if ($("#series option[value='" + data.series + "']").length === 0) {
        $("#series").append(new Option(data.series, data.series));
      }
      $("#series").val(data.series);

      $("#retention").val(data.retention || 0);

      $("#currency")
        .val(
          data.currency ||
            data.currency_items ||
            data.currency_company ||
            "AOA",
        )
        .trigger("change");
      if (data.manual_exchange_rate) {
        $("#manual_exchange_rate").val(data.manual_exchange_rate);
      }

      $("#items_list .item-list").remove();

      if (data.items && data.items.length > 0) {
        data.items.forEach((item) => {
          addItemRow({
            id: item.item_id || item.id,
            code: item.code,
            description: item.description || item.name,
            unit_price: item.unit_price,
            line_quantity: item.quantity,
            tax: item.tax,
            discount: item.discount,
          });
        });
      }
      syncRetentionUI();
      updateInvoiceSummary();

      setEditMode();
    });

    invoiceId = id;
  }
  // Expõe addItemRow ao script inline e repõe as linhas do rascunho
  window.addItemRow = addItemRow;

  if (
    typeof restoreDraftItems === "function" &&
    !new URLSearchParams(window.location.search).get("edit_id")
  ) {
    restoreDraftItems(addItemRow);
  }

  refreshLinesState();

  /* =========================================================
   SELECÇÃO DE CLIENTE
========================================================= */

  (function () {
    const selectBtn = document.getElementById("btn-select-contact");

    const modalElement = document.getElementById("contactSelectModal");

    const contactsList = document.getElementById("contactsList");

    const contactsLoading = document.getElementById("contactsLoading");

    const contactsEmpty = document.getElementById("contactsEmpty");

    const contactSearch = document.getElementById("contactSearch");

    const contactSelect = document.getElementById("contact-select");

    const contactIdInput = document.getElementById("contact_id");

    const contactSelectLabel = document.getElementById("contact-select-label");

    const contactForm = document.getElementById("contact-form");

    if (!selectBtn || !modalElement) {
      return;
    }

    const contactModal = bootstrap.Modal.getOrCreateInstance(modalElement);

    // Usa a MESMA lista já carregada por loadContacts() (contacts/ajax/fetch_contacts.php)
    // em vez de duplicar o pedido para o endpoint errado (get_contact.php é para 1 contacto).
    function currentContacts() {
      return contactsCache;
    }

    /* =====================================================
       ABRIR MODAL
    ===================================================== */

    selectBtn.addEventListener("click", function (event) {
      event.preventDefault();

      contactModal.show();

      openContactsList();
    });

    /* =====================================================
       PREPARAR/MOSTRAR A LISTA DE CONTACTOS
    ===================================================== */

    async function openContactsList() {
      contactsLoading.classList.remove("d-none");

      contactsEmpty.classList.add("d-none");

      contactsList.innerHTML = "";

      try {
        if (!contactsLoaded) {
          await contactsReadyPromise;
        }

        contactsLoading.classList.add("d-none");

        renderContacts(currentContacts());
      } catch (error) {
        console.error("Erro ao carregar contactos:", error);

        contactsLoading.classList.add("d-none");

        contactsList.innerHTML = "";

        contactsEmpty.classList.remove("d-none");

        contactsEmpty.querySelector("strong").textContent =
          "Erro ao carregar clientes";

        contactsEmpty.querySelector("span").textContent =
          "Não foi possível carregar a lista de clientes.";
      }
    }

    /* =====================================================
       RENDERIZAR CONTACTOS
    ===================================================== */

    function renderContacts(list, query) {
      contactsList.innerHTML = "";

      const showAnon =
        !query ||
        "cliente x anónimo anonimo consumidor final".includes(
          String(query || "").toLowerCase(),
        );

      if (showAnon) {
        const anonItem = document.createElement("div");
        anonItem.className = "contact-item";
        anonItem.dataset.contactId = ANON_VALUE;
        anonItem.dataset.name = "Cliente X";
        anonItem.dataset.nif = "";
        anonItem.innerHTML = `
                <div class="contact-avatar"><i class="bi bi-person-x"></i></div>
                <div class="contact-info">
                    <div class="contact-name">Cliente X</div>
                    <div class="contact-nif">Consumidor final / anónimo</div>
                </div>
                <div class="contact-check"><i class="bi bi-check-lg"></i></div>
            `;
        contactsList.appendChild(anonItem);
      }

      if ((!list || list.length === 0) && !showAnon) {
        contactsEmpty.classList.remove("d-none");

        return;
      }

      contactsEmpty.classList.add("d-none");

      (list || []).forEach((contact) => {
        const id = contact.id ?? contact.contact_id ?? contact.ID;

        const name =
          contact.name ??
          contact.contact_name ??
          contact.company_name ??
          "Cliente sem nome";

        const nif =
          contact.contributor ??
          contact.nif ??
          contact.tax_number ??
          contact.NIF ??
          "";

        const logo =
          contact.logo ??
          contact.logo_url ??
          contact.image ??
          contact.avatar ??
          "";

        const item = document.createElement("div");

        item.className = "contact-item";

        /*
         * Guardar o ID diretamente no elemento.
         */

        item.dataset.contactId = id;

        item.dataset.name = name;

        item.dataset.nif = nif;

        /*
         * Logo
         */

        let avatarHtml;

        if (logo) {
          avatarHtml = `
                    <div class="contact-avatar">

                        <img
                            src="${escapeHtml(logo)}"
                            alt=""
                            onerror="
                                this.style.display='none';
                                this.nextElementSibling.style.display='block';
                            "
                        >

                        <i
                            class="bi bi-building"
                            style="display:none;"
                        ></i>

                    </div>
                `;
        } else {
          avatarHtml = `
                    <div class="contact-avatar">
                        <i class="bi bi-building"></i>
                    </div>
                `;
        }

        /*
         * HTML do contacto
         */

        item.innerHTML = `

                ${avatarHtml}

                <div class="contact-info">

                    <div class="contact-name">
                        ${escapeHtml(name)}
                    </div>

                    <div class="contact-nif">
                        NIF: ${escapeHtml(nif || "—")}
                    </div>

                </div>

                <div class="contact-check">

                    <i class="bi bi-check-lg"></i>

                </div>

            `;

        contactsList.appendChild(item);
      });
    }

    /* =====================================================
       CLICAR NO CONTACTO
    ===================================================== */

    contactsList.addEventListener("click", function (event) {
      const item = event.target.closest(".contact-item");

      if (!item) {
        return;
      }

      const contactId = item.dataset.contactId;

      const contactName = item.dataset.name;

      const contactNif = item.dataset.nif;

      if (!contactId) {
        console.error("Contacto sem contact_id.");

        return;
      }

      /*
       * Marcar visualmente.
       */

      document.querySelectorAll(".contact-item.selected").forEach((element) => {
        element.classList.remove("selected");
      });

      item.classList.add("selected");

      /*
       * =================================================
       * GUARDAR CONTACT_ID
       * =================================================
       */

      if (contactIdInput) {
        contactIdInput.value = contactId;
      }

      /*
       * Alterar o botão.
       */

      if (contactSelectLabel) {
        contactSelectLabel.textContent = contactName;
      }

      /*
       * Disparar o "change" real em #contact-select: é o handler já
       * existente (mais acima neste ficheiro) que busca os dados
       * completos do contacto, trata o Cliente X anónimo, país/cidade
       * e desabilita os campos — definir só o .value não chamava isto.
       */

      if (contactSelect) {
        $(contactSelect).val(contactId).trigger("change");
      }

      /*
       * Fechar automaticamente.
       */

      setTimeout(() => {
        contactModal.hide();
      }, 120);
    });

    /* =====================================================
       PESQUISA
    ===================================================== */

    contactSearch.addEventListener("input", function () {
      const search = this.value.toLowerCase().trim();

      const filtered = currentContacts().filter((contact) => {
        const name = String(
          contact.name ?? contact.contact_name ?? contact.company_name ?? "",
        ).toLowerCase();

        const nif = String(
          contact.contributor ?? contact.nif ?? contact.tax_number ?? "",
        ).toLowerCase();

        return name.includes(search) || nif.includes(search);
      });

      renderContacts(filtered, search);
    });

    /* =====================================================
       AO ABRIR
    ===================================================== */

    modalElement.addEventListener("shown.bs.modal", function () {
      contactSearch.value = "";

      contactSearch.focus();
    });

    /* =====================================================
       ESCAPE HTML
    ===================================================== */

    function escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }
  })();
});
