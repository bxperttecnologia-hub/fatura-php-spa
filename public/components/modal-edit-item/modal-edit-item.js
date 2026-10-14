import { openComponent, populateSelect } from '../component-runtime.js';

export async function prepare(root, selectedCurrency) {
  const currencySelect = root.querySelector('select[name="currency"]');
  if (!currencySelect || currencySelect.dataset.loaded === 'true') return;
  const companyCurrency = await fetch('/api/v1/metadata/currency');
  if (!companyCurrency.ok) {
    throw new Error('Não foi possível determinar a moeda da empresa.');
  }
  const currency = await companyCurrency.json();
  await populateSelect(currencySelect, '/api/v1/metadata/currencies', {
    value: 'iso_code',
    label: (row) => `${row.currency} (${row.iso_code})`,
    selectedValue: selectedCurrency ?? currency.currency,
  });
  currencySelect.dataset.loaded = 'true';
}

export function open(options = {}) {
  const load = async (root, form) => {
    await prepare(root, options.values?.currency);
    await options.load?.(root, form);
  };

  return openComponent('modal-edit-item', { ...options, formSelector: '#editItemForm', load });
}
