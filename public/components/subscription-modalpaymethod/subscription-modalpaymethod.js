import { openComponent } from '../component-runtime.js';

export async function prepare(root) {
  const overview = await window.APP?.subscriptionReady;
  if (!overview) return;

  const planLabel = root.querySelector('#payPlanLabel');
  const amountLabel = root.querySelector('#payAmountLabel');
  if (planLabel) planLabel.textContent = overview.plan.name;
  if (amountLabel) {
    amountLabel.textContent = `${Number(overview.plan.price).toLocaleString('pt-PT', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} Kz`;
  }
}

export function open(options = {}) {
  return openComponent('subscription-modalpaymethod', options);
}
