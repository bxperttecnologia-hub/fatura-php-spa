$(function () {
  const modalElement = document.getElementById("userPermissionsModal");
  if (!modalElement) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const list = document.getElementById("userPermissionsList");
  const form = document.getElementById("userPermissionsForm");
  const saveButton = document.getElementById("saveUserPermissionsBtn");
  const csrfHeaders = window.APP?.csrf
    ? { "X-CSRF-Token": window.APP.csrf }
    : {};

  $(document).on("click", ".js-user-permissions", function () {
    const userId = String($(this).data("user-id"));
    $("#permissionsUserId").val(userId);
    $("#userPermissionsName").text($(this).data("user-name") || "");
    list.replaceChildren();
    saveButton.disabled = true;
    modal.show();

    $.ajax({
      url: "/manage_users/ajax/get_permissions.php",
      method: "GET",
      dataType: "json",
      data: {
        company_id: window.MANAGE_USERS_COMPANY_ID,
        user_id: userId,
      },
      success(response) {
        if (!response.success) {
          modal.hide();
          Swal.fire("Erro", response.message || "Não foi possível carregar as permissões.", "error");
          return;
        }

        for (const [module, label] of Object.entries(response.modules || {})) {
          const column = document.createElement("div");
          column.className = "col-12 col-sm-6";

          const wrapper = document.createElement("div");
          wrapper.className = "form-check border rounded p-3 ps-5 h-100";

          const input = document.createElement("input");
          input.className = "form-check-input";
          input.type = "checkbox";
          input.name = "allowed_modules[]";
          input.value = module;
          input.id = `permission-${userId}-${module}`;
          input.checked = Boolean(response.permissions?.[module]);

          const labelElement = document.createElement("label");
          labelElement.className = "form-check-label";
          labelElement.htmlFor = input.id;
          labelElement.textContent = label;

          wrapper.append(input, labelElement);
          column.append(wrapper);
          list.append(column);
        }

        if (response.locked) {
          list.insertAdjacentHTML(
            "afterbegin",
            '<div class="col-12"><div class="alert alert-warning mb-0">O perfil de proprietário mantém acesso total.</div></div>',
          );
        }
        saveButton.disabled = Boolean(response.locked);
      },
      error(xhr) {
        modal.hide();
        Swal.fire(
          "Erro",
          xhr.responseJSON?.message || "Falha ao carregar as permissões.",
          "error",
        );
      },
    });
  });

  $(form).on("submit", function (event) {
    event.preventDefault();
    saveButton.disabled = true;

    $.ajax({
      url: "/manage_users/ajax/save_permissions.php",
      method: "POST",
      dataType: "json",
      headers: csrfHeaders,
      data: {
        company_id: window.MANAGE_USERS_COMPANY_ID,
        user_id: $("#permissionsUserId").val(),
        allowed_modules: $(form)
          .find('input[name="allowed_modules[]"]:checked')
          .map((_, input) => input.value)
          .get(),
      },
      success(response) {
        if (!response.success) {
          saveButton.disabled = false;
          Swal.fire("Erro", response.message || "Não foi possível guardar as permissões.", "error");
          return;
        }
        modal.hide();
        Swal.fire("Sucesso", response.message || "Permissões atualizadas.", "success");
      },
      error(xhr) {
        saveButton.disabled = false;
        Swal.fire(
          "Erro",
          xhr.responseJSON?.message || "Falha ao guardar as permissões.",
          "error",
        );
      },
    });
  });
});