// Escapa HTML para usar em template strings (evita XSS)
export function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export function flash(container, message, type = 'success') {
  container.innerHTML = `<div class="alert alert-${type} py-2">${esc(message)}</div>`;
  setTimeout(() => (container.innerHTML = ''), 4000);
}
