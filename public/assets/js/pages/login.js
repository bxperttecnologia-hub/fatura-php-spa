import { api } from '../api.js';
import { esc } from '../ui.js';

// Só caminhos internos (evita open redirect via ?next=https://...)
function safeNext(n) {
  if (!n || n[0] !== '/' || n.startsWith('//') || n.includes('\\')) return '/';
  return /^\/login(\.php)?(\/|\?|$)/.test(n) ? '/' : n;
}

export function render(el) {
  const next = safeNext(window.APP.next || new URLSearchParams(location.search).get('next'));
  const expired = new URLSearchParams(location.search).has('expired');

  el.innerHTML = `
    <div class="mx-auto" style="max-width:420px;padding-top:6vh">
      <div class="text-center mb-4">
        <img src="/assets/img/logo/BXpert.png" alt="BXpert" style="max-height:64px" onerror="this.remove()">
        <h3 class="mt-3 mb-1">Acesso</h3>
        <span class="text-muted">Conecte-se com a melhor do mercado!</span>
      </div>
      <div id="err">${expired ? '<div class="alert alert-warning py-2 text-center">A sua sessão expirou. Inicie sessão novamente.</div>' : ''}</div>
      <form id="f" novalidate>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="user_email">Usuário, E-mail ou Telefone</label>
          <input id="user_email" name="user_email" class="form-control form-control-lg" autocomplete="username" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold" for="password">Senha</label>
          <div class="input-group input-group-lg">
            <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" id="toggle" tabindex="-1" aria-label="Mostrar senha"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="form-check mb-0">
            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
            <label class="form-check-label small" for="remember_me">Lembrar de mim</label>
          </div>
          <a href="/forgot_password.php" class="small text-decoration-none">Esqueci minha senha</a>
        </div>
        <button class="btn btn-primary btn-lg w-100" id="go">Acessar</button>
      </form>
      <p class="text-center mt-4 mb-0">Não tem uma conta? <a href="/register.php" class="fw-bold text-decoration-none">Cadastre-se</a></p>
    </div>`;

  const $ = s => el.querySelector(s);
  const showErr = msg => { $('#err').innerHTML = `<div class="alert alert-danger py-2 text-center">${esc(msg)}</div>`; };

  $('#toggle').onclick = () => {
    const i = $('#password');
    i.type = i.type === 'password' ? 'text' : 'password';
    $('#toggle i').className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  };

  $('#f').addEventListener('submit', async e => {
    e.preventDefault();
    const user_email = $('#user_email').value.trim();
    const password = $('#password').value;
    if (!user_email || !password) return showErr('Preencha todos os campos.');

    const body = { user_email, remember_me: $('#remember_me').checked };
    // Cifra a senha com a chave pública (mesmo esquema do login antigo); sem JSEncrypt segue em claro sobre HTTPS
    let enc = null;
    if (window.JSEncrypt && window.APP.publicKey) {
      const j = new window.JSEncrypt();
      j.setPublicKey(window.APP.publicKey);
      enc = j.encrypt(password);
    }
    if (enc) body.password_enc = enc; else body.password = password;

    const btn = $('#go');
    btn.disabled = true; btn.textContent = 'A entrar...';
    try {
      const { data } = await api('auth/login', { method: 'POST', body });
      window.APP.user = data.user;
      location.assign(next);   // recarrega: o servidor passa a renderizar nav + sidebar
    } catch (err) {
      showErr(err.message);
      $('#password').value = '';
      $('#password').focus();
      btn.disabled = false; btn.textContent = 'Acessar';
    }
  });
}
