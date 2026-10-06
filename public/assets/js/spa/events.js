window.BXpertAPI = window.BXpertAPI || {};

(function () {
  'use strict';

  const defaultHeaders = {
    'X-Requested-With': 'XMLHttpRequest'
  };

  function handleResponse(response) {
    if (!response) {
      throw new Error('Resposta vazia');
    }

    if (response.status >= 400) {
      throw new Error('Request falhou: ' + response.status);
    }

    return response;
  }

  async function request(url, options = {}) {
    const mergedOptions = {
      credentials: 'same-origin',
      headers: {
        ...defaultHeaders,
        ...(options.headers || {})
      },
      ...options
    };

    const response = await fetch(url, mergedOptions);
    handleResponse(response);
    return response;
  }

  async function get(url, params = {}, options = {}) {
    const query = new URLSearchParams(params);
    const finalUrl = query.toString() ? `${url}?${query.toString()}` : url;
    return request(finalUrl, { ...options, method: 'GET' });
  }

  async function post(url, data = {}, options = {}) {
    return request(url, {
      ...options,
      method: 'POST',
      body: data
    });
  }

  async function json(url, payload = {}, options = {}) {
    return request(url, {
      ...options,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers || {})
      },
      body: JSON.stringify(payload)
    }).then((response) => response.json());
  }

  async function formData(url, payload = {}, options = {}) {
    const form = payload instanceof FormData ? payload : new FormData();

    Object.entries(payload).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        form.append(key, value);
      }
    });

    return post(url, form, {
      ...options,
      headers: {
        ...(options.headers || {})
      }
    });
  }

  window.BXpertAPI = {
    request,
    handleResponse,
    get,
    post,
    json,
    formData
  };
})();

























