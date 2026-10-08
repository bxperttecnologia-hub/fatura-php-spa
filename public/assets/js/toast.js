(function () {
  "use strict";

  const fallbackDuration = 4500;
  let fallbackContainer;

  function getFallbackContainer() {
    if (fallbackContainer?.isConnected) return fallbackContainer;

    fallbackContainer = document.createElement("div");
    fallbackContainer.className = "bx-toast-fallback-container";
    fallbackContainer.setAttribute("aria-live", "polite");
    fallbackContainer.setAttribute("aria-relevant", "additions");
    (document.body || document.documentElement).appendChild(fallbackContainer);
    return fallbackContainer;
  }

  function fallbackToast(message, type, duration) {
    const container = getFallbackContainer();
    const toast = document.createElement("div");
    toast.className = `bx-toast-fallback bx-toast-fallback--${type}`;
    toast.setAttribute("role", type === "error" ? "alert" : "status");

    const text = document.createElement("span");
    text.textContent = message;

    const close = document.createElement("button");
    close.type = "button";
    close.className = "bx-toast-fallback__close";
    close.setAttribute("aria-label", "Fechar notificação");
    close.textContent = "×";
    close.addEventListener("click", () => toast.remove());

    toast.append(text, close);
    container.appendChild(toast);

    if (duration > 0) {
      window.setTimeout(() => toast.remove(), duration);
    }
  }

  function show(message, type = "info", options = {}) {
    let text = String(message ?? "").trim();
    if (!text) return;

    if (/<\/?[a-z][\s\S]*>|stack trace|fatal error/i.test(text)) {
      console.error("Technical details omitted from user notification:", text);
      text = "Ocorreu um erro inesperado. Tente novamente.";
      type = "error";
    }
    if (text.length > 300) {
      console.error("Long details omitted from user notification:", text);
      text = "Ocorreu um erro ao processar o pedido. Tente novamente.";
      type = "error";
    }

    const duration = Number(options.duration ?? fallbackDuration);
    if (window.toastr && typeof window.toastr[type] === "function") {
      window.toastr.options = {
        closeButton: true,
        progressBar: true,
        newestOnTop: true,
        preventDuplicates: true,
        positionClass: "toast-bottom-right",
        timeOut: duration,
        extendedTimeOut: 1000,
        escapeHtml: true
      };
      window.toastr[type](text);
      return;
    }

    fallbackToast(text, type, duration);
  }

  function classifyAlert(message) {
    const text = String(message ?? "");
    if (/sucesso|conclu[ií]d[oa]|agendad[oa]|atualizad[oa]/i.test(text)) {
      return "success";
    }
    if (/aten[cç][aã]o|selecione|ainda n[aã]o|nenhum[oa]? .{0,30}encontrad[oa]|n[aã]o encontrad[oa]/i.test(text)) {
      return "warning";
    }
    if (/erro|falha|n[aã]o foi poss[ií]vel|inv[aá]lid[oa]|n[aã]o autorizado/i.test(text)) {
      return "error";
    }
    return "info";
  }

  window.bxToast = {
    show,
    success: (message, options) => show(message, "success", options),
    error: (message, options) => show(message, "error", options),
    warning: (message, options) => show(message, "warning", options),
    info: (message, options) => show(message, "info", options)
  };

  // Compatibilidade com os scripts legados: alertas passam a não bloquear a página.
  window.alert = (message) => show(message, classifyAlert(message));
})();
