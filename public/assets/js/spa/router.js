/**
 * BXpert SPA Router
 * 
 * Único router consolidado da aplicação.
 * Responsabilidade: Rotas, navegação, history API, link interception.
 * 
 * Consolidação de:
 * - public/assets/js/router.js (antigo)
 * - public/assets/js/spa_router.js (refatorado)
 * - app1.js (removido - tinha função loadRoute() inexistente)
 */

const BXpertRouter = (function () {
  'use strict';

  const config = {
    mainSelector: '#app-main',
    loadingBarSelector: '#spa-loading-bar',
    linkSelector: 'a[data-spa]',
    sidebarSelector: '#sidebar',
    xRequestedWith: 'XMLHttpRequest'
  };

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

  let currentPath = null;
  let isNavigating = false;
  let abortController = null;

  function getBasePath() {
    const pathname = window.location.pathname || '/';

    if (pathname.endsWith('/public/index.php')) {
      return pathname.replace(/\/public\/index\.php$/, '/public');
    }

    if (pathname.endsWith('/index.php')) {
      return pathname.replace(/\/index\.php$/, '');
    }

    return pathname.replace(/\/+$/, '') || '/';
  }

  function stripBaseFromPath(path) {
    const base = getBasePath();
    if (base && base !== '/' && path.startsWith(base)) {
      return path.slice(base.length) || '/';
    }
    return path;
  }

  function routeKeyFromPath(path) {
    const raw = String(path || '/');
    const withoutHash = raw.split('#')[0];
    const withoutQuery = withoutHash.split('?')[0];

    let normalized = withoutQuery;

    if (/^https?:\/\//i.test(normalized)) {
      normalized = new URL(normalized, window.location.href).pathname;
    }

    normalized = stripBaseFromPath(normalized);

    if (/\/index\.php$/i.test(normalized)) {
      normalized = '/';
    } else if (/\.php$/i.test(normalized)) {
      const phpMatch = normalized.match(/^(.*)\.php$/i);
      normalized = phpMatch ? phpMatch[1] : normalized;
    }

    if (!normalized.startsWith('/')) {
      normalized = `/${normalized}`;
    }

    normalized = normalized.replace(/\/+$|\\+$/g, '');
    return normalized || '/';
  }

  function browserPathFromRoute(path) {
    const key = routeKeyFromPath(path);
    const base = getBasePath();

    if (base && base !== '/') {
      return `${base}${key === '/' ? '' : key}`;
    }

    return key;
  }

  function getMainEl() {
    return document.querySelector(config.mainSelector);
  }

  function getLoadingBar() {
    return document.querySelector(config.loadingBarSelector);
  }

  function showLoading() {
    const bar = getLoadingBar();
    if (!bar) return;
    bar.classList.remove('done');
    bar.classList.add('active');
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

  function matchRoute(path) {
    const routeKey = routeKeyFromPath(path);

    if (routes[routeKey]) {
      return routes[routeKey];
    }

    const pathWithoutQuery = routeKey.split('?')[0];
    if (routes[pathWithoutQuery]) {
      return routes[pathWithoutQuery];
    }

    return routes['/'];
  }

  async function loadView(path) {
    const route = matchRoute(path);
    const viewPath = route.view;

    showLoading();

    try {
      if (abortController) {
        abortController.abort();
      }
      abortController = new AbortController();

      const response = await fetch(viewPath, {
        signal: abortController.signal,
        headers: {
          'X-Requested-With': config.xRequestedWith
        }
      });

      if (!response.ok) {
        if (response.status === 401) {
          showSessionExpired();
          return null;
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const html = await response.text();
      return html;

    } catch (error) {
      if (error.name === 'AbortError') {
        console.log('Previous navigation aborted');
        return null;
      }
      console.error('Error loading view:', error);
      showError(error);
      return null;
    } finally {
      hideLoading();
    }
  }

  function renderView(html) {
    const main = getMainEl();
    if (!main) return;
    main.innerHTML = html;
  }

  function executeScripts(container) {
    const scripts = container.querySelectorAll('script');

    scripts.forEach((oldScript) => {
      const newScript = document.createElement('script');

      Array.from(oldScript.attributes).forEach((attr) => {
        newScript.setAttribute(attr.name, attr.value);
      });

      newScript.textContent = oldScript.textContent;
      oldScript.parentNode.replaceChild(newScript, oldScript);
    });
  }

  function initializePage(path) {
    const main = getMainEl();
    if (!main) return;

    if (window.lucide) {
      lucide.createIcons();
    }

    executeScripts(main);

    const event = new CustomEvent('bxpert:page-loaded', {
      detail: { path, route: matchRoute(path) }
    });
    document.dispatchEvent(event);

    if (window.__closeMobileMenu) {
      window.__closeMobileMenu();
    }

    highlightActiveLink();
    window.scrollTo(0, 0);
    main.scrollTo?.(0, 0);
  }

  function highlightActiveLink() {
    const sidebar = document.querySelector(config.sidebarSelector);
    if (!sidebar) return;

    const links = sidebar.querySelectorAll(config.linkSelector);
    const currentPathname = routeKeyFromPath(window.location.pathname);

    links.forEach((link) => {
      const href = link.getAttribute('href');
      const hrefPath = href ? routeKeyFromPath(href) : '';
      const isActive = hrefPath === currentPathname || href === currentPathname;
      link.classList.toggle('active', isActive);
    });
  }

  function showError(error) {
    const main = getMainEl();
    if (!main) return;
    main.innerHTML = `
      <div class="container mt-5">
        <div class="alert alert-danger" role="alert">
          <h4 class="alert-heading">Erro ao carregar página</h4>
          <p>${error?.message || 'Ocorreu um erro desconhecido'}</p>
          <hr>
          <p class="mb-0">
            <button class="btn btn-sm btn-outline-danger" onclick="window.location.reload()">
              Recarregar página
            </button>
          </p>
        </div>
      </div>
    `;
  }

  function showSessionExpired() {
    alert('Sua sessão expirou. Será redirecionado para a página de login.');
    window.location.href = 'login.php';
  }

  async function navigate(path, pushState = true) {
    const routeKey = routeKeyFromPath(path);

    if (isNavigating) return;
    if (routeKey === currentPath && !pushState) return;

    isNavigating = true;

    try {
      currentPath = routeKey;

      const html = await loadView(routeKey);
      if (html === null) return;

      renderView(html);
      initializePage(routeKey);

      if (pushState) {
        const browserPath = browserPathFromRoute(routeKey);
        history.pushState({ path: routeKey }, '', browserPath);
      }

    } finally {
      isNavigating = false;
    }
  }

  function bindLinks() {
    document.addEventListener('click', (event) => {
      const link = event.target.closest(config.linkSelector);

      if (!link) return;
      if (link.target === '_blank') return;
      if (link.origin && link.origin !== window.location.origin) return;
      if (link.hasAttribute('download')) return;
      if (event.ctrlKey || event.metaKey) return;

      event.preventDefault();

      const href = link.getAttribute('href');
      if (!href || href.startsWith('#')) return;

      const routeTarget = href.startsWith('http')
        ? new URL(href, window.location.href).pathname
        : href;

      navigate(routeTarget);
    });
  }

  function bindHistory() {
    window.addEventListener('popstate', () => {
      const path = routeKeyFromPath(window.location.pathname);
      navigate(path, false);
    });
  }

  function init() {
    bindLinks();
    bindHistory();
    highlightActiveLink();
    console.log('BXpertRouter initialized');
  }

  return {
    init,
    navigate,
    highlightActiveLink,
    loadPage: navigate
  };

})();

document.addEventListener('DOMContentLoaded', () => {
  BXpertRouter.init();
  BXpertRouter.highlightActiveLink();
});

window.BXpertRouter = BXpertRouter;
