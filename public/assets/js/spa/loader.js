(() => {
  'use strict';
  window.BXpertAPI = window.BXpertAPI || {};
  window.BXpertAPI.request = async function (url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    if (!response || response.status >= 400) {
      throw new Error('Request falhou: ' + (response ? response.status : 'sem resposta'));
    }
    return response;
  };
  window.BXpertAPI.get = async function (url, params = {}, options = {}) {
    const query = new URLSearchParams(params);
    const finalUrl = query.toString() ? `${url}?${query.toString()}` : url;
    return window.BXpertAPI.request(finalUrl, { ...options, method: 'GET' });
  };
  window.BXpertAPI.post = async function (url, data = {}, options = {}) {
    return window.BXpertAPI.request(url, { ...options, method: 'POST', body: data });
  };
  window.BXpertAPI.json = async function (url, payload = {}, options = {}) {
    const response = await window.BXpertAPI.request(url, {
      ...options,
      method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      body: JSON.stringify(payload)
    });
    return response.json();
  };
})();




























































































































































a
