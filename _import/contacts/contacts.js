/* =====================================================================
   Meus Clientes — lista / blocos
   ---------------------------------------------------------------------
   Backend inalterado. Endpoints usados (todos já existentes):
     GET  contacts/ajax/fetch_contacts.php?archived=0|1
     POST contacts/ajax/change_status.php   (id, status 0|1)
     POST contacts/ajax/delete_contact.php  (id)
     POST contacts/ajax/details_contact.php (id)
     POST contacts/ajax/get_contact.php     (id)   -> usado em "Duplicar"
     POST contacts/ajax/save_contact.php            -> usado em "Duplicar"
     GET  contacts/ajax/export_contacts.php?type=csv|excel|pdf
   Cadastro/edição: window.openContactForm(id?) e evento "contact:saved"
   (register_contact.js).

   Organização (equivalente a componentes):
     Dados .......... load(), prep(), computeView()
     Header/Tabs .... renderTabs(), eventos do tablist
     Toolbar ........ pesquisa, Filters (popover), ViewSwitcher, Export
     ClientList ..... listHTML()      ClientCard ... cardHTML()
     ClientActions .. actionsHTML(), moreMenuHTML()
     Pagination ..... renderFooter(), pageList()
     Estados ........ skeletonHTML(), emptyHTML(), noResultsHTML(), errorHTML()
   A mesma fonte de dados (state.data) alimenta lista e blocos.

   Botões de ação: mostram apenas o ícone; o rótulo aparece num tooltip
   ao passar o rato / focar (aria-label mantém o nome acessível).
   ===================================================================== */

$(function () {
  "use strict";

  const $app = $("#contactsApp");
  if (!$app.length) return;

  // ---------------------------------------------------------------
  // Constantes
  // ---------------------------------------------------------------
  const URL_FETCH = "contacts/ajax/fetch_contacts.php";
  const URL_STATUS = "contacts/ajax/change_status.php";
  const URL_DELETE = "contacts/ajax/delete_contact.php";
  const URL_DETAILS = "contacts/ajax/details_contact.php";
  const URL_GET = "contacts/ajax/get_contact.php";
  const URL_SAVE = "contacts/ajax/save_contact.php";
  const URL_EXPORT = "contacts/ajax/export_contacts.php";

  const PAGE_SIZES = [10, 25, 50, 100];
  const EXPORTS = {
    csv: {
      label: "CSV",
      ext: "csv",
      mime: /text\/csv|text\/plain|application\/csv/i,
    },
    excel: {
      label: "Excel",
      ext: "xlsx",
      mime: /spreadsheetml|ms-excel|octet-stream/i,
    },
    pdf: { label: "PDF", ext: "pdf", mime: /pdf/i },
  };

  // ---------------------------------------------------------------
  // Referências DOM
  // ---------------------------------------------------------------
  const $tabs = $app.find("[role='tab']");
  const $panel = $("#clientsPanel");
  const $view = $("#clientsView");
  const $footer = $("#clientsFooter");
  const $search = $("#cpSearch");
  const $searchWrap = $app.find(".cp-search");
  const $live = $("#cpLive");
  const $toasts = $("#cpToasts");
  const $bulk = $("#cpBulkbar");
  const $chips = $("#cpChips");
  const $filterBtn = $("#cpFilterBtn");
  const $exportBtn = $("#cpExportBtn");
  const $selCountry = $("#cpFilterCountry");
  const $selCity = $("#cpFilterCity");
  const $chkPhone = $("#cpFilterPhone");
  const $chkEmail = $("#cpFilterEmail");

  // ---------------------------------------------------------------
  // Estado
  // ---------------------------------------------------------------
  const store = {
    get(k, d) {
      try {
        const v = window.localStorage.getItem(k);
        return v === null ? d : v;
      } catch (e) {
        return d;
      }
    },
    set(k, v) {
      try {
        window.localStorage.setItem(k, v);
      } catch (e) {
        /* ignora (modo privado, etc.) */
      }
    },
  };

  const savedSize = parseInt(store.get("contacts:pageSize", "25"), 10);

  const state = {
    status: "loading", // loading | ready | error
    tab: "active", // active | archived
    view: store.get("contacts:view", "list") === "cards" ? "cards" : "list",
    query: "",
    filters: { country: "", city: "", hasPhone: false, hasEmail: false },
    page: 1,
    pageSize: PAGE_SIZES.indexOf(savedSize) > -1 ? savedSize : 25,
    selected: new Set(),
    data: { active: [], archived: [] },
    busy: false,
    exporting: null,
    animateNext: false,
    focusAfter: null,
  };

  let cv = null; // vista calculada no último render

  // ---------------------------------------------------------------
  // Utilitários
  // ---------------------------------------------------------------
  const esc = (s) =>
    String(s == null ? "" : s).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );

  const norm = (s) =>
    String(s == null ? "" : s)
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();

  const digits = (s) => String(s == null ? "" : s).replace(/\D/g, "");

  const fmtPhone = (raw) => {
    const compact = String(raw).replace(/\s/g, "");
    return /^\d{9}$/.test(compact)
      ? compact.replace(/(\d{3})(\d{3})(\d{3})/, "$1 $2 $3")
      : raw;
  };

  const reducedMotion = () =>
    !!(
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches
    );

  const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

  const debounce = (fn, ms) => {
    let t;
    return function () {
      const args = arguments;
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), ms);
    };
  };

  const wait = (ms) =>
    new Promise((r) => setTimeout(r, reducedMotion() ? 0 : ms));

  function prep(r) {
    const name = String(r.name || "").trim();
    const email = String(r.email || "").trim();
    const phone = String(r.telephone || "").trim();
    const country = String(r.country || "").trim();
    const city = String(r.city || "").trim();
    return {
      id: parseInt(r.id, 10),
      name,
      email,
      phone,
      country,
      city,
      active: parseInt(r.is_active, 10) === 1,
      _s: norm([name, email, phone, digits(phone), country, city].join(" | ")),
    };
  }

  const locationOf = (c) => [c.city, c.country].filter(Boolean).join(", ");
  const findById = (id) =>
    state.data.active.concat(state.data.archived).find((c) => c.id === id);

  function announce(msg) {
    $live.text("");
    setTimeout(() => $live.text(msg), 40);
  }

  // Pedido POST que devolve uma Promise (rejeita com a mensagem do servidor)
  function post(url, data) {
    return new Promise((resolve, reject) => {
      $.ajax({ url, type: "POST", data, dataType: "json" })
        .done(resolve)
        .fail((xhr) => {
          const msg =
            (xhr.responseJSON && xhr.responseJSON.message) ||
            "Erro inesperado. Tente novamente.";
          reject(new Error(msg));
        });
    });
  }

  // ---------------------------------------------------------------
  // Toasts (feedback discreto, com ação opcional "Desfazer")
  // ---------------------------------------------------------------
  function toast(message, opts) {
    opts = opts || {};
    const $t = $("<div>", {
      class: "cp-toast" + (opts.type === "error" ? " is-error" : ""),
      role: opts.type === "error" ? "alert" : "status",
    });
    if (opts.spinner)
      $t.append('<span class="cp-spin" aria-hidden="true"></span>');
    $t.append($("<span>").text(message));
    let timer = null;
    function close() {
      clearTimeout(timer);
      $t.remove();
    }
    if (opts.action) {
      $("<button>", { type: "button", text: opts.action.label })
        .on("click", function () {
          close();
          opts.action.run();
        })
        .appendTo($t);
    }
    $toasts.append($t);
    if (!opts.sticky)
      timer = setTimeout(close, opts.timeout || (opts.action ? 7000 : 4500));
    return { close };
  }

  function confirmDialog(o) {
    if (window.Swal) {
      return Swal.fire({
        title: o.title,
        text: o.text,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: o.confirmText,
        cancelButtonText: "Cancelar",
        focusCancel: true,
      }).then((r) => !!r.isConfirmed);
    }
    return Promise.resolve(window.confirm(o.title + "\n" + o.text));
  }

  // ---------------------------------------------------------------
  // Dados
  // ---------------------------------------------------------------
  function load(opts) {
    const silent = !!(opts && opts.silent);
    if (!silent) {
      state.status = "loading";
      render();
    }
    const req = (archived) =>
      $.ajax({
        url: URL_FETCH,
        type: "GET",
        data: { archived },
        dataType: "json",
        cache: false,
      });

    return new Promise((resolve) => {
      $.when(req(0), req(1))
        .done((a, b) => {
          const ja = a && a[0];
          const jb = b && b[0];
          if (
            ja &&
            ja.success &&
            Array.isArray(ja.data) &&
            jb &&
            jb.success &&
            Array.isArray(jb.data)
          ) {
            state.data.active = ja.data.map(prep);
            state.data.archived = jb.data.map(prep);
            state.status = "ready";
            pruneSelection();
          } else if (!silent) {
            state.status = "error";
          } else {
            toast("Não foi possível atualizar a lista.", { type: "error" });
          }
        })
        .fail(() => {
          if (!silent) state.status = "error";
          else toast("Não foi possível atualizar a lista.", { type: "error" });
        })
        .always(() => {
          render();
          resolve();
        });
    });
  }

  function computeView() {
    const all = state.data[state.tab];
    const tokens = norm(state.query).split(/\s+/).filter(Boolean);
    const f = state.filters;
    const items = all.filter(
      (c) =>
        (!f.country || c.country === f.country) &&
        (!f.city || c.city === f.city) &&
        (!f.hasPhone || c.phone) &&
        (!f.hasEmail || c.email) &&
        tokens.every((t) => c._s.indexOf(t) > -1),
    );
    const total = items.length;
    const pages = Math.max(1, Math.ceil(total / state.pageSize));
    state.page = Math.min(Math.max(1, state.page), pages);
    const start = (state.page - 1) * state.pageSize;
    return {
      all,
      items,
      total,
      pages,
      start,
      slice: items.slice(start, start + state.pageSize),
    };
  }

  function activeFilterCount() {
    const f = state.filters;
    return (
      (f.country ? 1 : 0) +
      (f.city ? 1 : 0) +
      (f.hasPhone ? 1 : 0) +
      (f.hasEmail ? 1 : 0)
    );
  }

  function pruneSelection() {
    const ids = new Set(computeView().items.map((c) => c.id));
    state.selected.forEach((id) => {
      if (!ids.has(id)) state.selected.delete(id);
    });
  }

  // ---------------------------------------------------------------
  // Render — blocos de HTML
  // ---------------------------------------------------------------
  // Ícones são decorativos: o nome acessível vem do aria-label do botão
  const ico = (name) => `<i class="bi ${name}" aria-hidden="true"></i>`;

  function statusHTML(c) {
    return c.active
      ? `<span class="cp-status is-active">${ico("bi-check-circle-fill")}Ativo</span>`
      : `<span class="cp-status is-archived">${ico("bi-archive")}Arquivado</span>`;
  }

  // Botão de ação: só ícone + tooltip (data-tip / title) + aria-label
  function iconBtn(act, icon, label, c) {
    return `<button type="button" class="cp-btn cp-btn-icon" data-act="${act}" data-id="${c.id}" data-tip aria-label="${label} ${esc(c.name)}" title="${label}">${ico(icon)}</button>`;
  }

  function moreMenuHTML(c) {
    const toggle = c.active
      ? `<button type="button" role="menuitem" class="cp-menuitem" data-act="archive" data-id="${c.id}">${ico("bi-archive")}Arquivar</button>`
      : `<button type="button" role="menuitem" class="cp-menuitem" data-act="restore" data-id="${c.id}">${ico("bi-arrow-counterclockwise")}Restaurar</button>`;
    return `
      <div class="cp-pop">
        <button type="button" class="cp-btn cp-btn-icon cp-more" data-pop aria-haspopup="menu" aria-expanded="false" aria-label="Mais ações para ${esc(c.name)}" title="Mais" data-tip>${ico("bi-three-dots")}</button>
        <div class="cp-pop-panel is-end" role="menu" aria-label="Ações de ${esc(c.name)}">
          <button type="button" role="menuitem" class="cp-menuitem" data-act="view" data-id="${c.id}">${ico("bi-card-list")}Ver cliente</button>
          <button type="button" role="menuitem" class="cp-menuitem" data-act="edit" data-id="${c.id}">${ico("bi-pencil")}Editar</button>
          <button type="button" role="menuitem" class="cp-menuitem" data-act="duplicate" data-id="${c.id}">${ico("bi-files")}Duplicar</button>
          ${toggle}
          <div class="cp-menu-sep" role="separator"></div>
          <button type="button" role="menuitem" class="cp-menuitem is-danger" data-act="delete" data-id="${c.id}">${ico("bi-trash")}Excluir</button>
        </div>
      </div>`;
  }

  function actionsHTML(c) {
    const toggle = c.active
      ? iconBtn("archive", "bi-archive", "Arquivar", c)
      : iconBtn("restore", "bi-arrow-counterclockwise", "Restaurar", c);
    return `<div class="cp-actions" role="group" aria-label="Ações de ${esc(c.name)}">${iconBtn("edit", "bi-pencil", "Editar", c)}${toggle}${moreMenuHTML(c)}</div>`;
  }

  const emailHTML = (c) =>
    c.email
      ? `<a href="mailto:${esc(c.email)}">${esc(c.email)}</a>`
      : "Sem email";
  const phoneHTML = (c) =>
    c.phone
      ? `<a href="tel:${esc(c.phone.replace(/\s/g, ""))}">${esc(fmtPhone(c.phone))}</a>`
      : "Não informado";

  function rowHTML(c) {
    const sel = state.selected.has(c.id);
    const loc = locationOf(c);
    return `
      <tr data-id="${c.id}" class="${sel ? "is-selected" : ""}">
        <td class="cp-col-check"><label class="cp-check cp-check-solo"><input type="checkbox" class="cp-row-check" data-id="${c.id}" aria-label="Selecionar ${esc(c.name)}" ${sel ? "checked" : ""}></label></td>
        <td class="cp-col-client">
          <div class="cp-client">
            <span class="cp-avatar" aria-hidden="true">${ico("bi-building")}</span>
            <button type="button" class="cp-name" data-act="view" data-id="${c.id}">${esc(c.name || "Sem nome")}</button>
          </div>
        </td>
        <td data-label="Contacto">
          <span class="cp-sub">${emailHTML(c)}</span>
          <span class="cp-sub">${phoneHTML(c)}</span>
        </td>
        <td data-label="Localização"><span class="cp-sub">${loc ? ico("bi-geo-alt") + " " + esc(loc) : "Não informado"}</span></td>
        <td class="cp-col-status" data-label="Estado">${statusHTML(c)}</td>
        <td class="cp-col-actions">${actionsHTML(c)}</td>
      </tr>`;
  }

  function listHTML(items) {
    return `
      <table class="cp-table">
        <caption class="visually-hidden-cp">Lista de clientes</caption>
        <thead>
          <tr>
            <th scope="col" class="cp-col-check"><label class="cp-check cp-check-solo"><input type="checkbox" id="cpSelectPage" aria-label="Selecionar todos os clientes desta página"></label></th>
            <th scope="col">Cliente</th>
            <th scope="col">Contacto</th>
            <th scope="col">Localização</th>
            <th scope="col" class="cp-col-status">Estado</th>
            <th scope="col" class="cp-col-actions">Ações</th>
          </tr>
        </thead>
        <tbody>${items.map(rowHTML).join("")}</tbody>
      </table>`;
  }

  function cardHTML(c) {
    const loc = locationOf(c);
    const restore = c.active
      ? ""
      : iconBtn("restore", "bi-arrow-counterclockwise", "Restaurar", c);
    return `
      <li class="cp-card" data-id="${c.id}">
        <div class="cp-card-top">
          <span class="cp-avatar" aria-hidden="true">${ico("bi-building")}</span>
          ${moreMenuHTML(c)}
        </div>
        <div class="cp-card-body">
          <button type="button" class="cp-name" data-act="view" data-id="${c.id}">${esc(c.name || "Sem nome")}</button>
          <span class="cp-sub">${emailHTML(c)}</span>
          <div class="cp-card-meta">
            <div>${ico("bi-telephone")}<span>${phoneHTML(c)}</span></div>
            <div>${ico("bi-geo-alt")}<span>${loc ? esc(loc) : "Não informado"}</span></div>
          </div>
        </div>
        <div class="cp-card-foot">
          <div class="cp-actions" role="group" aria-label="Ações de ${esc(c.name)}">
            ${iconBtn("edit", "bi-pencil", "Editar", c)}
            ${restore}
          </div>
          ${statusHTML(c)}
        </div>
      </li>`;
  }

  function gridHTML(items) {
    return `<ul class="cp-grid" role="list" aria-label="Lista de clientes em blocos">${items.map(cardHTML).join("")}</ul>`;
  }

  function skeletonHTML() {
    const n = 6;
    if (state.view === "cards") {
      return `<div class="cp-grid" aria-hidden="true">${'<div class="cp-skel cp-skel-card"></div>'.repeat(n)}</div>`;
    }
    const row =
      '<div class="cp-skel-row"><div class="cp-skel"></div><div class="cp-skel"></div><div class="cp-skel"></div><div class="cp-skel"></div><div class="cp-skel"></div></div>';
    return `<div aria-hidden="true">${row.repeat(n)}</div>`;
  }

  function stateHTML(icon, title, text, buttons, cls) {
    return `
      <div class="cp-state ${cls || ""}">
        <span class="cp-state-icon" aria-hidden="true">${ico(icon)}</span>
        <h2>${title}</h2>
        <p>${text}</p>
        ${buttons || ""}
      </div>`;
  }

  const emptyHTML = () =>
    state.tab === "active"
      ? stateHTML(
          "bi-people",
          "Ainda não tem clientes",
          "Adicione o primeiro cliente para começar a emitir documentos.",
          `<button type="button" class="cp-btn cp-btn-primary" data-act="new">${ico("bi-plus-lg")}Novo cliente</button>`,
        )
      : stateHTML(
          "bi-archive",
          "Sem clientes arquivados",
          "Os clientes que arquivar aparecem aqui e podem ser restaurados.",
        );

  const noResultsHTML = () =>
    stateHTML(
      "bi-search",
      "Nenhum cliente encontrado.",
      "Tente alterar os termos de pesquisa ou remover os filtros.",
      `<button type="button" class="cp-btn cp-btn-ghost" data-act="clear-all">Limpar pesquisa e filtros</button>`,
    );

  const errorHTML = () =>
    stateHTML(
      "bi-exclamation-triangle",
      "Não foi possível carregar os clientes",
      "Verifique a ligação e tente novamente.",
      `<button type="button" class="cp-btn cp-btn-ghost" data-act="retry">${ico("bi-arrow-clockwise")}Tentar novamente</button>`,
      "is-error",
    );

  // ---------------------------------------------------------------
  // Render — orquestração
  // ---------------------------------------------------------------
  function render() {
    cv = computeView();
    renderTabs();
    renderToolbar();
    renderBody();
    renderFooter();
    renderBulk();
  }

  function renderTabs() {
    $tabs.each(function () {
      const key = this.getAttribute("data-tab");
      const selected = key === state.tab;
      this.setAttribute("aria-selected", selected ? "true" : "false");
      this.tabIndex = selected ? 0 : -1;
      const n = state.status === "ready" ? state.data[key].length : "–";
      $(this).find(".cp-count").text(n);
    });
    $panel.attr({
      "aria-labelledby": "cpTab-" + state.tab,
      "aria-busy": state.status === "loading" ? "true" : "false",
    });
  }

  function renderToolbar() {
    $app.find("[data-view]").each(function () {
      this.setAttribute(
        "aria-pressed",
        this.getAttribute("data-view") === state.view ? "true" : "false",
      );
    });

    const n = activeFilterCount();
    $filterBtn
      .find(".cp-badge-dot")
      .text(n)
      .prop("hidden", n === 0);
    $filterBtn.attr(
      "aria-label",
      n ? `Filtros (${plural(n, "ativo", "ativos")})` : "Filtros",
    );

    const f = state.filters;
    const chips = [];
    if (f.country) chips.push(["country", "País: " + f.country]);
    if (f.city) chips.push(["city", "Cidade: " + f.city]);
    if (f.hasPhone) chips.push(["hasPhone", "Com telefone"]);
    if (f.hasEmail) chips.push(["hasEmail", "Com email"]);
    if (chips.length) {
      $chips
        .html(
          chips
            .map(
              ([k, t]) =>
                `<span class="cp-chip">${esc(t)}<button type="button" data-chip="${k}" aria-label="Remover filtro ${esc(t)}">${ico("bi-x-lg")}</button></span>`,
            )
            .join("") +
            `<button type="button" class="cp-linkbtn" data-chip="all">Limpar filtros</button>`,
        )
        .prop("hidden", false);
    } else {
      $chips.empty().prop("hidden", true);
    }

    buildFilterOptions();
    $searchWrap.toggleClass("has-value", state.query.length > 0);
  }

  function buildFilterOptions() {
    const all = state.data[state.tab];
    const uniq = (arr) =>
      Array.from(new Set(arr.filter(Boolean))).sort((a, b) =>
        a.localeCompare(b, "pt"),
      );
    const countries = uniq(all.map((c) => c.country));
    if (
      state.status === "ready" &&
      state.filters.country &&
      countries.indexOf(state.filters.country) < 0
    )
      state.filters.country = "";
    const cities = uniq(
      all
        .filter(
          (c) => !state.filters.country || c.country === state.filters.country,
        )
        .map((c) => c.city),
    );
    if (
      state.status === "ready" &&
      state.filters.city &&
      cities.indexOf(state.filters.city) < 0
    )
      state.filters.city = "";

    const opts = (list, val, label) =>
      `<option value="">${label}</option>` +
      list
        .map(
          (v) =>
            `<option value="${esc(v)}"${v === val ? " selected" : ""}>${esc(v)}</option>`,
        )
        .join("");
    $selCountry.html(opts(countries, state.filters.country, "Todos os países"));
    $selCity.html(opts(cities, state.filters.city, "Todas as cidades"));
    $chkPhone.prop("checked", state.filters.hasPhone);
    $chkEmail.prop("checked", state.filters.hasEmail);
  }

  function renderBody() {
    hideTips();
    let html;
    if (state.status === "loading") html = skeletonHTML();
    else if (state.status === "error") html = errorHTML();
    else if (!cv.all.length) html = emptyHTML();
    else if (!cv.total) html = noResultsHTML();
    else
      html = state.view === "cards" ? gridHTML(cv.slice) : listHTML(cv.slice);

    const anim = state.animateNext ? " cp-anim" : "";
    state.animateNext = false;
    $view.html(`<div class="cp-view${anim}">${html}</div>`);

    initTips($view[0]);
    syncSelectPage();
  }

  function renderFooter() {
    const show = state.status === "ready" && cv.total > 0;
    $footer.prop("hidden", !show);
    if (!show) {
      $footer.empty();
      return;
    }
    const from = cv.start + 1;
    const to = Math.min(cv.start + state.pageSize, cv.total);
    const sizes = PAGE_SIZES.map(
      (n) =>
        `<option value="${n}"${n === state.pageSize ? " selected" : ""}>${n}</option>`,
    ).join("");

    const pages = pageList(state.page, cv.pages)
      .map((p) =>
        p === "…"
          ? `<li class="cp-ellipsis" aria-hidden="true">…</li>`
          : `<li><button type="button" class="cp-btn cp-btn-ghost" data-page="${p}" ${p === state.page ? 'aria-current="page"' : ""} aria-label="Página ${p}">${p}</button></li>`,
      )
      .join("");

    $footer.html(`
      <div class="cp-pagesize">
        <label for="cpPageSize">Mostrar</label>
        <select id="cpPageSize">${sizes}</select>
        <span>${from}–${to} de ${cv.total}</span>
      </div>
      <nav aria-label="Paginação de clientes">
        <ul class="cp-pager">
          <li><button type="button" class="cp-btn cp-btn-ghost" data-page="${state.page - 1}" ${state.page === 1 ? "disabled" : ""}>Anterior</button></li>
          ${pages}
          <li><button type="button" class="cp-btn cp-btn-ghost" data-page="${state.page + 1}" ${state.page === cv.pages ? "disabled" : ""}>Próximo</button></li>
        </ul>
      </nav>`);

    if (state.focusAfter === "pager") {
      state.focusAfter = null;
      $footer.find("[aria-current='page']").trigger("focus");
    }
  }

  function pageList(cur, total) {
    const set = new Set([1, total, cur - 1, cur, cur + 1]);
    if (cur <= 3) [2, 3, 4].forEach((n) => set.add(n));
    if (cur >= total - 2)
      [total - 3, total - 2, total - 1].forEach((n) => set.add(n));
    const arr = Array.from(set)
      .filter((n) => n >= 1 && n <= total)
      .sort((a, b) => a - b);
    const out = [];
    arr.forEach((n, i) => {
      if (i && n - arr[i - 1] > 1) out.push("…");
      out.push(n);
    });
    return out;
  }

  // Barra contextual de seleção (só com seleção e só no modo Lista)
  function renderBulk() {
    const n = state.selected.size;
    const show = n > 0 && state.view === "list" && state.status === "ready";
    $bulk.prop("hidden", !show);
    if (!show) return;
    $bulk
      .find(".cp-bulk-count")
      .text(plural(n, "cliente selecionado", "clientes selecionados"));
    $bulk.find("[data-bulk='all']").prop("hidden", n >= cv.total);
    const archiving = state.tab === "active";
    $bulk
      .find("[data-bulk='status']")
      .html(
        archiving
          ? `${ico("bi-archive")}Arquivar`
          : `${ico("bi-arrow-counterclockwise")}Restaurar`,
      );
  }

  function syncSelectPage() {
    const el = document.getElementById("cpSelectPage");
    if (!el || !cv) return;
    const ids = cv.slice.map((c) => c.id);
    const n = ids.filter((id) => state.selected.has(id)).length;
    el.checked = n > 0 && n === ids.length;
    el.indeterminate = n > 0 && n < ids.length;
  }

  // ---------------------------------------------------------------
  // Tooltips (Bootstrap, se disponível; senão fica o atributo title)
  // ---------------------------------------------------------------
  function initTips(root) {
    if (!(window.bootstrap && bootstrap.Tooltip)) return;
    root.querySelectorAll("[data-tip]").forEach((el) =>
      bootstrap.Tooltip.getOrCreateInstance(el, {
        trigger: "hover focus",
        container: "body",
        placement: "top",
        delay: { show: 250, hide: 0 },
      }),
    );
  }

  function hideTips() {
    if (!(window.bootstrap && bootstrap.Tooltip)) return;
    $view[0].querySelectorAll("[data-tip]").forEach((el) => {
      const t = bootstrap.Tooltip.getInstance(el);
      if (t) t.dispose();
    });
  }

  // ---------------------------------------------------------------
  // Popovers / menus acessíveis (Filtros, Exportar, Mais)
  // ---------------------------------------------------------------
  function popItems($p) {
    return $p.find("[role='menuitem']:not(:disabled)");
  }

  function placePanel($wrap) {
    const $p = $wrap.children(".cp-pop-panel");
    $p.removeClass("is-up");
    const r = $p[0].getBoundingClientRect();
    const btn = $wrap.children("[data-pop]")[0].getBoundingClientRect();
    if (r.bottom > window.innerHeight - 8 && btn.top > r.height + 16)
      $p.addClass("is-up");
    if (!$p.hasClass("is-end") && r.right > window.innerWidth - 8)
      $p.addClass("is-end");
  }

  function openPop($wrap, focusLast) {
    closePops($wrap[0]);
    const $btn = $wrap.children("[data-pop]");
    const $p = $wrap.children(".cp-pop-panel");
    $wrap.addClass("is-open");
    $btn.attr("aria-expanded", "true");
    $wrap.closest(".cp-card, tr").addClass("has-open-pop");
    placePanel($wrap);
    void $p[0].offsetWidth;
    const $items = popItems($p);
    if ($items.length)
      $items.eq(focusLast ? $items.length - 1 : 0).trigger("focus");
    else $p.find("select, input, button").first().trigger("focus");
  }

  function closePop($wrap, returnFocus) {
    if (!$wrap.hasClass("is-open")) return;
    $wrap.removeClass("is-open");
    $wrap.children("[data-pop]").attr("aria-expanded", "false");
    $wrap.closest(".cp-card, tr").removeClass("has-open-pop");
    if (returnFocus) $wrap.children("[data-pop]").trigger("focus");
  }

  function closePops(except) {
    $app.find(".cp-pop.is-open").each(function () {
      if (this !== except) closePop($(this), false);
    });
  }

  $app.on("click", "[data-pop]", function (e) {
    e.preventDefault();
    // esconde o tooltip do botão para não ficar por cima do menu
    if (window.bootstrap && bootstrap.Tooltip) {
      const tip = bootstrap.Tooltip.getInstance(this);
      if (tip) tip.hide();
    }
    const $wrap = $(this).closest(".cp-pop");
    if ($wrap.hasClass("is-open")) closePop($wrap, true);
    else openPop($wrap);
  });

  $app.on("keydown", ".cp-pop", function (e) {
    const $wrap = $(this);
    if (e.target.closest(".cp-pop") !== this) return; // ignora popovers aninhados
    const $p = $wrap.children(".cp-pop-panel");
    const open = $wrap.hasClass("is-open");

    if (e.key === "Escape" && open) {
      e.preventDefault();
      e.stopPropagation();
      closePop($wrap, true);
      return;
    }

    const onTrigger = $(e.target).is("[data-pop]");
    if (
      onTrigger &&
      !open &&
      (e.key === "ArrowDown" || e.key === "ArrowUp") &&
      $p.attr("role") === "menu"
    ) {
      e.preventDefault();
      openPop($wrap, e.key === "ArrowUp");
      return;
    }
    if (!open || $p.attr("role") !== "menu") return;

    const $items = popItems($p);
    const idx = $items.index(document.activeElement);
    let next = null;
    if (e.key === "ArrowDown") next = idx < 0 ? 0 : (idx + 1) % $items.length;
    else if (e.key === "ArrowUp") next = idx <= 0 ? $items.length - 1 : idx - 1;
    else if (e.key === "Home") next = 0;
    else if (e.key === "End") next = $items.length - 1;
    else if (e.key === "Tab") closePop($wrap, false);
    if (next !== null) {
      e.preventDefault();
      $items.eq(next).trigger("focus");
    }
  });

  // fechar ao clicar fora ou quando o foco sai
  $(document).on("click", function (e) {
    if (!$(e.target).closest(".cp-pop").length) closePops();
  });
  $(document).on("focusin", function (e) {
    $app.find(".cp-pop.is-open").each(function () {
      if (!this.contains(e.target)) closePop($(this), false);
    });
  });
  // clicar num item do menu fecha-o
  $app.on("click", ".cp-menuitem", function () {
    closePop($(this).closest(".cp-pop"), false);
  });

  // ---------------------------------------------------------------
  // Abas
  // ---------------------------------------------------------------
  function selectTab(key, focus) {
    if (key !== state.tab) {
      state.tab = key;
      state.page = 1;
      state.selected.clear();
      state.animateNext = true;
      render();
      announce(
        `${key === "active" ? "Clientes ativos" : "Clientes arquivados"}: ${plural(cv.total, "cliente", "clientes")}.`,
      );
    }
    if (focus) $app.find(`[data-tab='${key}']`).trigger("focus");
  }

  $tabs.on("click", function () {
    selectTab(this.getAttribute("data-tab"), false);
  });

  $tabs.on("keydown", function (e) {
    const order = $tabs
      .map(function () {
        return this.getAttribute("data-tab");
      })
      .get();
    let i = order.indexOf(this.getAttribute("data-tab"));
    if (e.key === "ArrowRight") i = (i + 1) % order.length;
    else if (e.key === "ArrowLeft") i = (i - 1 + order.length) % order.length;
    else if (e.key === "Home") i = 0;
    else if (e.key === "End") i = order.length - 1;
    else return;
    e.preventDefault();
    selectTab(order[i], true);
  });

  // ---------------------------------------------------------------
  // Pesquisa (instantânea) + Ctrl/Cmd + K
  // ---------------------------------------------------------------
  const announceResults = debounce(() => {
    if (state.status !== "ready") return;
    announce(
      cv.total
        ? `${plural(cv.total, "cliente encontrado", "clientes encontrados")}.`
        : "Nenhum cliente encontrado.",
    );
  }, 500);

  function applyFilterChange() {
    state.page = 1;
    pruneSelection();
    render();
    announceResults();
  }

  function clearSearch() {
    $search.val("");
    state.query = "";
    applyFilterChange();
    $search.trigger("focus");
  }

  $search.on("input", function () {
    state.query = this.value;
    applyFilterChange();
  });

  $search.on("keydown", function (e) {
    if (e.key === "Escape" && this.value) {
      e.preventDefault();
      clearSearch();
    }
  });

  $app.on("click", ".cp-search-clear", clearSearch);

  $(document).on("keydown", function (e) {
    if (
      (e.ctrlKey || e.metaKey) &&
      !e.altKey &&
      String(e.key).toLowerCase() === "k"
    ) {
      if (document.querySelector(".modal.show")) return;
      e.preventDefault();
      $search.trigger("focus").trigger("select");
    }
  });

  // ---------------------------------------------------------------
  // Filtros
  // ---------------------------------------------------------------
  $selCountry.on("change", function () {
    state.filters.country = this.value;
    state.filters.city = "";
    applyFilterChange();
  });
  $selCity.on("change", function () {
    state.filters.city = this.value;
    applyFilterChange();
  });
  $chkPhone.on("change", function () {
    state.filters.hasPhone = this.checked;
    applyFilterChange();
  });
  $chkEmail.on("change", function () {
    state.filters.hasEmail = this.checked;
    applyFilterChange();
  });

  function resetFilters() {
    state.filters = { country: "", city: "", hasPhone: false, hasEmail: false };
  }

  $app.on("click", "[data-filters='clear']", function () {
    resetFilters();
    applyFilterChange();
  });
  $app.on("click", "[data-filters='close']", function () {
    closePop($(this).closest(".cp-pop"), true);
  });

  $chips.on("click", "[data-chip]", function () {
    const k = this.getAttribute("data-chip");
    if (k === "all") resetFilters();
    else if (k === "country") state.filters.country = state.filters.city = "";
    else if (k === "city") state.filters.city = "";
    else state.filters[k] = false;
    applyFilterChange();
    $filterBtn.trigger("focus");
  });

  // ---------------------------------------------------------------
  // Alternar Lista / Blocos (sem recarregar)
  // ---------------------------------------------------------------
  $app.on("click", "[data-view]", function () {
    const v = this.getAttribute("data-view");
    if (v === state.view) return;
    state.view = v;
    store.set("contacts:view", v);
    if (v === "cards") state.selected.clear(); // seleção múltipla só existe no modo Lista
    state.animateNext = true;
    render();
    announce(
      v === "cards" ? "Visualização em blocos." : "Visualização em lista.",
    );
  });

  // ---------------------------------------------------------------
  // Paginação
  // ---------------------------------------------------------------
  $footer.on("click", "[data-page]", function () {
    const p = parseInt(this.getAttribute("data-page"), 10);
    if (!p || p === state.page) return;
    state.page = p;
    state.focusAfter = "pager";
    render();
    const surface = $app.find(".cp-surface")[0];
    if (surface && surface.scrollIntoView)
      surface.scrollIntoView({
        block: "start",
        behavior: reducedMotion() ? "auto" : "smooth",
      });
  });

  $footer.on("change", "#cpPageSize", function () {
    state.pageSize = parseInt(this.value, 10) || 25;
    store.set("contacts:pageSize", String(state.pageSize));
    state.page = 1;
    render();
    $("#cpPageSize").trigger("focus");
  });

  // ---------------------------------------------------------------
  // Seleção múltipla
  // ---------------------------------------------------------------
  $view.on("change", ".cp-row-check", function () {
    const id = parseInt(this.getAttribute("data-id"), 10);
    if (this.checked) state.selected.add(id);
    else state.selected.delete(id);
    $(this).closest("tr").toggleClass("is-selected", this.checked);
    syncSelectPage();
    renderBulk();
  });

  $view.on("change", "#cpSelectPage", function () {
    const checked = this.checked;
    cv.slice.forEach((c) =>
      checked ? state.selected.add(c.id) : state.selected.delete(c.id),
    );
    $view.find(".cp-row-check").each(function () {
      this.checked = state.selected.has(
        parseInt(this.getAttribute("data-id"), 10),
      );
      $(this).closest("tr").toggleClass("is-selected", this.checked);
    });
    syncSelectPage();
    renderBulk();
  });

  $bulk.on("click", "[data-bulk]", function () {
    const k = this.getAttribute("data-bulk");
    const ids = Array.from(state.selected);
    if (k === "all") {
      cv.items.forEach((c) => state.selected.add(c.id));
      render();
      announce(
        `${plural(state.selected.size, "cliente selecionado", "clientes selecionados")}.`,
      );
    } else if (k === "none") {
      state.selected.clear();
      render();
    } else if (k === "status") {
      changeStatus(ids, state.tab === "active" ? 0 : 1);
    } else if (k === "export") {
      exportSelectedCSV(ids);
    } else if (k === "delete") {
      deleteClients(ids);
    }
  });

  // ---------------------------------------------------------------
  // Ações sobre clientes
  // ---------------------------------------------------------------
  function leave(ids) {
    ids.forEach((id) =>
      $app
        .find(`tr[data-id='${id}'], .cp-card[data-id='${id}']`)
        .addClass("is-leaving"),
    );
    return wait(200);
  }

  function keepFocus() {
    const a = document.activeElement;
    if (!a || a === document.body || !document.body.contains(a)) {
      $panel.attr("tabindex", "-1").trigger("focus");
    }
  }

  // Move clientes entre as listas (ativos <-> arquivados) sem novo pedido
  function moveLocal(ids, toActive) {
    const from = toActive ? "archived" : "active";
    const to = toActive ? "active" : "archived";
    const set = new Set(ids);
    const moved = state.data[from].filter((c) => set.has(c.id));
    state.data[from] = state.data[from].filter((c) => !set.has(c.id));
    moved.forEach((c) => (c.active = toActive));
    state.data[to] = state.data[to].concat(moved).sort((a, b) => b.id - a.id); // mesma ordem do backend (id DESC)
    ids.forEach((id) => state.selected.delete(id));
  }

  async function changeStatus(ids, status, isUndo) {
    if (state.busy || !ids.length) return;
    state.busy = true;
    const ok = [];
    let lastError = "";
    for (const id of ids) {
      try {
        const r = await post(URL_STATUS, { id, status });
        if (r.status === "success") ok.push(id);
        else lastError = r.message || "";
      } catch (err) {
        lastError = err.message;
      }
    }
    const toActive = status === 1;
    if (ok.length) {
      await leave(ok);
      moveLocal(ok, toActive);
      render();
      keepFocus();
      const msg = toActive
        ? ok.length === 1
          ? "Cliente restaurado."
          : `${ok.length} clientes restaurados.`
        : ok.length === 1
          ? "Cliente arquivado."
          : `${ok.length} clientes arquivados.`;
      toast(
        msg,
        isUndo
          ? {}
          : {
              action: {
                label: "Desfazer",
                run: () => changeStatus(ok, toActive ? 0 : 1, true),
              },
            },
      );
    }
    if (ok.length < ids.length) {
      toast(
        ok.length
          ? `${ids.length - ok.length} cliente(s) não foram atualizados.`
          : lastError || "Não foi possível atualizar o cliente.",
        { type: "error" },
      );
    }
    state.busy = false;
  }

  async function deleteClients(ids) {
    if (state.busy || !ids.length) return;
    const one = ids.length === 1 ? findById(ids[0]) : null;
    const confirmed = await confirmDialog({
      title: one ? `Excluir "${one.name}"?` : `Excluir ${ids.length} clientes?`,
      text: "Essa ação não pode ser desfeita! Clientes com facturas vinculadas serão apenas arquivados.",
      confirmText: "Sim, excluir!",
    });
    if (!confirmed) return;

    state.busy = true;
    const removed = [];
    const archived = [];
    let lastError = "";
    let lastMsg = "";
    for (const id of ids) {
      try {
        const r = await post(URL_DELETE, { id });
        if (r.success) {
          (r.archived ? archived : removed).push(id);
          if (r.archived && r.message) lastMsg = r.message;
        } else lastError = r.message || "";
      } catch (err) {
        lastError = err.message;
      }
    }
    if (removed.length || archived.length) {
      await leave(removed.concat(archived));
      state.data.active = state.data.active.filter(
        (c) => removed.indexOf(c.id) < 0,
      );
      state.data.archived = state.data.archived.filter(
        (c) => removed.indexOf(c.id) < 0,
      );
      const toArchive = archived.filter((id) =>
        state.data.active.some((c) => c.id === id),
      );
      if (toArchive.length) moveLocal(toArchive, false);
      removed.concat(archived).forEach((id) => state.selected.delete(id));
      render();
      keepFocus();
      if (removed.length)
        toast(
          removed.length === 1
            ? "Cliente excluído."
            : `${removed.length} clientes excluídos.`,
        );
      if (archived.length)
        toast(
          archived.length === 1 && lastMsg
            ? lastMsg
            : `${archived.length} cliente(s) com facturas foram arquivados em vez de excluídos.`,
          { timeout: 7000 },
        );
    }
    const failed = ids.length - removed.length - archived.length;
    if (failed)
      toast(lastError || `${failed} cliente(s) não foram excluídos.`, {
        type: "error",
      });
    state.busy = false;
  }

  // Duplicar: reutiliza get_contact.php + save_contact.php (sem novo backend)
  async function duplicateClient(id) {
    if (state.busy) return;
    const c = findById(id);
    const confirmed = await confirmDialog({
      title: `Duplicar "${c ? c.name : "cliente"}"?`,
      text: "Será criado um novo cliente com os mesmos dados.",
      confirmText: "Sim, duplicar",
    });
    if (!confirmed) return;
    state.busy = true;
    const t = toast("A duplicar cliente…", { spinner: true, sticky: true });
    try {
      const src = await post(URL_GET, { id });
      if (!src || src.error)
        throw new Error((src && src.error) || "Cliente não encontrado.");
      ["id", "company_id", "created_at", "updated_at", "is_active"].forEach(
        (k) => delete src[k],
      );
      src.name = (src.name || "") + " (cópia)";
      Object.keys(src).forEach((k) => {
        if (src[k] === null) src[k] = "";
      });
      const res = await new Promise((resolve, reject) => {
        $.ajax({ url: URL_SAVE, type: "POST", data: src, dataType: "text" })
          .done((txt) => {
            try {
              resolve(JSON.parse(txt));
            } catch (e) {
              reject(new Error("Resposta inválida do servidor."));
            }
          })
          .fail(() =>
            reject(new Error("Erro na requisição. Tente novamente.")),
          );
      });
      t.close();
      if (res.status !== "success")
        throw new Error(res.message || "Não foi possível duplicar o cliente.");
      state.busy = false;
      await load({ silent: true });
      toast("Cliente duplicado.");
    } catch (err) {
      t.close();
      toast(err.message, { type: "error" });
      state.busy = false;
    }
  }

  // Delegação de ações (lista e blocos usam o mesmo handler)
  $view.on("click", "[data-act]", function (e) {
    const act = this.getAttribute("data-act");
    const id = parseInt(this.getAttribute("data-id"), 10);
    if (window.bootstrap && bootstrap.Tooltip) {
      const tip = bootstrap.Tooltip.getInstance(this);
      if (tip) tip.hide();
    }
    switch (act) {
      case "view":
        openDetails(id);
        break;
      case "edit":
        if (window.openContactForm) window.openContactForm(id);
        break;
      case "duplicate":
        duplicateClient(id);
        break;
      case "archive":
        changeStatus([id], 0);
        break;
      case "restore":
        changeStatus([id], 1);
        break;
      case "delete":
        deleteClients([id]);
        break;
      case "new":
        if (window.openContactForm) window.openContactForm();
        break;
      case "retry":
        load();
        break;
      case "clear-all":
        state.query = "";
        $search.val("");
        resetFilters();
        applyFilterChange();
        $search.trigger("focus");
        break;
    }
    e.stopPropagation();
  });

  // Mobile: tocar no registo abre os detalhes (comportamento que já existia)
  $view.on("click", "tr[data-id]", function (e) {
    if (window.innerWidth > 768) return;
    if ($(e.target).closest("button, a, input, label, .cp-pop").length) return;
    openDetails(parseInt(this.getAttribute("data-id"), 10));
  });

  // Gravar (novo/edição) no modal existente -> a lista atualiza-se sozinha
  $(document).on("contact:saved", function (e, info) {
    load({ silent: true }).then(() => {
      toast(
        info && info.mode === "create"
          ? "Cliente criado."
          : "Cliente guardado.",
      );
    });
  });

  // ---------------------------------------------------------------
  // Detalhes do cliente (modal #contactModal já existente)
  // ---------------------------------------------------------------
  function openDetails(contactId) {
    $.ajax({
      url: URL_DETAILS,
      method: "POST",
      data: { id: contactId },
      dataType: "json",
    })
      .done(function (contact) {
        if (!contact || contact.error) {
          toast("Erro ao carregar detalhes do contato.", { type: "error" });
          return;
        }
        const text = (id, value, fallback) =>
          $(id).text(value || fallback || "Não informado");

        const link = (id, value, type) => {
          const $el = $(id);
          if (!$el.length) return;
          if (!value) {
            $el.text("Não informado");
            return;
          }
          const href =
            type === "mailto"
              ? "mailto:" + value
              : /^https?:\/\//i.test(value)
                ? value
                : "https://" + value;
          $el.empty().append(document.createTextNode(value + " "));
          $("<a>", {
            href,
            target: "_blank",
            rel: "noopener",
            class: "ms-1 text-decoration-none",
            "aria-label": "Abrir " + value,
          })
            .append(
              '<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>',
            )
            .appendTo($el);
        };

        const phone = (linkId, spanId, data) => {
          const $l = $(linkId);
          if (data) {
            $(spanId).text(data);
            $l.attr("href", "tel:" + data).css("display", "inline-flex");
          } else $l.hide();
        };

        text("#contactName", contact.name);
        text("#contactType", contact.type);
        text("#contactContributor", contact.contributor);
        link("#contactEmail", contact.email, "mailto");
        link("#contactWebsite", contact.website, "url");
        phone("#contactTelephoneLink", "#contactTelephone", contact.telephone);
        phone("#contactCellphoneLink", "#contactCellphone", contact.cellphone);
        text("#contactAddress", contact.address);
        text(
          "#contactLocation",
          [contact.country, contact.city].filter(Boolean).join(" / "),
        );
        text("#contactPoBox", contact.po_box);
        text("#contactFax", contact.fax);
        text("#contactPrefName", contact.pref_name);
        link("#contactPrefEmail", contact.pref_email, "mailto");
        phone(
          "#contactPrefTelephoneLink",
          "#contactPrefTelephone",
          contact.pref_telephone,
        );
        phone(
          "#contactPrefCellphoneLink",
          "#contactPrefCellphone",
          contact.pref_cellphone,
        );
        text("#contactNumberCopys", contact.numberCopys);
        text("#contactdue_date", contact.due_date);
        text("#contactLanguage", contact.language);
        text("#contactPaymentMethod", contact.payment_method);
        text("#contactCurrency", contact.currency);
        text(
          "#contactObservations",
          contact.observations,
          "Nenhuma observação",
        );
        text(
          "#contactUpdatedAt",
          contact.updated_at &&
            typeof window.formatDateTimeToBrazilian === "function"
            ? window.formatDateTimeToBrazilian(contact.updated_at)
            : contact.updated_at,
        );

        $("#contactModal").modal("show");
      })
      .fail(function () {
        toast("Erro ao carregar detalhes do contato.", { type: "error" });
      });
  }

  // ---------------------------------------------------------------
  // Exportar
  // ---------------------------------------------------------------
  function setExporting(type) {
    state.exporting = type;
    $exportBtn.attr("aria-busy", type ? "true" : "false");
    $exportBtn
      .find(".cp-export-icon")
      .html(
        type
          ? '<span class="cp-spin" aria-hidden="true"></span>'
          : ico("bi-download"),
      );
    $exportBtn.find(".cp-export-text").text(type ? "A gerar…" : "Exportar");
  }

  function saveBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
  }

  async function exportFile(type) {
    const cfg = EXPORTS[type];
    if (!cfg || state.exporting) return;
    if (!(window.fetch && window.Blob)) {
      window.location.href = `${URL_EXPORT}?type=${type}`; // navegadores antigos
      return;
    }
    setExporting(type);
    const t = toast(`A gerar ficheiro ${cfg.label}…`, {
      spinner: true,
      sticky: true,
    });
    try {
      const res = await fetch(`${URL_EXPORT}?type=${type}`, {
        credentials: "same-origin",
      });
      const ct = res.headers.get("Content-Type") || "";
      if (!res.ok || !cfg.mime.test(ct))
        throw new Error("Resposta inesperada do servidor.");
      const blob = await res.blob();
      const cd = res.headers.get("Content-Disposition") || "";
      const m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd);
      saveBlob(blob, m ? decodeURIComponent(m[1]) : `contatos.${cfg.ext}`);
      t.close();
      toast(`Exportação ${cfg.label} concluída.`);
    } catch (err) {
      t.close();
      toast(`Não foi possível exportar em ${cfg.label}. Tente novamente.`, {
        type: "error",
      });
    } finally {
      setExporting(null);
    }
  }

  // Exportação da seleção (CSV gerado no navegador com os dados já carregados)
  function exportSelectedCSV(ids) {
    const rows = ids.map(findById).filter(Boolean);
    if (!rows.length) return;
    const cell = (v) => `"${String(v == null ? "" : v).replace(/"/g, '""')}"`;
    const lines = [
      ["Nome", "Email", "Telefone", "País", "Cidade", "Estado"]
        .map(cell)
        .join(","),
    ].concat(
      rows.map((c) =>
        [
          c.name,
          c.email,
          c.phone,
          c.country,
          c.city,
          c.active ? "Ativo" : "Arquivado",
        ]
          .map(cell)
          .join(","),
      ),
    );
    saveBlob(
      new Blob(["\ufeff" + lines.join("\r\n")], {
        type: "text/csv;charset=utf-8",
      }),
      "clientes_selecionados.csv",
    );
    toast(
      `${plural(rows.length, "cliente exportado", "clientes exportados")} em CSV.`,
    );
  }

  $app.on("click", "[data-export]", function () {
    exportFile(this.getAttribute("data-export"));
  });

  // ---------------------------------------------------------------
  // Início
  // ---------------------------------------------------------------
  render();
  load();
});
