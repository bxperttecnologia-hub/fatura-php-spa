// ==================================================
// HELPERS GLOBAIS (toast + guardar ficheiro + download por fetch/blob)
// ==================================================
function bxToast(message, type = "info", opts = {}) {
  let host = document.getElementById("bxToastHost");
  if (!host) {
    host = document.createElement("div");
    host.id = "bxToastHost";
    host.setAttribute("role", "status");
    host.setAttribute("aria-live", "polite");
    document.body.appendChild(host);
  }
  const icons = {
    success: "bi-check-circle-fill",
    error: "bi-exclamation-triangle-fill",
    info: "bi-info-circle-fill",
  };
  const el = document.createElement("div");
  el.className = `bx-toast bx-toast--${type}`;
  el.innerHTML =
    '<span class="bx-toast__icon"></span><span class="bx-toast__msg"></span>';
  el.querySelector(".bx-toast__icon").innerHTML =
    type === "loading"
      ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>'
      : `<i class="bi ${icons[type] || icons.info}" aria-hidden="true"></i>`;
  el.querySelector(".bx-toast__msg").textContent = message;
  host.appendChild(el);

  let timer = null;
  const close = () => {
    clearTimeout(timer);
    el.classList.add("is-leaving");
    setTimeout(() => el.remove(), 160);
  };
  if (!opts.sticky && type !== "loading") {
    timer = setTimeout(close, opts.duration || (type === "error" ? 5000 : 2800));
  }
  return {
    close,
    update(msg) {
      el.querySelector(".bx-toast__msg").textContent = msg;
    },
  };
}

// Dispara o download de um Blob no próprio browser (sem abrir nova aba)
function bxSaveBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 4000);
}

// fetch -> blob -> <a download>. Dá feedback real de sucesso/erro
// (substitui o antigo window.location.href + setTimeout de 2 s).
async function downloadFromUrl(url, opts = {}) {
  const {
    method = "GET",
    body = null,
    fallbackName = "ficheiro",
    pendingMsg = "A preparar ficheiro...",
    okMsg = "Ficheiro descarregado com sucesso",
    expectPdf = false,
  } = opts;
  const toast = bxToast(pendingMsg, "loading");
  try {
    const res = await fetch(url, { method, body, credentials: "same-origin" });
    if (!res.ok) throw new Error(`Erro do servidor (${res.status}).`);
    const blob = await res.blob();

    // Alguns endpoints devolvem JSON/HTML de erro com status 200.
    if (blob.size < 4096) {
      const txt = (await blob.text()).trim();
      if (txt.startsWith("{") || txt.startsWith("<")) {
        let msg = "Resposta inesperada do servidor.";
        try {
          msg = JSON.parse(txt).error || msg;
        } catch (_) {}
        throw new Error(msg);
      }
    }
    if (expectPdf) {
      const head = await blob.slice(0, 5).text();
      if (!head.startsWith("%PDF")) throw new Error("O servidor não devolveu um PDF.");
    }

    const cd = res.headers.get("Content-Disposition") || "";
    const m = cd.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
    const filename = m ? decodeURIComponent(m[1]) : fallbackName;
    bxSaveBlob(blob, filename);
    toast.close();
    bxToast(okMsg, "success");
  } catch (err) {
    toast.close();
    bxToast(err.message || "Não foi possível descarregar o ficheiro.", "error");
  }
}

$(document).ready(function () {
  let invoices = []; // todos os dados vindos do servidor
  let receipts = []; // todos os dados vindos do servidor
  let credit_notes = []; // todos os dados vindos do servidor
  let delivery_notes = []; // todos os dados vindos do servidor
  let debit_notes = []; // todos os dados vindos do servidor
  let filteredInvoices = []; // após filtros/ordenação
  let currentPage = 1;
  let pageSize = 25;
  let docType = "invoices"; // invoices | receipts | credit_notes | debit_notes | delivery_notes

  // quais datasets já responderam (para mostrar skeleton enquanto carrega)
  const loaded = {
    invoices: false,
    receipts: false,
    credit_notes: false,
    delivery_notes: false,
    debit_notes: false,
  };
  // selecção persiste entre páginas e filtros (ids como string)
  const selectedIds = new Set();
  let bulkBusy = false;

  // ==================================================
  // HELPERS
  // ==================================================
  const esc = (v) =>
    String(v ?? "").replace(
      /[&<>"']/g,
      (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
    );

  // "YYYY-MM-DD" é lido como data LOCAL (new Date("2026-04-10") seria UTC e
  // pode mostrar o dia anterior em fusos negativos)
  const parseDate = (v) => {
    if (!v) return null;
    if (v instanceof Date) return isNaN(v) ? null : v;
    const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);
    const d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(v);
    return isNaN(d) ? null : d;
  };
  const fmtDate = (v) => {
    const d = parseDate(v);
    if (!d) return "-";
    return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
  };
  // 95 000,00 Kz  (símbolo/posição vêm da moeda do documento)
  const bxMoney = (value, symbol = "", position = "right") => {
    const [i, d] = (Number(value) || 0).toFixed(2).split(".");
    const num = `${i.replace(/\B(?=(\d{3})+(?!\d))/g, "\u00A0")},${d}`;
    if (!symbol) return num;
    return position === "left" ? `${symbol}\u00A0${num}` : `${num}\u00A0${symbol}`;
  };
  const startOfToday = () => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  };
  const daysOverdue = (row) => {
    const due = parseDate(row.due_date);
    if (!due) return 0;
    return Math.floor((startOfToday() - due) / 86400000);
  };

  // Estado "efectivo" de uma fatura. "Vencido" NÃO é um status da BD: é derivado
  // (vencimento passado e ainda não paga) — mesma regra que o updateInsights já usava.
  const STATUS_LABEL = {
    pago: "Pago",
    pendente: "Pendente",
    parcial: "Parcial",
    vencido: "Vencido",
    rascunho: "Rascunho",
    anulado: "Anulado",
  };
  function invoiceStatus(row) {
    const raw = String(row.status_invoice || "").trim();
    const n = raw.toLowerCase();
    let base;
    let known = true;
    if (n.includes("cancel") || n.includes("anul")) base = "anulado";
    else if (n.includes("rascun")) base = "rascunho";
    else if (n.includes("parcial")) base = "parcial";
    else if (n.includes("pago") || n.includes("quit")) base = "pago";
    else {
      base = "pendente";
      known = n.includes("pend");
    }
    const overdue = (base === "pendente" || base === "parcial") && daysOverdue(row) > 0;
    const key = overdue ? "vencido" : base;
    // status desconhecido da BD: mostra o nome real em vez de o rotular como "Pendente"
    const label = key === "pendente" && !known && raw ? raw : STATUS_LABEL[key];
    return { key, base, overdue, label };
  }

  const invoiceUrl = (row) =>
    `invoice.php?id=${String(row.issue_date || "").replaceAll("-", "")}/${row.company_id}/${row.id}`;

  const docCell = (type, label, number) => `
    <div class="bx-doc">
      <span class="bx-doc__type bx-doc__type--${type}">${esc(label)}</span>
      <span class="bx-doc__num">${esc(number || "-")}</span>
    </div>`;

  const pdfBtn = (attrs, label, cls = "") => `
    <button type="button" class="bx-icon-btn bx-icon-btn--pdf ${cls}" ${attrs}
      data-bs-toggle="tooltip" data-bs-title="Baixar PDF" aria-label="${esc(label)}">
      <i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span class="bx-btn__label">PDF</span>
    </button>`;
  const viewLink = (href, title, label) => `
    <a class="bx-icon-btn" href="${href}" data-bs-toggle="tooltip" data-bs-title="${esc(title)}"
      aria-label="${esc(label)}">
      <i class="bi bi-eye" aria-hidden="true"></i><span class="bx-btn__label">Visualizar</span>
    </a>`;

  // ==================================================
  // CONFIGURAÇÃO POR TIPO DE DOCUMENTO
  // Cada tipo define: cabeçalho da tabela, campos usados
  // pelos filtros (cliente/status/datas), como desenhar cada
  // linha e as colunas usadas na exportação (PDF/CSV).
  // ==================================================
  const SORT = '<i class="sort-icon bi bi-arrow-down-up text-muted ms-1" aria-hidden="true"></i>';
  const DOC_TYPES = {
    invoices: {
      colspan: 8,
      title: "Faturas",
      emptyLabel: "Nenhuma fatura encontrada",
      defaultSort: { key: "id", dir: "desc" },
      hasStatusFilter: true,
      clientLabel: "Cliente",
      clientField: "cliente",
      statusField: "status_invoice",
      dateField: "issue_date",
      headerHtml: `
        <tr>
          <th class="bx-th-check"><input type="checkbox" id="selectAll" class="form-check-input" aria-label="Selecionar todos os documentos desta página"></th>
          <th data-key="id">Documento ${SORT}</th>
          <th data-key="cliente">Cliente ${SORT}</th>
          <th>Status</th>
          <th data-key="issue_date">Emissão ${SORT}</th>
          <th data-key="due_date">Vencimento ${SORT}</th>
          <th data-key="final_total" class="bx-num">Total ${SORT}</th>
          <th class="text-end">Ações</th>
        </tr>
      `,
      renderRow: renderInvoiceRow,
      exportColumns: [
        ["Documento", (r) => r.codigo],
        ["Cliente", (r) => r.cliente],
        ["Status", (r) => invoiceStatus(r).label],
        ["Emissão", (r) => fmtDate(r.issue_date)],
        ["Vencimento", (r) => fmtDate(r.due_date)],
        [
          "Total",
          (r) => bxMoney(r.final_total, r.symbol, r.position),
          (r) => Number(r.final_total || 0).toFixed(2).replace(".", ","),
        ],
      ],
    },

    receipts: {
      colspan: 7,
      title: "Recibos",
      emptyLabel: "Nenhum recibo encontrado",
      defaultSort: { key: "issue_date", dir: "desc" },
      hasStatusFilter: false,
      clientLabel: "Referência",
      clientField: "reference",
      statusField: null,
      dateField: "issue_date",
      headerHtml: `
        <tr>
          <th data-key="number">Documento ${SORT}</th>
          <th data-key="reference">Referência ${SORT}</th>
          <th data-key="issue_date">Emissão ${SORT}</th>
          <th>Método</th>
          <th data-key="amount_paid" class="bx-num">Valor pago ${SORT}</th>
          <th data-key="pending_amount" class="bx-num">Pendente ${SORT}</th>
          <th class="text-end">Ações</th>
        </tr>
      `,
      renderRow: renderReceiptRow,
      exportColumns: [
        ["Documento", (r) => (r.serie ? `${r.serie} ${r.number || ""}` : r.number || "-")],
        ["Referência", (r) => r.reference],
        ["Emissão", (r) => fmtDate(r.issue_date)],
        ["Método", (r) => r.payment_method],
        ["Valor pago", (r) => bxMoney(r.amount_paid), (r) => Number(r.amount_paid || 0).toFixed(2).replace(".", ",")],
        ["Pendente", (r) => bxMoney(r.pending_amount), (r) => Number(r.pending_amount || 0).toFixed(2).replace(".", ",")],
      ],
    },

    credit_notes: {
      colspan: 6,
      title: "Notas de crédito",
      emptyLabel: "Nenhuma nota de crédito encontrada",
      defaultSort: { key: "issue_date", dir: "desc" },
      hasStatusFilter: false,
      clientLabel: "Fatura (ref.)",
      clientField: "invoice_id",
      statusField: null,
      dateField: "issue_date",
      headerHtml: `
        <tr>
          <th data-key="id">Documento ${SORT}</th>
          <th data-key="invoice_id">Fatura ${SORT}</th>
          <th data-key="issue_date">Emissão ${SORT}</th>
          <th>Moeda</th>
          <th data-key="final_total" class="bx-num">Total ${SORT}</th>
          <th class="text-end">Motivo</th>
        </tr>
      `,
      renderRow: renderCreditNoteRow,
      exportColumns: [
        ["Documento", (r) => r.id],
        ["Fatura", (r) => r.invoice_id],
        ["Emissão", (r) => fmtDate(r.issue_date)],
        ["Moeda", (r) => r.currency],
        ["Total", (r) => bxMoney(r.final_total), (r) => Number(r.final_total || 0).toFixed(2).replace(".", ",")],
        ["Motivo", (r) => r.reason],
      ],
    },

    debit_notes: {
      colspan: 7,
      title: "Notas de débito",
      emptyLabel: "Nenhuma nota de débito encontrada",
      defaultSort: { key: "id", dir: "desc" },
      hasStatusFilter: false,
      clientLabel: "Cliente",
      clientField: "client_name",
      statusField: null,
      dateField: "issue_date",
      headerHtml: `
        <tr>
          <th data-key="id">Documento ${SORT}</th>
          <th data-key="client_name">Cliente ${SORT}</th>
          <th data-key="invoice_id">Fatura ${SORT}</th>
          <th data-key="issue_date">Emissão ${SORT}</th>
          <th>Moeda</th>
          <th data-key="final_total" class="bx-num">Total ${SORT}</th>
          <th class="text-end">Ações</th>
        </tr>
      `,
      renderRow: renderDebitNoteRow,
      exportColumns: [
        ["Documento", (r) => `${r.serie ?? ""} ${r.number ?? ""}`.trim()],
        ["Cliente", (r) => r.client_name],
        ["Fatura", (r) => r.invoice_id],
        ["Emissão", (r) => fmtDate(r.issue_date)],
        ["Moeda", (r) => r.currency],
        ["Total", (r) => bxMoney(r.final_total), (r) => Number(r.final_total || 0).toFixed(2).replace(".", ",")],
      ],
    },

    delivery_notes: {
      colspan: 6,
      title: "Notas de entrega",
      emptyLabel: "Nenhuma nota de entrega encontrada",
      defaultSort: { key: "id", dir: "desc" },
      hasStatusFilter: false,
      clientLabel: "Cliente",
      clientField: "client_name",
      statusField: null,
      dateField: "issue_date",
      headerHtml: `
        <tr>
          <th data-key="id">Documento ${SORT}</th>
          <th data-key="client_name">Cliente ${SORT}</th>
          <th data-key="invoice_id">Fatura ${SORT}</th>
          <th data-key="issue_date">Emissão ${SORT}</th>
          <th data-key="total_quantity" class="bx-num">Qtd. ${SORT}</th>
          <th class="text-end">Ações</th>
        </tr>
      `,
      renderRow: renderDeliveryNoteRow,
      exportColumns: [
        ["Documento", (r) => `${r.serie ?? ""} ${r.number ?? ""}`.trim()],
        ["Cliente", (r) => r.client_name],
        ["Fatura", (r) => r.invoice_id],
        ["Emissão", (r) => fmtDate(r.issue_date)],
        ["Qtd.", (r) => Number(r.total_quantity || 0)],
      ],
    },
  };

  let sortKey = DOC_TYPES[docType].defaultSort.key;
  let sortDir = DOC_TYPES[docType].defaultSort.dir;

  // cabeçalho inicial (Faturas)
  $("#invoicesTableHead").html(DOC_TYPES[docType].headerHtml);
  $("#filterDocType").val(docType);

  loadInvoices();
  loadReceipts();
  loadCreditNotes();
  loadDeliveryNotes();
  loadDebitNotes();

  function loadInvoices() {
    $.ajax({
      url: "invoices/ajax/fetch_invoices.php",
      type: "GET",
      dataType: "json",

      success: function (json) {
        if (Array.isArray(json)) {
          invoices = json;
        } else if (json?.data && Array.isArray(json.data)) {
          invoices = json.data;
        } else if (json?.invoices && Array.isArray(json.invoices)) {
          invoices = json.invoices;
        } else {
          console.error("Formato inválido:", json);
          invoices = [];
        }

        loaded.invoices = true;
        if (docType === "invoices") {
          currentPage = 1;
          renderTable();
        }
        updateInsights();
      },

      error: function () {
        console.error("Erro ao carregar faturas.");
        loaded.invoices = true;
        bxToast("Erro ao carregar faturas.", "error");
        if (docType === "invoices") renderTable();
      },
    });
  }

  // Listar recibos
  function loadReceipts() {
    $.ajax({
      url: "invoices/ajax/fetch_receipts.php",
      type: "GET",
      dataType: "json",

      success: function (json) {
        if (Array.isArray(json)) {
          receipts = json;
        } else if (json?.data && Array.isArray(json.data)) {
          receipts = json.data;
        } else if (json?.receipts && Array.isArray(json.receipts)) {
          receipts = json.receipts;
        } else {
          console.error("Formato inválido:", json);
          receipts = [];
        }

        loaded.receipts = true;
        if (docType === "receipts") {
          currentPage = 1;
          renderTable();
        }
        updateInsights();
      },

      error: function () {
        console.error("Erro ao carregar recibos.");
        loaded.receipts = true;
        bxToast("Erro ao carregar recibos.", "error");
        if (docType === "receipts") renderTable();
      },
    });
  }

  // Listar notas de crédito
  function loadCreditNotes() {
    $.ajax({
      url: "invoices/ajax/fetch_credit_notes.php",
      type: "GET",
      dataType: "json",

      success: function (json) {
        if (Array.isArray(json)) {
          credit_notes = json;
        } else if (json?.data && Array.isArray(json.data)) {
          credit_notes = json.data;
        } else {
          console.error("Formato inválido:", json);
          credit_notes = [];
        }

        loaded.credit_notes = true;
        if (docType === "credit_notes") {
          currentPage = 1;
          renderTable();
        }
        updateInsights();
      },

      error: function () {
        console.error("Erro ao carregar notas de crédito.");
        loaded.credit_notes = true;
        bxToast("Erro ao carregar notas de crédito.", "error");
        if (docType === "credit_notes") renderTable();
      },
    });
  }

  // Listar notas de entrega
  function loadDeliveryNotes() {
    $.ajax({
      url: "invoices/ajax/fetch_delivery_notes.php",
      type: "GET",
      dataType: "json",

      success: function (json) {
        delivery_notes = Array.isArray(json?.data) ? json.data : [];

        loaded.delivery_notes = true;
        if (docType === "delivery_notes") {
          currentPage = 1;
          renderTable();
        }
      },

      error: function () {
        console.error("Erro ao carregar notas de entrega.");
        loaded.delivery_notes = true;
        bxToast("Erro ao carregar notas de entrega.", "error");
        if (docType === "delivery_notes") renderTable();
      },
    });
  }

  // Listar notas de débito
  function loadDebitNotes() {
    $.ajax({
      url: "invoices/ajax/fetch_debit_notes.php",
      type: "GET",
      dataType: "json",

      success: function (json) {
        debit_notes = Array.isArray(json?.data) ? json.data : [];

        loaded.debit_notes = true;
        if (docType === "debit_notes") {
          currentPage = 1;
          renderTable();
        }
        updateInsights();
      },

      error: function () {
        console.error("Erro ao carregar notas de débito.");
        loaded.debit_notes = true;
        bxToast("Erro ao carregar notas de débito.", "error");
        if (docType === "debit_notes") renderTable();
      },
    });
  }

  function currentDataset() {
    if (docType === "receipts") return receipts;
    if (docType === "credit_notes") return credit_notes;
    if (docType === "delivery_notes") return delivery_notes;
    if (docType === "debit_notes") return debit_notes;
    return invoices;
  }

  const collectionApiBase = ["api-sandibox.bxpert.co.ao", "www.api-sandibox.bxpert.co.ao"].includes(window.location.hostname)
    ? "https://api-sandibox.bxpert.co.ao"
    : "http://localhost:3000";

  function getSelectedInvoiceIds() {
    return [...selectedIds].map(Number).filter(Boolean);
  }

  function prepareCollectionModal() {
    const ids = getSelectedInvoiceIds();
    const list = document.getElementById("collectionSelectedList");
    const hidden = document.getElementById("collectionInvoiceIds");
    const selected = invoices.filter((invoice) => ids.includes(Number(invoice.id)));
    hidden.value = JSON.stringify(ids);
    list.innerHTML = selected.length
      ? selected.map((invoice) => `<div><strong>#${invoice.codigo || invoice.id}</strong> · ${invoice.cliente || "Sem cliente"} · ${formatCurrency(Number(invoice.final_total || 0), invoice.symbol || "", invoice.position || "left")}</div>`).join("")
      : "Nenhuma fatura selecionada.";
    document.getElementById("collectionError").classList.add("d-none");
  }

  async function submitCollection(event) {
    event.preventDefault();
    const ids = JSON.parse(document.getElementById("collectionInvoiceIds").value || "[]");
    const channels = [...document.querySelectorAll('input[name="collectionChannels"]:checked')].map((input) => input.value);
    const errorEl = document.getElementById("collectionError");
    const button = document.getElementById("submitCollectionBtn");
    const feature = document.getElementById("invoiceCollectionFeature");
    const companyId = feature?.dataset.companyId || "";

    if (!ids.length || !channels.length) {
      errorEl.textContent = "Selecione pelo menos uma fatura e um canal de contacto.";
      errorEl.classList.remove("d-none");
      return;
    }

    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A processar...';
    try {
      const response = await fetch(`${collectionApiBase}/api/collections`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          company_id: Number(companyId),
          invoice_ids: ids,
          channels,
          subject: document.getElementById("collectionSubject").value.trim(),
          message: document.getElementById("collectionMessage").value.trim(),
          scheduled_at: new Date(document.getElementById("collectionScheduleAt").value).toISOString(),
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Não foi possível criar a cobrança.");
      const status = result.schedule?.status || result.status || "scheduled";
      alert(status === "scheduled" ? "Cobrança agendada com sucesso." : "Cobrança processada. Consulte o resultado nos alertas.");
      bootstrap.Modal.getInstance(document.getElementById("collectionModal"))?.hide();
    } catch (error) {
      errorEl.textContent = error.message;
      errorEl.classList.remove("d-none");
    } finally {
      button.disabled = false;
      button.innerHTML = '<i class="bi bi-send-check me-1"></i> Confirmar cobrança';
    }
  }

  function setCollectionNow() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById("collectionScheduleAt").value = now.toISOString().slice(0, 16);
  }

  document.getElementById("openCollectionModalBtn")?.addEventListener("click", () => {
    if (docType !== "invoices") {
      $("#filterDocType").val("invoices").trigger("change");
    }
    prepareCollectionModal();
    setCollectionNow();
    bootstrap.Modal.getOrCreateInstance(document.getElementById("collectionModal")).show();
  });
  document.getElementById("collectionNowBtn")?.addEventListener("click", setCollectionNow);
  document.getElementById("collectionForm")?.addEventListener("submit", submitCollection);

  function updateInsights() {
    const status = {
      pending: 0,
      cancelled: 0,
      paid: 0,
      partial: 0,
      draft: 0,
      overdue: 0,
    };
    let expected = 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    invoices.forEach((invoice) => {
      const value = Number(invoice.final_total) || 0;
      const normalized = String(invoice.status_invoice || "").toLowerCase().trim();
      const due = invoice.due_date ? new Date(invoice.due_date) : null;
      if (due) due.setHours(0, 0, 0, 0);

      if (normalized.includes("cancel")) status.cancelled++;
      else if (normalized.includes("rascun")) status.draft++;
      else if (normalized.includes("parcial")) status.partial++;
      else if (normalized.includes("pago") || normalized.includes("quit")) {
        status.paid++;
        expected += value;
      } else status.pending++;

      if (due && due < today && !normalized.includes("pago") &&
        !normalized.includes("quit") && !normalized.includes("cancel") &&
        !normalized.includes("rascun")) {
        status.overdue++;
      }
    });

    $("#insightInvoices").text(invoices.length);
    $("#insightCreditNotes").text(credit_notes.length);
    $("#insightDebitNotes").text(debit_notes.length);
    $("#insightReceipts").text(receipts.length);
    $("#insightPending").text(status.pending);
    $("#insightCancelled").text(status.cancelled);
    $("#insightPaid").text(status.paid);
    $("#insightPartial").text(status.partial);
    $("#insightDraft").text(status.draft);
    $("#insightOverdue").text(status.overdue);
    $("#insightInvoicesHint").text(
      invoices.length
        ? `${status.pending + status.partial} em aberto · ${status.overdue} vencidas`
        : "Documentos emitidos e rascunhos",
    );
  }

  const intelligenceApiBase = ["api-sandibox.bxpert.co.ao", "www.api-sandibox.bxpert.co.ao"].includes(window.location.hostname)
    ? "https://api-sandibox.bxpert.co.ao"
    : "http://localhost:3000";

  async function loadCollectionSummary() {
    const summary = document.getElementById("invoiceCollectionSummary");
    if (!summary) return;
    try {
      const companyId = document.getElementById("invoiceCollectionFeature")?.dataset.companyId || "";
      const response = await fetch(`${intelligenceApiBase}/api/alert-rules?company_id=${encodeURIComponent(companyId)}`);
      const rules = await response.json();
      const activeRules = Array.isArray(rules) ? rules.filter((rule) => rule.active !== false) : [];
      const channels = [...new Set(activeRules.flatMap((rule) => Array.isArray(rule.channels) ? rule.channels : []))];
      summary.textContent = activeRules.length
        ? `${activeRules.length} regras ativas · canais: ${channels.join(" + ") || "padrão"}`
        : "Escalonamento padrão ativo para faturas pendentes";
    } catch (error) {
      summary.textContent = "Motor de alertas disponível para faturas pendentes";
    }
  }

  async function runCollection() {
    const button = document.getElementById("invoiceCollectionBtn");
    if (!button) return;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A analisar...';
    try {
      const response = await fetch(`${intelligenceApiBase}/api/robot/check-invoices`, { method: "POST" });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || "Falha ao executar cobrança");
      button.innerHTML = '<i class="bi bi-check2 me-1"></i> Cobrança executada';
      loadCollectionSummary();
    } catch (error) {
      console.error("Erro ao executar cobrança:", error);
      button.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Tentar novamente';
    } finally {
      button.disabled = false;
    }
  }

  document.getElementById("invoiceCollectionBtn")?.addEventListener("click", runCollection);
  loadCollectionSummary();

  if (new URLSearchParams(window.location.search).get("collection") === "1") {
    setTimeout(() => document.getElementById("openCollectionModalBtn")?.click(), 400);
  }

  // ==================================================
  // TROCA DE TIPO DE DOCUMENTO
  // ==================================================
  $("#filterDocType").on("change", function () {
    docType = $(this).val();
    const config = DOC_TYPES[docType];

    sortKey = config.defaultSort.key;
    sortDir = config.defaultSort.dir;
    currentPage = 1;
    selectedIds.clear(); // a selecção só existe para faturas

    // cabeçalho da tabela
    $("#invoicesTableHead").html(config.headerHtml);

    // label do campo "cliente/referência"
    $("#filterClientLabel").text(config.clientLabel);
    $("#filterClient")
      .val("")
      .attr("placeholder", `Pesquisar ${config.clientLabel.toLowerCase()}...`);

    // filtro de status só faz sentido para Faturas
    $("#filterStatusWrapper").toggle(config.hasStatusFilter);
    if (!config.hasStatusFilter) $("#filterStatus").val("");

    renderTable();
  });

  // ==================================================
  // FILTROS + ORDENAÇÃO
  // ==================================================
  function hasActiveFilters() {
    return !!(
      $("#filterClient").val().trim() ||
      $("#filterStatus").val() ||
      $("#filterStartDate").val() ||
      $("#filterEndDate").val()
    );
  }

  function applyFilterAndSort() {
    const config = DOC_TYPES[docType];
    const clienteFiltro = ($("#filterClient").val() || "").toLowerCase().trim();
    const statusFiltro = ($("#filterStatus").val() || "").toLowerCase().trim();
    const start = parseDate($("#filterStartDate").val());
    const end = parseDate($("#filterEndDate").val());
    if (end) end.setHours(23, 59, 59, 999);
    const dataset = currentDataset();

    filteredInvoices = dataset.filter((row) => {
      const clienteValor = String(row[config.clientField] ?? "").toLowerCase();

      if (clienteFiltro && !clienteValor.includes(clienteFiltro)) return false;

      if (config.hasStatusFilter && config.statusField && statusFiltro) {
        if (invoiceStatus(row).key !== statusFiltro) return false;
      }

      const current = parseDate(row[config.dateField]);
      if (current) {
        if (start && current < start) return false;
        if (end && current > end) return false;
      }

      return true;
    });

    if (sortKey) {
      filteredInvoices.sort((a, b) => {
        let va = a[sortKey] ?? "";
        let vb = b[sortKey] ?? "";

        // datas
        if (sortKey === "issue_date" || sortKey === "due_date") {
          va = va ? new Date(va).getTime() : 0;
          vb = vb ? new Date(vb).getTime() : 0;
        } else if (
          [
            "final_total",
            "amount_paid",
            "pending_amount",
            "id",
            "invoice_id",
            "total_quantity",
          ].includes(sortKey)
        ) {
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
  // RENDER DE LINHA POR TIPO DE DOCUMENTO
  // (cada <td> leva data-label: no mobile a tabela vira cartões só com CSS)
  // ==================================================
  function renderInvoiceRow(row) {
    const st = invoiceStatus(row);
    const isDraft = st.base === "rascunho";
    const checked = selectedIds.has(String(row.id));
    const code = row.codigo || "-";
    const overdueDays = st.overdue ? daysOverdue(row) : 0;

    const dueHtml = row.due_date
      ? `<span class="${st.overdue ? "bx-due--overdue" : ""}">${fmtDate(row.due_date)}${
          st.overdue
            ? `<span class="bx-due__sub">há ${overdueDays} ${overdueDays === 1 ? "dia" : "dias"}</span>`
            : ""
        }</span>`
      : "-";

    const actions = `
      <div class="bx-actions">
        ${viewLink(invoiceUrl(row), "Visualizar documento", `Visualizar factura ${code}`)}
        ${isDraft ? "" : pdfBtn(`data-id="${esc(row.id)}"`, `Baixar PDF da factura ${code}`, "js-pdf")}
        <button type="button" class="bx-icon-btn js-more" data-id="${esc(row.id)}"
          aria-haspopup="menu" aria-expanded="false" data-bs-toggle="tooltip" data-bs-title="Mais ações"
          aria-label="Mais ações da factura ${esc(code)}">
          <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
        </button>
      </div>`;

    return `
      <tr class="invoice-row${checked ? " is-selected" : ""}" data-id="${esc(row.id)}">
        <td class="bx-td-check">
          <input type="checkbox" class="invoice-check form-check-input" data-id="${esc(row.id)}"
            value="${esc(row.id)}" aria-label="Selecionar factura ${esc(code)}" ${checked ? "checked" : ""}>
        </td>
        <td class="bx-td-doc">${docCell("invoices", "Fatura", code)}</td>
        <td class="bx-td-client">${esc(row.cliente || "-")}</td>
        <td data-label="Status"><span class="bx-badge bx-badge--${st.key}">${esc(st.label)}</span></td>
        <td data-label="Emissão">${fmtDate(row.issue_date)}</td>
        <td data-label="Vencimento">${dueHtml}</td>
        <td class="bx-num bx-money bx-td-total" data-label="Total">${bxMoney(row.final_total, row.symbol || "", row.position || "right")}</td>
        <td class="bx-td-actions">${actions}</td>
      </tr>
    `;
  }

  function renderReceiptRow(row) {
    const number = row.serie ? `${row.serie} ${row.number || ""}` : row.number || "-";
    return `
      <tr class="invoice-row" data-id="${esc(row.id)}">
        <td class="bx-td-doc">${docCell("receipts", "Recibo", number)}</td>
        <td class="bx-td-client" data-label="Referência">${esc(row.reference || "-")}</td>
        <td data-label="Emissão">${fmtDate(row.issue_date)}</td>
        <td data-label="Método">${esc(row.payment_method || "-")}</td>
        <td class="bx-num bx-money" data-label="Valor pago">${bxMoney(row.amount_paid)}</td>
        <td class="bx-num" data-label="Pendente">${bxMoney(row.pending_amount)}</td>
        <td class="bx-td-actions">
          <div class="bx-actions">
            ${viewLink(
              `invoice.php?id=${String(row.issue_date || "").replaceAll("-", "")}/${row.company_id || ""}/${row.invoice_id}`,
              "Ver fatura",
              "Ver fatura do recibo",
            )}
          </div>
        </td>
      </tr>
    `;
  }

  function renderCreditNoteRow(row) {
    return `
      <tr class="invoice-row" data-id="${esc(row.id)}">
        <td class="bx-td-doc">${docCell("credit_notes", "Nota de crédito", row.id)}</td>
        <td class="bx-td-client" data-label="Fatura">${esc(row.invoice_id || "-")}</td>
        <td data-label="Emissão">${fmtDate(row.issue_date)}</td>
        <td data-label="Moeda">${esc(row.currency || "-")}</td>
        <td class="bx-num bx-money bx-td-total" data-label="Total">${bxMoney(row.final_total)}</td>
        <td class="text-end" data-label="Motivo">${esc(row.reason || "-")}</td>
      </tr>
    `;
  }

  function renderDebitNoteRow(row) {
    const number = `${row.serie ?? ""} ${row.number ?? ""}`.trim();
    return `
      <tr class="invoice-row" data-id="${esc(row.id)}">
        <td class="bx-td-doc">${docCell("debit_notes", "Nota de débito", number)}</td>
        <td class="bx-td-client">${esc(row.client_name) || "-"}</td>
        <td data-label="Fatura">${esc(row.invoice_id || "-")}</td>
        <td data-label="Emissão">${fmtDate(row.issue_date)}</td>
        <td data-label="Moeda">${esc(row.currency) || "-"}</td>
        <td class="bx-num bx-money bx-td-total" data-label="Total">${bxMoney(row.final_total)}</td>
        <td class="bx-td-actions">
          <div class="bx-actions">
            ${pdfBtn(
              `data-url="invoices/debit_note_pdf.php?id=${esc(row.id)}" data-filename="NotaDebito_${esc(number.replace(/\s+/g, "_"))}.pdf"`,
              `Baixar PDF da nota de débito ${number}`,
              "js-pdf-url",
            )}
          </div>
        </td>
      </tr>
    `;
  }

  function renderDeliveryNoteRow(row) {
    const number = `${row.serie ?? ""} ${row.number ?? ""}`.trim();
    return `
      <tr class="invoice-row" data-id="${esc(row.id)}">
        <td class="bx-td-doc">${docCell("delivery_notes", "Nota de entrega", number)}</td>
        <td class="bx-td-client">${esc(row.client_name) || "-"}</td>
        <td data-label="Fatura">${esc(row.invoice_id || "-")}</td>
        <td data-label="Emissão">${fmtDate(row.issue_date)}</td>
        <td class="bx-num" data-label="Qtd.">${Number(row.total_quantity || 0)}</td>
        <td class="bx-td-actions">
          <div class="bx-actions">
            ${pdfBtn(
              `data-url="invoices/delivery_note_pdf.php?id=${esc(row.id)}" data-filename="NotaEntrega_${esc(number.replace(/\s+/g, "_"))}.pdf"`,
              `Baixar PDF da nota de entrega ${number}`,
              "js-pdf-url",
            )}
          </div>
        </td>
      </tr>
    `;
  }

  // ==================================================
  // RENDER TABELA
  // ==================================================
  function disposeTooltips() {
    document
      .querySelectorAll('#invoicesTable [data-bs-toggle="tooltip"]')
      .forEach((el) => window.bootstrap?.Tooltip?.getInstance(el)?.dispose());
  }

  function renderTable() {
    const config = DOC_TYPES[docType];
    const $tbody = $("#invoicesTable tbody");
    disposeTooltips();
    $tbody.empty();

    // enquanto o dataset ainda não respondeu: skeleton
    if (!loaded[docType]) {
      const cell = '<td><span class="bx-skel"></span></td>';
      $tbody.html(
        Array.from({ length: 5 }, () => `<tr class="bx-skel-row">${cell.repeat(config.colspan)}</tr>`).join(""),
      );
      $("#tableInfo").text("A carregar...");
      $("#bxResultCount").text("");
      renderPagination(0);
      updateSortIcons();
      refreshSelectionUI();
      return;
    }

    applyFilterAndSort();

    if (!filteredInvoices.length) {
      $tbody.append(`
        <tr>
          <td colspan="${config.colspan}" class="text-center text-muted py-4">
            ${config.emptyLabel}
          </td>
        </tr>
      `);
      $("#tableInfo").text("Sem dados");
      $("#bxResultCount").text("0 documentos");
      renderPagination(0);
      updateSortIcons();
      refreshSelectionUI();
      return;
    }

    const totalItems = filteredInvoices.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * pageSize;
    const end = Math.min(start + pageSize, totalItems);
    const pageData = filteredInvoices.slice(start, end);

    $tbody.html(pageData.map((row) => config.renderRow(row)).join(""));

    $("#tableInfo").text(`${start + 1}–${end} de ${totalItems}`);
    $("#bxResultCount").text(`${totalItems} ${totalItems === 1 ? "documento" : "documentos"}`);
    renderPagination(totalPages);
    updateSortIcons();

    document.querySelectorAll('#invoicesTable [data-bs-toggle="tooltip"]').forEach((el) => {
      bootstrap.Tooltip.getOrCreateInstance(el, { trigger: "hover focus" });
    });

    refreshSelectionUI();
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
  // FILTROS (eventos): agora com botão "Filtrar" (ação principal)
  // ==================================================
  function applyFilters() {
    currentPage = 1;
    renderTable();
  }
  $("#btnApplyFilters").on("click", applyFilters);
  $("#filterClient").on("keydown", function (e) {
    if (e.key === "Enter") applyFilters();
  });

  $("#btnClearFilters").on("click", function () {
    $("#filterClient").val("");
    $("#filterStatus").val("");
    $("#filterStartDate").val("");
    $("#filterEndDate").val("");
    applyFilters();
  });

  // quando o dataset recarrega (ex.: após eliminar um rascunho)
  $(document).on("reload-invoices", function () {
    selectedIds.clear();
    loadInvoices();
  });

  // ==================================================
  // SELECÇÃO MÚLTIPLA (persiste entre páginas)
  // ==================================================
  function selectedRows() {
    return [...selectedIds]
      .map((id) => invoices.find((r) => String(r.id) === id))
      .filter(Boolean);
  }

  function refreshSelectionUI() {
    const n = selectedIds.size;
    const show = n > 0 && docType === "invoices";
    $("#bxSelectionBar").toggleClass("is-visible", show);
    $("#bxSelectionCount").text(`${n} ${n === 1 ? "documento selecionado" : "documentos selecionados"}`);
    $("#bxExportSelectedLabel").text(`Selecionados (${n})`);
    $("#bxExportSelectedGroup").prop("hidden", !show);
    if (!show) closeMenu(bulkMoreMenu, bulkMoreBtn);

    // checkbox do cabeçalho: marcado / indeterminado conforme a página actual
    const $checks = $("#invoicesTable tbody .invoice-check");
    const checked = $checks.filter(":checked").length;
    $("#selectAll")
      .prop("checked", $checks.length > 0 && checked === $checks.length)
      .prop("indeterminate", checked > 0 && checked < $checks.length);
  }

  function toggleRowSelection(input, on) {
    const id = String(input.dataset.id);
    if (on) selectedIds.add(id);
    else selectedIds.delete(id);
    $(input).closest("tr").toggleClass("is-selected", on);
  }

  $(document).on("change", "#selectAll", function () {
    const on = this.checked;
    $("#invoicesTable tbody .invoice-check").each(function () {
      this.checked = on;
      toggleRowSelection(this, on);
    });
    refreshSelectionUI();
  });

  $("#invoicesTable tbody").on("change", ".invoice-check", function () {
    toggleRowSelection(this, this.checked);
    refreshSelectionUI();
  });

  $("#bxClearSelection").on("click", function () {
    selectedIds.clear();
    $("#invoicesTable tbody .invoice-check").prop("checked", false);
    $("#invoicesTable tbody tr").removeClass("is-selected");
    refreshSelectionUI();
  });

  // ==================================================
  // CLIQUE NA LINHA (navegar para a fatura)
  // ==================================================
  $("#invoicesTable tbody").on("click", "tr.invoice-row", function (e) {
    if (docType !== "invoices") return; // recibos/notas usam apenas os botões de ação
    if ($(e.target).closest("a, button, input, label").length) return;

    const id = $(this).data("id");
    const row = filteredInvoices.find((r) => String(r.id) === String(id));
    if (row) window.location.href = invoiceUrl(row);
  });

  // ==================================================
  // BAIXAR PDF (direto, sem abrir menu nem nova aba)
  // O PDF da fatura é gerado no browser (jsPDF) a partir de get_invoice.php;
  // doc.save() já descarrega diretamente. Aqui só se acrescenta o feedback.
  // ==================================================
  function setBusy(btn, busy) {
    if (!btn) return;
    if (busy) {
      btn.dataset.html = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML =
        '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';
      window.bootstrap?.Tooltip?.getInstance(btn)?.hide();
    } else {
      btn.disabled = false;
      if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
    }
  }

  async function runInvoicePdf(id, btn) {
    setBusy(btn, true);
    try {
      await downloadPDF(id); // já mostra "Gerando PDF..." e o resultado
    } catch (_) {
      /* o erro já foi mostrado pelo downloadPDF */
    } finally {
      setBusy(btn, false);
    }
  }

  $("#invoicesTable tbody").on("click", ".js-pdf", function (e) {
    e.stopPropagation();
    if (!this.disabled) runInvoicePdf(this.dataset.id, this);
  });

  // notas de débito / entrega: o PDF vem do servidor (PHP); descarrega por fetch/blob
  $("#invoicesTable tbody").on("click", ".js-pdf-url", async function (e) {
    e.stopPropagation();
    const btn = this;
    if (btn.disabled) return;
    setBusy(btn, true);
    await downloadFromUrl(btn.dataset.url, {
      fallbackName: btn.dataset.filename || "documento.pdf",
      pendingMsg: "Gerando PDF...",
      okMsg: "PDF baixado com sucesso",
      expectPdf: true,
    });
    setBusy(btn, false);
  });

  // ==================================================
  // MENUS (helpers partilhados: Exportar, ⋮ da linha, "Mais" da barra)
  // ==================================================
  const exportBtn = document.getElementById("exportMenuBtn");
  const exportMenu = document.getElementById("exportMenuList");
  const bulkMoreBtn = document.getElementById("bxBulkMoreBtn");
  const bulkMoreMenu = document.getElementById("bxBulkMoreMenu");
  const rowMenu = document.getElementById("bxRowMenu");
  let rowMenuBtn = null;

  const menuItems = (menu) =>
    [...menu.querySelectorAll('[role="menuitem"]')].filter(
      (el) => !el.disabled && el.getAttribute("aria-disabled") !== "true" && !el.closest("[hidden]"),
    );

  function openMenu(menu, btn, focusFirst = false) {
    menu.classList.add("is-open");
    btn?.setAttribute("aria-expanded", "true");
    if (focusFirst) menuItems(menu)[0]?.focus();
  }
  function closeMenu(menu, btn, focusBack = false) {
    if (!menu || !menu.classList.contains("is-open")) return;
    menu.classList.remove("is-open");
    btn?.setAttribute("aria-expanded", "false");
    if (focusBack) btn?.focus();
  }
  function closeAllMenus() {
    closeMenu(exportMenu, exportBtn);
    closeMenu(bulkMoreMenu, bulkMoreBtn);
    closeRowMenu();
  }
  function bindMenuKeys(menu, btn) {
    menu.addEventListener("keydown", (e) => {
      const items = menuItems(menu);
      const i = items.indexOf(document.activeElement);
      if (e.key === "ArrowDown") {
        e.preventDefault();
        items[(i + 1) % items.length]?.focus();
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        items[(i - 1 + items.length) % items.length]?.focus();
      } else if (e.key === "Home") {
        e.preventDefault();
        items[0]?.focus();
      } else if (e.key === "End") {
        e.preventDefault();
        items[items.length - 1]?.focus();
      } else if (e.key === "Escape") {
        e.preventDefault();
        menu === rowMenu ? closeRowMenu(true) : closeMenu(menu, btn, true);
      } else if (e.key === "Tab") {
        menu === rowMenu ? closeRowMenu() : closeMenu(menu, btn);
      }
    });
  }
  bindMenuKeys(exportMenu, exportBtn);
  bindMenuKeys(bulkMoreMenu, bulkMoreBtn);
  bindMenuKeys(rowMenu, null);

  // ---------- Menu "Exportar" ----------
  function updateExportMenu() {
    const cfg = DOC_TYPES[docType];
    $("#bxExportTypeLabel").text(`Exportar ${cfg.title.toLowerCase()}`);
    // Excel/CSV vêm do servidor: só existem onde há endpoint configurado
    const hasServer = !!EXPORT_CONFIG[docType];
    ['[data-export-action="list-excel"]', '[data-export-action="list-csv"]'].forEach((sel) => {
      const el = exportMenu.querySelector(sel);
      el.disabled = !hasServer;
      el.title = hasServer ? "" : "Indisponível para este tipo de documento";
    });
  }

  exportBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    const open = exportMenu.classList.contains("is-open");
    closeAllMenus();
    if (!open) {
      updateExportMenu();
      openMenu(exportMenu, exportBtn);
    }
  });
  exportBtn.addEventListener("keydown", (e) => {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      closeAllMenus();
      updateExportMenu();
      openMenu(exportMenu, exportBtn, true);
    }
  });

  bulkMoreBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    const open = bulkMoreMenu.classList.contains("is-open");
    closeAllMenus();
    if (!open) openMenu(bulkMoreMenu, bulkMoreBtn);
  });
  bulkMoreBtn.addEventListener("keydown", (e) => {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      closeAllMenus();
      openMenu(bulkMoreMenu, bulkMoreBtn, true);
    }
  });

  // ---------- Menu "⋮" da linha ----------
  function closeRowMenu(focusBack = false) {
    if (!rowMenu.classList.contains("is-open")) return;
    rowMenu.classList.remove("is-open");
    if (rowMenuBtn) {
      rowMenuBtn.setAttribute("aria-expanded", "false");
      if (focusBack) rowMenuBtn.focus();
    }
    rowMenuBtn = null;
  }

  function rowMenuHtml(row) {
    const st = invoiceStatus(row);
    const isDraft = st.base === "rascunho";
    const url = invoiceUrl(row);
    // Imprimir / Enviar por email / Duplicar: NÃO existem na lista (hoje só na página da
    // fatura, invoice.js). Ficam estruturalmente previstos mas desativados — não simulam nada.
    return `
      <a class="bx-menu__item" role="menuitem" href="${url}"><i class="bi bi-eye" aria-hidden="true"></i> Visualizar</a>
      ${isDraft ? "" : `<button type="button" class="bx-menu__item" role="menuitem" data-row-action="pdf" data-id="${esc(row.id)}"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i> Baixar PDF</button>`}
      <button type="button" class="bx-menu__item" role="menuitem" disabled><i class="bi bi-printer" aria-hidden="true"></i> Imprimir <span class="bx-menu__hint">na fatura</span></button>
      <button type="button" class="bx-menu__item" role="menuitem" disabled><i class="bi bi-envelope" aria-hidden="true"></i> Enviar por email <span class="bx-menu__hint">na fatura</span></button>
      <div class="bx-menu__divider" role="separator"></div>
      <button type="button" class="bx-menu__item" role="menuitem" disabled><i class="bi bi-copy" aria-hidden="true"></i> Duplicar <span class="bx-menu__hint">em breve</span></button>
      ${
        isDraft
          ? `<a class="bx-menu__item" role="menuitem" href="create_invoices.php?edit_id=${esc(row.id)}"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
             <button type="button" class="bx-menu__item bx-menu__item--danger" role="menuitem" data-row-action="delete" data-id="${esc(row.id)}" data-company="${esc(row.company_id)}"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>`
          : ""
      }`;
  }

  function openRowMenu(btn, row) {
    closeAllMenus();
    rowMenu.innerHTML = rowMenuHtml(row);
    rowMenu.classList.add("is-open");
    rowMenuBtn = btn;
    btn.setAttribute("aria-expanded", "true");

    // posiciona junto ao botão; vira para cima se não couber em baixo
    const r = btn.getBoundingClientRect();
    const mw = rowMenu.offsetWidth;
    const mh = rowMenu.offsetHeight;
    let top = r.bottom + 6;
    if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 6);
    const left = Math.min(Math.max(8, r.right - mw), window.innerWidth - mw - 8);
    rowMenu.style.top = `${top}px`;
    rowMenu.style.left = `${left}px`;
  }

  $("#invoicesTable tbody").on("click", ".js-more", function (e) {
    e.stopPropagation();
    if (rowMenuBtn === this) return closeRowMenu();
    const row = invoices.find((r) => String(r.id) === String(this.dataset.id));
    if (row) openRowMenu(this, row);
  });
  $("#invoicesTable tbody").on("keydown", ".js-more", function (e) {
    if (e.key !== "ArrowDown") return;
    e.preventDefault();
    const row = invoices.find((r) => String(r.id) === String(this.dataset.id));
    if (!row) return;
    openRowMenu(this, row);
    menuItems(rowMenu)[0]?.focus();
  });

  rowMenu.addEventListener("click", (e) => {
    const item = e.target.closest("[data-row-action]");
    if (!item) return closeRowMenu(); // link "Visualizar"/"Editar": navega normalmente
    const { rowAction, id, company } = item.dataset;
    closeRowMenu();
    if (rowAction === "pdf") {
      runInvoicePdf(id, document.querySelector(`.js-pdf[data-id="${id}"]`));
    } else if (rowAction === "delete") {
      deleteInvoice(Number(id), Number(company)); // fluxo original (modal de confirmação)
    }
  });

  // fechar: clique fora, Esc, scroll, resize
  document.addEventListener("click", (e) => {
    if (!e.target.closest("#exportMenu")) closeMenu(exportMenu, exportBtn);
    if (!e.target.closest(".bx-selbar__more")) closeMenu(bulkMoreMenu, bulkMoreBtn);
    if (!e.target.closest("#bxRowMenu") && !e.target.closest(".js-more")) closeRowMenu();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeAllMenus();
  });
  window.addEventListener("resize", closeRowMenu);
  window.addEventListener("scroll", () => closeRowMenu(), true);

  // ==================================================
  // EXPORTAÇÃO
  // ==================================================
  //
  // Mapa: cada tipo de documento -> endpoint PHP que gera o ficheiro.
  // A barra de progresso falsa (sessão + setTimeout de 2 s) foi substituída por
  // fetch/blob com feedback real. O endpoint ?status=1 continua a existir, mas já não é usado.
  const EXPORT_CONFIG = {
    invoices: {
      url: "invoices/ajax/faturas_export.php",
      buildParams: (format) => ({ formato: format || "excel" }),
    },
    invoices_paid: {
      url: "invoices/ajax/faturas_export.php",
      // "situacao" (não "status") para não colidir com o "status=1" de progresso
      buildParams: (format) => ({ formato: format || "excel", situacao: "pago" }),
    },
    invoices_pending: {
      url: "invoices/ajax/faturas_export.php",
      buildParams: (format) => ({ formato: format || "excel", situacao: "pendente" }),
    },
    credit_notes: {
      url: "index/ajax/export_credit_notes.php",
      buildParams: (format) => ({ formato: format || "excel" }),
    },
    receipts: {
      url: "index/ajax/export_receipts.php",
      buildParams: (format) => ({ formato: format || "excel" }),
    },
    debit_notes: {
      url: "index/ajax/export_debit_notes.php",
      buildParams: (format) => ({ formato: format || "excel" }),
    },
    sales_report: {
      url: "index/ajax/export_sales_report.php",
      buildParams: () => ({}),
    },
  };

  /**
   * @param {string} type   chave em EXPORT_CONFIG
   * @param {string} [format] "excel" | "csv"
   * @param {{ids?: (string|number)[]}} [extra] ids => exporta só esses documentos
   *        (POST; requer o faturas_export.php atualizado)
   */
  function exportFile(type, format, extra = {}) {
    const config = EXPORT_CONFIG[type];
    if (!config) {
      bxToast("Este tipo de exportação ainda não está configurado.", "error");
      return Promise.resolve();
    }
    const params = config.buildParams(format);
    const ext = format === "csv" ? "csv" : "xlsx";
    const common = {
      fallbackName: `${type}.${ext}`,
      pendingMsg: "A preparar exportação...",
      okMsg: "Exportação concluída com sucesso",
    };

    if (extra.ids && extra.ids.length) {
      const body = new URLSearchParams({ ...params, ids: extra.ids.join(",") });
      return downloadFromUrl(config.url, { ...common, method: "POST", body });
    }
    const query = new URLSearchParams(params).toString();
    return downloadFromUrl(query ? `${config.url}?${query}` : config.url, common);
  }
  window.exportFile = exportFile; // mantém o nome global usado antes

  // PDF/CSV gerados no browser a partir das linhas já carregadas (respeitam filtros/seleção)
  function exportListPdf(rows, title, filename) {
    if (!rows.length) return bxToast("Não há documentos para exportar.", "info");
    if (!window.jspdf) return bxToast("Biblioteca de PDF não carregada.", "error");
    const toast = bxToast("Gerando PDF...", "loading");
    setTimeout(() => {
      try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: "landscape" });
        const cols = DOC_TYPES[docType].exportColumns;

        doc.setFont("helvetica", "bold");
        doc.setFontSize(14);
        doc.text(title, 14, 16);
        doc.setFont("helvetica", "normal");
        doc.setFontSize(9);
        doc.text(`Gerado em ${fmtDate(new Date())} · ${rows.length} documento(s)`, 14, 22);

        doc.autoTable({
          startY: 27,
          head: [cols.map((c) => c[0])],
          body: rows.map((r) => cols.map((c) => String(c[1](r) ?? ""))),
          styles: { fontSize: 8, cellPadding: 2 },
          headStyles: { fillColor: [22, 163, 74] },
          margin: { left: 14, right: 14 },
        });

        const pages = doc.getNumberOfPages();
        const w = doc.internal.pageSize.getWidth();
        const h = doc.internal.pageSize.getHeight();
        for (let i = 1; i <= pages; i++) {
          doc.setPage(i);
          doc.setFontSize(8);
          doc.text(`${i}/${pages}`, w - 14, h - 8, { align: "right" });
        }
        doc.save(filename);
        toast.close();
        bxToast("PDF baixado com sucesso", "success");
      } catch (err) {
        console.error(err);
        toast.close();
        bxToast("Erro ao gerar o PDF.", "error");
      }
    }, 30);
  }

  function exportSelectedCsv() {
    const rows = selectedRows();
    if (!rows.length) return;
    const cols = DOC_TYPES.invoices.exportColumns;
    const cell = (v) => `"${String(v ?? "").replace(/"/g, '""')}"`;
    const lines = [
      cols.map((c) => cell(c[0])).join(";"),
      ...rows.map((r) => cols.map((c) => cell((c[2] || c[1])(r))).join(";")),
    ];
    // BOM + ";" para o Excel (PT) abrir com acentos e colunas corretas
    bxSaveBlob(
      new Blob(["\uFEFF" + lines.join("\r\n")], { type: "text/csv;charset=utf-8" }),
      "faturas_selecionadas.csv",
    );
    bxToast("CSV exportado com sucesso", "success");
  }

  // vários PDFs: gera um a um (jsPDF) e entrega um único .zip
  async function bulkDownloadPdfs() {
    if (bulkBusy) return;
    const rows = selectedRows().filter((r) => invoiceStatus(r).base !== "rascunho");
    if (!rows.length) {
      return bxToast("Os documentos selecionados são rascunhos e não têm PDF.", "info");
    }
    const skipped = selectedIds.size - rows.length;

    bulkBusy = true;
    $("#bxSelectionBar button").prop("disabled", true);
    const toast = bxToast(`Gerando PDF 1 de ${rows.length}...`, "loading");
    const files = [];
    let failed = 0;

    for (let i = 0; i < rows.length; i++) {
      toast.update(`Gerando PDF ${i + 1} de ${rows.length}...`);
      try {
        files.push(await downloadPDF(rows[i].id, { silent: true, asBlob: true }));
      } catch (_) {
        failed++;
      }
    }

    try {
      if (files.length === 1) {
        bxSaveBlob(files[0].blob, files[0].filename);
      } else if (files.length > 1 && window.JSZip) {
        const zip = new JSZip();
        const used = new Set();
        files.forEach((f) => {
          let name = f.filename;
          let n = 1;
          while (used.has(name)) name = f.filename.replace(/\.pdf$/i, ` (${++n}).pdf`);
          used.add(name);
          zip.file(name, f.blob);
        });
        const blob = await zip.generateAsync({ type: "blob" });
        bxSaveBlob(blob, `faturas_${new Date().toISOString().slice(0, 10)}.zip`);
      } else {
        // sem JSZip: descarrega um a um (o browser pode pedir permissão para vários downloads)
        for (const f of files) {
          bxSaveBlob(f.blob, f.filename);
          await new Promise((r) => setTimeout(r, 400));
        }
      }
    } finally {
      toast.close();
      bulkBusy = false;
      $("#bxSelectionBar button").prop("disabled", false);
    }

    if (!files.length) return bxToast("Não foi possível gerar os PDFs.", "error");
    const notes = [];
    if (failed) notes.push(`${failed} falharam`);
    if (skipped) notes.push(`${skipped} rascunho(s) ignorado(s)`);
    bxToast(
      `${files.length} PDF(s) baixado(s)${notes.length ? " · " + notes.join(" · ") : ""}`,
      failed ? "error" : "success",
    );
  }

  function exportServerList(format) {
    if (docType !== "invoices") return exportFile(docType, format);
    // com filtros ativos exporta só o que está filtrado (ids); sem filtros, tudo
    if (hasActiveFilters()) {
      if (!filteredInvoices.length) return bxToast("Não há documentos para exportar.", "info");
      return exportFile("invoices", format, { ids: filteredInvoices.map((r) => r.id) });
    }
    return exportFile("invoices", format);
  }

  function runExportAction(action, el) {
    const cfg = DOC_TYPES[docType];
    switch (action) {
      case "list-pdf":
        return exportListPdf(filteredInvoices, cfg.title, `${cfg.title.replace(/\s+/g, "_")}.pdf`);
      case "list-excel":
        return exportServerList("excel");
      case "list-csv":
        return exportServerList("csv");
      case "report":
        return exportFile(el.dataset.report);
      case "selected-pdfs":
        return bulkDownloadPdfs();
      case "selected-excel":
        return exportFile("invoices", "excel", { ids: [...selectedIds] });
      case "selected-csv":
        return exportSelectedCsv();
      case "selected-list-pdf":
        return exportListPdf(selectedRows(), "Faturas selecionadas", "Faturas_selecionadas.pdf");
    }
  }

  $(document).on("click", "[data-export-action]", function (e) {
    e.stopPropagation();
    if (this.disabled) return;
    closeAllMenus();
    runExportAction(this.dataset.exportAction, this);
  });
  $("#bxBulkPdf").on("click", () => bulkDownloadPdfs());
  $("#bxBulkExcel").on("click", () => runExportAction("selected-excel"));

  // estado inicial da barra / checkbox
  refreshSelectionUI();
});

// ==================================================
// DOWNLOAD PDF DA FATURA
// ==================================================
// Devolve uma Promise. opts.silent = sem toasts; opts.asBlob = devolve {filename, blob}
// em vez de descarregar (usado para juntar vários PDFs num .zip).
function downloadPDF(invoiceId, opts = {}) {
  const silent = !!opts.silent;
  const asBlob = !!opts.asBlob;
  return new Promise((resolve, reject) => {
  const toast = silent ? null : bxToast("Gerando PDF...", "loading");
  const fail = (msg) => {
    if (toast) toast.close();
    if (!silent) bxToast(msg || "Erro ao gerar o PDF.", "error");
    reject(new Error(msg || "Erro ao gerar o PDF."));
  };
  $.ajax({
    url: "invoices/ajax/get_invoice.php",
    type: "GET",
    data: { id: invoiceId },
    dataType: "json",
    success: function (response) {
      if (response.error) {
        fail(response.error);
        return;
      }

      try {
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
        "../public/invoice_public.php?id=" +
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
      doc.text(`Fatura n.º ${response.codigo}`, 10, currentY);
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

      const rawName = `Fatura_${response.company_name}_${response.codigo}.pdf`;
      if (asBlob) {
        // "/" no código (2026/91) criaria pastas dentro do .zip
        resolve({ filename: rawName.replace(/[\\/:*?"<>|]+/g, "-"), blob: doc.output("blob") });
      } else {
        doc.save(rawName); // descarrega diretamente no browser, sem nova aba
        if (toast) toast.close();
        if (!silent) bxToast("PDF baixado com sucesso", "success");
        resolve({ filename: rawName });
      }
      } catch (err) {
        console.error(err);
        fail("Erro ao gerar o PDF.");
      }
    },
    error: function () {
      fail("Erro ao carregar os dados da fatura.");
    },
  });
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
// ELIMINAR FATURA
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
      url: "invoices/ajax/delete_invoice.php",
      type: "POST",
      data: { invoice_id: invoiceToDelete, company_id: invoice_companyId },

      success: function (response) {
        const modalEl = document.getElementById("deleteModal");
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();

        if (response.success) {
          Swal.fire({
            icon: "success",
            title: "Fatura eliminada!",
            timer: 1500,
            showConfirmButton: false,
          });

          // recarrega os dados sem DataTables
          $(document).trigger("reload-invoices");
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

// HTML Invoice (mantido, caso usado noutro ponto)
function renderInvoiceHTML(data) {
  const html = `
    <div>
      <h2>${data.company_name}</h2>
      <p>${data.company_address}</p>
      <p>Tel: ${data.company_phone}</p>

      <hr>

      <h3>Fatura nº ${data.codigo}</h3>
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

  $("#fatura-container").html(html);
}