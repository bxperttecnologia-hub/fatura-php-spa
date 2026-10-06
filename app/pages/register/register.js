$(document).ready(function () {
  // =========================================================
  // Utilitários de estado visual (definidos em register.php:
  // setFieldError / clearFieldError / setFieldSuccess / clearFieldSuccess)
  // Com fallback caso o inline script não tenha carregado.
  // =========================================================
  function fieldError(field, msg) {
    if (typeof window.setFieldError === "function") {
      window.setFieldError(field, msg);
    }
  }
  function fieldClearError(field) {
    if (typeof window.clearFieldError === "function") {
      window.clearFieldError(field);
    }
  }
  function fieldSuccess(field) {
    if (typeof window.setFieldSuccess === "function") {
      window.setFieldSuccess(field);
    }
  }
  function fieldClearSuccess(field) {
    if (typeof window.clearFieldSuccess === "function") {
      window.clearFieldSuccess(field);
    }
  }
  function fieldReset(field) {
    fieldClearError(field);
    fieldClearSuccess(field);
  }

  function mostrarErroGenerico(mensagem) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Erro",
        text: mensagem,
      });
    } else {
      alert(mensagem);
    }
  }

  // =========================================================
  // Estado do formulário / botão de submit
  // =========================================================
  function verificarCampos() {
    let usernameValido = $('.field-group[data-field="username"]').hasClass(
      "is-success",
    );
    let emailValido = $('.field-group[data-field="email"]').hasClass(
      "is-success",
    );
    let senhaValida = $('.field-group[data-field="password"]').hasClass(
      "is-success",
    );

    let formEl = document.getElementById("registerForm");
    let formValido = formEl ? formEl.checkValidity() : true;

    $("#submitBtn").prop(
      "disabled",
      !(usernameValido && emailValido && senhaValida && formValido),
    );
  }

  // Reavalia o botão sempre que qualquer campo obrigatório mudar
  $("#registerForm").on("input change", "input, select", verificarCampos);

  // =========================================================
  // Submissão do formulário
  // =========================================================
  $("#registerForm").on("submit", function (e) {
    e.preventDefault();

    let formEl = this;
    if (!formEl.checkValidity()) {
      formEl.reportValidity();
      return;
    }

    let $form = $(formEl);
    let $btn = $("#submitBtn");
    let formData = $form.serialize();

    if ($btn.prop("disabled")) return; // evita duplo clique/submissão

    let textoOriginal = $btn.text();
    $btn.prop("disabled", true).text("A processar...");

    $.ajax({
      url: "register/ajax/process_register.php",
      type: "POST",
      data: formData,
      dataType: "json",
      timeout: 20000,
    })
      .done(function (response) {
        if (response && response.success) {
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "success",
              title: "Cadastro realizado com sucesso!",
              showConfirmButton: false,
              timer: 1500,
            }).then(function () {
              window.location.href = "login.php";
            });
          } else {
            window.location.href = "login.php";
          }
        } else {
          mostrarErroGenerico(
            (response && response.message) ||
              "Não foi possível concluir o cadastro. Tente novamente.",
          );
        }
      })
      .fail(function (jqXHR, textStatus) {
        let mensagem =
          "Ocorreu um erro ao processar o seu pedido. Tente novamente.";

        if (textStatus === "timeout") {
          mensagem =
            "O pedido demorou demasiado tempo. Verifique a sua ligação e tente novamente.";
        } else if (jqXHR.status === 0) {
          mensagem =
            "Sem ligação à internet. Verifique a sua rede e tente novamente.";
        } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
          mensagem = jqXHR.responseJSON.message;
        } else if (jqXHR.status === 422 || jqXHR.status === 400) {
          mensagem =
            "Alguns dados são inválidos. Verifique os campos e tente novamente.";
        } else if (jqXHR.status === 409) {
          mensagem = "Já existe um registo com estes dados.";
        } else if (jqXHR.status >= 500) {
          mensagem = "Erro no servidor. Tente novamente mais tarde.";
        }

        mostrarErroGenerico(mensagem);
      })
      .always(function () {
        $btn.prop("disabled", false).text(textoOriginal);
        verificarCampos();
      });
  });

  // =========================================================
  // País / máscaras (telefone, CNPJ-NIF)
  // Seletor robusto: usa o contentor com id fixo definido em register.php,
  // em vez de assumir o id/estrutura exata gerada por gerarDropdownPaises().
  // =========================================================
  let $countrySelect = $(
    "#countryDropdownWrapper select, #countryDropdownWrapper input[type='hidden']",
  );

  function paisAtual() {
    let raw = ($countrySelect.val() || "Angola").toString().toLowerCase();
    return raw.indexOf("angola") !== -1 ? "angola" : "brasil";
  }

  function aplicarMascaraTelefone(input, country) {
    if (!$.fn.mask) return; // evita erro se o plugin de máscara não estiver carregado
    let mask =
      country === "angola" ? "+244 000 000 000" : "+55 (00) 00000-0000";
    let placeholder =
      country === "angola" ? "+244 ___ ___ ___" : "+55 (__) _____-____";
    $(input).mask(mask, { placeholder: placeholder });
  }

  function aplicarMascaraCnpjNif(input, country) {
    if (!$.fn.mask) return;
    let mask = country === "angola" ? "0000000000" : "00.000.000/0000-00";
    $(input).mask(mask);
  }

  function atualizarMascaras() {
    let country = paisAtual();
    aplicarMascaraTelefone("#phone", country);
    aplicarMascaraTelefone("#company_phone", country);
    aplicarMascaraCnpjNif("#registration_number", country);
  }

  atualizarMascaras();

  // Reaplica as máscaras quando o utilizador muda o país (o código anterior
  // lia o país só uma vez e nunca reagia a alterações)
  $countrySelect.on("change", atualizarMascaras);

  // =========================================================
  // Username: valida tamanho, evita pedidos excessivos (debounce)
  // e cancela pedidos antigos ainda pendentes (evita respostas fora de ordem)
  // =========================================================
  let usernameTimer = null;
  let usernameXhr = null;

  $("#username").on("input", function () {
    let username = $(this).val().trim();

    clearTimeout(usernameTimer);
    if (usernameXhr && usernameXhr.readyState !== 4) {
      usernameXhr.abort();
    }

    if (username.length === 0) {
      fieldReset("username");
      verificarCampos();
      return;
    }

    if (username.length < 3) {
      fieldClearSuccess("username");
      fieldError(
        "username",
        "O nome de usuário deve ter pelo menos 3 caracteres.",
      );
      verificarCampos();
      return;
    }

    // Só dispara o pedido 400ms depois do utilizador parar de escrever
    usernameTimer = setTimeout(function () {
      usernameXhr = $.post(
        "register/ajax/verifica_username.php",
        { username: username },
        null,
        "json",
      )
        .done(function (response) {
          if (!response) {
            fieldError(
              "username",
              "Erro ao verificar usuário. Tente novamente.",
            );
          } else if (response.error) {
            fieldError("username", response.error);
          } else if (response.existe) {
            fieldError("username", "Nome de usuário já está em uso!");
          } else {
            fieldSuccess("username");
          }
        })
        .fail(function (jqXHR, textStatus) {
          if (textStatus === "abort") return; // pedido cancelado por uma nova digitação, ignora
          fieldError("username", "Erro ao verificar usuário. Tente novamente.");
        })
        .always(verificarCampos);
    }, 400);
  });

  // =========================================================
  // E-mail: validação de formato real (o código anterior só checava o tamanho)
  // =========================================================
  let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  $("#email").on("input", function () {
    let email = $(this).val().trim();

    if (email.length === 0) {
      fieldReset("email");
      verificarCampos();
      return;
    }

    if (!emailRegex.test(email)) {
      fieldClearSuccess("email");
      fieldError("email", "Formato de e-mail inválido.");
    } else {
      fieldSuccess("email");
    }

    verificarCampos();
  });

  // =========================================================
  // Senha: mantém as regras visuais, mas agora integradas
  // com os estados has-error / is-success dos novos campos
  // =========================================================
  $("#password").on("input focus", function () {
    let senha = $(this).val();
    let regras = $("#passwordRules");

    if (senha.length > 0) {
      regras.removeClass("d-none");
    } else {
      regras.addClass("d-none");
    }

    let regrasValidas = 0;

    if (senha.length >= 6) {
      $("#rule-length").removeClass("text-danger").addClass("text-success");
      regrasValidas++;
    } else {
      $("#rule-length").removeClass("text-success").addClass("text-danger");
    }

    if (/[A-Z]/.test(senha)) {
      $("#rule-uppercase").removeClass("text-danger").addClass("text-success");
      regrasValidas++;
    } else {
      $("#rule-uppercase").removeClass("text-success").addClass("text-danger");
    }

    if (/[a-z]/.test(senha)) {
      $("#rule-lowercase").removeClass("text-danger").addClass("text-success");
      regrasValidas++;
    } else {
      $("#rule-lowercase").removeClass("text-success").addClass("text-danger");
    }

    if (/[!@#$%^&*]/.test(senha)) {
      $("#rule-special").removeClass("text-danger").addClass("text-success");
      regrasValidas++;
    } else {
      $("#rule-special").removeClass("text-success").addClass("text-danger");
    }

    if (senha.length === 0) {
      fieldReset("password");
    } else if (regrasValidas === 4) {
      fieldSuccess("password");
    } else {
      fieldClearSuccess("password");
      fieldError(
        "password",
        "A senha ainda não cumpre todos os requisitos acima.",
      );
    }

    verificarCampos();
  });

  $(document).on("click", function (event) {
    if (!$(event.target).closest("#password, #passwordRules").length) {
      $("#passwordRules").addClass("d-none");
    }
  });

  // Estado inicial do botão (todos os campos vazios no carregamento)
  verificarCampos();
});
