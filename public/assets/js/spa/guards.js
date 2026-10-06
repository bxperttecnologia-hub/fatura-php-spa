window.BXpertEvents = window.BXpertEvents || {};

(function () {
  'use strict';

  const listeners = new Map();

  function on(eventName, callback) {
    if (!listeners.has(eventName)) {
      listeners.set(eventName, new Set());
    }
    listeners.get(eventName).add(callback);
    return callback;
  }

  function off(eventName, callback) {
    if (!listeners.has(eventName)) return;
    listeners.get(eventName).delete(callback);
  }

  function emit(eventName, payload) {
    if (!listeners.has(eventName)) return;

    listeners.get(eventName).forEach((callback) => {
      try {
        callback(payload);
      } catch (error) {
        console.error(`Erro no evento ${eventName}:`, error);
      }
    });
  }

  window.BXpertEvents = {
    on,
    off,
    emit
  };
})();


























