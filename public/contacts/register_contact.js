/**
 * Registo / edição de cliente, dentro do modal #contactFormModal (contacts.php).
 *
 * API pública:
 *   window.openContactForm()      -> abre o modal para um cliente NOVO
 *   window.openContactForm(id)    -> abre o modal para EDITAR o cliente `id`
 *
 * Evento (disparado em `document` depois de gravar com sucesso):
 *   "contact:saved"  com  { mode: "create" | "update", id }
 *   -> contacts.js escuta este evento e atualiza a lista, sem recarregar a página.
 *
 * Links antigos (register_contact.php) redirecionam para contacts.php?new=1
 * ou contacts.php?edit=ID, que abrem o modal automaticamente (ver fim do ficheiro).
 */
$(document).ready(function () {
  const modalEl = document.getElementById("contactFormModal");
  if (!modalEl) return; // esta página não tem o modal de cliente

  const contactModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const $form = $("#contactForm");

  let countryMap = {};
  let contactId = null; // null = cliente novo; número = a editar esse cliente
  let loadToken = 0; // ignora respostas antigas se o utilizador abrir outro cliente
  let isSaving = false; // evita gravar duas vezes com duplo clique

  // Estado da consulta de NIF (declarado aqui em cima porque resetForm() o usa)
  const NIF_REGEX = /^(5\d+|\d{9}[A-Za-z0-9]+)$/;
  const NIF_CACHE_KEY = "agt_nif_cache_v1";
  const NIF_CACHE_TTL = 24 * 60 * 60 * 1000; // 24h
  const NIF_CACHE_MAX = 200;
  let lastNifChecked = "";
  let nifRequestId = 0;
  let nifWaitingForInternet = false;

  const defaultSubtitle = $("#contactFormModalSubtitle").text();

  // =====================================================================
  // ASSISTENTE DE ETAPAS (antes vivia num <script> inline em register_contact.php)
  // =====================================================================
  const steps = modalEl.querySelectorAll(".step-content");
  const indicators = modalEl.querySelectorAll(".step-progress .step");
  const bar = document.getElementById("stepBar");
  const prevBtn = document.getElementById("prevBtn");
  const nextBtn = document.getElementById("nextBtn");
  const saveBtn = document.getElementById("saveChangesContact");
  let current = 0;

  function updateWizard() {
    const lastStep = steps.length - 1;

    steps.forEach((s, i) => s.classList.toggle("active", i === current));
    indicators.forEach((s, i) => s.classList.toggle("active", i <= current));
    bar.style.width = (current / lastStep) * 100 + "%";

    prevBtn.style.display = current === 0 ? "none" : "";

    const isLast = current === lastStep;
    nextBtn.classList.toggle("d-none", isLast);
    saveBtn.classList.toggle("d-none", !isLast);
  }

  // ---------- validação ----------
  const PHONE_REGEX = /^[29]\d{8}$/; // 9 dígitos, começa por 2 ou 9
  const EMAIL_FIELD_NAMES = ["email", "pref_email"];

  function getLabel(field) {
    const label = field
      .closest(".col-12, .col-md-6, .mb-3")
      ?.querySelector("label");
    return label ? label.innerText : field.name;
  }

  // Devolve { field, message } com o primeiro erro da etapa, ou null se estiver tudo bem.
  function findStepError(stepIndex) {
    const fields = steps[stepIndex].querySelectorAll(
      "input:not([type=hidden]), select, textarea",
    );
    fields.forEach((f) => f.classList.remove("is-invalid"));

    for (const field of fields) {
      const value = (field.value || "").trim();
      const isEmailField = EMAIL_FIELD_NAMES.includes(field.name);

      // Email nunca é obrigatório, mesmo que o input tenha o atributo required
      if (field.required && !value && !isEmailField) {
        return { field, message: `Preencha o campo: ${getLabel(field)}` };
      }

      // campos opcionais vazios não são validados
      if (!value) continue;

      if (field.name === "name" && value.length < 6) {
        return { field, message: "Nome deve ter no mínimo 6 caracteres." };
      }

      if (field.name === "contributor") {
        const nifRegex1 = /^5\d+$/; // começa com 5 e só números
        const nifRegex2 = /^\d{9}[A-Za-z0-9]+$/; // 9 dígitos + alfanumérico
        if (!(nifRegex1.test(value) || nifRegex2.test(value))) {
          return {
            field,
            message: "NIF inválido. Ex: 943798589UB049 ou 50000000123214",
          };
        }
      }

      // EMAIL (opcional, mas se preenchido tem de ser válido)
      if (isEmailField && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        return { field, message: "Email inválido." };
      }

      // TELEFONES (9 dígitos, começa por 2 ou 9)
      if (
        ["telephone", "pref_telephone", "pref_cellphone"].includes(
          field.name,
        ) &&
        !PHONE_REGEX.test(value)
      ) {
        return {
          field,
          message: "Telefone inválido. Deve ter 9 dígitos e começar por 2 ou 9.",
        };
      }
    }

    return null;
  }

  // Vai para a etapa com erro, marca o campo e avisa.
  function showStepError(stepIndex, error) {
    current = stepIndex;
    updateWizard();
    error.field.classList.add("is-invalid");
    Swal.fire({ icon: "error", title: "Erro!", text: error.message }).then(
      () => error.field.focus(),
    );
  }

  nextBtn.addEventListener("click", () => {
    const error = findStepError(current);
    if (error) return showStepError(current, error);

    if (current < steps.length - 1) {
      current++;
      updateWizard();
    }
  });

  prevBtn.addEventListener("click", () => {
    if (current > 0) {
      current--;
      updateWizard();
    }
  });

  // limpa o destaque de erro ao digitar
  $form.on("input", "input, textarea, select", function () {
    this.classList.remove("is-invalid");
  });

  // Email nunca bloqueia o avanço por causa do atributo required
  $form
    .find('input[name="email"], input[name="pref_email"]')
    .removeAttr("required");

  // Enter nunca deve "submeter" o formulário (a gravação é feita pelo botão Salvar)
  $form.on("submit", (e) => e.preventDefault());

  // =====================================================================
  // HELPERS DE REDE / CAMPOS MANUAIS
  // =====================================================================
  function fetchWithTimeout(url, options = {}, ms = 8000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), ms);
    return fetch(url, { ...options, signal: controller.signal }).finally(() =>
      clearTimeout(timer),
    );
  }

  // Sem internet (GeoNames indisponível): troca o <select> por um campo de texto
  function toTextInput(id, value = "", placeholder = "") {
    const el = $("#" + id);
    if (!el.length || el.is("input")) return;
    if (el.hasClass("select2-hidden-accessible")) el.select2("destroy");
    const input = $("<input>", {
      type: "text",
      class: "form-control",
      id: id,
      name: id,
      placeholder: placeholder,
      required: el.prop("required"),
    }).val(value);
    el.replaceWith(input);
  }

  function enableManualLocation(country = "", city = "") {
    toTextInput("country", country, "País");
    toTextInput("city", city, "Cidade");
  }

  // ---------- botão de notas / observações ----------
  function refreshNotesButton() {
    const has = ($("#observations").val() || "").trim() !== "";
    $("#btnNotes").toggleClass("has-notes", has);
    $("#btnNotesIcon").text(has ? "task_alt" : "edit_note");
    $("#btnNotesText").text(
      has
        ? "Notas adicionadas — clique para editar"
        : "Acrescentar notas ou observações importantes",
    );
  }

  $("#btnNotes").on("click", async function () {
    const result = await Swal.fire({
      title: "Notas e observações",
      input: "textarea",
      inputValue: $("#observations").val() || "",
      inputPlaceholder:
        "Escreva aqui notas ou observações importantes sobre este cliente...",
      inputAttributes: { "aria-label": "Notas e observações" },
      showCancelButton: true,
      confirmButtonText: "Guardar",
      cancelButtonText: "Cancelar",
    });
    if (result.isConfirmed) {
      $("#observations").val((result.value || "").trim());
      refreshNotesButton();
    }
  });

  // Telefones: só dígitos, máximo 9
  $("#telephone, #pref_telephone, #pref_cellphone").on("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 9);
  });

  // ---------- país / cidade (GeoNames) ----------
  // O formulário atual não tem os campos #country/#city; estas funções só
  // trabalham se eles existirem (evita uma chamada de rede inútil a cada abertura).
  async function selectCountry(selectedCountry = "", selectedCity = "") {
    if (!$("#country").length) return false;

    const username = "israelsouza";

    const countrySelect = $("#country");
    const citySelect = $("#city");

    try {
      countrySelect.html('<option value="">Carregando países...</option>');

      citySelect.html('<option value="">Selecione um país primeiro</option>');

      // Destroy select2 antes de recriar
      if (countrySelect.hasClass("select2-hidden-accessible")) {
        countrySelect.select2("destroy");
      }

      if (citySelect.hasClass("select2-hidden-accessible")) {
        citySelect.select2("destroy");
      }

      if (navigator.onLine === false) throw new Error("Sem internet");

      const response = await fetchWithTimeout(
        `https://secure.geonames.org/countryInfoJSON?username=${username}`,
      );

      const data = await response.json();

      countryMap = {};

      let options = '<option value="">Selecione um país</option>';

      (data.geonames || []).forEach((country) => {
        countryMap[country.countryName] = country.geonameId;

        options += `
        <option value="${country.countryName}">
          ${country.countryName}
        </option>
      `;
      });

      countrySelect.html(options);

      countrySelect.select2({
        width: "100%",
        placeholder: "Selecione um país",
        allowClear: false,
        dropdownParent: countrySelect.parent(),
      });

      // Selecionar país automaticamente
      if (selectedCountry) {
        countrySelect.val(selectedCountry).trigger("change");

        await loadCities(selectedCountry, selectedCity);
      }

      return true;
    } catch (error) {
      console.error("Erro ao carregar países:", error);

      // Sem internet / GeoNames em baixo: preenchimento manual
      enableManualLocation(selectedCountry || "Angola", selectedCity);

      return false;
    }
  }

  async function loadCities(countryName, selectedCity = "") {
    if (!$("#city").length) return false;

    const citySelect = $("#city");

    try {
      const countryId = countryMap[countryName];

      if (!countryId) {
        citySelect.html('<option value="">Selecione um país primeiro</option>');

        return false;
      }

      // Destroy select2 antes de recriar
      if (citySelect.hasClass("select2-hidden-accessible")) {
        citySelect.select2("destroy");
      }

      citySelect.html('<option value="">Carregando cidades...</option>');

      if (navigator.onLine === false) throw new Error("Sem internet");

      const response = await fetchWithTimeout(
        `https://secure.geonames.org/childrenJSON?geonameId=${countryId}&username=israelsouza`,
      );

      const data = await response.json();

      let options = '<option value="">Selecione uma cidade</option>';

      const cities = [
        ...new Set(
          (data.geonames || []).map((city) => city.name).filter(Boolean),
        ),
      ];

      cities.forEach((cityName) => {
        options += `
        <option 
          value="${cityName}"
          ${cityName === selectedCity ? "selected" : ""}
        >
          ${cityName}
        </option>
      `;
      });

      citySelect.html(options);

      citySelect.select2({
        width: "100%",
        placeholder: "Selecione uma cidade",
        allowClear: false,
        dropdownParent: citySelect.parent(),
      });

      if (selectedCity) {
        citySelect.val(selectedCity).trigger("change");
      }

      return true;
    } catch (error) {
      console.error("Erro ao carregar cidades:", error);

      toTextInput("city", selectedCity, "Cidade");

      return false;
    }
  }

  $("#country").on("change", function () {
    if ($(this).data("ignore-change")) return;
    loadCities($(this).val());
  });

  // ---------- "usar definições padrão da conta" ----------
  function toggleFields() {
    const isChecked = $("#usar_definicoes").prop("checked");

    // Vencimento, idioma e moeda são fixos; observações agora usam o botão de notas.
    // Selects não podem ser editados, mas ainda serão enviados
    if (isChecked) {
      $("#numberCopys, #payment_method")
        .addClass("blocked-select")
        .attr("tabindex", "-1");
    } else {
      $("#numberCopys, #payment_method")
        .removeClass("blocked-select")
        .removeAttr("tabindex");
    }
  }

  $("#usar_definicoes").on("change", toggleFields);

  // =====================================================================
  // ESTADO DO MODAL (novo / editar)
  // =====================================================================
  function applyModalMode() {
    const isEdit = !!contactId;

    $("#contactFormModalTitle").text(
      modalEl.dataset[isEdit ? "titleEdit" : "titleNew"],
    );
    $("#contactFormModalSubtitle").text(
      isEdit ? "Atualize os dados do cliente" : defaultSubtitle,
    );
    saveBtn.textContent = isEdit ? "Salvar alterações" : "Salvar";

    // A consulta de NIF na AGT só faz sentido ao criar
    $("#btnConsultNif").toggleClass("d-none", isEdit);
  }

  function setLoading(loading) {
    saveBtn.disabled = loading;
    nextBtn.disabled = loading;
    $(modalEl).find(".modal-body").css("opacity", loading ? 0.5 : 1);
  }

  // Guarda o valor de cada campo depois de carregar, para na edição
  // enviarmos só o que mudou (getUpdatedFields).
  function snapshotOriginals() {
    $form.find("input, select, textarea").each(function () {
      $(this).data("original", $(this).val());
    });
  }

  function getUpdatedFields() {
    const updatedData = {};
    $form.find("input, select, textarea").each(function () {
      const fieldName = $(this).attr("name");
      if (fieldName && $(this).val() !== $(this).data("original")) {
        updatedData[fieldName] = $(this).val();
      }
    });
    return updatedData;
  }

  function resetForm() {
    $form[0].reset();

    // input hidden: reset() não os limpa quando o JS mudou o valor
    $("#observations").val("");
    refreshNotesButton();

    $form.find(".is-invalid").removeClass("is-invalid");
    $form.find("input, select, textarea").removeData("original");

    // Consulta de NIF: invalida pedidos em curso e limpa o estado
    nifRequestId++;
    lastNifChecked = "";
    nifWaitingForInternet = false;
    setNifStatus("", "");
    $("#btnConsultNif").prop("disabled", false);

    toggleFields();
    current = 0;
    updateWizard();
  }

  async function loadContactData(id, token) {
    setLoading(true);

    try {
      const contact = await $.ajax({
        url: "contacts/ajax/get_contact.php",
        method: "POST",
        data: { id: id },
        dataType: "json",
      });

      if (token !== loadToken) return; // o utilizador já abriu outro cliente

      if (!contact || !contact.id) {
        contactModal.hide();
        Swal.fire({
          icon: "error",
          title: "Erro!",
          text: "Contato não encontrado.",
        });
        return;
      }

      // INPUTS / TEXTAREAS
      $("#companyName").val(contact.name || "");
      $("#email").val(contact.email || "");
      $("#telephone").val(contact.telephone || "");
      $("#address").val(contact.address || "");
      $("#observations").val(contact.observations || "");
      refreshNotesButton();
      $("#contributor").val(contact.contributor || "");
      $("#po_box").val(contact.po_box || "");
      $("#website").val(contact.website || "");
      $("#fax").val(contact.fax || "");
      $("#pref_name").val(contact.pref_name || "");
      $("#pref_email").val(contact.pref_email || "");
      $("#pref_telephone").val(contact.pref_telephone || "");
      $("#pref_cellphone").val(contact.pref_cellphone || "");

      // SELECTS NORMAIS
      const selectFields = {
        "#type": contact.type,
        "#numberCopys": contact.numberCopys,
        "#payment_method": contact.payment_method,
      };

      Object.entries(selectFields).forEach(([selector, value]) => {
        const field = $(selector);

        if (field.length) {
          field.val(value ?? "").trigger("change");
        }
      });

      // PAÍS / CIDADE (só se o formulário tiver esses campos)
      await selectCountry(contact.country || "", contact.city || "");

      if (token !== loadToken) return;

      // ESTADO ORIGINAL (para enviar só o que mudar)
      snapshotOriginals();
    } catch (error) {
      if (token !== loadToken) return;

      console.error("Erro ao carregar contato:", error);

      contactModal.hide();
      Swal.fire({
        icon: "error",
        title: "Erro!",
        text: "Erro ao carregar detalhes do contato.",
      });
    } finally {
      if (token === loadToken) setLoading(false);
    }
  }

  // =====================================================================
  // ABRIR O MODAL
  // =====================================================================
  window.openContactForm = async function (id = null) {
    const parsed = parseInt(id, 10);
    contactId = parsed > 0 ? parsed : null;
    const token = ++loadToken;

    resetForm();
    applyModalMode();
    contactModal.show();

    if (contactId) {
      await loadContactData(contactId, token);
    } else {
      await selectCountry();
    }
  };

  $(document).on("click", "#newContact", function (e) {
    e.preventDefault();
    window.openContactForm();
  });

  // Ao fechar: descarta consultas de NIF ainda em curso
  modalEl.addEventListener("hide.bs.modal", () => {
    nifRequestId++;
  });

  // Depois de fechado: limpa tudo, para o próximo uso começar do zero
  modalEl.addEventListener("hidden.bs.modal", () => {
    contactId = null;
    loadToken++;
    resetForm();
    applyModalMode();
    setLoading(false);
  });

  // =====================================================================
  // GRAVAR
  // =====================================================================
  $(document).on("click", "#saveChangesContact", function (event) {
    event.preventDefault();
    if (isSaving) return;

    // Valida TODAS as etapas (antes só validava ao clicar em "Próximo",
    // por isso a última etapa podia ser gravada com dados inválidos).
    for (let i = 0; i < steps.length; i++) {
      const error = findStepError(i);
      if (error) return showStepError(i, error);
    }

    const isEdit = !!contactId;
    const editedId = contactId;
    let formData;
    let url;

    if (isEdit) {
      // Edição: envia apenas os campos modificados
      formData = getUpdatedFields();

      if (Object.keys(formData).length === 0) {
        contactModal.hide();
        Swal.fire({
          icon: "info",
          title: "Sem alterações",
          text: "Nenhum campo foi alterado.",
          timer: 1800,
          showConfirmButton: false,
        });
        return;
      }

      formData["id"] = editedId;
      url = "contacts/ajax/update_contact.php";
    } else {
      // Novo cliente: envia todos os dados do formulário
      formData = new FormData($form[0]);
      url = "contacts/ajax/save_contact.php";
    }

    isSaving = true;
    saveBtn.disabled = true;

    $.ajax({
      url: url,
      method: "POST",
      data: formData,
      dataType: "text", // o parse é feito abaixo, com tratamento de erro
      processData: !(formData instanceof FormData),
      contentType:
        formData instanceof FormData
          ? false
          : "application/x-www-form-urlencoded; charset=UTF-8",
      success: function (response) {
        let res;
        try {
          res = typeof response === "string" ? JSON.parse(response) : response;
        } catch (e) {
          Swal.fire({
            icon: "error",
            title: "Erro inesperado!",
            text: "Ocorreu um erro ao processar sua solicitação.",
          });
          return;
        }

        if (res && res.status === "success") {
          contactModal.hide();

          Swal.fire({
            icon: "success",
            title: "Sucesso!",
            text: res.message,
            timer: 1800,
            showConfirmButton: false,
          });

          // Avisa a lista de clientes para se atualizar (contacts.js)
          $(document).trigger("contact:saved", [
            {
              mode: isEdit ? "update" : "create",
              id: isEdit ? editedId : res.id || null,
            },
          ]);
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro!",
            text: (res && res.message) || "Não foi possível salvar.",
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Erro!",
          text: "Erro ao tentar salvar os dados. Tente novamente.",
        });
      },
      complete: function () {
        isSaving = false;
        saveBtn.disabled = false;
      },
    });
  });

  // Botão para preencher dados de teste (só aparece em localhost)
  $("#btnFillContact").on("click", function () {
    $("#type").val("Normal").trigger("change");
    $("#companyName").val("Empresa Teste " + Math.floor(Math.random() * 1000));
    $("#contributor").val(
      "5" + String(Math.floor(Math.random() * 1e9)).padStart(9, "0"),
    );
    $("#email").val(
      "contato" + Math.floor(Math.random() * 1000) + "@teste.com",
    );
    $("#address").val("Rua Exemplo, 123 - Centro");
    $("#telephone").val("923456789");
    $("#po_box").val("CP-123");
    $("#website").val("www.teste.com");
    $("#fax").val("222000000");

    $("#pref_name").val("Gerente Teste");
    $("#pref_email").val("gerente@teste.com");
    $("#pref_telephone").val("222555666");

    $("#observations").val("Cadastro de teste gerado automaticamente.");
    refreshNotesButton();

    // Tenta selecionar Angola se já estiver carregado
    if ($("#country option[value='Angola']").length > 0) {
      $("#country").val("Angola").trigger("change");
      setTimeout(function () {
        $("#city").val("Luanda").trigger("change");
      }, 1000);
    }
  });

  // =====================================================================
  // CONSULTA DE NIF NA AGT (com cache e modo offline)
  // =====================================================================
  function setNifStatus(type, text) {
    const el = document.getElementById("nifStatus");
    if (!el) return;
    const colors = {
      loading: "text-muted",
      success: "text-success",
      warning: "text-warning",
      error: "text-danger",
    };
    el.className = "form-text " + (colors[type] || "");
    el.textContent = text || "";
  }

  // ---- cache local (localStorage) ----
  function readNifCache() {
    try {
      return JSON.parse(localStorage.getItem(NIF_CACHE_KEY)) || {};
    } catch (e) {
      return {};
    }
  }

  function getCachedNif(key) {
    return readNifCache()[key] || null;
  }

  function saveCachedNif(key, data) {
    try {
      const cache = readNifCache();
      cache[key] = { t: Date.now(), data: data };
      const keys = Object.keys(cache);
      if (keys.length > NIF_CACHE_MAX) {
        keys
          .sort((a, b) => cache[a].t - cache[b].t)
          .slice(0, keys.length - NIF_CACHE_MAX)
          .forEach((k) => delete cache[k]);
      }
      localStorage.setItem(NIF_CACHE_KEY, JSON.stringify(cache));
    } catch (e) {
      /* localStorage indisponível: segue sem cache */
    }
  }

  // BI (9 dígitos + 2 letras + 3 dígitos) => AID, restantes => NIF
  function detectDocType(value) {
    return /^\d{9}[A-Za-z]{2}\d{3}$/.test(value) ? "AID" : "NIF";
  }

  async function nifAlreadyExists(value) {
    try {
      const r = await fetch("index/ajax/check_contribuitor.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ registration_number: value }),
      });
      const d = await r.json();
      return !!d.exists;
    } catch (e) {
      console.error("Erro ao verificar NIF duplicado:", e);
      return false;
    }
  }

  function applyNifData(c, note = "") {
    const field = document.getElementById("contributor");

    if (c.nome) {
      $("#companyName").val(c.nome).removeClass("is-invalid");
    }
    field.classList.remove("is-invalid");

    const partes = [c.nome, c.estado_label, c.regime_label]
      .filter(Boolean)
      .join(" · ");

    if (c.estado && c.estado !== "A") {
      setNifStatus("warning", `${partes} — contribuinte não ativo${note}`);
      Swal.fire({
        icon: "warning",
        title: "Atenção",
        text: `Este contribuinte consta como "${c.estado_label}" na AGT.`,
      });
    } else {
      setNifStatus(
        "success",
        partes + (c.nao_residente ? " · Não residente" : "") + note,
      );
    }
  }

  async function consultNIF(force = false) {
    if (contactId) return; // só na criação de cliente
    if (!modalEl.classList.contains("show")) return; // modal a fechar/fechado

    const field = document.getElementById("contributor");
    const value = field.value.trim();

    if (!value || !NIF_REGEX.test(value)) {
      setNifStatus("", "");
      return;
    }
    if (!force && value === lastNifChecked) return;

    lastNifChecked = value;
    const reqId = ++nifRequestId;

    // 1) NIF já existe na nossa base?
    if (await nifAlreadyExists(value)) {
      if (reqId !== nifRequestId) return;
      lastNifChecked = "";
      field.classList.add("is-invalid");
      field.value = "";
      setNifStatus("error", "Este NIF já existe.");
      Swal.fire({
        icon: "error",
        title: "Erro!",
        text: "Este NIF já existe. Por favor, insira outro.",
      });
      return;
    }

    if (reqId !== nifRequestId) return;

    const tipoDocumento = detectDocType(value);
    const cacheKey = tipoDocumento + "|" + value.toUpperCase();
    const cached = getCachedNif(cacheKey);

    // 2) Cache válida (24h): não repete o request
    if (!force && cached && Date.now() - cached.t < NIF_CACHE_TTL) {
      applyNifData(cached.data, " · (cache)");
      return;
    }

    // 3) Sem internet: usa cache antiga se existir, senão preenchimento manual
    if (navigator.onLine === false) {
      nifWaitingForInternet = true;
      if (cached) {
        applyNifData(cached.data, " · (cache, sem internet)");
      } else {
        setNifStatus(
          "warning",
          "Sem internet. Preencha os dados do cliente manualmente.",
        );
      }
      return;
    }

    // 4) Consulta à AGT (via proxy PHP)
    setNifStatus("loading", "A consultar NIF na AGT...");
    $("#btnConsultNif").prop("disabled", true);

    try {
      const response = await fetchWithTimeout(
        "contacts/ajax/consult_nif.php",
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            tipoDocumento: tipoDocumento,
            numeroDocumento: value,
            refresh: force,
          }),
        },
        20000,
      );
      const raw = await response.text();
      let res;
      try {
        res = JSON.parse(raw);
      } catch (parseError) {
        // o servidor devolveu HTML (erro PHP): mostra o início para diagnóstico
        console.error(
          "consult_nif.php não devolveu JSON (HTTP " + response.status + "):",
          raw.replace(/<[^>]+>/g, " ").slice(0, 500),
        );
        throw parseError;
      }

      if (reqId !== nifRequestId) return; // resposta antiga

      // diagnóstico: mostra na consola o motivo devolvido pelo servidor / AGT
      if (res.status !== "success") {
        console.warn("consult_nif.php respondeu:", response.status, res);
      }

      if (res.status === "success") {
        saveCachedNif(cacheKey, res.data);
        nifWaitingForInternet = false;
        applyNifData(res.data);
      } else if (res.status === "not_found") {
        setNifStatus(
          "warning",
          (res.message || "NIF não encontrado na AGT") +
            ". Preencha os dados manualmente.",
        );
      } else if (cached) {
        applyNifData(cached.data, " · (dados em cache)");
      } else {
        setNifStatus(
          "error",
          (res.message || "Falha na consulta à AGT") +
            ". Preencha os dados manualmente.",
        );
      }
    } catch (error) {
      if (reqId !== nifRequestId) return;
      console.error("Erro ao consultar NIF na AGT:", error);
      if (navigator.onLine === false) nifWaitingForInternet = true;
      if (cached) {
        applyNifData(cached.data, " · (dados em cache)");
      } else {
        setNifStatus(
          "error",
          "Não foi possível consultar a AGT. Preencha os dados manualmente.",
        );
      }
    } finally {
      if (reqId === nifRequestId) {
        $("#btnConsultNif").prop("disabled", false);
      }
    }
  }

  $("#contributor").on("blur", () => consultNIF());
  $("#contributor").on("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      consultNIF(true);
    }
  });
  $("#contributor").on("input", function () {
    // se o utilizador alterar o NIF, limpa o estado anterior
    if (this.value.trim() !== lastNifChecked) setNifStatus("", "");
  });
  $("#btnConsultNif").on("click", () => consultNIF(true));

  // Voltou a internet: repete a consulta que ficou pendente
  window.addEventListener("online", () => {
    if (
      nifWaitingForInternet &&
      modalEl.classList.contains("show") &&
      $("#contributor").val().trim()
    ) {
      nifWaitingForInternet = false;
      consultNIF(true);
    }
  });

  // =====================================================================
  // INIT
  // =====================================================================
  updateWizard();
  toggleFields();

  // Links antigos (register_contact.php) chegam aqui como ?new=1 ou ?edit=ID
  const params = new URLSearchParams(window.location.search);
  const editParam = parseInt(params.get("edit"), 10);

  if (params.has("new") || editParam > 0) {
    params.delete("new");
    params.delete("edit");
    const qs = params.toString();
    history.replaceState(
      null,
      "",
      window.location.pathname + (qs ? "?" + qs : ""),
    );

    window.openContactForm(editParam > 0 ? editParam : null);
  }
});
