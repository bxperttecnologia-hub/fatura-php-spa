(function () {
  'use strict';

  const attributePattern = /(?:^|[_\-\s])(?:prices?|precos?|pvp|valor|amount|salary|salario|wage|cost|custo|totals?|subtotals?|payment|pagamento|commissions?|comissoes|bonuses?|allowances?|indemnizacao|indemnity|rent|renda|credit|debt|loan|expense|despesa|net_salary|gross|fee|cash|dinheiro|retention|retencao|sales|discounts?|discount_amount|discount_value|desconto_valor)(?:$|[_\-\s])/i;
  const labelPattern = /\b(pre[cç]o|valor|montante|sal[aá]rio|custo|total|pagamento|comiss[aã]o|b[oó]nus|subs[ií]dio|indemniza[cç][aã]o|renda|cr[eé]dito|d[ií]vida|despesa|reten[cç][aã]o|fee)\b/i;
  const selector = 'input:not([type]), input[type="text"], input[type="number"]';

  function isMoneyInput(input) {
    if (!(input instanceof HTMLInputElement) || input.type === 'hidden' || input.disabled) return false;
    if (input.hasAttribute('data-money-input') || input.hasAttribute('data-price-display')) return true;

    const attributes = [
      input.name,
      input.id,
      input.className,
      input.getAttribute('placeholder'),
      input.getAttribute('aria-label'),
    ].filter(Boolean).join(' ').replace(/([a-z])([A-Z])/g, '$1 $2');
    if (attributePattern.test(attributes)) return true;

    const label = input.labels && Array.from(input.labels).map((item) => item.textContent).join(' ');
    return Boolean(label && labelPattern.test(label));
  }

  function parse(value) {
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    let source = String(value ?? '').trim().replace(/[\s\u00a0\u202f]/g, '').replace(/[^\d,.\-]/g, '');
    if (!source || source === '-' || source === ',' || source === '.') return null;

    const comma = source.lastIndexOf(',');
    const dot = source.lastIndexOf('.');
    if (comma !== -1 && dot !== -1) {
      const decimal = comma > dot ? ',' : '.';
      source = source.replace(/[,.]/g, (separator, offset) => (
        separator === decimal && offset === Math.max(comma, dot) ? '.' : ''
      ));
    } else if (comma !== -1) {
      source = source.replace(/,/g, (separator, offset) => offset === comma ? '.' : '');
    }

    const result = Number(source);
    return Number.isFinite(result) ? result : null;
  }

  function canonical(value) {
    const number = parse(value);
    return number === null ? '' : number.toFixed(2);
  }

  function format(value) {
    const normalized = canonical(value);
    if (!normalized) return '';
    const [whole, decimals] = normalized.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return `${grouped},${decimals}`;
  }

  function caretAfterDigits(value, digits) {
    if (digits <= 0) return 0;
    let seen = 0;
    for (let index = 0; index < value.length; index += 1) {
      if (/\d/.test(value[index]) && ++seen === digits) return index + 1;
    }
    return value.length;
  }

  function validate(input) {
    const value = parse(input.dataset.moneyRaw ?? input.value);
    let message = '';
    if (value !== null && input.min !== '' && value < Number(input.min)) {
      message = `O valor deve ser igual ou superior a ${input.min}.`;
    } else if (value !== null && input.max !== '' && value > Number(input.max)) {
      message = `O valor deve ser igual ou inferior a ${input.max}.`;
    }
    input.setCustomValidity(message);
  }

  function syncCompanion(input, value) {
    if (!input.hasAttribute('data-price-display')) return;
    const companion = input.form?.querySelector('[data-price-value]');
    if (companion) companion.value = value;
  }

  function initialize(input) {
    if (!isMoneyInput(input) || input.dataset.moneyInitialized === 'true') return;
    input.dataset.moneyInitialized = 'true';
    input.dataset.moneyType = input.type;
    if (input.type === 'number') input.type = 'text';
    input.inputMode = 'decimal';
    input.autocomplete = input.autocomplete || 'off';
    input.dataset.moneyRaw = canonical(input.value);
    input.value = format(input.dataset.moneyRaw);
    syncCompanion(input, input.dataset.moneyRaw);
    validate(input);
  }

  function initializeWithin(root) {
    if (root instanceof HTMLInputElement) initialize(root);
    root.querySelectorAll?.(selector).forEach(initialize);
  }

  function refreshWithin(root) {
    const inputs = root instanceof HTMLInputElement ? [root] : Array.from(root.querySelectorAll?.(selector) ?? []);
    inputs.forEach((input) => {
      if (!isMoneyInput(input)) return;
      initialize(input);
      input.dataset.moneyRaw = canonical(input.value);
      input.value = format(input.dataset.moneyRaw);
      syncCompanion(input, input.dataset.moneyRaw);
      validate(input);
    });
  }

  function rawValue(input) {
    return canonical(input.dataset.moneyRaw ?? input.value);
  }

  function installJQueryCompatibility() {
    const $ = window.jQuery;
    if (!$ || $.fn.moneyInputsInstalled) return;
    $.fn.moneyInputsInstalled = true;

    const originalVal = $.fn.val;
    $.fn.val = function (value) {
      if (arguments.length === 0) {
        const input = this[0];
        if (input && isMoneyInput(input) && input.dataset.moneyInitialized === 'true') {
          return rawValue(input);
        }
        return originalVal.call(this);
      }

      const result = originalVal.apply(this, arguments);
      this.each(function () {
        if (!isMoneyInput(this)) return;
        initialize(this);
        this.dataset.moneyRaw = canonical(this.value);
        this.value = format(this.dataset.moneyRaw);
        syncCompanion(this, this.dataset.moneyRaw);
        validate(this);
      });
      return result;
    };

    const originalSerializeArray = $.fn.serializeArray;
    $.fn.serializeArray = function () {
      const inputsByName = new Map();
      this.find(selector).each(function () {
        if (!isMoneyInput(this) || this.dataset.moneyInitialized !== 'true' || !this.name) return;
        const inputs = inputsByName.get(this.name) || [];
        inputs.push(this);
        inputsByName.set(this.name, inputs);
      });
      const offsets = new Map();
      return originalSerializeArray.call(this).map((entry) => {
        const inputs = inputsByName.get(entry.name);
        if (!inputs?.length) return entry;
        const offset = offsets.get(entry.name) || 0;
        const input = inputs[Math.min(offset, inputs.length - 1)];
        offsets.set(entry.name, offset + 1);
        return { ...entry, value: rawValue(input) };
      });
    };
  }

  document.addEventListener('focusin', (event) => {
    const input = event.target;
    if (!isMoneyInput(input)) return;
    initialize(input);
    input.dataset.moneyRaw = canonical(input.value);
    input.value = format(input.dataset.moneyRaw);
    syncCompanion(input, input.dataset.moneyRaw);
    input.dataset.moneyEditing = 'true';
  });

  document.addEventListener('keydown', (event) => {
    const input = event.target;
    if (!isMoneyInput(input) || input.dataset.moneyInitialized !== 'true') return;

    if (event.key === ',' || event.key === '.') {
      event.preventDefault();
      const comma = input.value.indexOf(',');
      if (comma !== -1) input.setSelectionRange(comma + 1, comma + 1);
      return;
    }

    if (event.key === 'Backspace' || event.key === 'Delete') {
      const start = input.selectionStart;
      const end = input.selectionEnd;
      if (start !== end) return;
      const index = event.key === 'Backspace' ? start - 1 : start;
      if (index >= 0 && input.value[index] === ' ') {
        event.preventDefault();
        const digitIndex = event.key === 'Backspace' ? index - 1 : index + 1;
        if (digitIndex < 0 || digitIndex >= input.value.length) return;
        input.setSelectionRange(digitIndex, digitIndex + 1);
        input.setRangeText('', digitIndex, digitIndex + 1, 'start');
        input.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }
  });

  document.addEventListener('input', (event) => {
    const input = event.target;
    if (!isMoneyInput(input)) return;
    initialize(input);
    const caret = input.selectionStart ?? input.value.length;
    const digitCount = (input.value.slice(0, caret).match(/\d/g) || []).length;
    const normalized = canonical(input.value);
    input.dataset.moneyRaw = normalized;
    input.value = normalized ? format(normalized) : '';
    syncCompanion(input, normalized);
    const maskedCaret = caretAfterDigits(input.value, digitCount);
    input.setSelectionRange(maskedCaret, maskedCaret);
    validate(input);
  });

  document.addEventListener('focusout', (event) => {
    const input = event.target;
    if (!isMoneyInput(input)) return;
    initialize(input);
    input.dataset.moneyRaw = canonical(input.value);
    input.value = format(input.dataset.moneyRaw);
    syncCompanion(input, input.dataset.moneyRaw);
    delete input.dataset.moneyEditing;
    validate(input);
  });

  document.addEventListener('formdata', (event) => {
    const valuesByName = new Map();
    for (const input of event.target.querySelectorAll?.(selector) ?? []) {
      if (isMoneyInput(input) && input.dataset.moneyInitialized === 'true' && input.name) {
        const values = valuesByName.get(input.name) || [];
        values.push(rawValue(input));
        valuesByName.set(input.name, values);
      }
    }
    valuesByName.forEach((values, name) => {
      event.formData.delete(name);
      values.forEach((value) => event.formData.append(name, value));
    });
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    const inputs = Array.from(form.querySelectorAll(selector)).filter((input) => (
      isMoneyInput(input) && input.dataset.moneyInitialized === 'true'
    ));
    inputs.forEach((input) => { input.value = rawValue(input); });
    setTimeout(() => inputs.forEach((input) => { input.value = format(input.dataset.moneyRaw); }), 0);
  }, true);

  const observer = new MutationObserver((records) => {
    records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType === Node.ELEMENT_NODE) initializeWithin(node);
    }));
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  window.MoneyInputFormatter = { canonical, format, initialize: initializeWithin, parse, refresh: refreshWithin };
  installJQueryCompatibility();
  initializeWithin(document);
})();
