import { route, initRouter, navigate } from './router.js';
import { api } from './api.js';

route('/login',    () => import('./pages/login.js'),     { auth: false, title: 'Entrar' });
route('/login.php', () => import('./pages/login.js'),    { auth: false, title: 'Entrar' });   // location.href = 'login.php' do código legado
route('/clientes', () => import('./pages/clientes.js'),  { title: 'Clientes (demo)' });

// Páginas do pages.zip: rotas definidas em app/config/pages.php (enviadas em window.APP.pages)
const fileRoutes = {};   // 'invoice.php' -> '/invoices/view'
for (const [path, { file, title }] of Object.entries(window.APP.pages ?? {})) {
  route(path, () => import('./pages/legacy.js').then(m => m.forRoute(path)), { title });
  fileRoutes[file] ??= path;
  // Alias /<ficheiro>.php: deep links e `location.href = 'x.php'` do código legado continuam a funcionar
  route('/' + file, () => import('./pages/legacy.js').then(m => m.forRoute(path)), { title });
}

// Links relativos para .php dentro do conteúdo injectado (também os criados em JS, ex.: DataTables)
document.addEventListener('click', e => {
  const a = e.target.closest('#app a[href]');
  if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey ||
      a.target === '_blank' || a.hasAttribute('download')) return;
  const m = a.getAttribute('href').match(/^(?:\.{1,2}\/|\/)*([\w-]+\.php)(\?.*)?$/);
  if (!m || !fileRoutes[m[1]]) return;
  e.preventDefault();
  navigate(fileRoutes[m[1]] + (m[2] ?? ''));
});
route('/404',      () => import('./pages/notfound.js'),  { auth: false, title: 'Não encontrado' });

// Sessão expirada/inválida: recarrega para o servidor devolver o shell de visitante (sem nav/sidebar)
// e leva o utilizador ao login, guardando a página onde estava.
document.addEventListener('app:unauthorized', () => {
  if (!window.APP.user) return;            // já está deslogado: evita ciclos
  window.APP.user = null;
  const here = location.pathname + location.search;
  location.assign('/login?expired=1' + (here !== '/' ? '&next=' + encodeURIComponent(here) : ''));
});

// Verificação periódica da sessão (auth/me NÃO prolonga a sessão): apanha a expiração mesmo sem navegar
if (window.APP.user) {
  let last = 0;
  const check = async () => {
    if (document.hidden || Date.now() - last < 15000) return;
    last = Date.now();
    try { await api('auth/me'); } catch { /* um 401 já dispara app:unauthorized */ }
  };
  document.addEventListener('visibilitychange', check);
  window.addEventListener('focus', check);
  setInterval(check, 60000);
}

// Delegação: o botão de logout só existe no DOM quando há sessão (nav.php)
document.addEventListener('click', async e => {
  const btn = e.target.closest('#btn-logout');
  if (!btn) return;
  e.preventDefault();
  try { await api('auth/logout', { method: 'POST' }); } catch {}
  window.APP.user = null;
  location.assign('/login');
});

initRouter();
