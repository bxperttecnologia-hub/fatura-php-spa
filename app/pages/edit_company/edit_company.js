$(document).ready(function () {
  $(".select2").select2();

  // --- Config ---
  const GEONAMES_USERNAME = "israelsouza"; // centralizado (antes estava duplicado/hardcoded em 2 lugares)

  // Mapa país -> geonameId (escopado ao módulo, antes era global implícito)
  let countryMap = {};

  // Controle de corrida entre requisições (evita resposta antiga sobrescrever a mais recente)
  let countryRequestId = 0;
  let cityRequestId = 0;

  // --- Cropper (Logo da empresa) ---
  let logoCropper = null;
  let logoCropModal = null;
  let croppedLogoBlob = null;
  let currentLogoObjectUrl = null; // para revogar blobs antigos e evitar memory leak

  function escapeHtml(str) {
    return String(str ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  }

  function ensureLogoModal() {
    if (!logoCropModal) {
      const el = document.getElementById("logoCropModal");
      if (!el || typeof bootstrap === "undefined" || !bootstrap.Modal) {
        throw new Error("Bootstrap/modal não disponível");
      }
      logoCropModal = new bootstrap.Modal(el);
    }
    return logoCropModal;
  }

  function openLogoCropperFromFile(file) {
    const imgEl = document.getElementById("logoCropImage");
    if (!imgEl) return;

    // destroi cropper anterior
    if (logoCropper) {
      logoCropper.destroy();
      logoCropper = null;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      imgEl.src = e.target.result;

      try {
        ensureLogoModal().show();
      } catch (err) {
        console.error(err);
        Swal.fire(
          "Erro",
          "Não foi possível abrir o editor de imagem.",
          "error",
        );
        return;
      }

      imgEl.onload = function () {
        logoCropper = new Cropper(imgEl, {
          // corte livre (sem proporção fixa)
          aspectRatio: NaN,
          viewMode: 1,
          dragMode: "move",
          autoCropArea: 1,
          background: false,
          responsive: true,
          movable: true,
          zoomable: true,
          rotatable: false,
          scalable: false,
          minContainerWidth: 320,
          minContainerHeight: 320,
        });
      };
    };
    reader.onerror = function () {
      Swal.fire("Erro", "Não foi possível ler o arquivo selecionado.", "error");
    };
    reader.readAsDataURL(file);
  }

  function setLogoLoading(isLoading) {
    if (isLoading) {
      $("#companyLogoSpinner").show();
      $("#companyLogo").css("opacity", "0");
    } else {
      $("#companyLogoSpinner").hide();
      $("#companyLogo").css("opacity", "1");
    }
  }

  function setLogoSrc(src) {
    const img = document.getElementById("companyLogo");
    if (!img) return;

    setLogoLoading(true);

    // remove handlers antigos para não duplicar
    img.onload = function () {
      setLogoLoading(false);
    };
    img.onerror = function () {
      setLogoLoading(false);
    };

    const isBlobUrl = src.startsWith("blob:");

    // revoga o object URL anterior (se houver) para não vazar memória
    if (currentLogoObjectUrl && currentLogoObjectUrl !== src) {
      URL.revokeObjectURL(currentLogoObjectUrl);
      currentLogoObjectUrl = null;
    }
    if (isBlobUrl) {
      currentLogoObjectUrl = src;
    }

    // cache-bust só faz sentido (e só funciona) para URLs http(s), não para blob:
    if (isBlobUrl) {
      img.src = src;
    } else {
      const bust = (src.includes("?") ? "&" : "?") + "t=" + Date.now();
      img.src = src + bust;
    }
  }

  $("#logo").on("change", function () {
    const file = this.files && this.files[0];
    if (!file) return;
    croppedLogoBlob = null;
    openLogoCropperFromFile(file);
  });

  $("#saveLogoCropBtn").on("click", function () {
    if (!logoCropper) {
      Swal.fire("Erro", "Nenhuma imagem para cortar.", "error");
      return;
    }

    const canvas = logoCropper.getCroppedCanvas({
      // tamanho final "seguro" para logo (mantém boa qualidade sem exagerar)
      maxWidth: 1200,
      maxHeight: 1200,
      imageSmoothingQuality: "high",
    });

    if (!canvas) {
      Swal.fire("Erro", "Falha ao gerar a imagem.", "error");
      return;
    }

    canvas.toBlob(
      function (blob) {
        if (!blob) {
          Swal.fire("Erro", "Falha ao gerar o arquivo da logo.", "error");
          return;
        }
        croppedLogoBlob = blob;
        // atualiza a prévia na tela
        const url = URL.createObjectURL(blob);
        setLogoSrc(url);

        ensureLogoModal().hide();
        Swal.fire(
          "Ok",
          "Logo ajustada. Agora é só clicar em \u201cSalvar Alterações\u201d.",
          "success",
        );
      },
      "image/png",
      0.95,
    );
  });

  // Máscara/validação do documento (Angola: NIF)
  const isAngola = (window.APP_LANG || "").toLowerCase() === "angola";
  if (isAngola) {
    $("#registration_help")
      .text("NIF deve conter apenas números (10 dígitos).")
      .show();

    // força apenas números e limita a 10 dígitos
    $("#registration_number")
      .attr("maxlength", "10")
      .on("input", function () {
        const digits = (this.value || "").replace(/\D/g, "").slice(0, 10);
        this.value = digits;
      });
  }

  let companyId = new URLSearchParams(window.location.search).get("id");
  if (companyId) {
    $.get(
      "assets/ajax/get_company.php",
      { id: companyId },
      function (response) {
        if (!response.success) {
          Swal.fire("Erro", "Empresa não encontrada", "error");
          return;
        }

        const data = response.data || {};

        const selectedCountry = data.country || "";
        const selectedCity = data.city || "";
        const ddi = data.phone_ddi || "";

        /*
      |--------------------------------------------------------------------------
      | Nome da empresa
      |--------------------------------------------------------------------------
      */
        $("#companyName").text(data.name || "");
        $("#nameComp").val(data.name || "");

        /*
      |--------------------------------------------------------------------------
      | Preencher campos automaticamente
      |--------------------------------------------------------------------------
      */
        $.each(data, function (key, value) {
          // país e cidade são preenchidos depois, quando as opções existirem
          // (evita tentar aplicar .val() em um <select> ainda vazio)
          if (key === "country" || key === "city") return;

          const field = $("#" + key);

          if (!field.length) return;

          // INPUT / TEXTAREA
          if (field.is("input, textarea")) {
            field.val(value ?? "");
            return;
          }

          // SELECT
          if (field.is("select")) {
            field.val(value ?? "").trigger("change");

            // Inicializa Select2 apenas se ainda não estiver iniciado
            if (!field.hasClass("select2-hidden-accessible")) {
              field.select2({
                width: "100%",
              });
            }

            return;
          }

          // IMG
          if (field.is("img")) {
            field.attr("src", value || "");
          }
        });

        /*
      |--------------------------------------------------------------------------
      | Logo da empresa
      |--------------------------------------------------------------------------
      */
        if (data.logo_url) {
          setLogoSrc(`assets/img/companies/${data.logo_url}`);
        }

        /*
      |--------------------------------------------------------------------------
      | País + Cidade
      |--------------------------------------------------------------------------
      */
        // chamada direta: selectCountry já é assíncrona e só popula o select
        // quando a lista de países chega, então o setTimeout(200ms) anterior
        // era um "chute" desnecessário e ainda podia falhar em conexões lentas.
        selectCountry(selectedCountry, selectedCity);

        /*
      |--------------------------------------------------------------------------
      | DDI do telefone
      |--------------------------------------------------------------------------
      */
        if (ddi) {
          loadDDI({
            phone_ddi: ddi,
          });
        }
      },
      "json",
    ).fail(function () {
      Swal.fire(
        "Erro",
        "Não foi possível carregar os dados da empresa.",
        "error",
      );
    });
  }

  $("#editCompanyForm").submit(function (e) {
    e.preventDefault();

    let formData = new FormData(this);

    if (croppedLogoBlob instanceof Blob) {
      formData.delete("logo");
      formData.append("logo", croppedLogoBlob, "logo.png");
    }

    const $submitBtn = $(this).find('[type="submit"]');
    $submitBtn.prop("disabled", true);

    $.ajax({
      url: "edit_company/ajax/update_company.php",
      type: "POST",
      data: formData,
      contentType: false,
      processData: false,
      dataType: "json",
      success: function (response) {
        if (response.success) {
          Swal.fire(
            "Sucesso",
            "Empresa atualizada com sucesso!",
            "success",
          ).then(() => (window.location.href = "list_companies.php"));
        } else {
          Swal.fire("Erro", response.message || "Erro desconhecido", "error");
        }
      },
      error: function (xhr) {
        console.error("SERVER ERROR:", xhr.responseText);
        Swal.fire("Erro", "Erro ao atualizar empresa", "error");
      },
      complete: function () {
        $submitBtn.prop("disabled", false);
      },
    });
  });

  // expõe funções usadas fora do closure (chamadas inline / outros scripts)
  window.selectCountry = selectCountry;
  window.loadCities = loadCities;
  window.loadDDI = loadDDI;

  function selectCountry(selectedCountry = "", selectedCity = "") {
    const countrySelect = $("#country");
    const citySelect = $("#city");
    const myRequestId = ++countryRequestId;

    countrySelect
      .html('<option value="">Carregando lista de países...</option>')
      .trigger("change");
    citySelect
      .html('<option value="">Selecione um país primeiro</option>')
      .trigger("change");

    fetch(
      `https://secure.geonames.org/countryInfoJSON?username=${GEONAMES_USERNAME}`,
    )
      .then((response) => response.json())
      .then((data) => {
        // resposta antiga chegando depois de uma mais nova: ignora
        if (myRequestId !== countryRequestId) return;

        if (!data.geonames) throw new Error("API retornou dados inválidos");

        countryMap = {};
        let options = '<option value="">Selecione um país</option>';

        data.geonames.forEach((country) => {
          countryMap[country.countryName] = country.geonameId;
          options += `<option value="${escapeHtml(country.countryName)}">${escapeHtml(country.countryName)}</option>`;
        });

        countrySelect.html(options).trigger("change");
        countrySelect.select2({
          width: "100%",
          placeholder: "Selecione um país",
          allowClear: false,
          dropdownParent: countrySelect.parent(),
        });

        countrySelect.off("change.editCompany").on("change.editCompany", function () {
          const selected = $(this).val() || "";
          loadCities(selected, "");
        });

        if (selectedCountry) {
          countrySelect.val(selectedCountry).trigger("change");
          loadCities(selectedCountry, selectedCity);
        }
      })
      .catch((error) => {
        if (myRequestId !== countryRequestId) return;
        console.error("❌ Erro ao carregar países:", error);
        countrySelect
          .html('<option value="">Erro ao carregar</option>')
          .trigger("change");
      });
  }

  function loadCities(countryName, selectedCity = "") {
    const citySelect = $("#city");
    const countryId = countryMap[countryName];
    const myRequestId = ++cityRequestId;

    if (!countryId) {
      citySelect
        .html('<option value="">Selecione um país primeiro</option>')
        .trigger("change");
      return;
    }

    citySelect
      .html('<option value="">Carregando cidades...</option>')
      .trigger("change");

    fetch(
      `https://secure.geonames.org/childrenJSON?geonameId=${countryId}&username=${GEONAMES_USERNAME}`,
    )
      .then((response) => response.json())
      .then((data) => {
        if (myRequestId !== cityRequestId) return;

        if (!data.geonames) throw new Error("API retornou dados inválidos");

        let options = '<option value="">Selecione uma cidade</option>';
        data.geonames.forEach((city) => {
          const isSelected = city.name === selectedCity ? "selected" : "";
          options += `<option value="${escapeHtml(city.name)}" ${isSelected}>${escapeHtml(city.name)}</option>`;
        });

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
        if (myRequestId !== cityRequestId) return;
        console.error("❌ Erro ao carregar cidades:", error);
        citySelect
          .html('<option value="">Erro ao carregar</option>')
          .trigger("change");
      });
  }

  function loadDDI(selectedDDIs = {}) {
    fetch("assets/ajax/get_countries.php")
      .then((response) => response.json())
      .then((data) => {
        const ddiOptions = data
          .map((country) => {
            return `<option value="${escapeHtml(country.phone)}">${escapeHtml(country.name)} (+${escapeHtml(country.phone)})</option>`;
          })
          .join("");

        const ddiFields = ["phone_ddi"];
        ddiFields.forEach((fieldId) => {
          const field = $(`#${fieldId}`);
          field.html(
            `<option value="">Selecione um País</option>` + ddiOptions,
          );

          if (selectedDDIs[fieldId]) {
            field.val(selectedDDIs[fieldId]).trigger("change");
          }

          field.select2({
            width: "auto",
            placeholder: "Selecione um País",
            allowClear: false,
            dropdownParent: field.parent(),
          });
        });
      })
      .catch((error) => {
        console.error("Erro ao carregar DDIs:", error);
      });
  }
});
