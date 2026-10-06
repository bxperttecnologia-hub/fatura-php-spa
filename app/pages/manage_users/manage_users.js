$(document).ready(function () {
  // --- Fluxo em 2 modais (checar email -> vincular OU criar) ---
  const checkModal = new bootstrap.Modal(
    document.getElementById("checkCollaboratorModal"),
  );
  const linkModal = new bootstrap.Modal(
    document.getElementById("linkCollaboratorModal"),
  );
  const createModal = new bootstrap.Modal(
    document.getElementById("createCollaboratorModal"),
  );

  // Modais de ações
  const changeRoleModal = new bootstrap.Modal(
    document.getElementById("changeRoleModal"),
  );
  const unlinkUserModal = new bootstrap.Modal(
    document.getElementById("unlinkUserModal"),
  );

  let lastCheckedEmail = null;

  function postCreateCollaborator(formData) {
    const company_id = window.MANAGE_USERS_COMPANY_ID;
    formData.append("company_id", company_id);

    return $.ajax({
      url: "manage_users/ajax/create_collaborator.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
    });
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || "").trim());
  }

  $("#addCollaboratorBtn").on("click", function () {
    // reset
    const f = document.getElementById("checkCollaboratorForm");
    if (f) f.reset();
    $('#checkCollaboratorForm input[name="email"]').val("");
    $("#checkContinueBtn").prop("disabled", true);
    checkModal.show();
  });

  // habilita botão só quando email estiver válido
  $('#checkCollaboratorForm input[name="email"]').on("input", function () {
    const email = $(this).val();
    $("#checkContinueBtn").prop("disabled", !isValidEmail(email));
  });

  $("#checkCollaboratorForm").on("submit", function (e) {
    e.preventDefault();
    const company_id = window.MANAGE_USERS_COMPANY_ID;
    const email = String(
      $(this).find('input[name="email"]').val() || "",
    ).trim();
    if (!isValidEmail(email)) {
      Swal.fire("Atenção", "Digite um email válido.", "warning");
      return;
    }

    // loading removido a pedido

    $.ajax({
      url: "manage_users/ajax/check_user.php",
      type: "GET",
      dataType: "json",
      data: { company_id, email },
      success: function (resp) {
        if (!resp.success) {
          Swal.fire(
            "Erro",
            resp.message || "Falha ao verificar usuário.",
            "error",
          );
          return;
        }

        lastCheckedEmail = email;

        if (resp.exists) {
          // preenche modal de vínculo
          const d = resp.data || {};
          $('#linkCollaboratorForm input[name="name"]').val(d.name || "");
          $('#linkCollaboratorForm input[name="email"]').val(d.email || email);
          $('#linkCollaboratorForm input[name="username"]').val(
            d.username || "",
          );
          $('#linkCollaboratorForm select[name="role"]').val("employee");

          // se já estiver vinculado, só informa e bloqueia botão
          if (resp.already_linked) {
            $("#linkUserInfo")
              .removeClass("alert-info")
              .addClass("alert-warning")
              .text("Este usuário já está vinculado a esta empresa.");
            $('#linkCollaboratorForm button[type="submit"]').prop(
              "disabled",
              true,
            );
          } else {
            $("#linkUserInfo")
              .removeClass("alert-warning")
              .addClass("alert-info")
              .text(
                "Este colaborador já possui conta. Vamos apenas vincular à empresa.",
              );
            $('#linkCollaboratorForm button[type="submit"]').prop(
              "disabled",
              false,
            );
          }

          checkModal.hide();
          linkModal.show();
        } else {
          // preenche modal de criação
          $('#createCollaboratorForm input[name="name"]').val("");
          $('#createCollaboratorForm input[name="email"]').val(email);
          $('#createCollaboratorForm input[name="username"]').val("");
          $('#createCollaboratorForm input[name="password"]').val("");
          $('#createCollaboratorForm select[name="role"]').val("employee");
          $('#createCollaboratorForm select[name="is_active"]').val("1");

          checkModal.hide();
          createModal.show();
        }
      },
      error: function () {
        Swal.fire("Erro", "Falha ao verificar usuário.", "error");
      },
    });
  });

  // Vincular usuário existente
  $("#linkCollaboratorForm").on("submit", function (e) {
    e.preventDefault();

    const $btn = $('#linkCollaboratorForm button[type="submit"]');
    const oldTxt = $btn.text();
    if ($btn.prop("disabled")) return;
    $btn.prop("disabled", true).text("Vinculando...");

    const fd = new FormData();
    fd.append("name", $('#linkCollaboratorForm input[name="name"]').val());
    fd.append("email", $('#linkCollaboratorForm input[name="email"]').val());
    fd.append("role", $('#linkCollaboratorForm select[name="role"]').val());

    postCreateCollaborator(fd)
      .done(function (resp) {
        if (resp.success) {
          createModal.hide();
          Swal.fire(
            "Sucesso",
            "Colaborador criado e vinculado. Um email foi enviado para ele definir a senha.",
            "success",
          );
          setTimeout(() => window.location.reload(), 600);
        } else {
          $btn.prop("disabled", false).text(oldTxt);
          Swal.fire(
            "Erro",
            resp.message || "Erro ao vincular colaborador.",
            "error",
          );
        }
      })
      .fail(function () {
        $btn.prop("disabled", false).text(oldTxt);
        Swal.fire("Erro", "Falha na requisição.", "error");
      });
  });

  // Criar usuário novo + vincular
  $("#createCollaboratorForm").on("submit", function (e) {
    e.preventDefault();

    const $btn = $('#createCollaboratorForm button[type="submit"]');
    const oldTxt = $btn.text();
    if ($btn.prop("disabled")) return;
    $btn.prop("disabled", true).text("Salvando...");

    const fd = new FormData(this);

    postCreateCollaborator(fd)
      .done(function (resp) {
        if (resp.success) {
          createModal.hide();
          if (resp.data && resp.data.generated_password) {
            Swal.fire(
              "Colaborador criado",
              "Senha temporária: " + resp.data.generated_password,
              "success",
            );
          } else {
            Swal.fire("Sucesso", "Colaborador criado e vinculado.", "success");
          }
          setTimeout(() => window.location.reload(), 600);
        } else {
          $btn.prop("disabled", false).text(oldTxt);
          Swal.fire(
            "Erro",
            resp.message || "Erro ao criar colaborador.",
            "error",
          );
        }
      })
      .fail(function () {
        $btn.prop("disabled", false).text(oldTxt);
        Swal.fire("Erro", "Falha na requisição.", "error");
      });
  });

  // Ações: abrir modais a partir da tabela (event delegation)
  $(document).on("click", ".js-change-role", function () {
    const userId = $(this).data("user-id");
    const name = $(this).data("user-name");
    const email = $(this).data("user-email");
    const currentRole = $(this).data("current-role");

    $("#changeRoleUserId").val(userId);
    $("#changeRoleUserName").text(name || "");
    $("#changeRoleUserEmail").text(email || "");
    $("#changeRoleSelect").val(currentRole || "employee");

    changeRoleModal.show();
  });

  $(document).on("click", ".js-unlink-user", function () {
    const userId = $(this).data("user-id");
    const name = $(this).data("user-name");
    const email = $(this).data("user-email");
    const currentRole = $(this).data("current-role");

    // não permitir remover owner via UI (backend também bloqueia pra admin)
    if (String(currentRole) === "owner") {
      Swal.fire(
        "Atenção",
        "Não é possível remover um proprietário (owner) por aqui.",
        "warning",
      );
      return;
    }

    $("#unlinkUserId").val(userId);
    $("#unlinkUserName").text(name || "");
    $("#unlinkUserEmail").text(email || "");
    unlinkUserModal.show();
  });

  $("#changeRoleForm").on("submit", function (e) {
    e.preventDefault();

    const $btn = $('#changeRoleForm button[type="submit"]');
    const oldTxt = $btn.text();
    if ($btn.prop("disabled")) return;
    $btn.prop("disabled", true).text("Salvando...");

    $.ajax({
      url: "manage_users/ajax/update_role.php",
      type: "POST",
      dataType: "json",
      data: {
        company_id: window.MANAGE_USERS_COMPANY_ID,
        user_id: $("#changeRoleUserId").val(),
        role: $("#changeRoleSelect").val(),
      },
      success: function (resp) {
        if (resp.success) {
          changeRoleModal.hide();
          Swal.fire("Sucesso", "Role atualizada.", "success");
          setTimeout(() => window.location.reload(), 600);
        } else {
          $btn.prop("disabled", false).text(oldTxt);
          Swal.fire("Erro", resp.message || "Erro ao atualizar role.", "error");
        }
      },
      error: function () {
        $btn.prop("disabled", false).text(oldTxt);
        Swal.fire("Erro", "Falha na requisição.", "error");
      },
    });
  });

  $("#unlinkUserForm").on("submit", function (e) {
    e.preventDefault();

    const $btn = $('#unlinkUserForm button[type="submit"]');
    const oldTxt = $btn.text();
    if ($btn.prop("disabled")) return;
    $btn.prop("disabled", true).text("Removendo...");

    $.ajax({
      url: "manage_users/ajax/unlink_user.php",
      type: "POST",
      dataType: "json",
      data: {
        company_id: window.MANAGE_USERS_COMPANY_ID,
        user_id: $("#unlinkUserId").val(),
      },
      success: function (resp) {
        if (resp.success) {
          unlinkUserModal.hide();
          Swal.fire("Sucesso", "Colaborador removido da empresa.", "success");
          setTimeout(() => window.location.reload(), 600);
        } else {
          $btn.prop("disabled", false).text(oldTxt);
          Swal.fire(
            "Erro",
            resp.message || "Erro ao remover colaborador.",
            "error",
          );
        }
      },
      error: function () {
        $btn.prop("disabled", false).text(oldTxt);
        Swal.fire("Erro", "Falha na requisição.", "error");
      },
    });
  });
});
