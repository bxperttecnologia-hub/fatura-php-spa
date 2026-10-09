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

  const $preloader = $("#preloader");
  const $container = $("#fatura-container");
  const retryDelays = [400, 900, 1600];
  const currentPath = window.location.pathname.replace(/\/+$/, "");
  const spaRoute = currentPath.match(/^(.*)\/(?:proformas|invoices)\/view$/);
  const endpointBase = spaRoute
    ? spaRoute[1]
    : currentPath.replace(/\/[^/]+\.php$/i, "");
  const endpointUrl = (endpoint) =>
    `${endpointBase}/${endpoint}`.replace(/^\/\//, "/");

  function requestWithRetry(options, retries = retryDelays.length) {
    return new Promise((resolve, reject) => {
      let attempts = 0;
      const request = () => {
        attempts += 1;
        $.ajax(options)
          .done(resolve)
          .fail((xhr, status, error) => {
            if (attempts > retries) {
              reject(new Error(error || xhr.statusText || "Falha no carregamento."));
              return;
            }
            window.setTimeout(
              request,
              retryDelays[attempts - 1] || retryDelays[retryDelays.length - 1],
            );
          });
      };
      request();
    });
  }

  function showLoadError(error) {
    console.error("Erro ao carregar a proforma:", error);
    $preloader.hide();
    $container
      .show()
      .empty()
      .append(
        $("<div>", {
          class: "alert alert-danger m-3",
          role: "alert",
          text: "Não foi possível carregar os dados da proforma. Verifique a ligação e tente novamente.",
        }),
        $("<button>", {
          type: "button",
          class: "btn btn-outline-primary mx-3 mb-3",
          text: "Tentar novamente",
        }).on("click", loadInvoice),
      );
  }

  // Carrega o JSON e o HTML em conjunto para não mostrar uma página vazia
  // enquanto um dos pedidos ainda está a ser repetido.
  function loadInvoice() {
    $preloader.text("Carregando...").show();
    $container.hide().empty();
    const query = `id=${encodeURIComponent(invoiceId)}`;
    const requests = [
      requestWithRetry({
        url: `${endpointUrl("proform/ajax/get_proform.php")}?${query}`,
        dataType: "json",
        cache: false,
      }).then((response) => {
        if (!response?.success || !response.data) {
          throw new Error(response?.error || "Resposta inválida dos dados da proforma.");
        }
        return response.data;
      }),
      requestWithRetry({
        url: `${endpointUrl("proform/ajax/proform_public.php")}?${query}`,
        dataType: "html",
        cache: false,
      }).then((html) => {
        const parsed = new DOMParser().parseFromString(html, "text/html");
        if (!parsed.querySelector(".invoice-page")) {
          throw new Error("O servidor não devolveu o documento da proforma.");
        }
        return html;
      }),
    ];

    Promise.all(requests)
      .then(([invoice, html]) => {
        currentInvoice = invoice;
        $container.html(html).show();
        $preloader.hide();

        $("#fatura-id").text(invoice.reference || "-");
        $("#action-proform-number").text(invoice.reference || "-");
        const statusText = invoice.status_invoice || "Proforma";
        $("#status-invoice")
          .text(statusText)
          .toggleClass("d-none", !statusText || statusText === "-")
          .toggleClass("is-draft", statusText === "Rascunho");
        $("#subtitle-client").text(invoice.client_name || "-");
        $("#action-proform-client").text(invoice.client_name || "-");

        setupButtons(invoice);
        const params = new URLSearchParams(window.location.search);
        if (params.get("send") === "1") {
          bootstrap.Modal.getOrCreateInstance(
            document.getElementById("modalEnviarEmail"),
          ).show();
        } else if (params.get("convert") === "1") {
          $("#btnChangeToInvoice").trigger("click");
        }
      })
      .catch(showLoadError);
  }

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

    const payload = {
      ...currentInvoice,
      logo_url: currentInvoice.logo_url || currentInvoice.company?.logo_url || currentInvoice.company?.logo || "",
      document_type: "PF",
      document_url: new URL(
        `proform/ajax/proform_public.php?id=${encodeURIComponent(currentInvoice.id)}`,
        window.location.href,
      ).toString(),
    };
    console.log("Payload enviado à API de PDF:", payload);

    fetch(`${apiBaseUrl}/api/invoices/pdf`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/pdf" },
      body: JSON.stringify(payload),
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

  $("#btnPagamentoRecibo").on("click", function () {
    if (!currentInvoice?.converted_invoice_id) {
      Swal.fire({
        icon: "info",
        title: "Proforma ainda não convertida",
        text: "Converta primeiro a proforma em fatura para registar ou consultar pagamentos e recibos.",
      });
      return;
    }

    const invoiceUrl = `/invoices/view?id=${encodeURIComponent(currentInvoice.converted_invoice_id)}`;
    if (typeof window.navigateSPA === "function") {
      window.navigateSPA(invoiceUrl);
    } else {
      window.location.assign(invoiceUrl);
    }
  });

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

    $.post("proform/ajax/send_invoice.php", $(this).serialize())
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
