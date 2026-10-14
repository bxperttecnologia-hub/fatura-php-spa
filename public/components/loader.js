export async function loadPageComponents(page) {
  const manifestResponse = await fetch('/components/manifest.json');
  if (!manifestResponse.ok) {
    throw new Error('Não foi possível carregar o manifesto de componentes.');
  }

  const manifest = await manifestResponse.json();
  const components = manifest.pages?.[page] || [];

  for (const name of components) {
    const placeholder = document.querySelector(`[data-component-placeholder="${name}"]`);
    let modalRoot = null;
    if (placeholder) {
      const response = await fetch(`/components/${name}/${name}.html`);
      if (!response.ok) throw new Error(`Não foi possível carregar o componente ${name}.`);
      const template = document.createElement('template');
      template.innerHTML = await response.text();
      modalRoot = template.content.querySelector('.modal');
      if (modalRoot) modalRoot.dataset.componentName = name;
      placeholder.replaceWith(template.content);
    }
    const component = await import(`/components/${name}/${name}.js`);
    window.APP_COMPONENTS ??= {};
    window.APP_COMPONENTS[name] = component;

    if (!modalRoot) continue;
    if (component.prepare) {
      try {
        await component.prepare(modalRoot);
      } catch (error) {
        console.error(error);
        const message = document.createElement('div');
        message.className = 'alert alert-danger';
        message.setAttribute('role', 'alert');
        message.textContent = error.message;
        modalRoot.querySelector('.modal-body')?.prepend(message);
      }
    }

    for (const trigger of document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target]')) {
      if (trigger.getAttribute('data-bs-target') !== `#${modalRoot.id}`) continue;
      trigger.removeAttribute('data-bs-toggle');
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        component.open().catch((error) => {
          console.error(error);
          const message = document.createElement('div');
          message.className = 'alert alert-danger';
          message.setAttribute('role', 'alert');
          message.textContent = error.message;
          trigger.insertAdjacentElement('afterend', message);
        });
      });
    }
  }
}
