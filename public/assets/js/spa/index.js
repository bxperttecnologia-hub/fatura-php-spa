window.BXpertModal = window.BXpertModal || {};

(function () {
  'use strict';

  function open(selectorOrElement) {
    const element = typeof selectorOrElement === 'string'
      ? document.querySelector(selectorOrElement)
      : selectorOrElement;

    if (!element) return null;

    const instance = window.bootstrap && window.bootstrap.Modal
      ? new bootstrap.Modal(element)
      : null;

    if (instance) {
      instance.show();
    }

    return instance;
  }

  function close(selectorOrElement) {
    const element = typeof selectorOrElement === 'string'
      ? document.querySelector(selectorOrElement)
      : selectorOrElement;

    if (!element) return null;

    const instance = window.bootstrap && window.bootstrap.Modal
      ? window.bootstrap.Modal.getInstance(element)
      : null;

    if (instance) {
      instance.hide();
    }

    setTimeout(() => {
      document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('padding-right');
      document.body.style.removeProperty('overflow');
    }, 200);

    return instance;
  }

  function destroy(selectorOrElement) {
    const element = typeof selectorOrElement === 'string'
      ? document.querySelector(selectorOrElement)
      : selectorOrElement;

    if (!element) return;

    const instance = window.bootstrap && window.bootstrap.Modal
      ? window.bootstrap.Modal.getInstance(element)
      : null;

    if (instance) {
      instance.dispose();
    }
  }

  window.BXpertModal = {
    open,
    close,
    destroy
  };
})();

























