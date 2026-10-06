(() => {
  'use strict';

  const config = {
    mainSelector: '#app-main',
    loadingBarSelector: '#spa-loading-bar',
    linkSelector: 'a[data-spa]',
    sidebarSelector: '#sidebar',
    xRequestedWith: 'XMLHttpRequest'
  };

  const state = {
    currentPath: '/',
    abortController: null,
    initializedPages: new Set()
  };

  function getPageMap() {
    const pages = window.APP && window.APP.pages;
    if (pages && typeof pages === 'object') {
      return pages;
    }
    return {};
  }

  function getBasePath() {
    if (window.APP && typeof window.APP.baseUrl === 'string' && window.APP.baseUrl.trim()) {
      const base = window.APP.baseUrl.trim();
      return base === '/' ? '/' : base.replace(/\/+$/, '');
    }

    const pathname = window.location.pathname || '/';
    return pathname === '/' ? '/' : pathname.replace(/\/+$/, '');
  }

  function browserPathFromRoute(routeKey) {
    const base = getBasePath();
    const path = routeKey === '/' ? '' : routeKey;
    if (base && base !== '/') {
      return `${base}${path}`;
    }
    return path || '/';
  }

  function routeKeyFromPath(rawPath) {
    const value = String(rawPath || '/');
    const withoutHash = value.split('#')[0];
    const withoutQuery = withoutHash.split('?')[0];

    let normalized = withoutQuery;
    if (/^https?:\/\//i.test(normalized)) {
      normalized = new URL(normalized, window.location.href).pathname;
    }

    const base = getBasePath();
    if (base && base !== '/' && normalized.startsWith(base)) {
      normalized = normalized.slice(base.length) || '/';
    }

    if (/\/index\.php$/i.test(normalized)) {
      normalized = '/';
    } else if (/\.php$/i.test(normalized)) {
      normalized = normalized.replace(/\.php$/i, '');
    }

    if (!normalized.startsWith('/')) {
      normalized = `/${normalized}`;
    }

    normalized = normalized.replace(/\/+$/, '') || '/';
    return normalized;
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
    }, 350);
  }

  function routeExists(routeKey) {
    const pages = getPageMap();
    return Object.prototype.hasOwnProperty.call(pages, routeKey) || routeKey === '/';
  }

  function fallbackRoute() {
    const pages = getPageMap();
    return Object.keys(pages).length ? Object.keys(pages)[0] : '/';
  }

  function resolveRoute(routeKey) {
    const key = routeKeyFromPath(routeKey);
    if (routeExists(key)) {
      return key;
    }
    return fallbackRoute();
  }

  function viewUrlForRoute(routeKey) {
    const base = getBasePath();
    const normalized = routeKeyFromPath(routeKey);
    const qs = `__route=${encodeURIComponent(normalized)}`;
    return `${base === '/' ? '' : base}/view.php?${qs}`;
  }

  function activateSidebar(routeKey) {
    const sidebar = document.querySelector(config.sidebarSelector);
    if (!sidebar) return;

    const current = routeKeyFromPath(routeKey);
    sidebar.querySelectorAll(config.linkSelector).forEach((link) => {
      const href = link.getAttribute('href') || '';
      const next = routeKeyFromPath(href);
      link.classList.toggle('active', next === current);
    });
  }

  function destroyPageState() {
    const main = getMainEl();
    if (!main) return;

    const pageKey = main.dataset.pageKey;
    if (pageKey) {
      const pageEvent = new CustomEvent('bxpert:page-before-leave', { detail: { path: pageKey } });
      document.dispatchEvent(pageEvent);
    }

    if (window.jQuery) {
      try {
        jQuery.each(jQuery('.dataTable').data(), function (_, value) {
          if (value && typeof value.destroy === 'function') {
            value.destroy();
          }
        });
      } catch (error) {
        console.warn('DataTable cleanup skipped:', error);
      }
    }

    if (window.Chart && window.Chart.instances) {
      try {
        Object.values(window.Chart.instances || {}).forEach((chart) => chart.destroy?.());
      } catch (error) {
        console.warn('Chart cleanup skipped:', error);
      }
    }

    if (window.__bxpertPageTimers) {
      window.__bxpertPageTimers.forEach((timer) => {
        clearTimeout(timer);
        clearInterval(timer);
      });
      window.__bxpertPageTimers = [];
    }

    document.querySelectorAll('.modal.show').forEach((modal) => {
      const instance = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(modal) : null;
      if (instance) instance.hide();
    });
  }

  function initializePage(path) {
    const main = getMainEl();
    if (!main) return;

    main.dataset.pageKey = path;
    if (window.lucide && typeof lucide.createIcons === 'function') {
      lucide.createIcons();
    }

    const initEvent = new CustomEvent('bxpert:page-loaded', {
      detail: { path, route: resolveRoute(path) }
    });
    document.dispatchEvent(initEvent);

    if (window.__closeMobileMenu) {
      window.__closeMobileMenu();
    }

    activateSidebar(path);
    window.scrollTo(0, 0);
    main.scrollTo?.(0, 0);
  }

  async function navigate(path, pushState = true) {
    const routeKey = resolveRoute(path); 
    if (!routeKey) {
      return;
    }

    if (state.abortController) {
      state.abortController.abort();
    }

    const controller = new AbortController();
    state.abortController = controller;
    state.currentPath = routeKey;

    showLoading();

    try {
      const url = viewUrlForRoute(routeKey);
      const response = await fetch(url, {
        signal: controller.signal,
        headers: { 'X-Requested-With': config.xRequestedWith }
      });

      if (!response.ok) {
        if (response.status === 401) {
          window.location.href = '/login';
          return;
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();
      if (payload && payload.redirect) {
        if (payload.external) {
          window.location.assign(payload.redirect);
          return;
        }
        return navigate(payload.redirect, true);
      }

      if (payload && payload.session_expired) {
        window.location.href = '/login';
        return;
      }

      const main = getMainEl();
      if (!main) {
        return;
      }

      destroyPageState();
      main.innerHTML = payload && payload.html ? payload.html : '';
      if (payload && payload.title) {
        document.title = payload.title;
      }
      initializePage(routeKey);

      if (pushState) {
        const browserPath = browserPathFromRoute(routeKey);
        history.pushState({ path: routeKey }, '', browserPath);
      }
    } catch (error) {
      if (error && error.name !== 'AbortError') {
        const main = getMainEl();
        if (main) {
          main.innerHTML = '<div class="alert alert-danger mt-3">Não foi possível carregar a página.</div>';
        }
      }
    } finally {
      hideLoading();
    }
  }

  function bindLinks() {
    document.addEventListener('click', (event) => {
      const link = event.target.closest(config.linkSelector);
      if (!link) {
        return;
      }
      if (link.target === '_blank' || link.hasAttribute('download')) {
        return;
      }
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
        return;
      }

      const href = link.getAttribute('href') || '';
      if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return;
      }

      const url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) {
        return;
      }

      event.preventDefault();
      navigate(url.pathname + url.search, true);
    });
  }

  function bindHistory() {
    window.addEventListener('popstate', () => {
      const routeKey = routeKeyFromPath(window.location.pathname + window.location.search);
      navigate(routeKey, false);
    });
  }

  function bootstrap() {
    bindLinks();
    bindHistory();
    const initial = routeKeyFromPath(window.location.pathname + window.location.search);
    activateSidebar(initial);
    state.currentPath = initial;
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      navigate(initial, false);
    } else {
      window.addEventListener('DOMContentLoaded', () => navigate(initial, false), { once: true });
    }
  }

  window.BXpertRouter = {
    init: bootstrap,
    navigate,
    activateSidebar,
    loadPage: navigate
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
  } else {
    bootstrap();
  }
})();
