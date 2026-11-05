(() => {
  const action = document.getElementById("btnDocumentHistory");
  const modal = document.getElementById("documentHistoryModal");
  const container = document.getElementById("fatura-container");
  if (!action || !modal || !container) return;

  const list = document.getElementById("documentHistoryList");
  const loading = document.getElementById("documentHistoryLoading");
  const error = document.getElementById("documentHistoryError");
  const endpoint = action.dataset.historyEndpoint;
  const pdfSegments = {
    invoice: "invoices",
    proforma: "proformas",
    receipt: "receipts",
    "credit-note": "credit-notes",
    "debit-note": "debit-notes",
    "delivery-note": "delivery-notes",
  };
  let rootDocument = null;
  let selectedDocument = null;
  let documents = [];
  let requestToken = 0;

  function pdfUrl(document) {
    const segment = pdfSegments[document.type];
    if (!segment || !Number.isInteger(Number(document.id)) || Number(document.id) <= 0) {
      throw new Error("Documento inválido.");
    }
    return `/api/documents/${segment}/${Number(document.id)}/pdf`;
  }

  function displayDate(value) {
    if (!value) return "-";
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    return match ? `${match[3]}/${match[2]}/${match[1]}` : String(value);
  }

  function updatePanel(record) {
    const label = record.label || "Documento";
    const number = record.number || `#${record.id}`;
    const title = record.type === "proforma"
      ? "Proforma"
      : label;
    const documentLabel = documentElement("invoice-document-label");
    if (documentLabel) documentLabel.textContent = title;

    const numberElement = documentElement("fatura-id");
    if (numberElement) numberElement.textContent = number;
    const sideNumber = documentElement("action-proform-number");
    if (sideNumber) sideNumber.textContent = number;

    const statusElement = documentElement("status-invoice");
    if (statusElement) {
      statusElement.textContent = record.status || "-";
      statusElement.classList.toggle("d-none", !record.status);
      statusElement.classList.toggle("is-draft", record.status === "Rascunho");
    }

    const meta = documentElement("invoice-document-meta");
    if (meta) meta.textContent = `Data: ${displayDate(record.date)}`;

    const proformaLabel = documentElement("proform-document-label");
    if (proformaLabel) proformaLabel.textContent = title.toUpperCase();
    const proformaPrefix = documentElement("proform-preview-label");
    if (proformaPrefix) proformaPrefix.textContent = `${title} nº`;
    const proformaClient = documentElement("action-proform-client");
    const invoiceClient = documentElement("subtitle-client");
    const client = rootDocument?.client || "";
    if (proformaClient && client) proformaClient.textContent = client;
    if (invoiceClient && client) invoiceClient.textContent = client;
  }

  function documentElement(id) {
    return document.getElementById(id);
  }

  let previewToken = 0;
  let previewUrl = null;

  function previewMessage(html, isError = false) {
    const box = document.createElement("div");
    box.className = "d-flex flex-column align-items-center justify-content-center text-center gap-3 p-5";
    box.style.cssText = `min-height:60vh;color:${isError ? "#b42318" : "#475569"}`;
    box.innerHTML = html;
    container.replaceChildren(box);
    return box;
  }

  // Descarrega o PDF (com loader) e só depois o mostra: assim há feedback enquanto o
  // servidor o gera e um erro legível se falhar, em vez de um quadro em branco.
  async function updatePreview(record) {
    const token = ++previewToken;
    const label = `${record.label || "Documento"} ${record.number || ""}`.trim();
    previewMessage(`
      <div class="spinner-border" role="status" style="width:2.5rem;height:2.5rem;color:#007abd"></div>
      <div><div class="fw-semibold">A carregar ${escapeHtml(label)}…</div>
      <div class="small">A gerar o PDF do documento.</div></div>`);
    try {
      const blob = await fetchPdf(record);
      if (token !== previewToken) return;   // entretanto escolheu-se outro documento
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(blob);
      const frame = document.createElement("iframe");
      frame.title = label;
      frame.src = `${previewUrl}#toolbar=0&navpanes=0`;
      frame.style.cssText = "display:block;width:100%;height:100%;min-height:calc(100vh - 2rem);border:0;background:#fff";
      container.replaceChildren(frame);
    } catch (previewError) {
      if (token !== previewToken) return;
      const box = previewMessage(`
        <i class="bi bi-exclamation-triangle" style="font-size:2rem"></i>
        <div><div class="fw-semibold">Não foi possível mostrar ${escapeHtml(label)}.</div>
        <div class="small">${escapeHtml(previewError.message || "Erro desconhecido.")}</div></div>
        <button type="button" class="btn btn-sm btn-outline-secondary">Tentar novamente</button>`, true);
      box.querySelector("button").addEventListener("click", () => updatePreview(record));
    }
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  }

  function setSelected(record) {
    if (record.type === "proforma") {
      const query = new URLSearchParams({ id: String(record.id) });
      const route = `/proformas/view?${query}`;
      if (typeof window.navigateSPA === "function") {
        window.navigateSPA(route);
      } else {
        window.location.assign(route);
      }
      return;
    }

    selectedDocument = {
      ...record,
      isRoot: rootDocument?.type === record.type
        && Number(rootDocument.id) === Number(record.id),
    };
    selectedDocument.pdf_url = record.pdf_url || pdfUrl(record);
    updatePanel(selectedDocument);
    updatePreview(selectedDocument);
    renderList();
    window.document.dispatchEvent(new CustomEvent("bx:document-selected", {
      detail: selectedDocument,
    }));
    bootstrap.Modal.getOrCreateInstance(modal).hide();
  }

  function renderList() {
    list.replaceChildren();
    documents.forEach((record) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "list-group-item list-group-item-action";
      const isSelected = selectedDocument
        && selectedDocument.type === record.type
        && Number(selectedDocument.id) === Number(record.id);
      if (isSelected) {
        item.classList.add("active");
        item.setAttribute("aria-current", "true");
      }

      const heading = document.createElement("div");
      heading.className = "d-flex justify-content-between align-items-center gap-2";
      const type = document.createElement("strong");
      type.textContent = record.label || "Documento";
      const status = document.createElement("span");
      status.className = "small";
      status.textContent = record.status || "-";
      heading.append(type, status);

      const details = document.createElement("div");
      details.className = "small mt-1";
      details.textContent = `${record.number || `#${record.id}`} · ${displayDate(record.date)}`;
      item.append(heading, details);
      item.addEventListener("click", () => setSelected(record));
      list.appendChild(item);
    });
  }

  async function loadHistory() {
    const token = ++requestToken;
    error.classList.add("d-none");
    loading.classList.remove("d-none");
    list.replaceChildren();
    try {
      const response = await fetch(`${endpoint}?id=${encodeURIComponent(rootDocument.id)}`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || payload?.success !== true || !Array.isArray(payload.data)) {
        throw new Error(payload?.error || payload?.message || "Não foi possível carregar o histórico documental.");
      }
      if (token !== requestToken) return;
      documents = payload.data.map((document) => ({
        ...document,
        pdf_url: document.pdf_url || pdfUrl(document),
      }));
      if (action.dataset.hideWhenAlone === "true") {
        action.classList.toggle("d-none", documents.length <= 1);
      }
      renderList();
    } catch (loadError) {
      if (token !== requestToken) return;
      error.textContent = loadError.message || "Não foi possível carregar o histórico documental.";
      error.classList.remove("d-none");
    } finally {
      if (token === requestToken) loading.classList.add("d-none");
    }
  }

  async function fetchPdf(document) {
    const response = await fetch(document.pdf_url || pdfUrl(document), {
      credentials: "same-origin",
      headers: { Accept: "application/pdf, application/json" },
    });
    const contentType = response.headers.get("Content-Type") || "";
    if (!response.ok || !contentType.includes("application/pdf")) {
      let message = `Não foi possível obter o PDF (${response.status}).`;
      try {
        const payload = await response.json();
        message = payload.error || payload.message || message;
      } catch {
        // A resposta pode não ser JSON.
      }
      throw new Error(message);
    }
    const blob = await response.blob();
    if (!blob.size || (await blob.slice(0, 5).text()) !== "%PDF-") {
      throw new Error("O servidor não devolveu um PDF válido.");
    }
    return blob;
  }

  function filename(document) {
    const base = `${document.label || "documento"}-${document.number || document.id}`
      .replace(/[\\/:*?"<>|]+/g, "-")
      .replace(/\s+/g, "_");
    return `${base}.pdf`;
  }

  async function downloadSelected() {
    if (!selectedDocument) throw new Error("Nenhum documento está seleccionado.");
    const blob = await fetchPdf(selectedDocument);
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename(selectedDocument);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
  }

  window.BXDocumentHistory = {
    setCurrentDocument(document) {
      rootDocument = {
        ...document,
        id: Number(document.id),
        isRoot: true,
      };
      rootDocument.pdf_url = rootDocument.pdf_url || pdfUrl(rootDocument);
      selectedDocument = rootDocument;
      updatePanel(rootDocument);
    },
    getSelectedDocument() {
      return selectedDocument;
    },
    /**
     * Mostra logo um documento da cadeia (ex.: o recibo/NC/ND acabado de emitir),
     * sem o utilizador ter de ir ao histórico. Procura-o no histórico para ter o
     * número e o estado; se ainda não aparecer, mostra-o só com o tipo e o id.
     */
    async showDocument({ type, id, label, number }) {
      if (!rootDocument) throw new Error("O documento ainda está a carregar.");
      let record = null;
      try {
        const response = await fetch(`${endpoint}?id=${encodeURIComponent(rootDocument.id)}`, {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const payload = await response.json();
        if (response.ok && payload?.success === true && Array.isArray(payload.data)) {
          documents = payload.data.map((d) => ({ ...d, pdf_url: d.pdf_url || pdfUrl(d) }));
          record = documents.find((d) => d.type === type && Number(d.id) === Number(id)) || null;
        }
      } catch {
        // sem histórico: usa os dados mínimos abaixo
      }
      setSelected(record || { type, id: Number(id), label: label || "Documento", number: number || `#${id}` });
    },
    fetchPdf,
    downloadSelected,
  };

  modal.addEventListener("show.bs.modal", () => {
    if (!rootDocument) {
      error.textContent = "O documento ainda está a carregar.";
      error.classList.remove("d-none");
      return;
    }
    loadHistory();
  });

  modal.addEventListener("hidden.bs.modal", () => {
    requestToken += 1;
  });
})();
