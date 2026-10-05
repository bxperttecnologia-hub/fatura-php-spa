export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

export async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch('/api/' + path, {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.APP.csrf },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));

  if (res.status === 401 && path !== 'auth/login') {
    document.dispatchEvent(new Event('app:unauthorized'));
  }
  if (!res.ok) throw new ApiError(data.message || `Erro ${res.status}`, res.status, data.errors || {});
  return data;
}
