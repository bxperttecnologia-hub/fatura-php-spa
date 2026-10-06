window.BXpertAuth = window.BXpertAuth || {};

(function () {
  'use strict';

  function isAuthenticated() {
    return !!(window.__BXPERT_SESSION__ || document.body.dataset.authenticated === 'true');
  }

  function requireAuth() {
    if (isAuthenticated()) {
      return true;
    }

    if (window.confirm('Sessão inválida. Deseja fazer login novamente?')) {
      window.location.href = 'login.php';
    }

    return false;
  }

  function handleUnauthorized() {
    alert('Sessão expirada. A sua sessão será reiniciada.');
    window.location.href = 'login.php';
  }

  window.BXpertAuth = {
    isAuthenticated,
    requireAuth,
    handleUnauthorized
  };
})();


























