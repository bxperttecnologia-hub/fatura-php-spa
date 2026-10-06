const routes = {};
let current = null;   // módulo da página atual
let navId = 0;        // evita corridas entre navegações rápidas

export function route(path, load, { auth = true, title = '' } = {}) {
  routes[path] = { load, auth, title };
}

// "/clientes/" -> "/clientes"; ignora query/hash
function normalize(path) {
  const p = path.split(/[?#]/)[0].replace(/\/+$/, '');
  return p === '' ? '/' : p;
}

export function updateChrome() {
  const u = window.APP.user;
  document.body.classList.toggle('guest', !u);
  const el = document.getElementById('nav-user');   // opcional
  if (el) el.textContent = u ? u.nome : '';
}

export async function navigate(url, push = true) {
  const id = ++navId;
  // Preserva a query string (?id=5): as páginas legadas lêem-na via $_GET
  const [rawPath, ...q] = url.split('#')[0].split('?');
  let path = normalize(rawPath);
  let search = q.length ? '?' + q.join('?') : '';

  // Guardas de acesso
  if (!window.APP.user && (routes[path] ?? routes['/404']).auth) {
    // Sem sessão: login primeiro, a lembrar para onde ia
    const dest = path + search;
    path = '/login';
    search = dest === '/' ? '' : '?next=' + encodeURIComponent(dest);
  }
  if (window.APP.user && path === '/login') { path = '/'; search = ''; }

  if (path + search !== location.pathname + location.search) {
    history[push ? 'pushState' : 'replaceState']({}, '', path + search);
  }

  const r = routes[path] ?? routes['/404'];
  const el = document.getElementById('app');

  try { current?.destroy?.(); } catch (e) { console.error(e); }  // limpa DataTables, charts, mapas...
  current = null;

  updateChrome();
  el.innerHTML = '<div class="spinner-border"></div>';

  try {
    const page = await r.load();
    if (id !== navId) return;                // chegou outra navegação entretanto
    current = page;
    document.title = (r.title ? r.title + ' · ' : '') + 'BXpert';
    el.innerHTML = '';
    await page.render(el);
    window.lucide?.createIcons();            // ícones de conteúdo injectado
  } catch (e) {
    if (id === navId) el.innerHTML = '<div class="alert alert-danger">Erro ao carregar a página.</div>';
    console.error(e);
  }

  if (id !== navId) return;
  document.querySelectorAll('a[data-link], a[data-spa]').forEach(a =>
    a.classList.toggle('active', normalize(a.getAttribute('href') || '') === path));
}

export function initRouter() {
  document.addEventListener('click', e => {
    const a = e.target.closest('a[data-link], a[data-spa]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank') return;
    const href = a.getAttribute('href');
    if (!href || !href.startsWith('/')) return;
    e.preventDefault();
    navigate(href);
  });
  window.addEventListener('popstate', () => navigate(location.pathname + location.search, false));
  navigate(location.pathname + location.search, false);
}
