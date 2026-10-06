$(document).ready(function () {
  // Mesma chave pública usada no login.js
  const publicKey = `-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyvD0FK5TIqd0enxe7D2Y
/scdFzENRlYZlaY/ejzDh1EuJpi1lOeQ+L68JEKlUNenwmL/vUTh91q+C+2FqEbb
0ROtk03ka+bHZ7bsFQfxxzQyY3Q3ihjJSol5rGPjZu/nML2vQlbMP63P66HAZhsy
dKLfpO6NYHhVDFcK5dkM3U3wAJqimIDj9jnWSLsbzhkh+cFRUDBBG0jamNIzkCtj
sFnTQ4VWWdTBnYBk4J6zAxgLUy1l+xkEDgHT5M/T687+e3SnQQ52+Itrvb0wBcST
uNMgjp78zDPQs0xhusVzsVf253sErSdfxNqbvPMjD3u7eDt2rtmRoFD0UA/dsnu/
EQIDAQAB
-----END PUBLIC KEY-----
`;

  const encrypt = new JSEncrypt();
  encrypt.setPublicKey(publicKey);

  const endpoints = {
    send: "login/ajax/forgot_send.php",
    verify: "login/ajax/forgot_verify.php",
    reset: "login/ajax/forgot_reset.php",
  };

  const subtitles = {
    method: "Escolha como deseja recuperar o acesso.",
    contact: "Informe os seus dados para receber o código.",
    code: "Digite o código que enviámos.",
    reset: "Defina a sua nova senha.",
  };

  let state = { method: null, identifier: "", channel: "email" };
  let timer = null;
  let busy = false;

  function show(step) {
    $(".fp-step").addClass("d-none");
    $("#step-" + step).removeClass("d-none");
    $("#fpSubtitle").text(subtitles[step]);
  }

  function showError(msg) {
    Swal.fire({ icon: "error", title: "Erro", text: msg });
  }

  function post(url, data, $btn, onOk) {
    if (busy) return;
    busy = true;
    $btn.prop("disabled", true);
    $.ajax({ url: url, type: "POST", data: data, dataType: "json" })
      .done(function (res) {
        if (res.success) onOk(res);
        else showError(res.message);
      })
      .fail(function () {
        showError("Falha na comunicação com o servidor.");
      })
      .always(function () {
        busy = false;
        $btn.not(".cooldown").prop("disabled", false);
      });
  }

  function startCooldown(seconds) {
    clearInterval(timer);
    const $b = $("#btnResend").addClass("cooldown").prop("disabled", true);
    $("#resendTimer").text(" (" + seconds + "s)");
    timer = setInterval(function () {
      seconds--;
      if (seconds <= 0) {
        clearInterval(timer);
        $b.removeClass("cooldown").prop("disabled", false);
        $("#resendTimer").text("");
      } else {
        $("#resendTimer").text(" (" + seconds + "s)");
      }
    }, 1000);
  }

  // ---- Passo 1: escolher método ----
  $(".method-card").on("click", function () {
    state.method = $(this).data("method");
    const isEmail = state.method === "email";

    $("#contactLabel").text(isEmail ? "E-mail" : "Telefone (com prefixo do país)");
    $("#contactIcon").text(isEmail ? "mail" : "call");
    $("#identifier")
      .attr({
        type: isEmail ? "email" : "tel",
        inputmode: isEmail ? "email" : "tel",
        autocomplete: isEmail ? "email" : "tel",
        placeholder: isEmail ? "seuemail@exemplo.com" : "+244 900 000 000",
      })
      .val("");
    $("#channelGroup").toggleClass("d-none", isEmail);

    show("contact");
    $("#identifier").trigger("focus");
  });

  // ---- Passo 2: enviar código ----
  function sendCode($btn) {
    state.identifier = $.trim($("#identifier").val());
    state.channel =
      state.method === "email" ? "email" : $("input[name=channel]:checked").val();

    post(
      endpoints.send,
      { method: state.method, identifier: state.identifier, channel: state.channel },
      $btn,
      function (res) {
        $("#maskedDest").text(res.masked);
        $("#code").val("");
        show("code");
        startCooldown(60);
        $("#code").trigger("focus");
      }
    );
  }

  $("#formSend").on("submit", function (e) {
    e.preventDefault();
    sendCode($("#btnSend"));
  });

  $("#btnResend").on("click", function () {
    sendCode($(this));
  });

  // ---- Passo 3: verificar código ----
  $("#code").on("input", function () {
    this.value = this.value.replace(/\D/g, "").slice(0, 6);
  });

  $("#formVerify").on("submit", function (e) {
    e.preventDefault();
    const code = $("#code").val();
    if (code.length !== 6) {
      showError("Digite o código de 6 dígitos.");
      return;
    }
    post(endpoints.verify, { code: code }, $("#btnVerify"), function () {
      show("reset");
      $("#new_password").trigger("focus");
    });
  });

  // ---- Passo 4: nova senha ----
  $("#formReset").on("submit", function (e) {
    e.preventDefault();
    const p1 = $("#new_password").val();
    const p2 = $("#confirm_password").val();

    if (p1.length < 8 || !/[A-Za-z]/.test(p1) || !/\d/.test(p1)) {
      showError("A senha deve ter pelo menos 8 caracteres, com letras e números.");
      return;
    }
    if (p1 !== p2) {
      showError("As senhas não coincidem.");
      return;
    }

    const e1 = encrypt.encrypt(p1);
    const e2 = encrypt.encrypt(p2);
    if (!e1 || !e2) {
      showError("Não foi possível proteger a senha. Tente novamente.");
      return;
    }

    post(
      endpoints.reset,
      { password: e1, password_confirm: e2 },
      $("#btnReset"),
      function () {
        Swal.fire({
          icon: "success",
          title: "Senha alterada",
          text: "Já pode entrar com a sua nova senha.",
        }).then(function () {
          window.location.href = "login.php";
        });
      }
    );
  });

  // ---- Mostrar/ocultar senha ----
  $(".toggle-pass").on("click", function () {
    const $input = $($(this).data("target"));
    const $icon = $(this).find("i");
    const hidden = $input.attr("type") === "password";
    $input.attr("type", hidden ? "text" : "password");
    $icon.text(hidden ? "visibility_off" : "visibility");
  });

  // ---- Voltar ----
  $("[data-back]").on("click", function (e) {
    e.preventDefault();
    show($(this).data("back"));
  });

  show("method");
});