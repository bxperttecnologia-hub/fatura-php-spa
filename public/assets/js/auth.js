const form = document.getElementById('loginForm');
const errorMessage = document.getElementById('error-message');
const submitButton = document.getElementById('btnAcessar');
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword?.addEventListener('click', () => {
  const isPassword = passwordInput.type === 'password';
  passwordInput.type = isPassword ? 'text' : 'password';
  togglePassword.setAttribute('aria-label', isPassword ? 'Ocultar palavra-passe' : 'Mostrar palavra-passe');
  togglePassword.querySelector('i').textContent = isPassword ? 'visibility_off' : 'visibility';
});

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  errorMessage?.classList.add('d-none');
  if (submitButton) submitButton.disabled = true;
  const formData = new FormData(form);
  const payload = {
    identifier: String(formData.get('identifier') || '').trim(),
    password: formData.get('password'),
    remember_me: formData.get('remember_me') === 'on',
  };

  try {
    const response = await fetch('/api/v1/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });

    const result = await response.json().catch(() => null);

    if (!response.ok) {
      const details = Array.isArray(result?.errors) ? result.errors.join(' ') : '';
      throw new Error([result?.message, details].filter(Boolean).join(' ') || 'Falha ao iniciar sessão.');
    }

    window.location.href = '/dashboard';
  } catch (error) {
    if (errorMessage) {
      errorMessage.textContent = error instanceof TypeError
        ? 'Não foi possível contactar o servidor. Verifique a ligação e tente novamente.'
        : error.message;
      errorMessage.classList.remove('d-none');
    } else {
      alert(error.message);
    }
  } finally {
    if (submitButton) submitButton.disabled = false;
  }
});
