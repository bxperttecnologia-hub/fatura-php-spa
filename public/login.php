<?php
if (!defined('SPA_FRAGMENT')) {
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: /login' . ($query !== '' ? '?' . $query : ''));
    exit;
}

require_once '../app/helpers/translation.php';
require_once '../app/config/db.php';
require_once '../app/helpers/functions.php';
require_once '../app/views/head.php';
?>
<style>
    /* Variáveis definidas na coluna do formulário */
    .login-col {
        --accent: var(--blue, #0d6efd);
        /* Para verde como na referência: --accent: #198754; */
        --login-bg: #f7f7f7;
        background: var(--login-bg);
    }

    /* ---- Contentor da SPA: anula padding/margem/transform herdados do <main id="app" class="p-4"> ----
       (um transform num ancestral faz o position:fixed deixar de ser relativo à janela e deslocava a página).
       :has() não depende da classe body.guest. */
    #app:has(> .login-page) {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: none !important;
        transform: none !important;
        filter: none !important;
    }

    .layout:has(.login-page) {
        display: block !important;
        min-height: 100vh;
    }

    /* ---- Ecrã inteiro, de ponta a ponta, sem padding nem margens ---- */
    .login-page {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1;
        display: flex;
        flex-wrap: nowrap;
        width: 100vw !important;
        height: 100vh;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow-x: hidden;
        overflow-y: auto;
    }

    /* ---- Coluna do formulário: sem padding; formulário centrado na horizontal e na vertical ---- */
    .login-col {
        position: relative;
        /* referência do seletor de idioma */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        padding: 0 !important;
        margin: 0 !important;
    }

    .login-col>.login-inner {
        width: 100%;
        min-height: 100vh;
    }

    /* Seletor de idioma: UM só no HTML, no topo esquerdo da coluna do formulário */
    .lang-switch {
        position: absolute;
        top: 1.5rem;
        left: 1.5rem;
        z-index: 100;
    }

    .login-wrap {
        width: 100%;
        max-width: 420px;
        padding: 0;
        margin-left: auto;
        margin-right: auto;
    }

    /* Só em ecrãs pequenos o formulário precisa de folga lateral */
    @media (max-width: 575.98px) {
        .login-wrap {
            padding: 0 1.25rem;
        }
    }

    /* ---- Painel da marca: colado às margens, ocupa o resto da largura ---- */
    #bgLogin {
        flex: 1 1 0;
        min-width: 0;
        min-height: 100vh;
        margin: 0 !important;
        padding: 0 !important;
    }

    .login-title {
        font-size: clamp(1.8rem, 5vw, 2.2rem);
        font-weight: 700;
        color: #212529;
    }

    .field {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .field label {
        position: absolute;
        top: -0.65rem;
        left: 1.4rem;
        padding: 0 .5rem;
        background: var(--login-bg);
        font-size: .8rem;
        font-weight: 600;
        color: #6c757d;
        z-index: 2;
    }

    .field .form-control {
        height: 58px;
        border-radius: 999px;
        border: 1px solid #dcdcdc;
        background: #fff;
        padding: 0 3rem;
    }

    .field .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 .2rem rgba(0, 0, 0, .05);
    }

    .field .icon-left,
    .field .icon-right {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        color: #9aa0a6;
        z-index: 3;
        display: flex;
    }

    .field .icon-left {
        left: 1.1rem;
    }

    .field .icon-right {
        right: 1.1rem;
        cursor: pointer;
    }

    .btn-login {
        width: 100%;
        height: 52px;
        border: 0;
        border-radius: 999px;
        background: var(--accent);
        color: #fff;
        font-weight: 500;
        transition: opacity .15s;
    }

    .btn-login:hover {
        opacity: .9;
    }

    .btn-login:focus-visible {
        outline: 3px solid rgba(13, 110, 253, .35);
        outline-offset: 2px;
    }

    .login-divider {
        display: flex;
        align-items: center;
        gap: .75rem;
        color: #9aa0a6;
        font-size: .85rem;
        margin: 1.5rem 0 1rem;
    }

    .login-divider::before,
    .login-divider::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e2e2e2;
    }

    .login-link {
        font-weight: 700;
        text-decoration: none;
        color: var(--accent);
    }

    /* Cabeçalho azul no mobile */
    .login-mobile-header {
        background: url('./assets/img/fundo-register.png') center/cover no-repeat;
        padding: 2rem 0;
    }

    .login-mobile-header img {
        max-height: 8rem;
    }

    .login-brand img {
        max-height: 20rem;
        max-width: 80%;
    }
</style>

<div class="login-page row gx-0 m-0 p-0">

    <!-- Coluna do formulário: o formulário fica centrado na horizontal e na vertical -->
    <div class="login-col col-12 col-md-6 col-lg-5 col-xl-5 d-flex flex-column justify-content-center align-items-center">

        <!-- Seletor de idioma (único) -->
        <div class="lang-switch">
            <?= gerarDropdownPaises($paises, $paisSelecionado); ?>
        </div>

        <div class="login-inner d-flex flex-column justify-content-between align-items-center min-vh-100">

            <!-- Header azul (mobile): no topo -->
            <div class="login-mobile-header d-block d-md-none w-100 order-first text-center">
                <img src="assets/img/logo/BXpert-Branca.png" alt="BXpert Logo">
            </div>

            <div class="login-wrap my-auto mx-auto py-4">
                <!-- Título -->
                <div class="text-center mb-4">
                    <h2 class="login-title mb-1"><?= t('Acesso') ?></h2>
                    <span class="text-secondary"><?= t('Conecte-se com a melhor do mercado!') ?></span>
                </div>

                <!-- Erro -->
                <div class="alert alert-danger text-center d-none" role="alert" id="error-message">
                    <?= t('Usuário não localizado') ?>
                </div>

                <!-- Formulário -->
                <form id="loginForm">
                    <div class="field">
                        <label for="user_email"><?= t('Usuário, E-mail ou Telefone') ?></label>
                        <span class="icon-left"><i class="material-icons-outlined">person</i></span>
                        <input type="text" class="form-control" id="user_email" name="user_email"
                            placeholder="<?= t('Digite seu usuário, e-mail ou telefone') ?>"
                            autocomplete="username" required>
                    </div>

                    <div class="field">
                        <label for="password"><?= t('Senha') ?></label>
                        <span class="icon-left"><i class="material-icons-outlined">lock</i></span>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="<?= t('Digite sua senha') ?>"
                            autocomplete="current-password" required>
                        <span class="icon-right" id="togglePassword" role="button" tabindex="0" aria-label="<?= t('Mostrar senha') ?>">
                            <i class="material-icons-outlined">visibility</i>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me"><?= t('Lembrar de mim') ?></label>
                        </div>
                        <a href="forgot_password.php" id="forgotPassword" class="text-decoration-none" style="font-size:13px;"><?= t('Esqueci minha senha') ?></a>
                    </div>

                    <button type="submit" id="btnAcessar" class="btn-login"><?= t('Acessar') ?></button>
                </form>

                <div class="login-divider"><?= t('ou') ?></div>

                <!-- Google login -->
                <div class="w-100 text-center">
                    <a href="loginGoogle/loginGoogle.php" class="d-flex justify-content-center align-items-center">
                        <button type="button" class="gsi-material-button">
                            <div class="gsi-material-button-state"></div>
                            <div class="gsi-material-button-content-wrapper">
                                <div class="gsi-material-button-icon">
                                    <!-- Ícone Google -->
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" style="display: block;">
                                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                                        <path fill="none" d="M0 0h48v48H0z"></path>
                                    </svg>
                                </div>
                                <span class="gsi-material-button-contents"><?= t('Continue com Google') ?></span>
                            </div>
                        </button>
                    </a>
                </div>

                <!-- Cadastro -->
                <div class="text-center mt-4">
                    <p class="mb-0"><?= t('Não tem uma conta?') ?> <a href="register.php" class="login-link"><?= t('Cadastre-se') ?></a></p>
                </div>
            </div>

        </div>
    </div>

    <!-- Painel da marca (desktop) -->
    <div class="col-md-6 col-lg-7 col-xl-7 d-none d-md-flex justify-content-center align-items-center min-vh-100 p-0" id="bgLogin">
        <div class="login-brand text-center">
            <img src="assets/img/logo/BXpert-Branca.png" alt="BXpert Logo" class="img-fluid">
        </div>
    </div>
</div>

<script>
    $(".country-option").on("click", function(e) {
        e.preventDefault();
        const country = $(this).data("country");
        const flagUrl = $(this).data("flag");
        const countryName = $(this).text().trim();

        $("#selectedFlag").attr("src", flagUrl);
        $("#selectedCountry").text(countryName);
        $("#country").val(country);

        $.post("../app/helpers/translation.php", {
            lang: country
        }, function() {
            location.reload();
        });
    });

    (function() {
        const toggle = document.getElementById('togglePassword');
        const input = document.getElementById('password');

        function flip() {
            const icon = toggle.querySelector('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.textContent = show ? 'visibility_off' : 'visibility';
        }
        toggle.addEventListener('click', flip);
        toggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                flip();
            }
        });
    })();
</script>

<script src="./assets/js/jsencrypt.min.js"></script>
<script src="login/login.js?v=0.2"></script>
<?php require_once '../app/views/footer.php'; ?>