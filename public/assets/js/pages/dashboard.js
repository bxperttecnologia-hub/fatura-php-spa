import { esc } from '../ui.js';

export function render(el) {
  el.innerHTML = `<h3>Dashboard</h3><p>Olá, ${esc(window.APP.user.nome)}.</p>`;
}
