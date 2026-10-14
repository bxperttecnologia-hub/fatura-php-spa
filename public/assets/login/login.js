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

    const formData = Object.fromEntries($(this).serializeArray().map(({ name, value }) => [name, value]));

    $.ajax({
      url: "/api/v1/auth/login",
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        email: formData.email || formData.user_email,
        password: formData.password,
      }),
      dataType: "json",
      success: function (response) {
        if (response.success) {
          window.location.href = "/dashboard";
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
