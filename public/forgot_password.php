<?php
require_once '../app/helpers/translation.php';
require_once '../app/config/db.php';
require_once '../app/helpers/functions.php';
require_once '../app/views/head.php';
?>
<style>
    .login-col {
        --accent: var(--blue, #0d6efd);
        --login-bg: #f7f7f7;
        background: var(--login-bg);
    }

    .login-wrap {
        width: 100%;
        max-width: 420px;
        padding: 0 1.25rem;
    }

    .login-title {
        font-size: 2.2rem;
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

    .field .code-input {
        text-align: center;
        letter-spacing: .6rem;
        font-size: 1.4rem;
        font-weight: 600;
        padding: 0 1rem;
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

    .btn-login:disabled {
        opacity: .6;
    }

    .method-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        width: 100%;
        padding: 1rem 1.25rem;
        margin-bottom: .75rem;
        text-align: left;
        background: #fff;
        border: 1px solid #dcdcdc;
        border-radius: 18px;
        transition: border-color .15s, box-shadow .15s;
    }

    .method-card:hover {
        border-color: var(--accent);
        box-shadow: 0 0 0 .2rem rgba(0, 0, 0, .04);
    }

    .method-card .material-icons-outlined {
        color: var(--accent);
        font-size: 28px;
    }

    .method-card small {
        display: block;
        color: #6c757d;
    }

    .login-link {
        font-weight: 700;
        text-decoration: none;
        color: var(--accent);
    }

    .link-btn {
        background: none;
        border: 0;
        padding: 0;
        color: var(--accent);
        font-weight: 600;
    }

    .link-btn:disabled {
        color: #9aa0a6;
    }
</style>

<div class="row w-100 mx-0 gx-0">

    <!-- Coluna do formulário -->
    <div class="col-12 col-sm-6 login-col">
        <div class="d-flex flex-column justify-content-between align-items-center min-vh-100">
            <div class="w-100 d-md-none d-flex justify-content-center aling-items-center">
                <?= gerarDropdownPaises($paises, $paisSelecionado); ?>
            </div>

            <div class="login-wrap my-auto py-4">
                <!-- Título -->
                <div class="text-center mb-4">
                    <h2 class="login-title mb-1"><?= t('Recuperar senha') ?></h2>
                    <span class="text-grey" id="fpSubtitle"><?= t('Escolha como deseja recuperar o acesso.') ?></span>
                </div>

                <!-- PASSO 1: escolher e-mail ou telefone -->
                <div id="step-method" class="fp-step">
                    <button type="button" class="method-card" data-method="email">
                        <i class="material-icons-outlined">mail</i>
                        <span>
                            <strong><?= t('E-mail') ?></strong>
                            <small><?= t('Receber o código no seu e-mail') ?></small>
                        </span>
                    </button>
                    <button type="button" class="method-card" data-method="phone">
                        <i class="material-icons-outlined">smartphone</i>
                        <span>
                            <strong><?= t('Telefone') ?></strong>
                            <small><?= t('Receber o código por SMS ou WhatsApp') ?></small>
                        </span>
                    </button>
                </div>

                <!-- PASSO 2: dados de contacto (e canal, quando é telefone) -->
                <div id="step-contact" class="fp-step d-none">
                    <form id="formSend" novalidate>
                        <div class="field">
                            <label for="identifier" id="contactLabel"><?= t('E-mail') ?></label>
                            <span class="icon-left"><i class="material-icons-outlined" id="contactIcon">mail</i></span>
                            <input type="email" class="form-control" id="identifier" name="identifier" required>
                        </div>

                        <div id="channelGroup" class="d-none mb-4">
                            <div class="fw-semibold small mb-2"><?= t('Receber código por') ?></div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="channel" id="chSms" value="sms" checked>
                                <label class="form-check-label" for="chSms">SMS</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="channel" id="chWa" value="whatsapp">
                                <label class="form-check-label" for="chWa">WhatsApp</label>
                            </div>
                        </div>

                        <button type="submit" id="btnSend" class="btn-login"><?= t('Enviar código') ?></button>
                    </form>
                    <div class="text-center mt-4">
                        <a href="#" class="login-link" data-back="method"><?= t('Escolher outro método') ?></a>
                    </div>
                </div>

                <!-- PASSO 3: código de verificação -->
                <div id="step-code" class="fp-step d-none">
                    <p class="text-center text-grey mb-4">
                        <?= t('Enviámos um código de 6 dígitos para') ?> <strong id="maskedDest"></strong>
                    </p>
                    <form id="formVerify" novalidate>
                        <div class="field">
                            <label for="code"><?= t('Código de verificação') ?></label>
                            <input type="text" class="form-control code-input" id="code" name="code"
                                inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                                placeholder="000000" required>
                        </div>
                        <button type="submit" id="btnVerify" class="btn-login"><?= t('Verificar código') ?></button>
                    </form>
                    <div class="text-center mt-4">
                        <button type="button" class="link-btn" id="btnResend" disabled>
                            <?= t('Reenviar código') ?><span id="resendTimer"></span>
                        </button>
                        <div class="mt-2">
                            <a href="#" class="login-link" data-back="contact"><?= t('Alterar dados') ?></a>
                        </div>
                    </div>
                </div>

                <!-- PASSO 4: nova senha -->
                <div id="step-reset" class="fp-step d-none">
                    <form id="formReset" novalidate>
                        <div class="field">
                            <label for="new_password"><?= t('Nova senha') ?></label>
                            <span class="icon-left"><i class="material-icons-outlined">lock</i></span>
                            <input type="password" class="form-control" id="new_password" autocomplete="new-password" required>
                            <span class="icon-right toggle-pass" data-target="#new_password">
                                <i class="material-icons-outlined">visibility</i>
                            </span>
                        </div>
                        <div class="field">
                            <label for="confirm_password"><?= t('Confirmar nova senha') ?></label>
                            <span class="icon-left"><i class="material-icons-outlined">lock</i></span>
                            <input type="password" class="form-control" id="confirm_password" autocomplete="new-password" required>
                            <span class="icon-right toggle-pass" data-target="#confirm_password">
                                <i class="material-icons-outlined">visibility</i>
                            </span>
                        </div>
                        <p class="small text-grey mb-4"><?= t('Mínimo de 8 caracteres, com letras e números.') ?></p>
                        <button type="submit" id="btnReset" class="btn-login"><?= t('Alterar senha') ?></button>
                    </form>
                </div>

                <!-- Voltar ao login -->
                <div class="text-center mt-4">
                    <a href="login.php" class="login-link"><?= t('Voltar ao login') ?></a>
                </div>
            </div>

            <!-- Header Azul Mobile -->
            <div class="d-block d-md-none w-100" style="background: url('assets/img/fundo-login-vermelho.png') center/cover no-repeat; padding: 2rem 0;">
                <div class="text-center">
                    <img src="assets/img/logo/BXpert-Branca.png" alt="BXpert Logo" style="max-height: 8rem; ">
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 d-none d-md-flex justify-content-center align-items-center vh-100" id="bgLogin">
        <div style="position:absolute;z-index:100;right:2rem;top:3rem">
            <?= gerarDropdownPaises($paises, $paisSelecionado); ?>
        </div>
        <div class="text-center">
            <img src="assets/img/logo/BXpert-Branca.png" alt="BXpert Logo" style="max-height: 20rem;">
        </div>
    </div>
</div>

<script>
    $(".country-option").on("click", function(e) {
        e.preventDefault();
        country = $(this).data("country");
        let flagUrl = $(this).data("flag");
        let countryName = $(this).text().trim();

        $("#selectedFlag").attr("src", flagUrl);
        $("#selectedCountry").text(countryName);
        $("#country").val(country);

        $.post("../app/helpers/translation.php", {
            lang: country
        }, function(response) {
            location.reload();
        });
    });
</script>

<script src="./assets/js/jsencrypt.min.js"></script>
<script src="login/forgot.js?v=0.1"></script>
<?php require_once '../app/views/footer.php'; ?>