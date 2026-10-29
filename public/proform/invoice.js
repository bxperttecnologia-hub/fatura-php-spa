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
    $.get("/proform/ajax/get_proform.php", { id: invoiceId })
      .done((inv) => {
        currentInvoice = inv?.data;

        $("#fatura-id").text(`${inv.data?.reference}`);
        $("#action-proform-number").text(inv.data?.reference || "-");
        const statusText = inv.data?.status_invoice || "Proforma";
        $("#status-invoice")
          .text(statusText)
          .toggleClass("d-none", !statusText || statusText === "-")
          .toggleClass("is-draft", statusText === "Rascunho");
        $("#subtitle-client").text(inv.data?.client_name);
        $("#action-proform-client").text(inv.data?.client_name || "-");

        setupButtons(inv?.data);
        const params = new URLSearchParams(window.location.search);
        if (params.get("send") === "1") {
          bootstrap.Modal.getOrCreateInstance(
            document.getElementById("modalEnviarEmail"),
          ).show();
        } else if (params.get("convert") === "1") {
          $("#btnChangeToInvoice").trigger("click");
        }
      })
      .fail(() => {
        alert("Erro ao carregar factura proforma");
      });
  }

  // =========================
  // 3. CARREGAR HTML VISUAL
  // =========================
  $("#fatura-container").load(
    `/proform/ajax/proform_public.php?id=${invoiceId}`,
  );

  // =========================
  // 4. BOTÕES CONDICIONAIS
  // =========================
  function setupButtons(inv) {
    const normalizedStatus = String(inv.status_invoice || "")
      .trim()
      .toLowerCase();
    if (normalizedStatus === "rascunho") {
      $("#btnFinalizar, #btnEditar").removeClass("d-none");
    }

    $("#btnPdf, #generatePdf, #btnEnviar").removeClass("d-none");
    if (normalizedStatus.startsWith("cancel")) {
      $("#btnNotaCredito").removeClass("d-none");
    }
  }

  $("#btnNotaCredito").on("click", function () {
    if (currentInvoice?.converted_invoice_id) {
      const invoiceUrl = `/invoices/view?id=${encodeURIComponent(currentInvoice.converted_invoice_id)}`;
      if (typeof window.navigateSPA === "function") {
        window.navigateSPA(invoiceUrl);
      } else {
        window.location.assign(invoiceUrl);
      }
      return;
    }

    Swal.fire({
      icon: "info",
      title: "Nota de crédito indisponível",
      text: "A nota de crédito é emitida sobre uma fatura. Esta proforma ainda não está associada a uma fatura convertida.",
    });
  });

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

    // PDF gerado pela própria aplicação (public/document_pdf.php); já não depende do serviço Node.
    fetch(`/api/documents/proformas/${encodeURIComponent(currentInvoice.id)}/pdf`, {
      credentials: "same-origin",
      headers: { Accept: "application/pdf" },
    })
      .then(async (response) => {
        if (!response.ok) {
          let details = "";
          try {
            const error = await response.clone().json();
            details = error.message || error.error || "";
          } catch (_) {
            details = (await response.text()).slice(0, 240);
          }
          throw new Error(details || `HTTP ${response.status}`);
        }
        return response.blob();
      })
      .then((blob) => {
        if (
          !blob.size ||
          (blob.type &&
            !/^application\/(pdf|octet-stream)$/i.test(blob.type))
        ) {
          throw new Error("O serviço não devolveu um ficheiro PDF válido.");
        }
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${currentInvoice.reference || "proforma"}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 60000);
      })
      .catch((error) => {
        console.error("Erro ao gerar PDF da proforma:", error);
        Swal.fire({
          icon: "error",
          title: "Erro ao imprimir a proforma",
          text: error.message || "Não foi possível gerar o PDF da proforma.",
        });
      })
      .finally(() => {
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
        url: "/proform/ajax/convert_proforma.php",
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
                const invoiceUrl = `/invoices/view?id=${encodeURIComponent(response.new_invoice_id)}`;
                if (typeof window.navigateSPA === "function") window.navigateSPA(invoiceUrl);
                else window.location.href = invoiceUrl;
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
              const invoiceUrl = `/invoices/view?id=${encodeURIComponent(response.new_invoice_id)}`;
              if (typeof window.navigateSPA === "function") window.navigateSPA(invoiceUrl);
              else window.location.href = invoiceUrl;
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
    const editUrl = `/proformas/create?edit_id=${encodeURIComponent(currentInvoice.id)}&return_to=${encodeURIComponent(`/proformas/view?id=${currentInvoice.id}`)}`;
    if (typeof window.navigateSPA === "function") {
      window.navigateSPA(editUrl);
    } else {
      window.location.assign(editUrl);
    }
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

    $.post("/proform/ajax/send_invoice.php", $(this).serialize())
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
