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

  function updatePreview(record) {
    const frame = document.createElement("iframe");
    frame.title = `${record.label || "Documento"} ${record.number || ""}`.trim();
    frame.src = `${pdfUrl(record)}#toolbar=0&navpanes=0`;
    frame.style.cssText = "display:block;width:100%;height:100%;min-height:calc(100vh - 2rem);border:0;background:#fff";
    container.replaceChildren(frame);
  }

  function setSelected(record) {
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
