// Cobrança automática (lista de faturas): configuração, avisos por enviar hoje e histórico.
// Endpoint: invoices/ajax/collections.php
(() => {
  if (window.__bxAutoCollectionBound) return;   // SPA: liga os eventos uma só vez
  window.__bxAutoCollectionBound = true;

  const url = "invoices/ajax/collections.php";
  const $ = (id) => document.getElementById(id);
  const esc = (v) =>
    String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  const money = (v) => Number(v || 0).toLocaleString("pt-PT", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const stage = (d) => (d < 0 ? `${Math.abs(d)} dia(s) antes` : d === 0 ? "no vencimento" : `${d} dia(s) depois`);
  const csrf = () => window.APP?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || "";
  const STATUS = {
    sent: '<span class="badge bg-success">Enviado</span>',
    failed: '<span class="badge bg-danger">Falhou</span>',
    skipped: '<span class="badge bg-secondary">Sem e-mail</span>',
    pending: '<span class="badge bg-warning text-dark">Por enviar</span>',
  };

  function showError(message) {
    const el = $("acError");
    if (!el) return;
    el.textContent = message || "";
    el.classList.toggle("d-none", !message);
  }

  function renderPending(items) {
    const body = $("acPendingBody");
    $("acPendingCount").textContent = items.filter((i) => i.status === "pending").length;
    body.innerHTML = items.length
      ? items.map((i) => `<tr>
          <td><a href="/invoices/view?id=${encodeURIComponent(i.invoice_id)}" data-spa>${esc(i.reference)}</a></td>
          <td>${esc(i.client)}</td>
          <td>${i.status === "skipped" ? `<span class="text-muted">${esc(i.error || "sem e-mail")}</span>` : esc(i.email)}</td>
          <td class="text-end">${money(i.amount_due)}</td>
          <td>${esc(stage(i.stage_day))}</td></tr>`).join("")
      : '<tr><td colspan="5" class="text-muted">Nenhum aviso por enviar hoje.</td></tr>';
  }

  function renderHistory(rows) {
    $("acHistoryBody").innerHTML = rows.length
      ? rows.map((r) => `<tr>
          <td>${esc(new Date(String(r.created_at).replace(" ", "T")).toLocaleString("pt-PT"))}</td>
          <td>${esc(r.reference)}</td>
          <td>${esc(r.client_name)}<div class="text-muted">${esc(r.recipient || "")}</div></td>
          <td>${esc(stage(Number(r.stage_day)))}${r.trigger === "manual" ? ' <span class="text-muted">(manual)</span>' : ""}</td>
          <td>${STATUS[r.status] || esc(r.status)}${r.error ? `<div class="text-danger">${esc(r.error)}</div>` : ""}</td></tr>`).join("")
      : '<tr><td colspan="5" class="text-muted">Ainda não foram enviados avisos.</td></tr>';
  }

  async function request(action, body) {
    const res = await fetch(`${url}?action=${action}`, body
      ? { method: "POST", credentials: "same-origin", headers: { "X-CSRF-Token": csrf() }, body }
      : { credentials: "same-origin" });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) throw new Error(data.error || data.message || "Não foi possível comunicar com o servidor.");
    return data;
  }

  async function load() {
    showError("");
    $("acPendingBody").innerHTML = '<tr><td colspan="5" class="text-muted">A carregar…</td></tr>';
    try {
      const data = await request("get");
      const s = data.settings;
      $("acEnabled").checked = !!s.enabled;
      $("acDays").value = s.reminder_days;
      $("acSubject").value = s.email_subject || "";
      $("acMessage").value = s.email_message || "";
      $("acSmtpWarning").classList.toggle("d-none", !!s.smtp_configured);
      renderPending(data.pending || []);
      renderHistory(data.history || []);
    } catch (e) {
      showError(e.message);
    }
  }

  document.addEventListener("show.bs.modal", (e) => {
    if (e.target?.id === "autoCollectionModal") load();
  });

  document.addEventListener("submit", async (e) => {
    if (e.target?.id !== "autoCollectionForm") return;
    e.preventDefault();
    const btn = $("acSaveBtn");
    btn.disabled = true;
    showError("");
    try {
      const form = new FormData(e.target);
      if (!$("acEnabled").checked) form.delete("enabled");
      await request("save", form);
      await load();
      window.Swal?.fire({ icon: "success", title: "Cobrança automática", text: $("acEnabled").checked ? "Ativa: os avisos são enviados todos os dias." : "Configuração guardada (desativada).", timer: 2200, showConfirmButton: false });
    } catch (err) {
      showError(err.message);
    } finally {
      btn.disabled = false;
    }
  });

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest?.("#acRunBtn");
    if (!btn) return;
    btn.disabled = true;
    const label = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A enviar…';
    showError("");
    try {
      const data = await request("run", new FormData());
      const r = data.result;
      renderHistory(data.history || []);
      await load();
      window.Swal?.fire({
        icon: r.failed ? "warning" : "success",
        title: "Avisos de cobrança",
        html: `Enviados: <b>${r.sent}</b> · Falhados: <b>${r.failed}</b> · Sem e-mail: <b>${r.skipped}</b>`,
      });
    } catch (err) {
      showError(err.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = label;
    }
  });
})();
