(() => {
  "use strict";

  const STORAGE_KEY = "bxpert_support_chat";
  const HIDDEN_SUGGESTION_KEY = "bxpert_support_chat_suggestion_hidden";
  const suggestions = [
    "Precisa de ajuda para criar uma fatura?",
    "Quer saber como consultar os seus relatórios?",
    "Precisa de ajuda com os alertas?",
    "Como podemos ajudar?",
  ];
  const apiBaseUrl = (() => {
    const hostname = window.location.hostname;
    const isProduction = hostname === "api-crm.bxpert.co.ao" ||
      hostname === "www.api-crm.bxpert.co.ao";
    return isProduction ? "https://api-crm.bxpert.co.ao" : "http://localhost:3002";
  })();
  const socketUrl = `${apiBaseUrl}/support-chat`;
  const httpUrl = `${apiBaseUrl}/api/support/chat`;

  function getCompanyId() {
    const companyField = document.getElementById("company_id") ||
      document.getElementById("id_company");
    return companyField?.value || document.body.dataset.companyId || "";
  }

  const state = {
    messages: [],
    inputValue: "",
    isLoading: false,
    isConnected: false,
    conversationId: "",
    error: "",
    socket: null,
    requestId: 0,
  };

  function escapeHtml(value) {
    const node = document.createElement("div");
    node.textContent = String(value ?? "");
    return node.innerHTML;
  }

  function loadState() {
    try {
      const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
      if (!saved || typeof saved !== "object") return;
      state.conversationId = saved.conversationId || "";
      state.messages = Array.isArray(saved.messages) ? saved.messages : [];
    } catch (_) {
      state.messages = [];
    }
  }

  function persistState() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      conversationId: state.conversationId,
      messages: state.messages.slice(-80),
    }));
  }

  function renderMessages() {
    const list = document.getElementById("bx-support-messages");
    if (!list) return;

    if (!state.messages.length) {
      state.messages = [{ role: "assistant", content: "Olá! Sou o assistente BXpert. Como posso ajudar?" }];
    }

    list.innerHTML = state.messages.map((message) => {
      const role = message.role === "user" ? "user" : "assistant";
      const time = message.createdAt
        ? new Date(message.createdAt).toLocaleTimeString("pt-PT", { hour: "2-digit", minute: "2-digit" })
        : "";
      return `<div class="bx-support-message is-${role}">
        <div class="bx-support-bubble">${escapeHtml(message.content)}</div>
        ${time ? `<small class="bx-support-message-time">${time}</small>` : ""}
      </div>`;
    }).join("");

    if (state.isLoading) {
      list.insertAdjacentHTML("beforeend", `<div class="bx-support-message is-assistant" aria-label="O assistente está a responder">
        <div class="bx-support-typing"><i></i><i></i><i></i></div>
      </div>`);
    }
    list.scrollTop = list.scrollHeight;
  }

  function setError(message) {
    state.error = message || "Não foi possível obter uma resposta agora. Tente novamente.";
    const error = document.getElementById("bx-support-error");
    if (error) {
      error.hidden = !state.error;
      error.textContent = state.error;
    }
  }

  function setLoading(loading) {
    state.isLoading = loading;
    const input = document.getElementById("bx-support-input");
    const send = document.getElementById("bx-support-send");
    if (input) input.disabled = loading;
    if (send) send.disabled = loading || !state.inputValue.trim();
    renderMessages();
  }

  function updateConnectionStatus() {
    const status = document.getElementById("bx-support-status");
    if (!status) return;
    status.textContent = state.isConnected ? "Ligado" : "Modo de atendimento disponível";
    status.classList.toggle("is-offline", !state.isConnected);
  }

  function updateSendState() {
    const send = document.getElementById("bx-support-send");
    if (send) send.disabled = state.isLoading || !state.inputValue.trim();
  }

  function addMessage(role, content) {
    if (!content) return;
    state.messages.push({ role, content: String(content), createdAt: new Date().toISOString() });
    persistState();
    renderMessages();
  }

  function responseAnswer(response) {
    if (!response || typeof response !== "object") return "";
    if (response.conversation_id) state.conversationId = response.conversation_id;
    return typeof response.answer === "string" ? response.answer.trim() : "";
  }

  async function sendHttp(question) {
    const response = await fetch(httpUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        question,
        conversation_id: state.conversationId || undefined,
        company_id: getCompanyId() || undefined,
        history: state.messages.slice(0, -1).slice(-20).map(({ role, content }) => ({ role, content })),
      }),
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  }

  function connectSocket() {
    if (typeof window.io !== "function") {
      state.isConnected = false;
      updateConnectionStatus();
      return;
    }

    state.socket = window.io(socketUrl, {
      path: "/socket.io",
      transports: ["websocket", "polling"],
      reconnection: true,
      reconnectionAttempts: Infinity,
      reconnectionDelay: 1000,
    });

    state.socket.on("connect", () => {
      state.isConnected = true;
      updateConnectionStatus();
    });
    state.socket.on("disconnect", () => {
      state.isConnected = false;
      updateConnectionStatus();
    });
    state.socket.on("connect_error", () => {
      state.isConnected = false;
      updateConnectionStatus();
    });
  }

  async function sendMessage() {
    const input = document.getElementById("bx-support-input");
    const question = (input?.value || state.inputValue).trim();
    if (!question || state.isLoading) return;

    state.inputValue = "";
    if (input) {
      input.value = "";
      input.style.height = "auto";
    }
    addMessage("user", question);
    setError("");
    setLoading(true);
    const requestId = ++state.requestId;

    try {
      let response;
      if (state.socket && state.isConnected) {
        response = await new Promise((resolve, reject) => {
          const timer = setTimeout(() => reject(new Error("Socket timeout")), 12000);
          state.socket.emit("chat:message", {
            conversation_id: state.conversationId || undefined,
            question,
            company_id: getCompanyId() || undefined,
          }, (acknowledgement) => {
            clearTimeout(timer);
            if (!acknowledgement || acknowledgement.error) {
              reject(new Error(acknowledgement?.error || "Socket error"));
              return;
            }
            resolve(acknowledgement);
          });
        });
      } else {
        response = await sendHttp(question);
      }

      if (requestId !== state.requestId) return;
      const answer = responseAnswer(response);
      if (!answer) throw new Error("Resposta vazia");
      addMessage("assistant", answer);
      setError("");
    } catch (error) {
      if (requestId !== state.requestId) return;
      try {
        const response = await sendHttp(question);
        const answer = responseAnswer(response);
        if (!answer) throw new Error("Resposta vazia");
        addMessage("assistant", answer);
        setError("");
      } catch (_) {
        setError("Não foi possível contactar o atendimento neste momento. Verifique a ligação e tente novamente.");
      }
    } finally {
      if (requestId === state.requestId) setLoading(false);
    }
  }

  function clearConversation() {
    if (state.socket?.connected && state.conversationId) {
      state.socket.emit("chat:clear", { conversation_id: state.conversationId });
    }
    state.messages = [{ role: "assistant", content: "Olá! Sou o assistente BXpert. Como posso ajudar?" }];
    state.conversationId = "";
    state.error = "";
    persistState();
    renderMessages();
    setError("");
  }

  function openChat() {
    const panel = document.getElementById("bx-support-panel");
    const suggestion = document.getElementById("bx-support-suggestion");
    if (!panel) return;
    panel.hidden = false;
    if (suggestion) suggestion.hidden = true;
    localStorage.setItem(HIDDEN_SUGGESTION_KEY, "1");
    document.getElementById("bx-support-input")?.focus();
    renderMessages();
  }

  function closeChat() {
    const panel = document.getElementById("bx-support-panel");
    if (panel) panel.hidden = true;
  }

  function useSuggestedQuestion(question) {
    const input = document.getElementById("bx-support-input");
    if (!input || state.isLoading) return;
    input.value = question;
    state.inputValue = question;
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, 112)}px`;
    updateSendState();
    input.focus();
  }

  function mount() {
    if (document.getElementById("bx-support-chat-root")) return;
    loadState();

    const suggestion = suggestions[Math.floor(Math.random() * suggestions.length)];
    document.body.insertAdjacentHTML("beforeend", `<div id="bx-support-chat-root">
      <div class="bx-support-launcher-wrap">
        <div id="bx-support-suggestion" class="bx-support-suggestion" role="button" tabindex="0" hidden>
          <button type="button" class="bx-support-suggestion-close" aria-label="Ocultar sugestão">&times;</button>
          ${escapeHtml(suggestion)}
        </div>
        <button type="button" id="bx-support-launcher" class="bx-support-launcher" aria-label="Abrir atendimento BXpert" aria-controls="bx-support-panel" aria-expanded="false">
          <span class="material-icons-round" aria-hidden="true">support_agent</span>
        </button>
      </div>
      <section id="bx-support-panel" class="bx-support-panel" aria-label="Atendimento BXpert" hidden>
        <header class="bx-support-panel-header">
          <div class="bx-support-panel-title">
            <div><h2>Atendimento BXpert</h2><p class="bx-support-panel-subtitle">Dúvidas sobre faturação, RH e o sistema</p></div>
            <div class="bx-support-panel-actions">
              <button type="button" id="bx-support-clear" class="bx-support-icon-button" aria-label="Limpar conversa" title="Limpar conversa"><span class="material-icons-round" aria-hidden="true">delete_sweep</span></button>
              <button type="button" id="bx-support-close" class="bx-support-icon-button" aria-label="Fechar atendimento"><span class="material-icons-round" aria-hidden="true">close</span></button>
            </div>
          </div>
          <span id="bx-support-status" class="bx-support-status" role="status">A ligar...</span>
        </header>
        <div id="bx-support-messages" class="bx-support-messages" role="log" aria-live="polite" aria-label="Mensagens da conversa"></div>
        <div class="bx-support-question-suggestions" aria-label="Sugestões de perguntas">
          <span class="bx-support-suggestions-label">Sugestões</span>
          <div class="bx-support-suggestions-list">
            ${suggestions.map((question) => `<button type="button" class="bx-support-question" data-question="${escapeHtml(question)}">${escapeHtml(question)}</button>`).join("")}
          </div>
        </div>
        <div id="bx-support-error" class="bx-support-error" role="alert" hidden></div>
        <form id="bx-support-form" class="bx-support-composer">
          <textarea id="bx-support-input" class="bx-support-input" rows="1" placeholder="Escreva a sua dúvida..." aria-label="Mensagem para o atendimento"></textarea>
          <button type="submit" id="bx-support-send" class="bx-support-send" aria-label="Enviar mensagem" disabled><span class="material-icons-round" aria-hidden="true">send</span></button>
        </form>
      </section>
    </div>`);

    const suggestionEl = document.getElementById("bx-support-suggestion");
    if (!localStorage.getItem(HIDDEN_SUGGESTION_KEY)) {
      suggestionEl.hidden = false;
      window.setTimeout(() => { suggestionEl.hidden = true; }, 8000);
    }
    document.getElementById("bx-support-launcher").addEventListener("click", openChat);
    document.getElementById("bx-support-close").addEventListener("click", closeChat);
    document.getElementById("bx-support-clear").addEventListener("click", clearConversation);
    document.querySelectorAll(".bx-support-question").forEach((button) => {
      button.addEventListener("click", () => useSuggestedQuestion(button.dataset.question || ""));
    });
    suggestionEl.addEventListener("click", openChat);
    suggestionEl.querySelector("button").addEventListener("click", (event) => {
      event.stopPropagation();
      suggestionEl.hidden = true;
      localStorage.setItem(HIDDEN_SUGGESTION_KEY, "1");
    });
    document.getElementById("bx-support-form").addEventListener("submit", (event) => {
      event.preventDefault();
      sendMessage();
    });
    const input = document.getElementById("bx-support-input");
    input.addEventListener("input", () => {
      state.inputValue = input.value;
      input.style.height = "auto";
      input.style.height = `${Math.min(input.scrollHeight, 112)}px`;
      updateSendState();
    });
    input.addEventListener("keydown", (event) => {
      if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    });
    suggestionEl.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") openChat();
    });
    renderMessages();
    updateConnectionStatus();
    connectSocket();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mount);
  else mount();
})();
