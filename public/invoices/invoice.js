const currentYear = new Date().getFullYear();
const pg_serie = document.getElementById("pg_serie");
const get = new URLSearchParams(window.location.search).get("id");
const invoiceId = get.substring(get.lastIndexOf("/") + 1);
let document_type = "FT";

if (!invoiceId) {
  alert("Fatura não encontrada!");
}

pg_serie.value = currentYear;
function loadFaturaWithRetry(invoiceId, maxRetries = 5) {
  let attempts = 0;
  const $preloader = $("#preloader");
  const $container = $("#fatura-container");

  function tryLoad() {
    attempts++;
    $preloader.show();
    $container.hide();

    $container.load(
      "invoices/ajax/invoice_public.php?id=" + invoiceId,
      function (response, status) {
        if (status === "success") {
          $preloader.hide();
          $container.show();
        } else if (attempts <= maxRetries) {
          setTimeout(tryLoad, 1200); // espera 1.2s antes de tentar dnv
        } else {
          $preloader.hide();
          $container
            .show()
            .html(
              '<div style="color:#b12; font-weight:bold; padding: 15px;">Erro ao carregar a fatura. Tente novamente mais tarde.</div>',
            );
        }
      },
    );
  }

  tryLoad();
}

loadFaturaWithRetry(invoiceId, 5);
// ───────── invoice.js ─────────
$(function () {
  // id vindo da query‑string
  const invoiceId = new URLSearchParams(location.search)
    .get("id")
    ?.split("/")
    .pop();
  if (!invoiceId) return alert("Fatura não encontrada");

  // ---------------- VAR GLOBAL ----------------
  let currentInvoice = null; // visível a todos abaixo

  // O HTML da fatura já é carregado por loadFaturaWithRetry() acima.
  // ---------- carrega JSON da fatura ----------
  $.getJSON("invoices/ajax/get_invoice.php", { id: invoiceId })
    .done((response) => {
      // Se os dados vêm dentro de response.data
      const inv = response.data;

      if (!inv) {
        throw new Error("Dados da fatura não encontrados.");
      }

      currentInvoice = inv; // guarda para modal

      // Esconde todos os botões antes
      $(
        "#btnFinalizar, #btnEditar, #btnCloneToInvoice, #btnNotaCredito, #btnNotaDebito, #btnNotaEntrega, #generatePdf, #btnEnviar, #btnDeleteInvoice, #btnRecibo",
      ).addClass("d-none");

      // Preenche formulário/UI
      $('#formPagamento [name="invoice_id"]').val(inv.id);

      $("#fatura-id").text(
        inv.status_invoice === "Rascunho"
          ? `${inv.series}/${inv.id}`
          : inv.numero_validacao,
      );

      const statusText = inv.status_invoice || "-";
      $("#status-invoice")
        .text(statusText)
        .toggleClass("d-none", !statusText || statusText === "-")
        .toggleClass("is-draft", statusText === "Rascunho");

      $("#subtitle-client").text(inv.client_name || "-");

      // Mostrar botões conforme status
      if (inv.status_invoice === "Rascunho") {
        $("#btnFinalizar").removeClass("d-none");
        $("#btnEditar").removeClass("d-none");
        $("#generatePdf").removeClass("d-none");
        $("#btnEnviar").removeClass("d-none");
        $("#btnCloneToInvoice").removeClass("d-none");
        $("#btnDeleteInvoice").removeClass("d-none");
      } else {
        $("#btnRecibo").removeClass("d-none");
        $("#btnCloneToInvoice").removeClass("d-none");
        $("#btnNotaCredito").removeClass("d-none");
        $("#btnNotaDebito").removeClass("d-none");
        $("#btnNotaEntrega").removeClass("d-none");
        $("#generatePdf").removeClass("d-none");
        $("#btnEnviar").removeClass("d-none");
      }
    })
    .fail((xhr) => {
      console.error("Erro AJAX:", xhr);

      Swal.fire({
        icon: "error",
        title: "Erro ao carregar fatura",
        text:
          xhr.responseJSON?.message || "Não foi possível carregar os dados.",
        timer: 2000,
        showConfirmButton: false,
      });
    });

  $("#btnDeleteInvoice")
    .off("click")
    .on("click", function () {
      Swal.fire({
        title: "Tem a certeza?",
        text: "Se a fatura estiver em rascunho será eliminada. Caso contrário será cancelada.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sim, continuar",
        cancelButtonText: "Cancelar",
        reverseButtons: true,
      }).then((result) => {
        if (!result.isConfirmed) {
          return;
        }

        const $btn = $("#btnDeleteInvoice");
        $btn.prop("disabled", true);

        $.ajax({
          url: "invoices/ajax/delete_invoice.php",
          type: "POST",
          dataType: "json",
          data: {
            invoice_id: invoiceId,
          },

          success: function (response) {
            if (response.success) {
              Swal.fire({
                icon: "success",
                title: "Sucesso",
                text: response.message,
                confirmButtonText: "OK",
              }).then(() => {
                // Atualiza a tabela sem voltar à primeira página
                $("#invoicesTable").DataTable().ajax.reload(null, false);

                if (typeof loadDashboardCards === "function") {
                  loadDashboardCards();
                }

                // Se estiver na página de edição pode recarregar
                location.replace("list_invoices.php");
              });
            } else {
              Swal.fire({
                icon: "error",
                title: "Erro",
                text:
                  response.message || "Não foi possível concluir a operação.",
              });
            }
          },

          error: function (xhr) {
            Swal.fire({
              icon: "error",
              title: "Erro",
              text:
                xhr.responseJSON?.message ||
                "Erro de comunicação com o servidor.",
            });
          },

          complete: function () {
            $btn.prop("disabled", false);
          },
        });
      });
    });

  //=============================================================
  //  Botao de gerar recibo
  //=============================================================

  $("#btnRecibo").on("click", function () {
    if (!currentInvoice || !currentInvoice.id) {
      console.error("Nenhuma fatura selecionada.");
      return;
    }

    const $btn = $(this);

    $.ajax({
      url: "invoices/ajax/get_last_receipt.php",
      type: "GET",
      dataType: "json",
      data: {
        invoice_id: currentInvoice.id,
      },

      beforeSend: function () {
        $btn.prop("disabled", true);
      },

      success: function (res) {
        if (!res || !res.success) {
          Swal.fire({
            icon: "warning",
            title: "Aviso",
            text: res?.message || "Nenhum recibo encontrado para esta fatura.",
          });
          return;
        }

        const data = res.data || {};

        if (data.length > 0) {
          renderReceipts(data);
          showReceiptsModal();
        } else {
          showPaymentModal();
        }
      },

      error: function (xhr, status, error) {
        console.error("AJAX ERROR:", xhr.responseText);

        Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Não foi possível obter o recibo.",
        });
      },

      complete: function () {
        $btn.prop("disabled", false);
      },
    });
  });

  /* ======================================================
   * MODAIS
   * ====================================================== */

  // ---------- 3) abrir recibo ou modal Pagamento ----------
  function showPaymentModal() {
    new bootstrap.Modal(document.getElementById("modalPagamento")).show();
  }

  function showReceiptsModal() {
    new bootstrap.Modal(document.getElementById("modalReceipts")).show();
  }

  /* ======================================================
   * RENDER RECIBOS
   * ====================================================== */

  function renderReceipts(receipts) {
    const container = $("#receiptsList");

    container.empty();

    if (!receipts.length) {
      container.html(`
      <div class="alert alert-warning mb-0">
        Nenhum recibo encontrado.
      </div>
    `);
      return;
    }

    const html = receipts
      .map((receipt) => {
        const amount = formatCurrency(receipt.amount_paid);

        const createdAt = receipt.created_at
          ? new Date(receipt.created_at).toLocaleDateString("pt-PT")
          : "-";

        return `
        <div class="border rounded-3 p-3 mb-3 bg-light shadow-sm">
          
          <div class="d-flex justify-content-between align-items-center mb-2">
            
            <div>
              <h6 class="mb-1 fw-bold">
                Recibo #${receipt.receipt_number || receipt.id}
              </h6>

              <small class="text-muted">
                ${createdAt}
              </small>
            </div>

            <span class="badge bg-success fs-6">
              ${amount} Kz
            </span>

          </div>

          <div class="d-flex gap-2 mt-3">

            <a
              href="invoices/recibo_pdf.php?id=${receipt.id}"
              target="_blank"
              class="btn btn-sm btn-primary"
            >
              <i class="fa fa-file-pdf me-1"></i>
              Ver PDF
            </a>

            <button
              class="btn btn-sm btn-outline-secondary btnPrintReceipt"
              data-id="${receipt.id}"
            >
              <i class="fa fa-print me-1"></i>
              Imprimir
            </button>

          </div>

        </div>
      `;
      })
      .join("");

    container.html(html);
  }

  /* ======================================================
   * UTILITÁRIOS
   * ====================================================== */

  function formatCurrency(value) {
    return Number(value || 0).toLocaleString("pt-PT", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  /* ======================================================
   * EVENTO IMPRIMIR
   * ====================================================== */

  $(document).off("click", ".btnPrintReceipt");

  $(document).on("click", ".btnPrintReceipt", function () {
    const id = $(this).data("id");

    if (!id) return;

    window.open(`invoices/recibo_pdf.php?id=${id}`, "_blank");
  });

  // ---------- 4) abre modal Pagamento ----------
  $("#modalPagamento").on("show.bs.modal", function () {
    if (!currentInvoice) return alert("Fatura ainda não carregada!");

    console.log(currentInvoice);

    const total = Number(currentInvoice.final_total) || 0;
    const jaPago = Number(currentInvoice.paid_total) || 0;
    const retention = Number(currentInvoice.retention) || 0;
    const saldo = total - jaPago;

    $("#pg_valor")
      .val(saldo.toFixed(2))
      .attr("max", saldo) // HTML5 — impede submit se > max
      .data("saldo", saldo); // guarda para o listener abaixo

    $("#pg_saldo").text(
      `Kz de ${saldo.toLocaleString("pt-PT", { minimumFractionDigits: 2 })} Kz`,
    );

    // data = hoje
    $("#pg_data").val(new Date().toISOString().slice(0, 10));

    $("#pg_obs").val(retention != null ? `Retenção: ${retention}` : "");
  });

  // ---------- 4) submit do pagamento ----------
  $("#formPagamento").on("submit", function (e) {
    e.preventDefault();

    const $form = $(this);
    const $btn = $form.find("[type=submit]");
    $btn.prop("disabled", true);

    Swal.fire({
      title: "Processando...",
      text: "Registrando o pagamento, aguarde.",
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading(),
    });

    $.post("invoices/ajax/registrar_pagamento.php", $form.serialize())
      .done((resp) => {
        Swal.close();
        bootstrap.Modal.getInstance(
          document.getElementById("modalPagamento"),
        ).hide();

        // abre o PDF do recibo gerado
        if (resp && resp.receipt_id) {
          window.open(
            "invoices/recibo_pdf.php?id=" + resp.receipt_id,
            "_blank",
          );
        }

        Swal.fire({
          icon: "success",
          title: "Sucesso",
          text: "Pagamento registrado com sucesso!",
        }).then(() => {
          location.reload();
        });
      })
      .fail((xhr) => {
        Swal.close();
        Swal.fire({
          icon: "error",
          title: "Erro",
          text:
            "Erro ao registrar: " + (xhr.responseText || "Tente novamente."),
        });
      })
      .always(() => {
        $btn.prop("disabled", false);
      });
  });

  /*=============================================================================================
                                  FUNÇÃO GERAR PDF DA FACTURA
============================================================================================= */

  /**
   * gerarPdfFatura.js
   * -----------------------------------------------------------------------
   * Geração de PDF de fatura 100% orientada a DADOS — não depende de nenhum
   * elemento HTML renderizado na página.
   *
   * Usa jsPDF (client-side), com posicionamento MANUAL (x/y) igual ao layout
   * original — dá controle total pixel a pixel. A tabela de itens pagina
   * automaticamente: a cada linha checamos se ainda cabe na página atual;
   * se não couber, abrimos uma nova página e repetimos o cabeçalho da tabela.
   *
   * Dependências (CDN — coloque antes deste arquivo):
   *   <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
   *   <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
   *   (qrcodejs só é necessário se você quiser gerar o QR no próprio browser,
   *   ver gerarQrCodeDataURL() mais abaixo)
   *
   * -----------------------------------------------------------------------
   * FORMATO DOS DADOS ESPERADOS (invoiceData) — igual ao da versão anterior
   * -----------------------------------------------------------------------
   * {
   *   document_type: 'PF' | 'FT',
   *   reference:     'PF 2026/000005',
   *   issue_date:    '2026-06-24',
   *   due_date:      '2026-06-24',
   *   observation:   '-',
   *   company: { name, address, phone, email, website, registration_number,
   *              logoImage: 'data:image/png;base64,....' },
   *   client:  { name, contributor, address },
   *   items:   [ { code, name, description, unit_price, quantity, tax, discount } ],
   *   totals:  { total_sum, total_discount, total_tax, retention_value, final_total }, // opcional
   *   moneySymbol: 'Kz', moneyPos: 'right',
   *   vat_regime:  'geral' | 'simplificado',
   *   iban:        'AO06.0006.0000.1234.5678.9012.3',
   *   qrImage:     'data:image/png;base64,....'
   * }
   * -----------------------------------------------------------------------
   */

  // ---------------------------------------------------------------------------
  // Constantes de layout (pt — A4 = 595.28 x 841.89)
  // ---------------------------------------------------------------------------
  const PAGE_WIDTH = 595.28;
  const PAGE_HEIGHT = 841.89;
  const MARGIN_LEFT = 40;
  const MARGIN_RIGHT = 40;
  const MARGIN_TOP = 40;
  const MARGIN_BOTTOM = 70;
  const CONTENT_RIGHT = PAGE_WIDTH - MARGIN_RIGHT; // 555.28
  const GRAY = [139, 139, 139]; // #8b8b8b — linhas divisórias
  const GRAY_LABEL = [102, 102, 102]; // #666666 — rótulos "opacos"
  const GRAY_FOOTER = [150, 150, 150];
  const BLACK = [0, 0, 0];

  // colunas da tabela de itens
  // 🔧 Reformuladas com folgas seguras entre colunas — antes "Descrição" (até x=385)
  // ficava a poucos pontos de "Preço Uni." (right-align x=390), e valores grandes
  // (ex: "3.916.666,67 Kz") invadiam o espaço da descrição e sobrepunham o texto.
  const COL_CODE_X = MARGIN_LEFT; // 40
  const COL_CODE_MAX_WIDTH = 60; // largura máx. antes de encolher a fonte

  const COL_DESC_X = 105;
  const COL_DESC_WIDTH = 130; // termina em x=235 (antes: 385)

  const COL_PRECO_RIGHT_X = 335; // valor termina aqui (right-align)
  const COL_PRECO_MAX_WIDTH = 85; // início mín. em x=250 → folga de 15pt da Descrição

  const COL_QTD_CENTER_X = 365;
  const COL_TAXA_CENTER_X = 405;
  const COL_DESCPCT_CENTER_X = 440;

  const COL_TOTAL_RIGHT_X = CONTENT_RIGHT; // 555.28
  const COL_TOTAL_MAX_WIDTH = 95; // início mín. em x=460 → folga de 17pt da coluna Desc.

  // ---------------------------------------------------------------------------
  // Utils
  // ---------------------------------------------------------------------------

  function formatCurrency(value, symbol = "Kz", position = "right") {
    const n = Number(value) || 0;
    const fixed = n.toFixed(2);
    const [intPart, decPart] = fixed.split(".");
    const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    const formatted = `${withThousands},${decPart}`;
    return position === "left"
      ? `${symbol} ${formatted}`
      : `${formatted} ${symbol}`;
  }

  function dateBr(dateStr) {
    if (!dateStr) return "-";
    const d = new Date(dateStr);
    if (isNaN(d)) return "-";
    const dd = String(d.getDate()).padStart(2, "0");
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    return `${dd}/${mm}/${d.getFullYear()}`;
  }

  function calcItemTotal(it) {
    const base = it.unit_price * it.quantity;
    const discount = base * ((it.discount || 0) / 100);
    const tax = (base - discount) * ((it.tax || 0) / 100);
    return { base, discount, tax, total: base - discount + tax };
  }

  async function imageUrlToDataURL(url) {
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`Falha ao carregar imagem (${res.status}).`);
    }
    const blob = await res.blob();
    if (!blob.size) {
      throw new Error("A imagem recebida está vazia.");
    }
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  }

  function gerarQrCodeDataURL(text, size = 200) {
    return new Promise((resolve, reject) => {
      if (typeof QRCode === "undefined") {
        reject(
          new Error("Biblioteca qrcodejs não carregada (ver topo do arquivo)."),
        );
        return;
      }
      const div = document.createElement("div");
      div.style.display = "none";
      document.body.appendChild(div);
      new QRCode(div, {
        text,
        width: size,
        height: size,
        correctLevel: QRCode.CorrectLevel.L,
      });
      setTimeout(() => {
        const el = div.querySelector("canvas") || div.querySelector("img");
        const dataUrl =
          el.tagName === "CANVAS" ? el.toDataURL("image/png") : el.src;
        document.body.removeChild(div);
        resolve(dataUrl);
      }, 50);
    });
  }

  /** Carrega uma dataURL como HTMLImageElement (para saber a proporção real). */
  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = reject;
      img.src = src;
    });
  }

  /** Ajusta um retângulo maxW x maxH mantendo a proporção original da imagem. */
  function fitBox(imgW, imgH, maxW, maxH) {
    const ratio = Math.min(maxW / imgW, maxH / imgH);
    return { w: imgW * ratio, h: imgH * ratio };
  }

  function getImageFormat(dataUrl) {
    const match = /^data:image\/(\w+);/.exec(dataUrl || "");
    if (!match) return "PNG";
    const ext = match[1].toUpperCase();
    return ext === "JPG" ? "JPEG" : ext;
  }

  /**
   * 🔧 Remove tags HTML e decodifica entidades comuns (&nbsp;, &amp;, etc.)
   * Corrige itens vindos com HTML bruto, ex: "<p>venda de telemoveis</p>"
   * ou "Contabilidade&nbsp; - Avença Mensal&nbsp;".
   */
  function stripHtml(str) {
    if (!str) return "";
    return String(str)
      .replace(/<[^>]*>/g, " ")
      .replace(/&nbsp;/gi, " ")
      .replace(/&amp;/gi, "&")
      .replace(/&lt;/gi, "<")
      .replace(/&gt;/gi, ">")
      .replace(/&quot;/gi, '"')
      .replace(/&#39;/gi, "'")
      .replace(/\s+/g, " ")
      .trim();
  }

  /**
   * 🔧 Desenha texto respeitando uma largura máxima: se o texto (ex: um valor
   * monetário grande) for mais largo que o espaço disponível na coluna, a
   * fonte é reduzida progressivamente até caber — evita qualquer sobreposição
   * com as colunas vizinhas, independentemente da magnitude do valor.
   */
  function drawFittedText(
    doc,
    text,
    x,
    y,
    maxWidth,
    align = "left",
    baseSize = 8,
  ) {
    let size = baseSize;
    doc.setFontSize(size);
    while (doc.getTextWidth(text) > maxWidth && size > 6) {
      size -= 0.5;
      doc.setFontSize(size);
    }
    doc.text(text, x, y, { align });
    doc.setFontSize(baseSize);
  }

  // ---------------------------------------------------------------------------
  // Adaptador: converte o JSON "achatado" da sua API para o formato esperado
  // ---------------------------------------------------------------------------

  async function prepareInvoiceData(api, options = {}) {
    // reproduz literalmente a concatenação do PHP original:
    // "{endereco}, {cidade} - {país}" — mesmo que cidade/país venham vazios
    const juntarEndereco = (endereco, cidade, pais) =>
      `${endereco || ""}, ${cidade || ""} - ${pais || ""}`;

    const issueDate = new Date(api.issue_date);
    const dueDate = new Date(issueDate);
    dueDate.setDate(dueDate.getDate() + (Number(api.due_date) || 0));

    const invoiceData = {
      document_type: api.document_type,
      reference: api.reference,
      issue_date: api.issue_date,
      due_date: dueDate.toISOString().slice(0, 10),
      observation: api.observation,

      company: {
        name: api.company_name,
        address: juntarEndereco(
          api.company_address,
          api.company_city,
          api.company_country,
        ),
        phone: api.company_phone,
        email: api.company_email,
        website: api.website,
        registration_number: api.registration_number,
        logoImage: null,
      },

      client: {
        name: api.client_name,
        contributor: api.client_contributor,
        address: juntarEndereco(
          api.client_address,
          api.client_city,
          api.client_country,
        ),
      },

      items: api.items,

      totals: {
        total_sum: api.total_sum,
        total_discount: api.total_discount,
        total_tax: api.total_tax,
        retention_value: api.retention_value,
        final_total: api.final_total,
      },

      moneySymbol: api.symbol,
      moneyPos: api.position,
      vat_regime: api.vat_regime,
      iban: api.bank_details || "-",
      qrImage: null,
    };

    if (api.logo_url && options.logoBaseUrl) {
      try {
        const logoFile = String(api.logo_url).split("/").pop();
        const logoUrl = new URL(
          encodeURIComponent(logoFile),
          options.logoBaseUrl,
        ).toString();

        invoiceData.company.logoImage = await imageUrlToDataURL(logoUrl);
      } catch (e) {
        console.warn("Não foi possível carregar o logo:", e);
      }
    }

    if (api.id && options.qrBaseUrl) {
      try {
        invoiceData.qrImage = await gerarQrCodeDataURL(
          options.qrBaseUrl + api.id,
        );
      } catch (e) {
        console.warn("Não foi possível gerar o QR code:", e);
      }
    }

    return invoiceData;
  }

  // ---------------------------------------------------------------------------
  // Desenho — cabeçalho (empresa + logo)
  // ---------------------------------------------------------------------------

  function drawCompanyHeader(doc, company, logoImg) {
    let y = MARGIN_TOP + 12;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(13);
    doc.setTextColor(...BLACK);
    doc.text((company.name || "").toUpperCase(), MARGIN_LEFT, y);
    y += 15;

    doc.setFont("helvetica", "normal");
    doc.setFontSize(8.5);
    const enderecoLinhas = doc.splitTextToSize(
      String(company.address || ""),
      260,
    );
    enderecoLinhas.forEach((linha) => {
      doc.text(linha, MARGIN_LEFT, y);
      y += 10;
    });
    y += 2;

    [
      `Tel: ${company.phone || "-"}`,
      `E-mail: ${company.email || "-"}`,
      `Website: ${company.website || "-"}`,
      `Contribuinte: ${company.registration_number || "-"}`,
    ].forEach((linha) => {
      doc.text(linha, MARGIN_LEFT, y);
      y += 10;
    });

    // logo / imagem à direita
    let imgBottom = MARGIN_TOP;
    if (logoImg) {
      const box = 105;
      const { w, h } = fitBox(
        logoImg.naturalWidth,
        logoImg.naturalHeight,
        box,
        box,
      );
      const x = CONTENT_RIGHT - w;
      doc.addImage(logoImg, getImageFormat(logoImg.src), x, MARGIN_TOP, w, h);
      imgBottom = MARGIN_TOP + h;
    }

    return Math.max(y, imgBottom) + 28; // espaço antes do rótulo de via
  }

  // ---------------------------------------------------------------------------
  // Desenho — meta (Via / Título / Cliente / Datas)
  // ---------------------------------------------------------------------------

  function drawMeta(doc, invoiceData, y, viaLabel = "Original") {
    const {
      document_type,
      reference,
      issue_date,
      due_date,
      observation,
      client,
    } = invoiceData;

    doc.setFont("helvetica", "normal");
    doc.setFontSize(9);
    doc.setTextColor(...BLACK);
    // 🔧 antes era fixo "Original" — agora reflete a via atual (Original/Duplicado)
    doc.text(viaLabel, MARGIN_LEFT, y);
    y += 17;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(14);
    const docTitle = document_type === "PF" ? "Proforma" : "Factura";
    doc.text(`${docTitle} n.º ${reference || ""}`, MARGIN_LEFT, y);
    y += 20;

    const leftLabelX = MARGIN_LEFT;
    const leftValueX = MARGIN_LEFT + 72;
    const rightLabelX = MARGIN_LEFT + 260;
    const rightValueX = rightLabelX + 95;
    const rowGap = 13;

    doc.setFontSize(9);

    // ----- coluna esquerda -----
    let ly = y;
    doc.setFont("helvetica", "normal");
    doc.text("Cliente:", leftLabelX, ly);
    doc.setFont("helvetica", "bold");
    doc.text((client.name || "").toUpperCase(), leftValueX, ly);
    ly += rowGap;

    doc.setFont("helvetica", "normal");
    doc.text("Contribuinte:", leftLabelX, ly);
    doc.text(client.contributor || "-", leftValueX, ly);
    ly += rowGap;

    doc.text("Endereço:", leftLabelX, ly);
    const enderecoWidth = rightLabelX - leftValueX - 10;
    const enderecoLinhas = doc.splitTextToSize(
      client.address || "-",
      enderecoWidth,
    );
    doc.text(enderecoLinhas, leftValueX, ly);
    const leftBottom = ly + enderecoLinhas.length * 11;

    // ----- coluna direita -----
    let ry = y;
    doc.text("Data de emissão:", rightLabelX, ry);
    doc.text(dateBr(issue_date), rightValueX, ry);
    ry += rowGap;

    doc.text("Vencimento:", rightLabelX, ry);
    doc.text(dateBr(due_date), rightValueX, ry);
    ry += rowGap;

    doc.text("Observações:", rightLabelX, ry);
    const obsWidth = CONTENT_RIGHT - rightValueX;
    const obsLinhas = doc.splitTextToSize(observation || "-", obsWidth);
    doc.text(obsLinhas, rightValueX, ry);
    const rightBottom = ry + obsLinhas.length * 11;

    return Math.max(leftBottom, rightBottom) + 14;
  }

  // ---------------------------------------------------------------------------
  // Desenho — tabela de itens (com paginação manual)
  // ---------------------------------------------------------------------------

  function drawItemsTableHeader(doc, y) {
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8);
    doc.setTextColor(...GRAY_LABEL);
    doc.text("Código", COL_CODE_X, y);
    doc.text("Descrição", 100, y);
    doc.text("Preço Uni.", COL_PRECO_RIGHT_X, y, { align: "right" });
    doc.text("Qtd.", COL_QTD_CENTER_X, y, { align: "center" });
    doc.text("Taxa/IVA", COL_TAXA_CENTER_X, y, { align: "center" });
    doc.text("Desc.", COL_DESCPCT_CENTER_X, y, { align: "center" });
    doc.text("Total", COL_TOTAL_RIGHT_X, y, { align: "right" });
    doc.setTextColor(...BLACK);
    return y + 14;
  }

  function drawTopBorder(doc, y) {
    doc.setDrawColor(...GRAY);
    doc.setLineWidth(1.6);
    doc.line(MARGIN_LEFT, y, CONTENT_RIGHT, y);
  }

  function drawItemsTable(doc, invoiceData, y) {
    const { items, moneySymbol = "Kz", moneyPos = "right" } = invoiceData;
    const bottomLimit = PAGE_HEIGHT - MARGIN_BOTTOM - 90; // reserva espaço p/ sumário

    drawTopBorder(doc, y);
    y += 10;
    y = drawItemsTableHeader(doc, y);
    y += 5;

    items.forEach((it) => {
      // 🔧 limpa HTML/entidades antes de calcular a quebra de linha
      const nomeLimpo = stripHtml(it.name || it.description || "");
      const codigoLimpo = stripHtml(it.code || "");

      const descLinhas = doc.splitTextToSize(nomeLimpo, COL_DESC_WIDTH);
      const rowHeight = Math.max(14, descLinhas.length * 10 + 4);

      if (y + rowHeight > bottomLimit) {
        // fecha o bloco atual e continua numa nova página
        doc.setDrawColor(...GRAY);
        doc.setLineWidth(1.3);
        doc.line(MARGIN_LEFT, y, CONTENT_RIGHT, y);

        doc.addPage();
        y = MARGIN_TOP + 20;
        drawTopBorder(doc, y);
        y += 10;
        y = drawItemsTableHeader(doc, y);
        y += 5;
      }

      const { total } = calcItemTotal(it);
      doc.setFont("helvetica", "normal");
      doc.setFontSize(8);
      doc.setTextColor(...BLACK);

      // 🔧 código e valores monetários usam drawFittedText: encolhem a fonte
      // automaticamente se não couberem na largura da coluna, em vez de
      // sobrepor a coluna vizinha (era o bug visto nos preços grandes)
      drawFittedText(
        doc,
        codigoLimpo,
        COL_CODE_X,
        y,
        COL_CODE_MAX_WIDTH,
        "left",
      );
      doc.text(descLinhas, COL_DESC_X, y);
      drawFittedText(
        doc,
        formatCurrency(it.unit_price, moneySymbol, moneyPos),
        COL_PRECO_RIGHT_X,
        y,
        COL_PRECO_MAX_WIDTH,
        "right",
      );
      doc.text(String(it.quantity), COL_QTD_CENTER_X, y, { align: "center" });
      doc.text(`${it.tax || 0}%`, COL_TAXA_CENTER_X, y, { align: "center" });
      doc.text(`${it.discount || 0}%`, COL_DESCPCT_CENTER_X, y, {
        align: "center",
      });
      drawFittedText(
        doc,
        formatCurrency(total, moneySymbol, moneyPos),
        COL_TOTAL_RIGHT_X,
        y,
        COL_TOTAL_MAX_WIDTH,
        "right",
      );

      y += rowHeight;
    });

    // barra grossa final do bloco de itens
    doc.setDrawColor(...GRAY);
    doc.setLineWidth(1.5);
    doc.line(MARGIN_LEFT, y, CONTENT_RIGHT, y);
    y += 18;

    return y;
  }

  // ---------------------------------------------------------------------------
  // Desenho — Dados fiscais/bancários + Sumário
  // ---------------------------------------------------------------------------

  function drawTotalsSection(doc, invoiceData, y) {
    const {
      vat_regime,
      iban,
      moneySymbol = "Kz",
      moneyPos = "right",
    } = invoiceData;

    const calcTotals = () => {
      let total_sum = 0,
        total_discount = 0,
        total_tax = 0;
      invoiceData.items.forEach((it) => {
        const { base, discount, tax } = calcItemTotal(it);
        total_sum += base;
        total_discount += discount;
        total_tax += tax;
      });
      return {
        total_sum,
        total_discount,
        total_tax,
        retention_value: 0,
        final_total: total_sum - total_discount + total_tax,
      };
    };
    const totals = invoiceData.totals || calcTotals();

    const leftX = MARGIN_LEFT;
    const leftWidth = 260;
    const rightX = MARGIN_LEFT + 300;
    const rightValueRightX = CONTENT_RIGHT;

    // ----- ESQUERDA: Dados fiscais e bancários -----
    let ly = y;
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8);
    doc.setTextColor(...GRAY_LABEL);
    doc.text("Dados fiscais e bancários", leftX, ly);
    ly += 14;

    doc.setFont("helvetica", "normal");
    doc.setTextColor(...BLACK);
    doc.text(
      `Regime de IVA: ${vat_regime === "simplificado" ? "Regime Simplificado" : "Regime Geral"}`,
      leftX,
      ly,
    );
    ly += 12;

    const bensLinhas = doc.splitTextToSize(
      "Bens e serviços: Os bens e serviços foram colocados à disposição do adquirente na data do documento.",
      leftWidth,
    );
    doc.text(bensLinhas, leftX, ly);
    ly += bensLinhas.length * 10 + 2;

    doc.text(`Dados bancários: ${iban || "-"}`, leftX, ly);
    ly += 8;

    doc.setDrawColor(...GRAY);
    doc.setLineWidth(1);
    doc.line(leftX, ly, leftX + leftWidth, ly);
    ly += 4;

    // ----- DIREITA: Sumário -----
    let ry = y;
    doc.setFont("helvetica", "bold");
    doc.setFontSize(8);
    doc.setTextColor(...GRAY_LABEL);
    doc.text("Sumário", rightX, ry);
    ry += 14;

    doc.setFont("helvetica", "normal");
    doc.setTextColor(...BLACK);

    const rows = [
      [
        "Total ilíquido:",
        formatCurrency(totals.total_sum, moneySymbol, moneyPos),
      ],
      [
        "Desconto:",
        formatCurrency(totals.total_discount, moneySymbol, moneyPos),
      ],
      [
        "Sem Imposto/IVA c Desc.:",
        formatCurrency(
          totals.total_sum - totals.total_discount,
          moneySymbol,
          moneyPos,
        ),
      ],
      ["Imposto/IVA:", formatCurrency(totals.total_tax, moneySymbol, moneyPos)],
      [
        "Retenção:",
        formatCurrency(totals.retention_value, moneySymbol, moneyPos),
      ],
    ];
    rows.forEach(([label, value]) => {
      doc.text(label, rightX, ry);
      doc.text(value, rightValueRightX, ry, { align: "right" });
      ry += 12;
    });

    // barra grossa acima do Total
    doc.setDrawColor(...GRAY);
    doc.setLineWidth(1.5);
    doc.line(rightX, ry, rightValueRightX, ry);
    ry += 15;

    doc.setFont("helvetica", "bold");
    doc.setFontSize(11);
    doc.text("Total:", rightX, ry);
    doc.text(
      formatCurrency(totals.final_total, moneySymbol, moneyPos),
      rightValueRightX,
      ry,
      { align: "right" },
    );

    return Math.max(ly, ry) + 20;
  }

  // ---------------------------------------------------------------------------
  // Desenho — rodapé (aplicado em todas as páginas, no final)
  // ---------------------------------------------------------------------------

  /**
   * 🔧 Corrigido: antes, o número da página só era desenhado quando NÃO havia
   * QR code (o QR "escondia" a numeração). Agora ambos aparecem sempre,
   * em posições que não se sobrepõem. `page`/`totalPages` passados aqui já
   * são relativos à VIA atual (ver gerarPdfFatura), não ao documento inteiro.
   */
  function drawFooter(doc, qrImg, qrDataUrl, page, totalPages) {
    const y = PAGE_HEIGHT - 32;

    doc.setFont("helvetica", "normal");
    doc.setFontSize(7);
    doc.setTextColor(...GRAY_FOOTER);
    doc.text("Powered By BXpert", MARGIN_LEFT, y);

    // número de página sempre visível, centralizado
    doc.text(`Página ${page} / ${totalPages}`, PAGE_WIDTH / 2, y, {
      align: "center",
    });

    if (qrImg) {
      const size = 58;
      const x = CONTENT_RIGHT - size;
      doc.addImage(
        qrImg,
        getImageFormat(qrDataUrl),
        x,
        y - size + 12,
        size,
        size,
      );
    }
    doc.setTextColor(...BLACK);
  }

  // ---------------------------------------------------------------------------
  // Uma via completa da fatura
  // ---------------------------------------------------------------------------

  function drawInvoicePage(doc, invoiceData, assets, viaLabel = "Original") {
    let y = drawCompanyHeader(doc, invoiceData.company, assets.logoImg);
    y = drawMeta(doc, invoiceData, y, viaLabel);
    y = drawItemsTable(doc, invoiceData, y);
    drawTotalsSection(doc, invoiceData, y);
  }

  // ---------------------------------------------------------------------------
  // Função principal
  // ---------------------------------------------------------------------------

  // Pede o PDF à API e devolve o Blob. Não descarrega nada: a pré-visualização
  // decide se mostra, imprime ou descarrega o mesmo ficheiro.
  async function fetchInvoicePdfBlob(invoiceData, copies = 2) {
    const payload = {
      ...invoiceData,
      copies: Number(copies) || 1,
      document_url: new URL(
        `invoices/ajax/invoice_public.php?id=${encodeURIComponent(invoiceData.id)}`,
        window.location.href,
      ).toString(),
    };

    const hostname = window.location.hostname;
    const apiBaseUrl =
      hostname === "api-crm.bxpert.co.ao" ||
      hostname === "www.api-crm.bxpert.co.ao"
        ? "https://api-crm.bxpert.co.ao"
        : "http://localhost:3004";

    let response;
    try {
      response = await fetch(`${apiBaseUrl}/api/invoices/pdf`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/pdf",
        },
        body: JSON.stringify(payload),
      });
    } catch (_) {
      throw new Error(
        `Não foi possível conectar à API de PDF em ${apiBaseUrl}. Verifique se o bxintelligence-api-rest está em execução.`,
      );
    }

    const contentType = response.headers.get("content-type") || "";
    const isPdf =
      contentType.includes("application/pdf") ||
      contentType.includes("application/octet-stream");

    if (!response.ok) {
      let errorMessage = `Erro HTTP ${response.status}`;

      try {
        const errorData = await response.clone().json();
        if (errorData?.message) errorMessage = errorData.message;
        if (errorData?.error) errorMessage += `: ${errorData.error}`;
      } catch (_) {
        const text = await response.text();
        if (text) errorMessage = text.slice(0, 240);
      }

      throw new Error(errorMessage);
    }

    if (!isPdf) {
      const fallbackText = await response.text();
      throw new Error(
        `Resposta inesperada do servidor PDF: ${fallbackText.slice(0, 240)}`,
      );
    }

    const blob = await response.blob();

    if (!blob || blob.size === 0) {
      throw new Error("O servidor devolveu um PDF vazio.");
    }

    return blob;
  }

  function createInvoicePageClone(element, items, viaLabel) {
    const clone = element.cloneNode(true);
    const itemsGrid = clone.querySelector(".items-grid");
    const originalRows = Array.from(
      itemsGrid?.querySelectorAll(".items-row:not(.items-head)") || [],
    );

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
    clone.dataset.itemCount = String(originalRows.length);
    return clone;
  }

  function splitInvoiceItems(element, items, viaLabel) {
    const pages = [];
    const maxHeight = 1080;
    let cursor = 0;

    while (cursor < items.length || !pages.length) {
      let end = cursor;
      let accepted = null;

      while (end < items.length) {
        const candidate = createInvoicePageClone(
          element,
          items.slice(cursor, end + 1),
          viaLabel,
        );
        document.body.appendChild(candidate);
        const height = candidate.getBoundingClientRect().height;
        candidate.remove();

        if (height > maxHeight && end > cursor) break;
        accepted = candidate;
        end += 1;
        if (height > maxHeight) break;
      }

      if (!accepted) {
        accepted = createInvoicePageClone(
          element,
          items.slice(cursor, cursor + 1),
          viaLabel,
        );
        end = cursor + 1;
      }

      pages.push(accepted);
      cursor = end;
    }

    return pages;
  }

  async function downloadRenderedInvoicePdf(element, filename) {
    const JsPDF = window.jspdf?.jsPDF;
    if (!element || typeof html2canvas !== "function" || !JsPDF) {
      throw new Error("O gerador visual de PDF não está disponível.");
    }

    const itemsGrid = element.querySelector(".items-grid");
    const items = Array.from(
      itemsGrid?.querySelectorAll(".items-row:not(.items-head)") || [],
    );
    const staging = document.createElement("div");
    staging.style.position = "fixed";
    staging.style.left = "0";
    staging.style.top = "0";
    staging.style.zIndex = "2147483647";
    staging.style.pointerEvents = "none";
    staging.style.width = "190mm";
    staging.style.background = "#fff";
    document.body.appendChild(staging);

    const pages = [
      ...splitInvoiceItems(element, items, "Original"),
      ...splitInvoiceItems(element, items, "Duplicado"),
    ];
    const pdf = new JsPDF({
      unit: "mm",
      format: "a4",
      orientation: "portrait",
    });
    try {
      for (const [index, page] of pages.entries()) {
        staging.replaceChildren(page);

        const images = Array.from(page.querySelectorAll("img"));
        await Promise.all(
          images.map((image) => {
            if (image.complete) return Promise.resolve();
            return new Promise((resolve) => {
              image.addEventListener("load", resolve, { once: true });
              image.addEventListener("error", resolve, { once: true });
            });
          }),
        );

        const canvas = await html2canvas(page, {
          scale: 2,
          useCORS: true,
          backgroundColor: "#ffffff",
          logging: false,
          windowWidth: page.scrollWidth,
          windowHeight: page.scrollHeight,
        });

        if (index > 0) pdf.addPage();

        const pageWidth = 210;
        const pageHeight = 297;
        const margin = 10;
        const width = pageWidth - margin * 2;
        const height = Math.min(
          pageHeight - margin * 2,
          (canvas.height * width) / canvas.width,
        );
        const x = margin;
        const y = margin;

        pdf.addImage(
          canvas.toDataURL("image/jpeg", 1),
          "JPEG",
          x,
          y,
          width,
          height,
          undefined,
          "FAST",
        );
      }

      const blob = pdf.output("blob");
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } finally {
      staging.remove();
    }
  }

  // ---------------------------------------------------------------------------
  // TALÃO TÉRMICO (80mm) — HTML usado na pré-visualização e impressão
  // ---------------------------------------------------------------------------
  // Gera um recibo simplificado (tipo POS) a partir dos mesmos dados já
  // carregados em `currentInvoice`, formatado para papel de rolo 80mm, e
  // imprime usando o diálogo nativo do browser (não depende de API externa).
  // ---------------------------------------------------------------------------

  function escapeHtml(value) {
    return String(value ?? "").replace(
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
  }

  function buildThermalReceiptHtml(inv) {
    const symbol = inv.symbol || inv.company_symbol || "Kz";
    const position = inv.position || inv.company_position || "right";
    const items = Array.isArray(inv.items) ? inv.items : [];

    const rowsHtml = items
      .map((it) => {
        const { total } = calcItemTotal(it);
        const qty = Number(it.quantity) || 0;
        const price = Number(it.unit_price) || 0;
        return `
        <div class="t-item-name">${escapeHtml(it.name || it.code || "-")}</div>
        <div class="t-item-line">
          <span>${qty} x ${formatCurrency(price, symbol, position)}</span>
          <span>${formatCurrency(total, symbol, position)}</span>
        </div>`;
      })
      .join("");

    const docLabel = inv.document_type === "PF" ? "Proforma" : "Factura";
    const docNumber =
      inv.reference ||
      inv.codigo ||
      (inv.series ? `${inv.series}/${inv.id}` : inv.id);

    return `<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Talão ${escapeHtml(String(docNumber))}</title>
<style>
  @page { size: 80mm auto; margin: 0; }
  * { box-sizing: border-box; }
  html, body {
    margin: 0;
    padding: 0;
  }
  body {
    width: 72mm;
    margin: 0 auto;
    padding: 3mm 3mm 8mm;
    font-family: "Courier New", Courier, monospace;
    font-size: 11px;
    line-height: 1.35;
    color: #000;
  }
  .t-center { text-align: center; }
  .t-bold { font-weight: bold; }
  .t-line { border-top: 1px dashed #000; margin: 5px 0; }
  .t-row { display: flex; justify-content: space-between; }
  .t-item-name { margin-top: 5px; }
  .t-item-line { display: flex; justify-content: space-between; font-size: 10.5px; }
  .t-total-row { display: flex; justify-content: space-between; font-size: 13px; margin-top: 2px; }
  .t-footer { text-align: center; font-size: 10px; margin-top: 10px; }
  div, p { margin: 0; }
</style>
</head>
<body>
  <div class="t-center t-bold" style="font-size:13px;">${escapeHtml(inv.company_name || "")}</div>
  ${inv.company_address ? `<div class="t-center">${escapeHtml(inv.company_address)}</div>` : ""}
  ${inv.company_city ? `<div class="t-center">${escapeHtml(inv.company_city)}</div>` : ""}
  ${inv.company_phone ? `<div class="t-center">Tel: ${escapeHtml(inv.company_phone)}</div>` : ""}
  ${inv.registration_number ? `<div class="t-center">NIF: ${escapeHtml(inv.registration_number)}</div>` : ""}
  <div class="t-line"></div>
  <div class="t-center t-bold">${escapeHtml(docLabel)} Simplificada</div>
  <div class="t-center">Nº ${escapeHtml(String(docNumber))}</div>
  <div class="t-center">${dateBr(inv.issue_date)}</div>
  <div class="t-line"></div>
  <div>Cliente: ${escapeHtml(inv.client_name || "Consumidor Final")}</div>
  ${inv.client_contributor ? `<div>Contribuinte: ${escapeHtml(inv.client_contributor)}</div>` : ""}
  <div class="t-line"></div>
  ${rowsHtml}
  <div class="t-line"></div>
  <div class="t-row"><span>Subtotal:</span><span>${formatCurrency(inv.total_sum, symbol, position)}</span></div>
  ${Number(inv.total_discount) > 0 ? `<div class="t-row"><span>Desconto:</span><span>${formatCurrency(inv.total_discount, symbol, position)}</span></div>` : ""}
  <div class="t-row"><span>IVA:</span><span>${formatCurrency(inv.total_tax, symbol, position)}</span></div>
  ${Number(inv.retention_value) > 0 ? `<div class="t-row"><span>Retenção:</span><span>${formatCurrency(inv.retention_value, symbol, position)}</span></div>` : ""}
  <div class="t-line"></div>
  <div class="t-total-row t-bold"><span>TOTAL:</span><span>${formatCurrency(inv.final_total, symbol, position)}</span></div>
  <div class="t-line"></div>
  <div class="t-footer">
    Obrigado pela preferência!<br>
    Documento processado por computador
  </div>
</body>
</html>`;
  }

  // ---------------------------------------------------------------------------
  // PRÉ-VISUALIZAR E IMPRIMIR
  // O botão "Imprimir / Baixar" abre o modal #modalPrintPreview: o utilizador
  // escolhe o formato (A4 em PDF ou talão de 80mm), vê o documento e só depois
  // imprime ou descarrega.
  // ---------------------------------------------------------------------------
  const ppModalEl = document.getElementById("modalPrintPreview");

  if (ppModalEl) {
    const ppFrame = document.getElementById("ppFrame");
    const ppLoading = document.getElementById("ppLoading");
    const ppError = document.getElementById("ppError");
    const ppErrorText = document.getElementById("ppErrorText");
    const ppCopiesBox = document.getElementById("ppCopiesBox");
    const ppPrintBtn = document.getElementById("ppPrint");
    const ppDownloadBtn = document.getElementById("ppDownload");

    // Estilos só para o ecrã: dão fundo cinzento e "papel" ao talão na pré-visualização.
    // Ficam dentro de @media screen, por isso não afectam a impressão.
    const PP_THERMAL_SCREEN_CSS =
      "<style>@media screen{html{background:#d1d5db}" +
      "body{background:#fff;margin:16px auto;box-shadow:0 2px 10px rgba(0,0,0,.25)}}</style>";

    const pp = {
      format: "a4", // "a4" | "thermal"
      copies: 2,
      cache: new Map(), // nº de vias -> { url, blob }
      token: 0, // descarta respostas de pedidos antigos
    };

    function ppSetState(state, message) {
      ppLoading.classList.toggle("d-none", state !== "loading");
      ppError.classList.toggle("d-none", state !== "error");
      if (state === "error")
        ppErrorText.textContent = message || "Erro desconhecido.";

      const ready = state === "ready";
      ppPrintBtn.disabled = !ready;
      ppDownloadBtn.disabled = !ready;
    }

    function ppSyncControls() {
      const isA4 = pp.format === "a4";
      ppCopiesBox.classList.toggle("d-none", !isA4);
      ppDownloadBtn.classList.toggle("d-none", !isA4);
    }

    function ppFilename() {
      const base = String(currentInvoice?.reference || "fatura").replace(
        /[\\/:*?"<>|]+/g,
        "-",
      );
      return `${base}.pdf`;
    }

    function ppReleaseCache() {
      pp.cache.forEach((entry) => URL.revokeObjectURL(entry.url));
      pp.cache.clear();
    }

    async function ppRender() {
      const token = ++pp.token;

      if (!currentInvoice) {
        ppSetState("error", "A fatura ainda está a carregar.");
        return;
      }

      ppSetState("loading");

      try {
        ppFrame.onload = () => {
          if (token === pp.token) ppSetState("ready");
        };

        if (pp.format === "thermal") {
          const html = buildThermalReceiptHtml(currentInvoice).replace(
            "</head>",
            PP_THERMAL_SCREEN_CSS + "</head>",
          );
          ppFrame.srcdoc = html;
          return;
        }

        let entry = pp.cache.get(pp.copies);

        if (!entry) {
          const blob = await fetchInvoicePdfBlob(currentInvoice, pp.copies);
          if (token !== pp.token) return; // o utilizador já mudou de opção
          entry = { blob, url: URL.createObjectURL(blob) };
          pp.cache.set(pp.copies, entry);
        }

        ppFrame.removeAttribute("srcdoc");
        ppFrame.src = entry.url;
      } catch (error) {
        console.error("Erro na pré-visualização:", error);
        if (token === pp.token) ppSetState("error", error.message);
      }
    }

    // Abre sempre em A4 (o formato habitual) e limpa tudo ao fechar
    ppModalEl.addEventListener("shown.bs.modal", () => {
      pp.format =
        ppModalEl.querySelector('input[name="pp_format"]:checked')?.value ||
        "a4";
      pp.copies = Number(document.getElementById("ppCopies").value) || 2;
      ppSyncControls();
      ppRender();
    });

    ppModalEl.addEventListener("hidden.bs.modal", () => {
      pp.token++;
      ppFrame.onload = null;
      ppFrame.removeAttribute("srcdoc");
      ppFrame.src = "about:blank";
      ppReleaseCache();
    });

    ppModalEl.querySelectorAll('input[name="pp_format"]').forEach((radio) => {
      radio.addEventListener("change", () => {
        pp.format = radio.value;
        ppSyncControls();
        ppRender();
      });
    });

    document.getElementById("ppCopies").addEventListener("change", (e) => {
      pp.copies = Number(e.target.value) || 2;
      ppRender();
    });

    document.getElementById("ppRetry").addEventListener("click", ppRender);

    ppPrintBtn.addEventListener("click", () => {
      try {
        ppFrame.contentWindow.focus();
        ppFrame.contentWindow.print();
      } catch (error) {
        // Alguns browsers não deixam imprimir o visualizador de PDF dentro da página
        const entry = pp.cache.get(pp.copies);
        if (pp.format === "a4" && entry) {
          window.open(entry.url, "_blank");
        } else {
          console.error("Erro ao imprimir:", error);
          alert("Não foi possível abrir a impressão.\n\n" + error.message);
        }
      }
    });

    ppDownloadBtn.addEventListener("click", () => {
      const entry = pp.cache.get(pp.copies);
      if (!entry) return;

      const link = document.createElement("a");
      link.href = entry.url;
      link.download = ppFilename();
      document.body.appendChild(link);
      link.click();
      link.remove();
    });
  }

  // ---------- Nota de Crédito ----------
  $("#btnNotaCredito").on("click", async function () {
    try {
      // Validação da fatura
      if (!currentInvoice?.id) {
        return Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Fatura ainda não carregada.",
        });
      }

      // Verifica se já existe nota de crédito
      const verifyResponse = await $.ajax({
        url: "invoices/ajax/credit_notes.php",
        method: "GET",
        dataType: "json",
        data: {
          invoice_id: currentInvoice.id,
        },
      });

      // Se já existir nota de crédito
      if (verifyResponse?.data?.id) {
        return (window.location.href = `credit_notes/ajax/generate_pdf.php?id=${verifyResponse.data.id}`);
      }

      // Pergunta antes de emitir
      const result = await Swal.fire({
        title: "Emitir Nota de Crédito?",
        text: "A Nota de Crédito será associada a esta fatura.",
        input: "textarea",
        inputLabel: "Motivo (opcional)",
        inputPlaceholder: "Descreva o motivo da correção/anulação…",
        showCancelButton: true,
        confirmButtonText: "Emitir",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#3085d6",
      });

      if (!result.isConfirmed) return;

      // Criar nota de crédito
      const res = await $.ajax({
        url: "invoices/ajax/create_credit_note.php",
        method: "POST",
        dataType: "json",
        data: {
          invoice_id: currentInvoice.id,
          reason: result.value || "",
        },
      });

      // Sucesso
      if (res?.success && res?.credit_note_id) {
        window.location.href = `credit_notes/ajax/generate_pdf.php?id=${res.credit_note_id}`;
      } else {
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: res?.error || "Não foi possível emitir a Nota de Crédito.",
        });
      }
    } catch (error) {
      console.error("Erro ao processar Nota de Crédito:", error);

      Swal.fire({
        icon: "error",
        title: "Erro",
        text: error?.responseText || "Falha ao processar a Nota de Crédito.",
      });
    }
  });

  // ---------- Nota de Débito ----------
  $("#btnNotaDebito").on("click", async function () {
    try {
      if (!currentInvoice?.id) {
        return Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Fatura ainda não carregada.",
        });
      }

      // Notas de débito já emitidas para esta fatura
      const verify = await $.ajax({
        url: "invoices/ajax/debit_notes.php",
        method: "GET",
        dataType: "json",
        data: { invoice_id: currentInvoice.id },
      });

      const existing = verify?.data || [];

      if (existing.length) {
        const last = existing[0];

        const choice = await Swal.fire({
          title: "Nota de Débito",
          text: `Já existem ${existing.length} nota(s) de débito para esta fatura.`,
          showDenyButton: true,
          showCancelButton: true,
          confirmButtonText: "Emitir nova",
          denyButtonText: `Ver última (${last.serie} ${last.number})`,
          cancelButtonText: "Cancelar",
        });

        if (choice.isDenied) {
          return window.open(`invoices/debit_note_pdf.php?id=${last.id}`, "_blank");
        }
        if (!choice.isConfirmed) return;
      }

      const fmt = (v) =>
        Number(v || 0).toLocaleString("pt-PT", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });

      // Formulário com linhas editáveis
      const form = await Swal.fire({
        title: "Emitir Nota de Débito",
        width: 780,
        html: `
          <textarea id="ndReason" class="swal2-textarea" style="margin:0 0 12px;width:100%" placeholder="Motivo (obrigatório) – ex.: juros de mora, portes, correção de preço"></textarea>
          <table class="table table-sm align-middle mb-2" style="font-size:.9rem">
            <thead>
              <tr>
                <th style="text-align:left">Descrição</th>
                <th style="width:80px">Qtd.</th>
                <th style="width:120px">Preço unit.</th>
                <th style="width:80px">IVA %</th>
                <th style="width:110px;text-align:right">Total</th>
                <th style="width:34px"></th>
              </tr>
            </thead>
            <tbody id="ndLines"></tbody>
          </table>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <button type="button" id="ndAddLine" class="swal2-styled" style="background:#6c757d;margin:0">+ Linha</button>
            <div style="text-align:right;font-size:.9rem">
              <div>Subtotal: <strong id="ndSub">0,00</strong></div>
              <div>IVA: <strong id="ndTax">0,00</strong></div>
              <div style="font-size:1.05rem">Total: <strong id="ndTotal">0,00</strong></div>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: "Emitir",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#3085d6",
        didOpen: () => {
          const body = document.getElementById("ndLines");

          const recalc = () => {
            let sub = 0;
            let tax = 0;
            body.querySelectorAll("tr").forEach((tr) => {
              const q = parseFloat(tr.querySelector(".nd-qty").value) || 0;
              const p = parseFloat(tr.querySelector(".nd-price").value) || 0;
              const t = parseFloat(tr.querySelector(".nd-tax").value) || 0;
              const base = Math.round(q * p * 100) / 100;
              const taxVal = Math.round(base * t) / 100;
              tr.querySelector(".nd-line-total").textContent = fmt(base);
              sub += base;
              tax += taxVal;
            });
            document.getElementById("ndSub").textContent = fmt(sub);
            document.getElementById("ndTax").textContent = fmt(tax);
            document.getElementById("ndTotal").textContent = fmt(sub + tax);
          };

          const addLine = () => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
              <td><input type="text" class="form-control form-control-sm nd-desc" maxlength="500" placeholder="Descrição"></td>
              <td><input type="number" class="form-control form-control-sm nd-qty" min="0" step="any" value="1"></td>
              <td><input type="number" class="form-control form-control-sm nd-price" min="0" step="any" value=""></td>
              <td><input type="number" class="form-control form-control-sm nd-tax" min="0" max="100" step="any" value="14"></td>
              <td class="nd-line-total" style="text-align:right">0,00</td>
              <td><button type="button" class="btn btn-sm btn-link text-danger nd-del" title="Remover">&times;</button></td>
            `;
            tr.addEventListener("input", recalc);
            tr.querySelector(".nd-del").addEventListener("click", () => {
              if (body.querySelectorAll("tr").length > 1) {
                tr.remove();
                recalc();
              }
            });
            body.appendChild(tr);
          };

          document.getElementById("ndAddLine").addEventListener("click", addLine);
          addLine();
        },
        preConfirm: () => {
          const reason = document.getElementById("ndReason").value.trim();
          if (!reason) {
            Swal.showValidationMessage("Indique o motivo da nota de débito.");
            return false;
          }

          const items = [];
          const rows = document.querySelectorAll("#ndLines tr");
          for (let i = 0; i < rows.length; i++) {
            const tr = rows[i];
            const description = tr.querySelector(".nd-desc").value.trim();
            const quantity = parseFloat(tr.querySelector(".nd-qty").value);
            const unit_price = parseFloat(tr.querySelector(".nd-price").value);
            const tax = parseFloat(tr.querySelector(".nd-tax").value);

            if (!description) {
              Swal.showValidationMessage(`Linha ${i + 1}: indique a descrição.`);
              return false;
            }
            if (!(quantity > 0)) {
              Swal.showValidationMessage(`Linha ${i + 1}: quantidade inválida.`);
              return false;
            }
            if (!(unit_price > 0)) {
              Swal.showValidationMessage(`Linha ${i + 1}: indique o preço unitário.`);
              return false;
            }
            if (isNaN(tax) || tax < 0 || tax > 100) {
              Swal.showValidationMessage(`Linha ${i + 1}: IVA deve estar entre 0 e 100.`);
              return false;
            }
            items.push({ description, quantity, unit_price, tax });
          }

          return { reason, items };
        },
      });

      if (!form.isConfirmed) return;

      const res = await $.ajax({
        url: "invoices/ajax/create_debit_note.php",
        method: "POST",
        dataType: "json",
        data: {
          invoice_id: currentInvoice.id,
          reason: form.value.reason,
          items: JSON.stringify(form.value.items),
        },
      });

      if (res?.success && res?.debit_note_id) {
        window.open(`invoices/debit_note_pdf.php?id=${res.debit_note_id}`, "_blank");
      } else {
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: res?.error || "Não foi possível emitir a Nota de Débito.",
        });
      }
    } catch (error) {
      console.error("Erro ao processar Nota de Débito:", error);

      let msg = "Falha ao processar a Nota de Débito.";
      try {
        msg = JSON.parse(error?.responseText)?.error || msg;
      } catch (_) {}

      Swal.fire({ icon: "error", title: "Erro", text: msg });
    }
  });

  // ---------- Nota de Entrega ----------
  $("#btnNotaEntrega").on("click", async function () {
    try {
      if (!currentInvoice?.id) {
        return Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Fatura ainda não carregada.",
        });
      }

      // Notas já emitidas + se ainda há itens por entregar
      const verify = await $.ajax({
        url: "invoices/ajax/delivery_notes.php",
        method: "GET",
        dataType: "json",
        data: { invoice_id: currentInvoice.id },
      });

      const existing = verify?.data || [];
      const hasPending = verify?.has_pending !== false;

      // Já existem notas: ver a última ou emitir nova (entrega do que falta)
      if (existing.length) {
        const last = existing[0];

        if (!hasPending) {
          return window.open(`invoices/delivery_note_pdf.php?id=${last.id}`, "_blank");
        }

        const choice = await Swal.fire({
          title: "Nota de Entrega",
          text: `Já existem ${existing.length} nota(s) para esta fatura. Ainda há itens por entregar.`,
          showDenyButton: true,
          showCancelButton: true,
          confirmButtonText: "Emitir nova (itens em falta)",
          denyButtonText: `Ver última (${last.serie} ${last.number})`,
          cancelButtonText: "Cancelar",
        });

        if (choice.isDenied) {
          return window.open(`invoices/delivery_note_pdf.php?id=${last.id}`, "_blank");
        }
        if (!choice.isConfirmed) return;
      }

      // Dados da nota
      const form = await Swal.fire({
        title: "Emitir Nota de Entrega?",
        html: `
          <input id="dnAddress" class="swal2-input" placeholder="Local de entrega (opcional – por defeito, morada do cliente)">
          <textarea id="dnNotes" class="swal2-textarea" placeholder="Observações (opcional)"></textarea>
        `,
        showCancelButton: true,
        confirmButtonText: "Emitir",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#3085d6",
        preConfirm: () => ({
          address: document.getElementById("dnAddress").value.trim(),
          notes: document.getElementById("dnNotes").value.trim(),
        }),
      });

      if (!form.isConfirmed) return;

      const res = await $.ajax({
        url: "invoices/ajax/create_delivery_note.php",
        method: "POST",
        dataType: "json",
        data: {
          invoice_id: currentInvoice.id,
          delivery_address: form.value.address,
          notes: form.value.notes,
        },
      });

      if (res?.success && res?.delivery_note_id) {
        window.open(`invoices/delivery_note_pdf.php?id=${res.delivery_note_id}`, "_blank");
      } else {
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: res?.error || "Não foi possível emitir a Nota de Entrega.",
        });
      }
    } catch (error) {
      console.error("Erro ao processar Nota de Entrega:", error);

      let msg = "Falha ao processar a Nota de Entrega.";
      try {
        msg = JSON.parse(error?.responseText)?.error || msg;
      } catch (_) {}

      Swal.fire({ icon: "error", title: "Erro", text: msg });
    }
  });

  $("#pg_valor").on("input", function () {
    const saldo = $(this).data("saldo"); // quanto ainda falta pagar
    const valor = parseFloat(this.value) || 0;
    const $submit = $("#formPagamento button[type=submit]");

    if (valor > saldo) {
      // marca o campo como inválido visualmente
      $(this).addClass("is-invalid");

      // mostra aviso (Bootstrap 5)
      if (!$("#pg_valor_feedback").length) {
        $('<div id="pg_valor_feedback" class="invalid-feedback">')
          .text(
            `O valor não pode exceder o saldo de ${saldo.toLocaleString("pt-PT", { minimumFractionDigits: 2 })} Kz.`,
          )
          .insertAfter(this);
      }

      $submit.prop("disabled", true); // impede o submit
    } else {
      $(this).removeClass("is-invalid");
      $("#pg_valor_feedback").remove();
      $submit.prop("disabled", false);
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
      return alert("Fatura ainda não carregada!");
    }

    // Id oculto
    $("#email_invoice_id").val(currentInvoice.id);

    // Assunto default
    const codigo = `${currentInvoice.reference}`;
    $('input[name="subject"]').val(
      `Fatura #${codigo} – ${currentInvoice.company_name}`,
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

      <p>Segue em anexo a <strong>fatura nº ${codigo}</strong>,
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

  // ---------- 6) Finalizar Fatura (Rascunho -> Pendente) ----------
  // ---------- 6) Finalizar Fatura (Rascunho -> Pendente) ----------
  $("#btnFinalizar").on("click", function () {
    Swal.fire({
      title: "Finalizar Fatura?",
      text: "A fatura deixará de ser rascunho e passará para Pendente.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Sim, finalizar",
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          "invoices/ajax/update_status.php",
          {
            invoice_id: currentInvoice.id,
            new_status: "Finalizada",
            document_type: document_type,
          },
          function (res) {
            if (res.success) {
              Swal.fire(
                "Sucesso",
                "Fatura finalizada com sucesso!",
                "success",
              ).then(() => location.reload());
            } else {
              Swal.fire(
                "Erro",
                res.error || "Erro ao atualizar status",
                "error",
              );
            }
          },
          "json",
        );
      }
    });
  });

  // ---------- 7) Editar Fatura (Redirecionar) ----------
  $("#btnEditar").on("click", function () {
    window.location.href = `create_${document_type === "PF" ? "proform" : "invoices"}.php?edit_id=${currentInvoice.id}`;
  });

  // ===========================================
  // CLONAR FACTURA
  // ===========================================
  $("#btnCloneToInvoice").on("click", function () {
    // ==========================================
    // VALIDAR
    // ==========================================

    if (!invoiceId) {
      return Swal.fire({
        icon: "error",
        title: "Erro",
        text: "Documento não encontrado.",
      });
    }

    // ==========================================
    // CONFIRMAR
    // ==========================================
    Swal.fire({
      icon: "question",
      title: "Clonar Factura",
      text: "Deseja clonar esta Factura Recibo?",
      showCancelButton: true,
      confirmButtonText: "Clonar",
      cancelButtonText: "Cancelar",
      reverseButtons: true,
    }).then((result) => {
      // cancelado
      if (!result.isConfirmed) {
        return;
      }

      // ==========================================
      // AJAX
      // ==========================================
      $.ajax({
        url: "invoices/ajax/clone_invoice.php",

        type: "POST",

        dataType: "json",

        data: {
          invoice_id: invoiceId,
        },

        // ==========================================
        // BEFORE SEND
        // ==========================================
        beforeSend: function () {
          $("#btnCloneToInvoice").prop("disabled", true).html(`
            <span class="spinner-border spinner-border-sm"></span>
            Clonando...
          `);
        },

        // ==========================================
        // SUCCESS
        // ==========================================
        success: function (data) {
          if (!data.success) {
            Swal.fire({
              icon: "error",
              title: "Erro",
              text: data.error || "Erro ao clonar factura.",
            });

            return;
          }

          Swal.fire({
            icon: "success",
            title: "Sucesso",
            text: "Factura clonada com sucesso!",
            timer: 1800,
            showConfirmButton: false,
          });

          // redirecionar
          setTimeout(() => {
            window.location.href = "invoice.php?id=" + data.new_invoice_id;
          }, 1500);
        },

        // ==========================================
        // ERROR
        // ==========================================
        error: function (xhr) {
          console.error(xhr);

          Swal.fire({
            icon: "error",
            title: "Erro Interno",
            text:
              xhr.responseJSON?.error ||
              xhr.responseText ||
              "Erro ao clonar factura.",
          });
        },

        // ==========================================
        // COMPLETE
        // ==========================================
        complete: function () {
          $("#btnCloneToInvoice").prop("disabled", false).html(`
            <span class="material-icons-outlined">
              content_copy
            </span>
            Clonar Factura
          `);
        },
      });
    });
  });
});

$(document).ready(function () {
  const get = new URLSearchParams(window.location.search).get("id");
  const invoiceId = get.substring(get.lastIndexOf("/") + 1);
  if (!invoiceId) {
    alert("Fatura não encontrada!");
    return;
  }

  $.ajax({
    url: "invoices/ajax/get_invoice.php",
    type: "GET",
    data: { id: invoiceId },
    dataType: "json",
    success: function (response) {
      if (response.error) {
        alert(response.error);
        return;
      }
    },
    error: function () {
      alert("Erro ao carregar os dados da fatura.");
    },
  });

  function generateQRCode(text) {
    const qr = qrcode(0, "L");
    qr.addData(text);
    qr.make();
    const qrCodeImgTag = qr.createImgTag(5);
    const base64Image = qrCodeImgTag.match(/src="([^"]*)"/)[1];
    return base64Image;
  }
});
