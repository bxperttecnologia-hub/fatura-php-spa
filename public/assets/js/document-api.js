(function () {
  let apiConfig = null;
  let configRequest = null;

  async function getApiConfig() {
    if (apiConfig) return apiConfig;
    if (configRequest) return configRequest;

    configRequest = (async () => {
      const response = await fetch('/api.php?r=documents/config', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.data?.api_base_url || !payload.data?.company_id) {
        throw new Error(payload.message || 'Não foi possível obter a configuração da API de documentos.');
      }
      apiConfig = {
        apiBaseUrl: String(payload.data.api_base_url).replace(/\/+$/, ''),
        companyId: String(payload.data.company_id),
      };
      return apiConfig;
    })();

    try {
      return await configRequest;
    } finally {
      configRequest = null;
    }
  }

  async function request(path, options = {}) {
    const config = await getApiConfig();
    const url = new URL(`${config.apiBaseUrl}${path}`);
    url.searchParams.set('company_id', config.companyId);
    const response = await fetch(url, {
      ...options,
      credentials: 'omit',
      headers: {
        Accept: options.accept || 'application/pdf, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json',
        ...(options.headers || {}),
      },
    });

    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      throw new Error(payload.error || `A API de documentos respondeu com erro ${response.status}.`);
    }

    const blob = await response.blob();
    if (!blob.size) throw new Error('A API devolveu um ficheiro vazio.');
    return { blob, response };
  }

  function saveBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  }

  async function download(path, filename) {
    const { blob, response } = await request(path);
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename="?([^";]+)"?/i);
    saveBlob(blob, match?.[1] || filename || 'documento');
  }

  async function openPdf(path) {
    const popup = window.open('', '_blank');
    if (!popup) throw new Error('O navegador bloqueou a nova janela do documento.');
    try {
      const { blob, response } = await request(path, { accept: 'application/pdf, application/json' });
      if (!(response.headers.get('Content-Type') || '').includes('application/pdf')) {
        throw new Error('A API não devolveu um PDF.');
      }
      const url = URL.createObjectURL(blob);
      popup.location.replace(url);
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
      popup.close();
      throw error;
    }
  }

  window.BXDocumentApi = Object.freeze({ download, openPdf });
})();
