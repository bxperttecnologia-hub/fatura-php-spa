<?php
require_once '../app/helpers/translation.php';
require_once '../app/config/db.php';
require_once '../app/helpers/functions.php';
require_once '../app/views/head.php';
?>

<style>
    /* ===== Estilo de input "pill" com label sobre a borda ===== */
    .field-group {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .field-group>label {
        position: absolute;
        top: -0.6rem;
        left: 1.1rem;
        background: #fff;
        padding: 0 0.45rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #6b7280;
        z-index: 2;
        transition: color .2s ease;
    }

    .field-wrapper {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        width: 100%;
        border: 1.5px solid #d7dbe0;
        border-radius: 999px;
        padding: 0.7rem 1.1rem;
        background: #fff;
        transition: border-color .2s ease, box-shadow .2s ease;
    }

    .field-wrapper i,
    .field-wrapper .field-icon {
        font-size: 1rem;
        color: #9aa0a6;
        flex: 0 0 auto;
        transition: color .2s ease;
    }

    .field-wrapper input,
    .field-wrapper select {
        border: none;
        outline: none;
        background: transparent;
        flex: 1 1 auto;
        font-size: 0.95rem;
        color: #212529;
        min-width: 0;
        padding: 0;
        box-shadow: none !important;
    }

    .field-wrapper select {
        appearance: none;
        -webkit-appearance: none;
    }

    /* Estado ACTIVE (foco) */
    .field-group:focus-within .field-wrapper {
        border-color: var(--blue, #2f9bff);
        box-shadow: 0 0 0 4px rgba(47, 155, 255, 0.12);
    }

    .field-group:focus-within>label,
    .field-group:focus-within .field-wrapper i {
        color: var(--blue, #2f9bff);
    }

    /* Estado FILLED (com valor, sem foco) */
    .field-group.is-filled:not(:focus-within) .field-wrapper {
        border-color: #b9c0c9;
    }

    /* Estado ERROR */
    .field-group.has-error .field-wrapper {
        border-color: #ff4d4f;
        box-shadow: 0 0 0 4px rgba(255, 77, 79, 0.12);
    }

    .field-group.has-error>label,
    .field-group.has-error .field-wrapper i {
        color: #ff4d4f;
    }

    .field-error-msg {
        display: none;
        align-items: center;
        gap: 0.35rem;
        color: #ff4d4f;
        font-size: 0.8rem;
        margin: 0.4rem 0 0 1.1rem;
    }

    .field-group.has-error .field-error-msg {
        display: flex;
    }

    .field-prefix {
        color: #9aa0a6;
        font-size: 0.95rem;
        flex: 0 0 auto;
        user-select: none;
    }

    #passwordRules {
        width: 100%;
        margin-top: 0.5rem;
        border-radius: 12px;
        position: absolute !important;
        z-index: 10;
    }
</style>

<div class="row w-100 mx-0 gx-0">
    <!-- Coluna da Imagem à esquerda -->
    <div class="col-12 col-md-6 d-flex justify-content-center align-items-center vh-100 position-relative" id="bgRegister">
        <div style="position:absolute; z-index:100; left:2rem; top:3rem">
            <?= gerarDropdownPaises($paises ?? [], $paisSelecionado ?? ''); ?>
        </div>
        <img class="img-fluid" src="assets/img/logo/BXpert-Branca.png" style="max-width: 80%;">
    </div>

    <!-- Coluna do Formulário à direita -->
    <div class="col-12 col-md-6">
        <div class="container min-vh-100 d-flex align-items-center justify-content-center">
            <div style="max-width: 420px; width: 100%;">
                <h2 class="fw-bold text-center mb-1">Registre-se</h2>
                <p class="text-center text-muted mb-4">Crie a sua conta em menos de um minuto.</p>

                <!-- Passo 1: Dados -->
                <form id="stepInfo" novalidate>
                    <div class="field-group" data-field="name">
                        <label for="name">Nome completo</label>
                        <div class="field-wrapper">
                            <i class="bi bi-person"></i>
                            <input type="text" id="name" name="name" required minlength="3" placeholder="Digite seu nome">
                        </div>
                        <div class="field-error-msg">Por favor, insira o seu nome completo.</div>
                    </div>

                    <div class="field-group" data-field="phone">
                        <label for="phone">Telefone (com prefixo do país)</label>
                        <div class="field-wrapper">
                            <i class="bi bi-telephone"></i>
                            <input type="tel" id="phone" name="phone" placeholder="+244 900 000 000" required>
                        </div>
                        <div class="field-error-msg">Número de telefone inválido.</div>
                    </div>

                    <div class="mb-4">
                        <span class="form-label d-block text-muted small fw-bold mb-2">Receber código por</span>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="channel" id="chSms" value="sms" checked>
                            <label class="form-check-label" for="chSms">SMS</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="channel" id="chWa" value="whatsapp">
                            <label class="form-check-label" for="chWa">WhatsApp</label>
                        </div>
                    </div>

                    <button type="submit" id="btnEnviar" class="btn btn-success w-100 rounded-pill py-2">Enviar código</button>
                </form>

                <!-- Passo 2: Código -->
                <form id="stepCode" class="d-none" novalidate>
                    <p id="codeHelp" class="text-muted text-center mb-3"></p>

                    <div class="field-group" data-field="code">
                        <label for="code">Código de verificação</label>
                        <div class="field-wrapper">
                            <input class="text-center fw-bold" type="text" id="code" name="code"
                                inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
                        </div>
                        <div class="field-error-msg">Código inválido.</div>
                    </div>

                    <button type="submit" id="btnVerificar" class="btn btn-success w-100 rounded-pill py-2">Confirmar</button>
                    <button type="button" id="btnReenviar" class="btn btn-link w-100 mt-2 text-decoration-none" disabled>Reenviar código</button>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0">Já tem conta? <a href="login.php" class="text-decoration-none fw-bold" style="color:var(--blue, #2f9bff)">Clique aqui para logar.</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- <script src="register/register.js?v=1.6"></script> -->

<script>
    $(function() {
        var $info = $('#stepInfo'),
            $code = $('#stepCode'),
            timer;

        function erro(msg) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: msg
                });
            } else {
                alert(msg);
            }
        }

        function msgDe(xhr) {
            if (xhr.status === 429) {
                return 'Muitas tentativas em pouco tempo. Por favor, aguarde alguns minutos antes de tentar novamente.';
            }
            if (xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
            if (xhr.responseJSON && xhr.responseJSON.error) {
                return xhr.responseJSON.error;
            }
            return 'Ocorreu um erro ao processar a solicitação. Tente novamente.';
        }

        function cooldown(seg) {
            var $b = $('#btnReenviar').prop('disabled', true).text('Reenviar em ' + seg + 's');
            clearInterval(timer);
            timer = setInterval(function() {
                seg--;
                if (seg <= 0) {
                    clearInterval(timer);
                    $b.prop('disabled', false).text('Reenviar código');
                } else {
                    $b.text('Reenviar em ' + seg + 's');
                }
            }, 1000);
        }

        function enviar() {
            return $.ajax({
                    url: 'register/ajax/send_otp.php',
                    type: 'POST',
                    data: $info.serialize(),
                    dataType: 'json',
                    timeout: 20000
                })
                .done(function(r) {
                    if (r && r.phone_masked) {
                        $info.addClass('d-none');
                        $code.removeClass('d-none');
                        $('#codeHelp').text('Enviámos um código para ' + r.phone_masked + '.');
                        $('#code').val('').focus();
                        cooldown(60);
                    } else {
                        erro('Resposta inválida do servidor.');
                    }
                })
                .fail(function(xhr) {
                    // DEBUG TEMPORÁRIO: mostra a resposta em bruto do servidor
                    // para vermos exatamente o que o send_otp.php devolveu.
                    alert('DEBUG (status ' + xhr.status + '):\n' + xhr.responseText);
                    erro(msgDe(xhr));
                });
        }

        $info.on('submit', function(e) {
            e.preventDefault();
            if (!this.checkValidity()) return this.reportValidity();
            var $b = $('#btnEnviar').prop('disabled', true);
            enviar().always(function() {
                $b.prop('disabled', false);
            });
        });

        $('#btnReenviar').on('click', enviar);

        $code.on('submit', function(e) {
            e.preventDefault();
            var $b = $('#btnVerificar').prop('disabled', true);
            $.ajax({
                    url: 'register/ajax/verify_otp.php',
                    type: 'POST',
                    data: $code.serialize(),
                    dataType: 'json',
                    timeout: 20000
                })
                .done(function(r) {
                    if (r && r.redirect) {
                        window.location.href = r.redirect;
                    } else {
                        window.location.href = 'index.php';
                    }
                })
                .fail(function(xhr) {
                    erro(msgDe(xhr));
                })
                .always(function() {
                    $b.prop('disabled', false);
                });
        });
    });

    // Controladores de estado dos campos (is-filled / has-error)
    document.querySelectorAll('.field-group').forEach(function(group) {
        var control = group.querySelector('input, select');
        if (!control) return;

        function syncFilled() {
            if (control.value && control.value.trim() !== '') {
                group.classList.add('is-filled');
            } else {
                group.classList.remove('is-filled');
            }
        }

        syncFilled();
        control.addEventListener('input', function() {
            syncFilled();
            group.classList.remove('has-error');
        });
        control.addEventListener('change', syncFilled);
    });

    window.setFieldError = function(fieldName, message) {
        var group = document.querySelector('.field-group[data-field="' + fieldName + '"]');
        if (!group) return;
        var msgEl = group.querySelector('.field-error-msg');
        group.classList.add('has-error');
        if (msgEl && message) msgEl.textContent = message;
    };

    window.clearFieldError = function(fieldName) {
        var group = document.querySelector('.field-group[data-field="' + fieldName + '"]');
        if (!group) return;
        group.classList.remove('has-error');
    };

    // Tratamento seguro para campos externos (como Website) caso existam
    var regForm = document.getElementById('registerForm');
    if (regForm) {
        regForm.addEventListener('submit', function() {
            var websiteInput = document.getElementById('website');
            if (websiteInput && websiteInput.value && websiteInput.value.trim() !== '') {
                var value = websiteInput.value.trim().replace(/^https?:\/\//i, '');
                websiteInput.value = 'https://' + value;
            }
        });
    }
</script>