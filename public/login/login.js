$(document).ready(function () {
  if (window.location.search.includes("x22")) {
    $("#error-message")
      .removeClass("d-none")
      .fadeIn(500)
      .delay(3000)
      .fadeOut(500);
  }

  $("#loginForm").submit(function (e) {
    e.preventDefault();

    // Carrega a chave pública
    let publicKey = `-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyvD0FK5TIqd0enxe7D2Y
/scdFzENRlYZlaY/ejzDh1EuJpi1lOeQ+L68JEKlUNenwmL/vUTh91q+C+2FqEbb
0ROtk03ka+bHZ7bsFQfxxzQyY3Q3ihjJSol5rGPjZu/nML2vQlbMP63P66HAZhsy
dKLfpO6NYHhVDFcK5dkM3U3wAJqimIDj9jnWSLsbzhkh+cFRUDBBG0jamNIzkCtj
sFnTQ4VWWdTBnYBk4J6zAxgLUy1l+xkEDgHT5M/T687+e3SnQQ52+Itrvb0wBcST
uNMgjp78zDPQs0xhusVzsVf253sErSdfxNqbvPMjD3u7eDt2rtmRoFD0UA/dsnu/
EQIDAQAB
-----END PUBLIC KEY-----
`;

    let formData = $(this).serializeArray();
    let encrypt = new JSEncrypt();
    encrypt.setPublicKey(publicKey);

    // Encontra o campo de senha e criptografa
    formData.forEach((field) => {
      if (field.name === "password") {
        field.value = encrypt.encrypt(field.value);
      }
    });

    $.ajax({
      url: "login/ajax/process_login.php",
      type: "POST",
      data: $.param(formData),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.href = "index.php";
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro",
            text: response.message,
          });
        }
      },
      error: function () {
        Swal.fire({
          icon: "error",
          title: "Erro",
          text: "Falha na comunicação com o servidor.",
        });
      },
    });
  });
});
