window.BXpertSPA = window.BXpertSPA || {};

(function () {
  'use strict';

  const lifecycle = {
    beforeLeave() {
      return Promise.resolve();
    },
    destroyPage() {
      return Promise.resolve();
    },
    loadView(url) {
      return window.BXpertSPA.loadView(url);
    },
    render(html) {
      return window.BXpertSPA.renderView(html);
    },
    initializePage(path) {
      return window.BXpertSPA.initializeView(path);
    },
    afterEnter() {
      return Promise.resolve();
    }
  };

  window.BXpertSPA = {
    ...window.BXpertSPA,
    lifecycle
  };
})();

























