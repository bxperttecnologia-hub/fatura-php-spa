/* Recursos Humanos — utilitários partilhados pelas páginas do módulo (window.RH) */
(function (window, $) {
  'use strict';

  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const num = value => {
    const n = Number(String(value ?? '').replace(/\s/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
  };
  // 1 234 567,89 (separador de milhares sempre, também em números de 4 dígitos)
  const money = value => {
    const n = Number(value || 0);
    const [int, dec] = Math.abs(n).toFixed(2).split('.');
    return `${n < 0 ? '-' : ''}${int.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0')},${dec}`;
  };
  const kz = value => `${money(value)} Kz`;
  const months = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  const monthLabel = ym => {
    if (!/^\d{4}-\d{2}$/.test(ym || '')) return ym || '—';
    const [y, m] = ym.split('-');
    const name = months[Number(m) - 1];
    return `${name.charAt(0).toUpperCase()}${name.slice(1)} ${y}`;
  };
  const currentMonth = () => new Date().toISOString().slice(0, 7);
  const dateLabel = d => (d && !String(d).startsWith('0000')) ? String(d).slice(0, 10).split('-').reverse().join('/') : '—';

  function errorMessage(xhr, fallback) {
    const r = xhr && xhr.responseJSON;
    return (r && (r.message || r.error)) || fallback || 'Ocorreu um erro. Tente novamente.';
  }

  /** POST JSON com mensagens de erro já tratadas. Devolve uma Promise com a resposta. */
  function post(url, data) {
    return new Promise((resolve, reject) => {
      $.ajax({ url, method: 'POST', data, dataType: 'json', traditional: false })
        .done(resp => (resp && resp.success === false) ? reject(resp.message || 'Operação não concluída.') : resolve(resp))
        .fail(xhr => reject(errorMessage(xhr)));
    });
  }

  function get(url, data) {
    return new Promise((resolve, reject) => {
      $.ajax({ url, method: 'GET', data, dataType: 'json' })
        .done(resolve)
        .fail(xhr => reject(errorMessage(xhr)));
    });
  }

  function toast(message, icon = 'success') {
    if (window.Swal) {
      window.Swal.fire({ toast: true, position: 'top-end', icon, title: message, showConfirmButton: false, timer: 3200, timerProgressBar: true });
    } else {
      window.alert(message);
    }
  }

  function fail(message) {
    if (window.Swal) window.Swal.fire('Atenção', String(message), 'warning');
    else window.alert(message);
  }

  /** Mostra o resultado de uma acção em massa (mensagem + lista de erros). */
  function report(resp) {
    const errors = (resp && resp.errors) || [];
    if (!errors.length) return toast(resp.message || 'Concluído.');
    window.Swal.fire({
      icon: resp.done || resp.sent || resp.created ? 'info' : 'warning',
      title: resp.message || 'Concluído com avisos',
      html: `<ul class="text-start small mb-0">${errors.map(e => `<li>${esc(e)}</li>`).join('')}</ul>`,
    });
  }

  async function confirm(title, text, confirmText = 'Confirmar', icon = 'question') {
    const r = await window.Swal.fire({ title, html: text, icon, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Cancelar', reverseButtons: true });
    return r.isConfirmed;
  }

  /** Select2 de funcionários (rh/ajax/search_employees.php). */
  function employeeSelect($el, options = {}) {
    return $el.select2(Object.assign({
      placeholder: 'Selecione o funcionário',
      allowClear: true,
      width: '100%',
      ajax: {
        url: 'rh/ajax/search_employees.php',
        dataType: 'json',
        delay: 200,
        data: params => ({ term: params.term || '' }),
        processResults: data => ({ results: Array.isArray(data) ? data : (data.results || []) }),
      },
    }, options));
  }

  function debounce(fn, wait = 350) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
  }

  window.RH = { esc, num, money, kz, monthLabel, currentMonth, dateLabel, post, get, toast, fail, report, confirm, employeeSelect, debounce, errorMessage };
})(window, window.jQuery);
