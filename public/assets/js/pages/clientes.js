import { api } from '../api.js';
import { esc, flash } from '../ui.js';

let t;   // timer do debounce (módulo, para o destroy() o limpar)

export async function render(el) {
  el.innerHTML = `
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="m-0">Clientes</h3>
      <button id="novo" class="btn btn-primary">Novo cliente</button>
    </div>
    <div id="msg"></div>

    <form id="form" class="card card-body mb-3 d-none">
      <input type="hidden" name="id">
      <div class="row g-2">
        <div class="col-md-4"><input name="nome" class="form-control" placeholder="Nome"><div class="text-danger small" data-err="nome"></div></div>
        <div class="col-md-4"><input name="email" type="email" class="form-control" placeholder="Email"><div class="text-danger small" data-err="email"></div></div>
        <div class="col-md-3"><input name="telefone" class="form-control" placeholder="Telefone"></div>
        <div class="col-md-1"><button class="btn btn-success w-100">OK</button></div>
      </div>
    </form>

    <input id="q" class="form-control mb-3" placeholder="Pesquisar...">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead><tr><th>Nome</th><th>Email</th><th>Telefone</th><th></th></tr></thead>
        <tbody id="rows"></tbody>
      </table>
    </div>`;

  const $ = s => el.querySelector(s);
  const form = $('#form');
  let items = [];

  async function load() {
    let data;
    try {
      ({ data } = await api('clientes?q=' + encodeURIComponent($('#q').value)));
    } catch (err) {
      flash($('#msg'), err.message, 'danger');
      return;
    }
    items = data;
    $('#rows').innerHTML = items.length
      ? items.map(c => `
        <tr data-id="${c.id}">
          <td>${esc(c.nome)}</td><td>${esc(c.email)}</td><td>${esc(c.telefone)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-act="edit">Editar</button>
            <button class="btn btn-sm btn-outline-danger" data-act="del">Apagar</button>
          </td>
        </tr>`).join('')
      : '<tr><td colspan="4" class="text-muted">Sem resultados</td></tr>';
  }

  function openForm(c = {}) {
    form.reset();
    form.classList.remove('d-none');
    form.querySelectorAll('[data-err]').forEach(d => (d.textContent = ''));
    for (const k of ['id', 'nome', 'email', 'telefone']) form.elements[k].value = c[k] ?? '';
    form.elements.nome.focus();
  }

  $('#novo').onclick = () => openForm();

  $('#q').oninput = () => { clearTimeout(t); t = setTimeout(load, 300); }; // debounce

  $('#rows').addEventListener('click', async e => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const id = btn.closest('tr').dataset.id;
    if (btn.dataset.act === 'edit') return openForm(items.find(c => c.id == id));
    if (!confirm('Apagar este cliente?')) return;
    try {
      const r = await api('clientes/' + id, { method: 'DELETE' });
      flash($('#msg'), r.message);
      load();
    } catch (err) { flash($('#msg'), err.message, 'danger'); }
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    form.querySelectorAll('[data-err]').forEach(d => (d.textContent = ''));
    const body = Object.fromEntries(new FormData(form));
    const id = body.id; delete body.id;
    try {
      const r = await api(id ? 'clientes/' + id : 'clientes', { method: id ? 'PUT' : 'POST', body });
      form.classList.add('d-none');
      flash($('#msg'), r.message);
      load();
    } catch (err) {
      const errs = err.errors || {};
      for (const [k, v] of Object.entries(errs)) {
        const d = form.querySelector(`[data-err="${k}"]`);
        if (d) d.textContent = v;
      }
      if (!Object.keys(errs).length) flash($('#msg'), err.message, 'danger');
    }
  });

  await load();
}

// Chamado pelo router ao sair da página (limpar DataTables, charts, etc.)
export function destroy() { clearTimeout(t); }
