import { openComponent, populateSelect } from '../component-runtime.js';

export async function prepare(root, values = {}) {
  const copiesSelect = root.querySelector('#numberCopys');
  if (copiesSelect && copiesSelect.dataset.loaded !== 'true') {
    await populateSelect(copiesSelect, '/api/v1/metadata/number-copies', {
      value: 'value',
      label: (row) => row.label,
      selectedValue: values.numberCopys ?? 1,
    });
    copiesSelect.dataset.loaded = 'true';
  }
  const countrySelect = root.querySelector('select[name="country"]');
  if (countrySelect && countrySelect.dataset.loaded !== 'true') {
    await populateSelect(countrySelect, '/api/v1/metadata/countries', {
      value: 'name',
      label: (row) => row.name,
      selectedValue: values.country,
    });
    countrySelect.dataset.loaded = 'true';
  }
}

export function open(options = {}) {
  const load = async (root, form) => {
    await prepare(root, options.values);
    await options.load?.(root, form);
  };

  return openComponent('contact-form', {
    ...options,
    formSelector: '#contactForm',
    savedEvent: 'contact:saved',
    load,
  });
}
