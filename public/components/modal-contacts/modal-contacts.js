import { openComponent } from '../component-runtime.js';

export function open(options = {}) {
  return openComponent('modal-contacts', {
    ...options,
    selectionSelector: '.contact-item',
    selectionResult: (item) => ({
      contactId: item.dataset.contactId,
      name: item.dataset.name,
      nif: item.dataset.nif,
    }),
  });
}
