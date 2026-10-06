/**
 * BXpert SPA Router
 *
 * Single source of truth for SPA navigation while preserving the current PHP backend.
 * - supports history.pushState / popstate
 * - intercepts links marked with data-spa
 * - preserves legacy global aliases used by existing layout code
 */

(function () {
  'use strict';

  const routes = {
    '/': { view: 'index.php', name: 'dashboard' },
    '/contacts': { view: 'contacts.php', name: 'contacts' },
    '/contacts/create': { view: 'register_contact.php', name: 'register_contact' },
    '/items': { view: 'items.php', name: 'items' },
    '/invoices': { view: 'list_invoices.php', name: 'invoices' },
    '/invoices/create': { view: 'create_invoices.php', name: 'create_invoice' },
    '/invoices/view': { view: 'invoice.php', name: 'invoice_view' },
    '/proformas': { view: 'list_proforms.php', name: 'proformas' },
    '/proformas/create': { view: 'create_proform.php', name: 'create_proform' },
    '/proformas/view': { view: 'proform.php', name: 'proform_view' },
    '/stock': { view: 'stock.php', name: 'stock' },
    '/employees': { view: 'employees.php', name: 'employees' },
    '/ponto': { view: 'ponto.php', name: 'ponto' },
    '/vacations': { view: 'vacations.php', name: 'vacations' },
    '/positions': { view: 'positions.php', name: 'positions' },
    '/payroll': { view: 'payroll.php', name: 'payroll' },
    '/subscription': { view: 'subscription.php', name: 'subscription' },
    '/manage_users': { view: 'manage_users.php', name: 'manage_users' },
    '/perfil': { view: 'perfil.php', name: 'perfil' },
    '/help': { view: 'help.php', name: 'help' },
    '/notificacoes': { view: 'notificacoes.php', name: 'notificacoes' },
    '/intelligence': { view: 'intelligence.php', name: 'intelligence' }
  };

  const state = {
    currentPath: null,
    isNavigating: false,
    abortController: null
  };

  function normalizeRoutePath(value) {
    if (!value) return '/';

    const url = new URL(value, window.location.origin);
    let pathname = url.pathname || '/';

    if (pathname === '/index.php') return '/';
    if (pathname.endsWith('.php')) {
      const base = pathname.split('/').pop().replace(/\.php$/i, '');
      const aliasMap = {
        contacts: '/contacts',
        register_contact: '/contacts/create',
        items: '/items',
        list_invoices: '/invoices',
        create_invoices: '/invoices/create',
        invoice: '/invoices/view',
        list_proforms: '/proformas',
        create_proform: '/proformas/create',
        proform: '/proformas/view',
        stock: '/stock',
        employees: '/employees',
        ponto: '/ponto',
        vacations: '/vacations',
        positions: '/positions',
        payroll: '/payroll',
        subscription: '/subscription',
        manage_users: '/manage_users',
        perfil: '/perfil',
        help: '/help',
        notificacoes: '/notificacoes',
        intelligence: '/intelligence'
      };

      return aliasMap[base] || pathname;
    }

    if (pathname.length > 1 && pathname.endsWith('/')) {
      pathname = pathname.slice(0, -1);
    }

    return pathname || '/';
  }

  function matchRoute(path) {
    const normalized = normalizeRoutePath(path);
    const exact = routes[normalized];
    if (exact) return exact;

    const withoutQuery = normalized.split('?')[0];
    if (routes[withoutQuery]) return routes[withoutQuery];

    return routes['/'];
  }

  function getMainElement() {
    return document.querySelector('#app-main');
  }

  function getLoadingBar() {
    return document.querySelector('#spa-loading-bar');
  }

  function showLoading() {
    const bar = getLoadingBar();
    if (!bar) return;
    bar.classList.add('active');
    bar.classList.remove('done');
    bar.style.width = '70%';
  }

  function hideLoading() {
    const bar = getLoadingBar();
    if (!bar) return;
    bar.classList.remove('active');
    bar.classList.add('done');
    bar.style.width = '100%';

    setTimeout(() => {
      bar.classList.remove('done');
      bar.style.width = '0%';
    }, 400);
  }

  function renderView(html) {
    const main = getMainElement();
    if (!main) return;
    main.innerHTML = html;
  }

  function executeScripts(container) {
    const scripts = container.querySelectorAll('script');

    scripts.forEach((oldScript) => {
      const newScript = document.createElement('script');
      Array.from(oldScript.attributes).forEach((attribute) => {
        newScript.setAttribute(attribute.name, attribute.value);
      });
      newScript.textContent = oldScript.textContent;
      oldScript.parentNode.replaceChild(newScript, oldScript);
    });
  }

  function initializePage(path) {
    const main = getMainElement();
    if (!main) return;

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }

    executeScripts(main);

    document.dispatchEvent(new CustomEvent('bxpert:page-loaded', {
      detail: { path, route: matchRoute(path) }
    }));

    if (window.__closeMobileMenu) {
      window.__closeMobileMenu();
    }

    highlightActiveLink();
    window.scrollTo(0, 0);
    if (main.scrollTo) main.scrollTo(0, 0);
  }

  function highlightActiveLink() {
    const sidebar = document.querySelector('#sidebar');
    if (!sidebar) return;

    const links = sidebar.querySelectorAll('a[data-spa]');
    const currentPath = normalizeRoutePath(window.location.pathname);

    links.forEach((link) => {
      const href = normalizeRoutePath(link.getAttribute('href'));
      const active = href === currentPath || href === state.currentPath;
      link.classList.toggle('active', active);
    });
  }

  function showError(error) {
    const main = getMainElement();
    if (!main) return;

    main.innerHTML = `
      <div class="container mt-5">
        <div class="alert alert-danger" role="alert">
          <h4 class="alert-heading">Erro ao carregar página</h4>
          <p>${error && error.message ? error.message : 'Ocorreu um erro desconhecido.'}</p>
          <hr>
          <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.location.reload()">
            Recarregar página
          </button>
        </div>
      </div>
    `;
  }

  function showSessionExpired() {
    alert('A sua sessão expirou. Será redirecionado para o login.');
    window.location.href = 'login.php';
  }

  async function loadView(path) {
    const route = matchRoute(path);
    const fileToLoad = route.view || path;

    showLoading();

    try {
      if (state.abortController) {
        state.abortController.abort();
      }

      state.abortController = new AbortController();

      const response = await fetch(fileToLoad, {
        method: 'GET',
        credentials: 'same-origin',
        signal: state.abortController.signal,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        if (response.status === 401) {
          showSessionExpired();
          return null;
        }
        throw new Error(`HTTP ${response.status}`);
      }

      return await response.text();
    } catch (error) {
      if (error && error.name === 'AbortError') {
        return null;
      }
      console.error('BXpertRouter load error:', error);
      showError(error);
      return null;
    } finally {
      hideLoading();
    }
  }

  async function navigate(path, pushStateFlag = true) {
    if (state.isNavigating) return;
    if (!path) return;

    const routePath = normalizeRoutePath(path);
    if (state.currentPath === routePath && !pushStateFlag) return;

    state.isNavigating = true;

    try {
      state.currentPath = routePath;
      const html = await loadView(routePath);
      if (html === null) return;

      renderView(html);
      initializePage(routePath);

      if (pushStateFlag) {
        const finalUrl = routePath === '/' ? window.location.origin + '/' : routePath;
        history.pushState({ path: routePath }, '', finalUrl);
      }
    } finally {
      state.isNavigating = false;
    }
  }

  function bindLinks() {
    document.addEventListener('click', (event) => {
      const anchor = event.target.closest('a[data-spa]');
      if (!anchor) return;

      if (anchor.target === '_blank') return;
      if (anchor.hasAttribute('download')) return;
      if (event.ctrlKey || event.metaKey) return;

      const href = anchor.getAttribute('href');
      if (!href || href.startsWith('#')) return;

      const url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) return;

      event.preventDefault();
      navigate(url.pathname, true);
    });
  }

  function bindHistory() {
    window.addEventListener('popstate', () => {
      const path = window.location.pathname || '/';
      navigate(path, false);
    });
  }

  function init() {
    bindLinks();
    bindHistory();
    highlightActiveLink();
    console.log('BXpertRouter initialized');
  }

  const api = {
    init,
    navigate,
    loadPage: navigate,
    highlightActiveLink,
    matchRoute,
    normalizeRoutePath
  };

  window.BXpertRouter = api;
  window.SpaRouter = api;

  document.addEventListener('DOMContentLoaded', () => {
    api.init();
    api.highlightActiveLink();
  });

  return api;
})();































































































































