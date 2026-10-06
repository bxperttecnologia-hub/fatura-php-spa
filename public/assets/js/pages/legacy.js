// Carrega uma página PHP legada (app/pages/*.php) para dentro do #app.
// O servidor (public/view.php) devolve só o conteúdo; aqui injectamos o HTML, executamos os
// <script> da página e limpamos tudo (DataTables, charts, intervals, handlers) ao sair.
import { navigate } from '../router.js';

const $ = window.jQuery;
let active = null;           // página legada actualmente montada
let patched = false;

const isAbsolute = u => /^([a-z][a-z0-9+.-]*:|\/\/|\/|#)/i.test(u);
// 'rh/ajax/x.php' ou '../app/y.php' -> '/rh/ajax/x.php' (as rotas SPA não têm profundidade fixa)
const toAbs = u => (typeof u === 'string' && u && !isAbsolute(u)) ? '/' + u.replace(/^(\.{1,2}\/)+/, '') : u;

// Instalado uma só vez: faz com que o código legado (URLs relativos, listeners globais) funcione numa SPA.
function patchGlobals() {
  if (patched) return;
  patched = true;

  if ($) {
    $.ajaxPrefilter(o => { if (o.url) o.url = toAbs(o.url); });
    // 401 em qualquer endpoint AJAX legado (sessão expirada) -> volta ao login
    $.ajaxSetup({
      headers: { 'X-CSRF-Token': window.APP.csrf },
      statusCode: { 401: () => document.dispatchEvent(new Event('app:unauthorized')) },
    });

    // Handlers em document/window criados pela página recebem o namespace .spaPage -> off() no destroy
    const on = $.fn.on;
    $.fn.on = function (types, ...rest) {
      if (active && typeof types === 'string' && this.toArray().some(el => el === document || el === window)) {
        types = types.split(/\s+/).filter(Boolean).map(t => t + '.spaPage').join(' ');
      }
      return on.call(this, types, ...rest);
    };
  }

  // (o head.php já converte URLs relativos de fetch/XHR; aqui só se detecta a sessão expirada)
  const _fetch = window.fetch.bind(window);
  window.fetch = async (input, init) => {
    const res = await _fetch(input, init);
    const url = typeof input === 'string' ? input : input?.url || '';
    if (res.status === 401 && !url.includes('/api/')) document.dispatchEvent(new Event('app:unauthorized'));
    return res;
  };

  const _si = window.setInterval.bind(window);
  window.setInterval = (...a) => { const id = _si(...a); active?.intervals.add(id); return id; };

  for (const target of [document, window]) {
    const add = target.addEventListener.bind(target);
    target.addEventListener = (type, fn, opts) => { active?.listeners.push([target, type, fn, opts]); return add(type, fn, opts); };
  }
}

// Scripts externos já presentes no shell (jQuery, DataTables…) não se recarregam.
// Scripts da aplicação são reiniciados a cada montagem da rota, para refazer handlers e pedidos AJAX;
// inline via eval indirecto -> let/const ficam locais (sem "already declared" ao voltar à página),
// var/function ficam globais (onclick="fn()" continua a funcionar).
async function runScripts(scripts, page) {
  const loaded = new Set([...document.scripts].map(s => s.src).filter(Boolean));
  for (const s of scripts) {
    try {
      const src = s.getAttribute('src');
      if (src) {
        const url = new URL(src, location.origin).href;
        const repeat = s.hasAttribute('data-spa-repeat') || new URL(url).origin === location.origin;
        if (loaded.has(url) && !repeat) continue;
        await new Promise(ok => {
          const el = document.createElement('script');
          el.src = url; el.onload = el.onerror = ok;
          if (repeat) {
            el.dataset.spaPageScript = 'true';
            page.scripts.add(el);
          }
          document.head.appendChild(el);
          loaded.add(url);
        });
      } else {
        (0, eval)(s.textContent);
      }
    } catch (e) { console.error('Erro em script da página', e); }
  }
}

function cleanup(page) {
  page.intervals.forEach(clearInterval);
  page.listeners.forEach(([t, type, fn, opts]) => t.removeEventListener(type, fn, opts));
  page.scripts.forEach(script => script.remove());
  if ($) { $(document).off('.spaPage'); $(window).off('.spaPage'); }
  try { $?.fn?.dataTable?.tables?.().forEach(t => $(t).DataTable().destroy()); } catch {}
  try { Object.values(window.Chart?.instances ?? {}).forEach(c => c.destroy()); } catch {}
  document.querySelectorAll('.modal-backdrop, .select2-container--open, .select2-dropdown').forEach(n => n.remove());
  document.body.classList.remove('modal-open');
  document.body.style.removeProperty('overflow');
  document.body.style.removeProperty('padding-right');
}

export function forRoute(routePath) {
  const page = { intervals: new Set(), listeners: [], scripts: new Set() };

  return {
    async render(el) {
      patchGlobals();
      const qs = location.search ? '&' + location.search.slice(1) : '';
      const res = await fetch(`/view.php?__route=${encodeURIComponent(routePath)}${qs}`, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'spa' },
      });
      const data = await res.json().catch(() => ({}));

      if (res.status === 401) { document.dispatchEvent(new Event('app:unauthorized')); return; }
      if (!res.ok) { el.innerHTML = `<div class="alert alert-danger"></div>`; el.firstChild.textContent = data.message || `Erro ${res.status}`; return; }
      if (data.redirect) { data.external ? location.assign(data.redirect) : navigate(data.redirect); return; }

      // Algumas páginas trazem <html>/<head>/<body> próprios: ficamos com style/link/script + conteúdo do body
      const doc = new DOMParser().parseFromString(data.html, 'text/html');
      const nodes = [...doc.head.children, ...doc.body.childNodes];
      const holder = document.createElement('div');
      holder.append(...nodes);

      const scripts = [...holder.querySelectorAll('script')];
      scripts.forEach(s => s.remove());
      // O shell já tem <main id="app">: evita aplicar novamente o layout global a <main> legado
      holder.querySelectorAll('main').forEach(m => {
        const d = document.createElement('div');
        for (const attr of m.attributes) d.setAttribute(attr.name, attr.value);
        d.append(...m.childNodes);
        m.replaceWith(d);
      });

      const abs = window.__spaAbs ?? (u => u);
      holder.querySelectorAll('[src],[href],[action],[poster]').forEach(n => {
        for (const a of ['src', 'href', 'action', 'poster']) {
          const v = n.getAttribute(a);
          if (v && !v.startsWith('{') && !v.includes('<?')) { const r = abs(v); if (r !== v) n.setAttribute(a, r); }
        }
      });

      el.append(...holder.childNodes);
      active = page;                 // a partir daqui, handlers/intervals criados pertencem a esta página
      await runScripts(scripts, page);
    },
    destroy() {
      cleanup(page);
      if (active === page) active = null;
    },
  };
}
