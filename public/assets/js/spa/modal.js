window.BXpertSPA = window.BXpertSPA || {};

(function () {
  'use strict';

  function showLoading() {
    const bar = document.querySelector('#spa-loading-bar');
    if (!bar) return;
    bar.classList.add('active');
    bar.classList.remove('done');
    bar.style.width = '70%';
  }

  function hideLoading() {
    const bar = document.querySelector('#spa-loading-bar');
    if (!bar) return;
    bar.classList.remove('active');
    bar.classList.add('done');
    bar.style.width = '100%';

    setTimeout(() => {
      bar.classList.remove('done');
      bar.style.width = '0%';
    }, 350);
  }

  async function loadView(url) {
    showLoading();

    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });

      if (!response.ok) {
        throw new Error('Erro ao carregar a página: ' + response.status);
      }

      return await response.text();
    } finally {
      hideLoading();
    }
  }

  function renderView(html) {
    const main = document.querySelector('#app-main') || document.body;
    if (!main) return;
    main.innerHTML = html;
  }

  function initializeView(path) {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }

    document.dispatchEvent(new CustomEvent('bxpert:page-loaded', {
      detail: { path }
    }));
  }

  window.BXpertSPA = {
    showLoading,
    hideLoading,
    loadView,
    renderView,
    initializeView
  };
})();

























