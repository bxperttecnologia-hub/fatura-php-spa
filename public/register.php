<?php
require_once '../app/helpers/translation.php';
require_once '../app/config/db.php';
require_once '../app/helpers/functions.php';
require_once '../app/partials/head.php';
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
                <p class="text-center text-muted mb-4">Crie a sua conta e adicione uma empresa para começar.</p>

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

                <!-- Passo 3: Empresa -->
                <form id="stepCompany" class="d-none" novalidate>
                    <p class="text-muted text-center mb-3">Adicione a sua empresa para começar.</p>
                    <div class="field-group" data-field="registration_number">
                        <label for="registration_number">NIF da empresa</label>
                        <div class="field-wrapper">
                            <i class="bi bi-card-text"></i>
                            <input type="text" id="registration_number" name="registration_number" minlength="5" maxlength="25" required placeholder="Digite o NIF">
                        </div>
                        <div class="field-error-msg">Indique um NIF válido.</div>
                    </div>
                    <button type="button" id="btnConsultCompanyNif" class="btn btn-outline-success w-100 rounded-pill mb-3">Consultar NIF na AGT</button>
                    <div id="companyNifStatus" class="small text-center mb-3" role="status" aria-live="polite"></div>
                    <div class="field-group" data-field="company_name">
                        <label for="company_name">Nome da empresa</label>
                        <div class="field-wrapper">
                            <i class="bi bi-building"></i>
                            <input type="text" id="company_name" name="company_name" required minlength="2" placeholder="Nome da empresa">
                        </div>
                        <div class="field-error-msg">Indique o nome da empresa.</div>
                    </div>
                    <button type="submit" id="btnCreateCompany" class="btn btn-success w-100 rounded-pill py-2">Continuar</button>
                </form>

                <!-- Passo 4: Senha -->
                <form id="stepPassword" class="d-none" novalidate>
                    <p class="text-muted text-center mb-3">Defina uma senha para concluir o seu registo.</p>
                    <div class="field-group" data-field="password">
                        <label for="registration_password">Senha</label>
                        <div class="field-wrapper">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="registration_password" name="password" autocomplete="new-password" required>
                        </div>
                        <div class="field-error-msg">Use pelo menos 6 caracteres, com maiúscula, minúscula e um carácter especial.</div>
                    </div>
                    <div class="field-group" data-field="password_confirm">
                        <label for="registration_password_confirm">Confirmar senha</label>
                        <div class="field-wrapper">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="registration_password_confirm" name="password_confirm" autocomplete="new-password" required>
                        </div>
                        <div class="field-error-msg">As senhas não coincidem.</div>
                    </div>
                    <button type="submit" id="btnSetPassword" class="btn btn-success w-100 rounded-pill py-2">Concluir registo</button>
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
        var registrationBasePath = new URL('.', window.location.href).pathname.replace(/\/$/, '');
        var $info = $('#stepInfo'),
            $code = $('#stepCode'),
            $company = $('#stepCompany'),
            $password = $('#stepPassword'),
            registrationCsrf = null,
            timer;

        function showStep($step) {
            $info.add($code).add($company).add($password).addClass('d-none');
            $step.removeClass('d-none');
        }

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
                    url: registrationBasePath + '/register/ajax/send_otp.php',
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
                    url: registrationBasePath + '/register/ajax/verify_otp.php',
                    type: 'POST',
                    data: $code.serialize(),
                    dataType: 'json',
                    timeout: 20000
                })
                .done(function(r) {
                    if (r && r.next_step === 'company') {
                        registrationCsrf = r.csrf || null;
                        showStep($company);
                        $('#registration_number').focus();
                    } else {
                        erro((r && r.message) || 'Não foi possível avançar para os dados da empresa.');
                    }
                })
                .fail(function(xhr) {
                    erro(msgDe(xhr));
                })
                .always(function() {
                    $b.prop('disabled', false);
                });
        });

        $('#btnConsultCompanyNif').on('click', function() {
            var nif = $('#registration_number').val().trim();
            if (!/^[A-Za-z0-9]{5,25}$/.test(nif)) {
                window.setFieldError('registration_number', 'Indique um NIF válido (5 a 25 letras ou números).');
                $('#registration_number').trigger('focus');
                return;
            }

            var $button = $(this).prop('disabled', true).text('A consultar...');
            $('#companyNifStatus').removeClass('text-danger text-success').addClass('text-muted').text('A consultar o NIF na AGT...');
            $.ajax({
                url: registrationBasePath + '/contacts/ajax/consult_nif.php',
                type: 'GET',
                data: { tipoDocumento: 'NIF', numeroDocumento: nif },
                dataType: 'json',
                timeout: 20000
            }).done(function(response) {
                if (response.status === 'success' && response.data) {
                    if (response.data.nome) $('#company_name').val(response.data.nome).trigger('input');
                    $('#companyNifStatus').removeClass('text-muted text-danger').addClass('text-success').text('NIF encontrado na AGT. Confirme o nome da empresa.');
                } else if (response.status === 'not_found') {
                    $('#companyNifStatus').removeClass('text-muted text-success').addClass('text-danger').text((response.message || 'NIF não encontrado na AGT.') + ' Pode preencher o nome manualmente.');
                } else {
                    $('#companyNifStatus').removeClass('text-muted text-success').addClass('text-danger').text(response.message || 'Não foi possível consultar o NIF. Pode preencher os dados manualmente.');
                }
            }).fail(function(xhr) {
                $('#companyNifStatus').removeClass('text-muted text-success').addClass('text-danger').text(msgDe(xhr) + ' Pode preencher os dados manualmente.');
            }).always(function() {
                $button.prop('disabled', false).text('Consultar NIF na AGT');
            });
        });

        $company.on('submit', function(e) {
            e.preventDefault();
            var nif = $('#registration_number').val().trim();
            if (!/^[A-Za-z0-9]{5,25}$/.test(nif)) {
                window.setFieldError('registration_number', 'Indique um NIF válido (5 a 25 letras ou números).');
                return;
            }
            var $button = $('#btnCreateCompany').prop('disabled', true);
            $.ajax({
                url: registrationBasePath + '/register/ajax/create_company.php',
                type: 'POST',
                data: $company.serialize(),
                headers: { 'X-CSRF-Token': registrationCsrf || '' },
                dataType: 'json',
                timeout: 20000
            }).done(function(response) {
                if (response && response.success) {
                    showStep($password);
                    $('#registration_password').focus();
                } else {
                    erro((response && response.message) || 'Não foi possível criar a empresa.');
                }
            }).fail(function(xhr) {
                erro(msgDe(xhr));
            }).always(function() {
                $button.prop('disabled', false);
            });
        });

        $password.on('submit', function(e) {
            e.preventDefault();
            var password = $('#registration_password').val();
            var confirmation = $('#registration_password_confirm').val();
            if (password !== confirmation) {
                window.setFieldError('password_confirm', 'As senhas não coincidem.');
                return;
            }
            var $button = $('#btnSetPassword').prop('disabled', true);
            $.ajax({
                url: registrationBasePath + '/register/ajax/set_password.php',
                type: 'POST',
                data: $password.serialize(),
                headers: { 'X-CSRF-Token': registrationCsrf || '' },
                dataType: 'json',
                timeout: 20000
            }).done(function(response) {
                if (!response || !response.success) {
                    erro((response && response.message) || 'Não foi possível definir a senha.');
                    return;
                }

                $.ajax({
                    url: registrationBasePath + '/api/auth/login',
                    type: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-CSRF-Token': registrationCsrf || '' },
                    data: JSON.stringify({
                        user_email: $('#phone').val().trim(),
                        password: password
                    }),
                    dataType: 'json',
                    timeout: 20000
                }).done(function() {
                    window.location.href = registrationBasePath + '/';
                }).fail(function() {
                    erro('Registo concluído, mas não foi possível iniciar sessão automaticamente. Entre com o telefone e a senha que definiu.');
                    window.setTimeout(function() { window.location.href = 'login.php'; }, 1800);
                });
            }).fail(function(xhr) {
                erro(msgDe(xhr));
            }).always(function() {
                $button.prop('disabled', false);
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