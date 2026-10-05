$(function () {
  // =========================
  // 1. GET ID LIMPO
  // =========================
  const invoiceId = new URLSearchParams(window.location.search)
    .get("id")
    ?.split("/")
    .pop();

  if (!invoiceId) {
    alert("Factura Proforma não encontrada!");
    return;
  }

  let currentInvoice = null;

  // =========================
  // 2. CARREGAR DADOS JSON
  // =========================
  function loadInvoice() {
    $.get("proform/ajax/get_proform.php", { id: invoiceId })
      .done((inv) => {
        currentInvoice = inv?.data;

        $("#fatura-id").text(`${inv.data?.reference}`);
        $("#status-invoice").text("PROFORMA");
        $("#subtitle-client").text(inv.data?.client_name);

        setupButtons(inv?.data);
      })
      .fail(() => {
        alert("Erro ao carregar factura proforma");
      });
  }

  // =========================
  // 3. CARREGAR HTML VISUAL
  // =========================
  $("#fatura-container").load(
    `proform/ajax/proform_public.php?id=${invoiceId}`,
  );

  // =========================
  // 4. BOTÕES CONDICIONAIS
  // =========================
  function setupButtons(inv) {
    if (inv.status_invoice === "Rascunho") {
      $("#btnFinalizar, #btnEditar").removeClass("d-none");
    }

    $("#btnPdf, #generatePdf, #btnEnviar").removeClass("d-none");
    $("#btnNotaCredito").removeClass("d-none");
  }

  // =========================
  // 5. GERAR PDF PROFORMA
  // =========================
  function createProformaPage(element, items, viaLabel) {
    const clone = element.cloneNode(true);
    const itemsGrid = clone.querySelector(".items-grid");
    if (itemsGrid) {
      const header = itemsGrid.querySelector(".items-head")?.cloneNode(true);
      itemsGrid.innerHTML = "";
      if (header) itemsGrid.appendChild(header);
      items.forEach((item) => itemsGrid.appendChild(item.cloneNode(true)));
    }
    const via = clone.querySelector(".inv-meta > div > span");
    if (via) via.textContent = viaLabel;
    clone.style.width = "190mm";
    clone.style.maxWidth = "190mm";
    clone.style.margin = "0";
    return clone;
  }

  function splitProformaItems(element, items, viaLabel) {
    const pages = [];
    const maxHeight = 1080;
    let cursor = 0;

    while (cursor < items.length || !pages.length) {
      let end = cursor;
      let accepted = null;

      while (end < items.length) {
        const candidate = createProformaPage(element, items.slice(cursor, end + 1), viaLabel);
        document.body.appendChild(candidate);
        const height = candidate.getBoundingClientRect().height;
        candidate.remove();
        if (height > maxHeight && end > cursor) break;
        accepted = candidate;
        end += 1;
        if (height > maxHeight) break;
      }

      if (!accepted) {
        accepted = createProformaPage(element, items.slice(cursor, cursor + 1), viaLabel);
        end = cursor + 1;
      }
      pages.push(accepted);
      cursor = end;
    }
    return pages;
  }

  function gerarPdfProforma() {
    if (!currentInvoice) return;

    const button = document.getElementById("generatePdf");
    const element = document.querySelector("#fatura-container .invoice-page");
    if (!element || button?.disabled) return;

    const originalHtml = button?.innerHTML;
    if (button) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Preparando PDF...';
    }

    const hostname = window.location.hostname;
    const apiBaseUrl = hostname === "api-crm.bxpert.co.ao" || hostname === "www.api-crm.bxpert.co.ao"
      ? "https://api-crm.bxpert.co.ao"
      : "http://localhost:3004";

    fetch(`${apiBaseUrl}/api/invoices/pdf`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/pdf" },
      body: JSON.stringify({
        ...currentInvoice,
        document_type: "PF",
        document_url: new URL(
          `proform/ajax/proform_public.php?id=${encodeURIComponent(currentInvoice.id)}`,
          window.location.href,
        ).toString(),
      }),
    })
      .then((response) => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.blob();
      })
      .then((blob) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${currentInvoice.reference || "proforma"}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
      })
      .catch((error) => {
        console.error("Erro ao gerar PDF da proforma:", error);
        alert("Não foi possível gerar o PDF da proforma.");
      })
      .finally(() => {
        staging.remove();
        if (button) {
          button.disabled = false;
          button.innerHTML = originalHtml;
        }
      });
  }

  $("#btnPdf, #generatePdf").on("click", gerarPdfProforma);

  // =========================
  // 6. FINALIZAR PROFORMA -> FACTURA
  // =========================
  $("#btnChangeToInvoice").on("click", function (event) {
    event.preventDefault();

    const $btn = $(this);

    // CORRIGIDO: evita cliques duplicados a abrir vários
    // diálogos de confirmação antes do primeiro ser respondido.
    if ($btn.prop("disabled")) return;

    const proformaId = currentInvoice?.id;

    if (!proformaId) {
      return Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Proforma não encontrada.",
      });
    }

    $btn.prop("disabled", true);

    Swal.fire({
      title: "Converter em Factura?",
      text: "A Proforma será convertida numa Factura.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sim, converter",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
    }).then((result) => {
      if (!result.isConfirmed) {
        $btn.prop("disabled", false);
        return;
      }

      $.ajax({
        url: "proform/ajax/convert_proforma.php",
        type: "POST",
        dataType: "json",
        data: {
          invoice_id: proformaId,
        },

        beforeSend: function () {
          $btn.html(`
          <span class="spinner-border spinner-border-sm"></span>
          Convertendo...
        `);
        },

        success: function (response) {
          if (!response.success) {
            Swal.fire({
              icon: "error",
              title: "Erro",
              text: response.error || "Falha ao converter a Proforma.",
            });
            return;
          }

          // Já existe uma factura gerada
          if (response.already_converted) {
            Swal.fire({
              icon: "info",
              title: "Factura já existente",
              text: `A Factura ${response.reference} já foi emitida anteriormente.`,
              confirmButtonText: "Abrir Factura",
            }).then((r) => {
              // CORRIGIDO: só redireciona se o utilizador
              // clicar mesmo em "Abrir Factura".
              if (r.isConfirmed) {
                window.location.href = `invoice.php?id=${response.new_invoice_id}`;
              }
            });

            return;
          }

          // Nova factura criada
          Swal.fire({
            icon: "success",
            title: "Sucesso",
            text: `Factura Recibo ${response.reference} criada com sucesso.`,
            confirmButtonText: "Abrir Factura",
          }).then((r) => {
            // CORRIGIDO: mesma verificação aqui.
            if (r.isConfirmed) {
              window.location.href = `invoice.php?id=${response.new_invoice_id}`;
            }
          });
        },

        error: function (xhr) {
          Swal.fire({
            icon: "error",
            title: "Erro",
            text:
              xhr.responseJSON?.error ||
              "Erro interno ao converter a Proforma.",
          });
        },

        complete: function () {
          $btn.prop("disabled", false).html(`
          <span class="material-icons-outlined">receipt_long</span>
          Emitir Factura Recibo
        `);
        },
      });
    });
  });

  // =========================
  // 7. EDITAR PROFORMA
  // =========================
  $("#btnEditar").on("click", function () {
    window.location.href = `create_proform.php?edit_id=${currentInvoice.id}`;
  });

  /* ---------- 1. inicializa Quill ---------- */
  const quill = new Quill("#editor-container", {
    theme: "snow",
    modules: {
      toolbar: "#editor-toolbar",
    },
  });

  /* ---------- 2. abre a modal ---------- */
  $("#modalEnviarEmail").on("show.bs.modal", function () {
    if (!currentInvoice) {
      return alert("Proforma ainda não carregada!");
    }

    // Id oculto
    $("#email_invoice_id").val(currentInvoice.id);

    // Assunto default
    const codigo = `${currentInvoice.reference}`;
    $('input[name="subject"]').val(
      `Proforma #${codigo} – ${currentInvoice.company_name}`,
    );

    /* --- Corpo default (HTML) --- */
    const issue = new Intl.DateTimeFormat("pt-BR").format(
      new Date(currentInvoice.issue_date),
    );
    const dueDate = new Intl.DateTimeFormat("pt-BR").format(
      new Date(
        new Date(currentInvoice.issue_date).setDate(
          +currentInvoice.issue_date.split("-")[2] + +currentInvoice.due_date,
        ),
      ),
    );
    const total = Number(currentInvoice.final_total).toLocaleString("pt-PT", {
      minimumFractionDigits: 2,
    });

    const template = `
      <p>Prezado(a) <strong>${currentInvoice.client_name}</strong>,</p>

      <p>Segue em anexo a <strong>Proforma nº ${codigo}</strong>,
      no valor de <strong>${currentInvoice.company_symbol} ${total}</strong>,
      emitida em ${issue} e com vencimento em ${dueDate}.</p>

      <p>Qualquer dúvida estou à disposição.</p>

      <p>Atenciosamente,<br>
      &nbsp;</p>`;

    quill.setContents(quill.clipboard.convert(template));
  });

  /* ---------- 3. submit ---------- */
  $("#formEnviarEmail").on("submit", function (e) {
    e.preventDefault();

    // valida Bootstrap
    if (this.checkValidity() === false) {
      this.classList.add("was-validated");
      return;
    }

    // passa o HTML do Quill para <textarea hidden>
    $("#body-hidden").val(quill.root.innerHTML);

    $.post("invoices/ajax/send_invoice.php", $(this).serialize())
      .done(() => {
        bootstrap.Modal.getInstance(
          document.getElementById("modalEnviarEmail"),
        ).hide();
        alert("E‑mail enviado com sucesso!");
      })
      .fail((xhr) => {
        alert("Erro: " + xhr.responseText);
      });
  });

  // =========================
  // INIT
  // =========================
  loadInvoice();
});
