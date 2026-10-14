import { openComponent } from '../component-runtime.js';

export async function prepare(root) {
  const overview = await window.APP?.subscriptionReady;
  if (!overview) return;

  const plansContainer = root.querySelector('[data-subscription-plans]');
  if (!plansContainer) throw new Error('A lista de planos não está disponível.');

  const planCards = overview.plans.map((plan) => {
    const column = document.createElement('div');
    column.className = 'col-md-4';

    const card = document.createElement('div');
    card.className = 'border rounded-4 p-3 h-100 d-flex flex-column';
    card.dataset.planCode = plan.code;
    const isCurrent = plan.code === overview.plan_code;
    card.classList.toggle('border-primary', isCurrent);
    card.classList.toggle('border-2', isCurrent);

    const name = document.createElement('h6');
    name.className = 'fw-bold';
    name.textContent = plan.name;

    const price = document.createElement('div');
    price.className = 'fs-5 fw-bold mb-2';
    price.textContent = `${plan.price} Kz`;

    const limits = document.createElement('ul');
    limits.className = 'small text-muted flex-grow-1 ps-3 mb-3';
    for (const [label, key] of [
      ['Faturas/mês', 'invoice_limit_month'],
      ['Utilizadores', 'user_limit'],
      ['RH', 'rh_employee_limit'],
    ]) {
      const item = document.createElement('li');
      item.textContent = `${label}: ${plan[key] ?? '∞'}`;
      limits.append(item);
    }

    const currentButton = document.createElement('button');
    currentButton.className = 'btn btn-outline-secondary btn-sm';
    currentButton.type = 'button';
    currentButton.disabled = true;
    currentButton.dataset.currentPlan = '';
    currentButton.textContent = 'Plano atual';
    currentButton.classList.toggle('d-none', !isCurrent);

    const chooseButton = document.createElement('button');
    chooseButton.className = 'btn btn-primary btn-sm btnChoosePlan';
    chooseButton.type = 'button';
    chooseButton.dataset.code = plan.code;
    chooseButton.textContent = 'Selecionar';
    chooseButton.classList.toggle('d-none', isCurrent);

    card.append(name, price, limits, currentButton, chooseButton);
    column.append(card);
    return column;
  });
  plansContainer.replaceChildren(...planCards);
}

export function open(options = {}) {
  return openComponent('subscription-modalplans', options);
}
