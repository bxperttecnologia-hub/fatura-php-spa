const mountedComponents = new Map();

const readJson = async (response) => {
  if (response.status === 204) return null;
  const contentType = response.headers.get('content-type') || '';
  if (!contentType.includes('application/json')) {
    throw new Error(`Resposta inesperada do servidor (${response.status}).`);
  }
  return response.json();
};

export async function mountComponent(name, target = document.body) {
  if (!mountedComponents.has(name)) {
    const existing = document.querySelector(`[data-component-name="${name}"]`);
    const pending = existing
      ? Promise.resolve(existing)
      : fetch(`/components/${name}/${name}.html`)
        .then(async (response) => {
          if (!response.ok) {
            throw new Error(`Não foi possível carregar o componente ${name}.`);
          }
          const markup = await response.text();
          const template = document.createElement('template');
          template.innerHTML = markup;
          const root = template.content.querySelector('.modal');
          if (!root) {
            throw new Error(`O componente ${name} não contém um modal.`);
          }
          root.dataset.componentName = name;
          document.body.append(template.content);
          return root;
        });
    mountedComponents.set(name, pending);
  }

  const root = await mountedComponents.get(name);
  if (target !== document.body && !target.contains(root)) target.append(root);
  return root;
}

export async function populateSelect(select, url, { value, label, selectedValue } = {}) {
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Não foi possível carregar as opções (${response.status}).`);
  }
  const data = await readJson(response);
  const rows = Array.isArray(data) ? data : data?.data;
  if (!Array.isArray(rows)) throw new Error('O servidor devolveu uma lista inválida.');

  const placeholder = select.options[0]?.cloneNode(true);
  select.replaceChildren();
  if (placeholder) select.append(placeholder);
  for (const row of rows) {
    const option = document.createElement('option');
    option.value = String(value ? row[value] : row.value);
    option.textContent = String(label ? label(row) : row.label);
    if (row.translationKey) option.dataset.i18n = row.translationKey;
    option.selected = String(selectedValue ?? '') === option.value;
    select.append(option);
  }
  if (!selectedValue && placeholder) select.selectedIndex = 0;
}

const clearFieldErrors = (form) => {
  form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
  form.querySelectorAll('[data-component-error]').forEach((message) => message.remove());
};

const showFieldErrors = (form, errors) => {
  const entries = Array.isArray(errors)
    ? errors.map((error) => [error.path || error.param, error.msg || error.message])
    : Object.entries(errors || {});

  for (const [name, value] of entries) {
    const field = form.elements.namedItem(name);
    if (!field || !('classList' in field)) continue;
    field.classList.add('is-invalid');
    const message = document.createElement('div');
    message.className = 'invalid-feedback';
    message.dataset.componentError = '';
    message.textContent = Array.isArray(value) ? value.join(', ') : String(value);
    field.insertAdjacentElement('afterend', message);
  }
};

const showRequestError = (root, error) => {
  let message = root.querySelector('[data-component-request-error]');
  if (!message) {
    message = document.createElement('div');
    message.className = 'alert alert-danger';
    message.dataset.componentRequestError = '';
    message.setAttribute('role', 'alert');
    root.querySelector('.modal-body')?.prepend(message);
  }
  message.textContent = error.message;
};

export async function openComponent(name, options = {}) {
  const root = await mountComponent(name);
  const modal = window.bootstrap?.Modal?.getOrCreateInstance(root);
  if (!modal) throw new Error('O componente Bootstrap Modal não está disponível.');

  const form = options.formSelector ? root.querySelector(options.formSelector) : root.querySelector('form');
  if (form && options.values) {
    for (const [key, value] of Object.entries(options.values)) {
      const field = form.elements.namedItem(key);
      if (field) field.value = value ?? '';
    }
  }

  if (options.load) await options.load(root, form);

  return new Promise((resolve, reject) => {
    const controller = new AbortController();
    let settled = false;
    let submitted = false;

    const finish = (result) => {
      if (settled) return;
      settled = true;
      controller.abort();
      resolve(result);
    };

    root.addEventListener('hidden.bs.modal', () => {
      finish(submitted ? { status: 'saved', data: null } : { status: 'cancelled' });
    }, { signal: controller.signal, once: true });
    if (options.savedEvent) {
      document.addEventListener(options.savedEvent, (event) => {
        finish({ status: 'saved', data: event.detail ?? null });
        modal.hide();
      }, { signal: controller.signal, once: true });
    }
    if (options.selectionSelector) {
      root.addEventListener('click', (event) => {
        const selected = event.target.closest(options.selectionSelector);
        if (!selected || !root.contains(selected)) return;
        const data = options.selectionResult ? options.selectionResult(selected) : selected.dataset;
        finish({ status: 'saved', data });
        modal.hide();
      }, { signal: controller.signal });
    }

    if (form && options.submitUrl) {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(form);
        if (!form.reportValidity()) return;

        const submitButton = event.submitter || form.querySelector('[type="submit"]');
        if (submitButton) submitButton.disabled = true;
        try {
          const request = options.buildRequest
            ? options.buildRequest(form)
            : {
              method: options.method || form.getAttribute('method') || 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(Object.fromEntries(new FormData(form))),
            };
          const response = await fetch(options.submitUrl, request);
          const body = await readJson(response);
          if (response.status === 422) {
            showFieldErrors(form, body?.errors);
            return;
          }
          if (!response.ok) {
            throw new Error(body?.message || `O pedido falhou (${response.status}).`);
          }
          submitted = true;
          finish({ status: 'saved', data: body });
          modal.hide();
        } catch (error) {
          if (options.onError) options.onError(error, root);
          else showRequestError(root, error);
        } finally {
          if (submitButton?.isConnected) submitButton.disabled = false;
        }
      }, { signal: controller.signal });
    } else if (form) {
      form.addEventListener('submit', () => {
        submitted = form.checkValidity();
      }, { signal: controller.signal });
    }

    try {
      options.beforeShow?.(root, modal);
      if (!options.alreadyOpened) modal.show();
    } catch (error) {
      controller.abort();
      reject(error);
    }
  });
}
