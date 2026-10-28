(function () {
const PERFIL_DEBUG = false;

// Cropper
let cropper = null;
let cropModal = null;

function openCropModalFromFile(file) {
  const imgEl = document.getElementById("cropImage");
  const previewEl = document.getElementById("cropPreview");

  // garante modal (evita null no cropModal.show())
  if (!cropModal) {
    const modalEl = document.getElementById("cropModal");
    if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal) {
      console.error("Crop modal/bootstrap não disponível");
      Swal && Swal.fire
        ? Swal.fire("Erro", "Não foi possível abrir o ajuste de foto.", "error")
        : alert("Não foi possível abrir o ajuste de foto.");
      return;
    }
    cropModal = new bootstrap.Modal(modalEl);
  }

  // limpa cropper anterior
  if (cropper) {
    cropper.destroy();
    cropper = null;
  }

  const reader = new FileReader();
  reader.onload = function (e) {
    // reseta imagens (evita prévia antiga)
    imgEl.src = "";
    previewEl.src = "";

    imgEl.src = e.target.result;
    previewEl.src = e.target.result;

    cropModal.show();

    // espera a imagem carregar para iniciar o cropper
    imgEl.onload = function () {
      cropper = new Cropper(imgEl, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: "move",
        autoCropArea: 1,
        background: false,
        responsive: true,
        modal: true,
        guides: true,
        center: true,
        highlight: true,
        minContainerWidth: 320,
        minContainerHeight: 320,
        movable: true,
        zoomable: true,
        rotatable: false,
        scalable: false,
        ready: function () {
          // força atualização da prévia ao iniciar
          const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
          if (canvas) {
            previewEl.src = canvas.toDataURL("image/jpeg", 0.9);
          }
        },
        crop: function () {
          if (!cropper) return;
          const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
          if (canvas) {
            previewEl.src = canvas.toDataURL("image/jpeg", 0.9);
          }
        },
      });
    };
  };
  reader.readAsDataURL(file);
}

// (removido) ajuste de enquadramento da foto já salva: agora o crop é aplicado apenas ao selecionar uma nova foto.

async function uploadCroppedImage() {
  if (!cropper) return;

  const canvas = cropper.getCroppedCanvas({
    width: 600,
    height: 600,
    imageSmoothingQuality: "high",
  });
  if (!canvas) return;

  return new Promise((resolve, reject) => {
    canvas.toBlob(
      function (blob) {
        if (!blob) {
          reject(new Error("Falha ao gerar imagem."));
          return;
        }

        const formData = new FormData();
        formData.append("profileImage", blob, "profile.jpg");

        fetch("perfil/ajax/upload.php", {
          method: "POST",
          body: formData,
        })
          .then((r) => r.json())
          .then((data) => {
            if (data.success) {
              // cache-bust
              document
                .getElementById("profileImage")
                .setAttribute(
                  "src",
                  `assets/img/profiles/${data.imagePath}?t=${Date.now()}`,
                );
              resolve(data);
            } else {
              reject(new Error(data.message || "Erro ao salvar a imagem."));
            }
          })
          .catch(reject);
      },
      "image/jpeg",
      0.92,
    );
  });
}

// Selecionar nova foto → abre cropper
document.getElementById("profileInput").addEventListener("change", function () {
  const file = this.files[0];
  if (!file) return;
  openCropModalFromFile(file);
});

$(document).ready(function () {
  function ensureCropModal() {
    if (!cropModal) {
      const el = document.getElementById("cropModal");
      if (!el) throw new Error("Modal de crop não encontrada no DOM.");
      cropModal = new bootstrap.Modal(el);
    }
    return cropModal;
  }

  // (removido) botão de ajustar enquadramento da foto já salva

  $("#saveCropBtn").on("click", function () {
    let m;
    try {
      m = ensureCropModal();
    } catch (e) {
      console.error(e);
      Swal.fire("Erro", "Bootstrap/modal não carregou corretamente.", "error");
      return;
    }

    uploadCroppedImage()
      .then(() => {
        m.hide();
        Swal.fire("Sucesso", "Foto atualizada!", "success");
      })
      .catch((err) => {
        console.error(err);
        Swal.fire("Erro", err.message || "Erro ao atualizar a foto.", "error");
      });
  });

  // Adiciona apenas os campos do formulário de perfil (evita pegar selects de menus/nav)
  const form = document.getElementById("profileForm");

  form.addEventListener("submit", (e) => {
    e.preventDefault();

    // formData precisa ser recriado a cada submit, senão acumula dados de envios anteriores
    const formData = {};

    const $btn = $("#update-profile").prop("disabled", true);

    // form.elements pode não ter .forEach garantido em todos os browsers/contextos;
    // Array.from() converte para array de verdade
    Array.from(form.elements).forEach((field) => {
      // Ignora campos sem "name" (ex.: botões sem name) e botões de submit/reset
      if (
        !field ||
        !field.name ||
        field.type === "submit" ||
        field.type === "reset"
      ) {
        return;
      }

      // Trata checkbox/radio corretamente (value sempre existe, mas só deve ir se marcado)
      if (
        (field.type === "checkbox" || field.type === "radio") &&
        !field.checked
      ) {
        return;
      }

      formData[field.name] = field.value.trim();
    });

    if (PERFIL_DEBUG) {
      console.log("PERFIL DEBUG enviando:", formData);
    }

    $.ajax({
      url: "/api/profile",
      type: "PATCH",
      contentType: "application/json",
      data: JSON.stringify(formData),
      dataType: "json",
      complete: function () {
        $btn.prop("disabled", false);
      },
      success: function (response) {
        if (PERFIL_DEBUG) {
          console.log("PERFIL DEBUG resposta:", response);
        }

        if (response.status === "success") {
          if (window.APP && window.APP.user) {
            window.APP.user.name = formData.name;
            window.APP.user.nome = formData.name;
            window.APP.user.email = formData.email;
          }

          Swal.fire({
            icon: "success",
            title: "Sucesso!",
            text: "Perfil atualizado com sucesso.",
            timer: 2000,
            showConfirmButton: false,
          }).then(() => {
            const $profileCard = $("#profileImage").closest(".card");
            if (formData.name) $profileCard.find("h5").text(formData.name);
            if (formData.email) $profileCard.find("small").text(formData.email);
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Erro!",
            text:
              response.message || "Houve um problema ao atualizar o perfil.",
          });
        }
      },
      error: function (xhr, textStatus, errorThrown) {
        if (PERFIL_DEBUG) {
          console.log("PERFIL DEBUG falha:", {
            status: xhr.status,
            textStatus,
            errorThrown,
            responseText: xhr.responseText,
          });
        }
        Swal.fire({
          icon: "error",
          title: "Erro!",
          text:
            (xhr.responseJSON && xhr.responseJSON.message) ||
            `Falha ao atualizar perfil (HTTP ${xhr.status}: ${textStatus}).`,
        });
      },
    });
  });
});

function esc(v) {
  return $("<div>").text(v == null ? "" : String(v)).html().replace(/"/g, "&quot;");
}

let countryMap = {};

function selectCountry(selectedCountry = "", selectedCity = "") {
  const username = "israelsouza";
  const countrySelect = $("#country");
  const citySelect = $("#city");

  countrySelect
    .html('<option value="">Carregando lista de países...</option>')
    .trigger("change");
  citySelect
    .html('<option value="">Selecione um país primeiro</option>')
    .trigger("change");

  fetch(`https://secure.geonames.org/countryInfoJSON?username=${username}`)
    .then((response) => response.json())
    .then((data) => {
      if (!data.geonames) throw new Error("API retornou dados inválidos");

      countryMap = {};
      let options = '<option value="">Selecione um país</option>';

      data.geonames.forEach((country) => {
        countryMap[country.countryName] = country.geonameId;
        options += `<option value="${esc(country.countryName)}">${esc(country.countryName)}</option>`;
      });

      // país salvo que não existe na lista da API (nome diferente): mantém como opção
      // para o valor não ser perdido/apagado ao salvar
      if (selectedCountry && !countryMap[selectedCountry]) {
        options += `<option value="${esc(selectedCountry)}">${esc(selectedCountry)}</option>`;
      }

      countrySelect.html(options);
      countrySelect.select2({
        width: "100%",
        placeholder: "Selecione um país",
        allowClear: false,
        dropdownParent: countrySelect.parent(),
      });

      // Seleção inicial: "change.select2" só atualiza a UI e NÃO dispara o handler abaixo
      // (antes disparava loadCities duas vezes e a resposta sem cidade podia chegar por último,
      // deixando a cidade vazia e apagando-a no banco ao salvar)
      if (selectedCountry) {
        countrySelect.val(selectedCountry).trigger("change.select2");
        loadCities(selectedCountry, selectedCity);
      }

      // Quando o usuário trocar o país, carregar cidades
      countrySelect.off("change.perfil").on("change.perfil", function () {
        loadCities($(this).val() || "", "");
      });
    })
    .catch((error) => {
      console.error("❌ Erro ao carregar países:", error);
      countrySelect
        .html('<option value="">Erro ao carregar</option>')
        .trigger("change");
    });
}

let cityReq = 0;

function loadCities(countryName, selectedCity = "") {
  const citySelect = $("#city");
  const countryId = countryMap[countryName];
  const myReq = ++cityReq; // ignora respostas antigas que chegarem atrasadas

  if (!countryId) {
    let html = '<option value="">Selecione um país primeiro</option>';
    if (selectedCity) {
      html += `<option value="${esc(selectedCity)}" selected>${esc(selectedCity)}</option>`;
    }
    citySelect.html(html).trigger("change");
    return;
  }

  citySelect
    .html('<option value="">Carregando cidades...</option>')
    .trigger("change");

  fetch(
    `https://secure.geonames.org/childrenJSON?geonameId=${countryId}&username=israelsouza`,
  )
    .then((response) => response.json())
    .then((data) => {
      if (myReq !== cityReq) return;
      if (!data.geonames) throw new Error("API retornou dados inválidos");

      let options = '<option value="">Selecione uma cidade</option>';
      let found = false;
      data.geonames.forEach((city) => {
        const isSel = city.name === selectedCity;
        if (isSel) found = true;
        options += `<option value="${esc(city.name)}" ${isSel ? "selected" : ""}>${esc(city.name)}</option>`;
      });
      if (selectedCity && !found) {
        options += `<option value="${esc(selectedCity)}" selected>${esc(selectedCity)}</option>`;
      }

      citySelect.html(options).trigger("change");
      citySelect.select2({
        width: "100%",
        placeholder: "Selecione uma cidade",
        allowClear: false,
        dropdownParent: citySelect.parent(),
      });

      if (selectedCity) {
        citySelect.val(selectedCity).trigger("change");
      }
    })
    .catch((error) => {
      if (myReq !== cityReq) return;
      console.error("❌ Erro ao carregar cidades:", error);
      // mantém a cidade salva para não ser apagada se a API falhar
      let html = '<option value="">Erro ao carregar</option>';
      if (selectedCity) {
        html += `<option value="${esc(selectedCity)}" selected>${esc(selectedCity)}</option>`;
      }
      citySelect.html(html).trigger("change");
    });
}

window.selectCountry = selectCountry;
})();
