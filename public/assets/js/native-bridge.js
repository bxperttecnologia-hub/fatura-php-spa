/**
 * Ponte entre a SPA e a app mobile BXpert (Expo + react-native-webview).
 * Fora da app não faz nada. Dentro da app:
 *  - marca <html> com .is-app (e .is-app-ios / .is-app-android) para o CSS esconder o que não se aplica;
 *  - downloads/PDFs (blob:, <a download>, window.open('')) seguem para o nativo, que partilha/abre o ficheiro;
 *  - window.print() imprime pelo nativo;
 *  - links para outros domínios abrem fora da app;
 *  - regista o token de push recebido do nativo (POST /api/push/register, com sessão + CSRF);
 *  - abre o ecrã indicado quando o utilizador toca numa notificação.
 *
 * Protocolo:
 *   web -> nativo: ReactNativeWebView.postMessage(JSON.stringify({ v: 1, command, payload }))
 *   nativo -> web: window.BXNative.receive({ command, payload })
 */
(function () {
  'use strict';

  var RN = window.ReactNativeWebView;
  var ua = navigator.userAgent || '';
  var match = ua.match(/BXpertApp\/([\d.]+)(?: \((ios|android)\))?/);
  if (!RN && !match) {
    window.BX_NATIVE = false;
    return;
  }

  var platform = (match && match[2]) || (/iPhone|iPad|iPod/.test(ua) ? 'ios' : 'android');
  window.BX_NATIVE = true;
  var root = document.documentElement;
  root.classList.add('is-app', 'is-app-' + platform);

  // ---------- CSS do modo app ----------
  var css = [
    // Login Google não funciona dentro de WebViews (a Google bloqueia: disallowed_useragent)
    '.is-app .gsi-material-button, .is-app a[href*="loginGoogle"] { display: none !important; }',
    // iOS: a compra da subscrição dentro da app exigiria In-App Purchase (App Store 3.1.1)
    '.is-app-ios [data-bs-target="#modalPlans"], .is-app-ios #btnRenew, .is-app-ios #modalPayMethod { display: none !important; }',
    '.is-app-ios .bx-app-subscription-note { display: block !important; }',
    // Sem zoom acidental ao focar inputs no iOS (fonte >= 16px)
    '.is-app-ios input, .is-app-ios select, .is-app-ios textarea { font-size: max(16px, 1em); }',
    // Respeita o "notch" / barra de gestos
    '.is-app body { padding-bottom: env(safe-area-inset-bottom); }',
  ].join('\n');
  var style = document.createElement('style');
  style.id = 'bx-native-style';
  style.textContent = css;
  (document.head || root).appendChild(style);

  function post(command, payload) {
    try {
      RN && RN.postMessage(JSON.stringify({ v: 1, command: command, payload: payload || {} }));
    } catch (e) { /* nativo indisponível */ }
  }

  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return (window.APP && window.APP.csrf) || (meta && meta.content) || '';
  }

  function loggedIn() {
    return !!(window.APP && window.APP.user && window.APP.user.id);
  }

  // ---------- Ficheiros: blob:, <a download>, window.open ----------
  var MAX_FILE_BYTES = 25 * 1024 * 1024;
  var blobs = new Map();                        // blob: URL -> Blob (para os enviar ao nativo)
  var createObjectURL = URL.createObjectURL.bind(URL);
  var revokeObjectURL = URL.revokeObjectURL.bind(URL);
  URL.createObjectURL = function (obj) {
    var url = createObjectURL(obj);
    if (obj instanceof Blob) blobs.set(url, obj);
    return url;
  };
  URL.revokeObjectURL = function (url) {
    // O nativo pode ainda estar a ler o ficheiro: liberta mais tarde.
    setTimeout(function () { blobs.delete(url); revokeObjectURL(url); }, 120000);
  };

  function extFor(mime) {
    return ({
      'application/pdf': '.pdf',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '.xlsx',
      'text/csv': '.csv',
      'application/xml': '.xml',
      'text/xml': '.xml',
      'image/png': '.png',
      'image/jpeg': '.jpg',
    })[(mime || '').split(';')[0]] || '';
  }

  function safeName(name, mime) {
    var base = String(name || 'documento').replace(/[\\/:*?"<>|\r\n]+/g, '_').slice(0, 120) || 'documento';
    return /\.[a-z0-9]{2,5}$/i.test(base) ? base : base + extFor(mime);
  }

  function filenameFromResponse(res) {
    var cd = res.headers.get('Content-Disposition') || '';
    var m = cd.match(/filename\*=UTF-8''([^;]+)/i) || cd.match(/filename="?([^";]+)"?/i);
    try { return m ? decodeURIComponent(m[1]) : ''; } catch (e) { return m ? m[1] : ''; }
  }

  function sendBlob(blob, name, action) {
    if (blob.size > MAX_FILE_BYTES) {
      alert('O ficheiro é demasiado grande para abrir na app (' + Math.round(blob.size / 1048576) + ' MB).');
      return;
    }
    var reader = new FileReader();
    reader.onload = function () {
      var dataUrl = String(reader.result || '');
      post('file', {
        name: safeName(name, blob.type),
        mime: blob.type || 'application/octet-stream',
        base64: dataUrl.slice(dataUrl.indexOf(',') + 1),
        action: action || 'open',
      });
    };
    reader.onerror = function () { alert('Não foi possível ler o ficheiro.'); };
    reader.readAsDataURL(blob);
  }

  function sameOrigin(url) {
    try { return new URL(url, location.href).origin === location.origin; } catch (e) { return false; }
  }

  /** Abre/partilha um URL de ficheiro (blob:, data: ou do próprio servidor, com a sessão). */
  function handleFileUrl(url, name, action) {
    var known = blobs.get(url);
    if (known) { sendBlob(known, name, action); return; }
    fetch(url, { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        var fromHeader = filenameFromResponse(res);
        return res.blob().then(function (b) { sendBlob(b, name || fromHeader, action); });
      })
      .catch(function () { alert('Não foi possível obter o documento.'); });
  }

  function isFileUrl(url) {
    return /^(blob|data):/i.test(url);
  }

  function openExternal(url) {
    post('openExternal', { url: new URL(url, location.href).href });
  }

  // <a download> e <a href="blob:..."> (inclui link.click() de elementos fora do DOM)
  function interceptAnchor(a) {
    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return false;
    if (a.hasAttribute('download') || isFileUrl(href)) {
      handleFileUrl(a.href, a.getAttribute('download') || '', 'share');
      return true;
    }
    if (/^(tel|mailto|sms|whatsapp):/i.test(href)) {
      post('openExternal', { url: href });
      return true;
    }
    if (/^https?:/i.test(a.href) && !sameOrigin(a.href)) {
      openExternal(a.href);
      return true;
    }
    if (a.target === '_blank' && sameOrigin(a.href)) {
      // Sem separadores na app: abre no mesmo ecrã (o "voltar" regressa)
      if (typeof window.navigateSPA === 'function' && !/\.(pdf|xlsx|csv|xml)(\?|$)/i.test(a.pathname)) {
        window.navigateSPA(a.pathname + a.search + a.hash);
      } else {
        handleFileUrl(a.href, '', 'open');
      }
      return true;
    }
    return false;
  }

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0) return;
    var a = e.target && e.target.closest && e.target.closest('a[href]');
    if (a && interceptAnchor(a)) {
      e.preventDefault();
      e.stopImmediatePropagation();
    }
  }, true);

  var anchorClick = HTMLAnchorElement.prototype.click;
  HTMLAnchorElement.prototype.click = function () {
    if (!this.isConnected && interceptAnchor(this)) return;   // os ligados ao DOM passam pelo listener acima
    return anchorClick.apply(this, arguments);
  };

  // window.open: '' / about:blank devolvem uma "janela" falsa cujo location recebe o blob (document-api.js)
  var nativeOpen = window.open;
  window.open = function (url) {
    url = url ? String(url) : '';
    if (!url || url === 'about:blank') {
      var fake = {
        closed: false,
        close: function () { fake.closed = true; },
        focus: function () {},
        document: { write: function () {}, close: function () {}, title: '' },
        location: {
          replace: function (u) { handleFileUrl(String(u), '', 'open'); },
          assign: function (u) { handleFileUrl(String(u), '', 'open'); },
          set href(u) { handleFileUrl(String(u), '', 'open'); },
          get href() { return 'about:blank'; },
        },
      };
      return fake;
    }
    var abs = new URL(url, location.href).href;
    if (isFileUrl(url)) { handleFileUrl(url, '', 'open'); return null; }
    if (!sameOrigin(abs)) { openExternal(abs); return null; }
    if (/\.(pdf|xlsx|csv|xml)(\?|$)|\/pdf(\?|$)|export/i.test(abs)) { handleFileUrl(abs, '', 'open'); return null; }
    location.assign(abs);
    return null;
  };
  window.__bxNativeOpen = nativeOpen;

  // window.print(): a WebView não imprime; o nativo imprime o HTML actual
  window.print = function () {
    var html = '<!doctype html>' + document.documentElement.outerHTML
      .replace(/<head([^>]*)>/i, '<head$1><base href="' + location.origin + '/">');
    post('print', { html: html });
  };

  // ---------- Push ----------
  function registerPush(p) {
    if (!loggedIn() || !p || !p.token) return;
    fetch('/api/push/register', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
      body: JSON.stringify({
        token: p.token,
        platform: p.platform || platform,
        device_name: p.deviceName || '',
        app_version: p.appVersion || (match && match[1]) || '',
      }),
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.data) post('badge', { count: d.data.unread || 0 }); })
      .catch(function () {});
  }

  function refreshBadge() {
    if (!loggedIn()) { post('badge', { count: 0 }); return; }
    fetch('/api/push/badge', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.data) post('badge', { count: d.data.unread || 0 }); })
      .catch(function () {});
  }

  /** Abre um caminho interno; se a notificação for de outra empresa, troca de empresa primeiro. */
  function openPath(path, companyId) {
    if (typeof path !== 'string' || !/^\/(?!\/)/.test(path)) path = '/';
    var current = window.APP && window.APP.user && Number(window.APP.user.company_id);
    companyId = Number(companyId || 0);
    if (!loggedIn() || !companyId || companyId === current) {
      if (typeof window.navigateSPA === 'function' && loggedIn()) window.navigateSPA(path);
      else location.assign(path);
      return;
    }
    fetch('/assets/ajax/get_companies.php', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (list) {
        var c = Array.isArray(list) && list.find(function (x) { return Number(x.id) === companyId; });
        if (!c) { location.assign(path); return null; }
        return fetch('/assets/ajax/change_company.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            company_id: String(c.id),
            name_company: c.name || '',
            registration_number: c.registration_number || '',
            email_company: c.email || '',
            csrf_token: csrf(),
          }),
        }).then(function () { location.assign(path); });
      })
      .catch(function () { location.assign(path); });
  }

  // Nativo -> web
  window.BXNative = Object.freeze({
    platform: platform,
    receive: function (msg) {
      if (!msg || typeof msg !== 'object') return;
      var p = msg.payload || {};
      switch (msg.command) {
        case 'pushToken': registerPush(p); break;
        case 'navigate': openPath(p.url, p.companyId); break;
        case 'resume': refreshBadge(); document.dispatchEvent(new Event('app:resume')); break;
        default:
      }
    },
  });

  // Avisa o nativo do estado da sessão em cada carregamento (o nativo responde com o token de push)
  function ready() {
    post('ready', {
      loggedIn: loggedIn(),
      userId: loggedIn() ? window.APP.user.id : null,
      companyId: loggedIn() ? window.APP.user.company_id : null,
      path: location.pathname + location.search,
    });
    if (!loggedIn()) post('badge', { count: 0 });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
  else ready();
})();
