import { api } from '../api.js';
import { esc } from '../ui.js';

export function render(el) {
  el.innerHTML = `
    <div class="mx-auto" style="max-width:360px">
      <h3 class="mb-3">Entrar</h3>
      <div id="err"></div>
      <form id="f">
        <input name="email" type="email" class="form-control mb-2" placeholder="Email" required autofocus>
        <input name="password" type="password" class="form-control mb-3" placeholder="Palavra-passe" required>
        <button class="btn btn-primary w-100">Entrar</button>
      </form>
    </div>`;

  el.querySelector('#f').addEventListener('submit', async e => {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(e.target));
    const btn = e.target.querySelector('button');
    btn.disabled = true;
    try {
      const { data } = await api('auth/login', { method: 'POST', body });
      window.APP.user = data.user;
      location.assign('/');   // recarrega: o servidor passa a renderizar nav + sidebar
    } catch (err) {
      el.querySelector('#err').innerHTML = `<div class="alert alert-danger py-2">${esc(err.message)}</div>`;
      btn.disabled = false;
    }
  });
}
