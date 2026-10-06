// ========== DADOS DO CLIENTE / CONTATO ==========

// Ao carregar a página, inicia os tooltips, carrega países e contatos
$(document).ready(function () {
    initializeTooltips();
    selectCountry(); // Carrega países para o form novo contato

    // Carrega contatos existentes para o select
    $.ajax({
        url: "contacts/ajax/fetch_contacts.php", // Ajuste o path se necessário
        type: "GET",
        dataType: "json",
        success: function (data) {
            let select = $("#contact-select");
            select.empty().append('<option value=""><?= t("Selecione um contato...") ?></option>');
            if (data.length > 0) {
                data.forEach((contato) => {
                    select.append(`<option value="${contato.id}">${contato.name} - ${contato.email}</option>`);
                });
            } else {
                select.append('<option value=""><?= t("Nenhum contato encontrado") ?></option>');
            }
        },
        error: function (xhr, status, error) {
            console.error("Erro na requisição AJAX:", status, error);
            console.log(xhr.responseText);
        },
    });

    // Ao selecionar um contato, carrega os dados no formulário e bloqueia os campos
    $("#contact-select").on("change", function () {
        let contatoId = $(this).val();
        if (contatoId) {
            $.ajax({
                url: "contacts/ajax/get_contact.php",
                type: "POST",
                data: { id: contatoId },
                dataType: "json",
                success: function (contato) {
                    $("#contact_id").val(contato.id);
                    $("#name").val(contato.name).prop("disabled", true);
                    $("#email").val(contato.email).prop("disabled", true);
                    $("#contributor").val(contato.contributor).prop("disabled", true);
                    $("#po_box").val(contato.po_box).prop("disabled", true);
                    $("#address").val(contato.address).prop("disabled", true);
                    selectCountry(contato.country, contato.city);

                    $("#country").prop("disabled", true);
                    $("#city").prop("disabled", true);

                    $("#contact-form").show();
                },
                error: function (xhr, status, error) {
                    console.error("Erro ao buscar dados do contato:", status, error);
                },
            });
        } else {
            $("#contact-form").fadeOut();
        }
    });

    // Tooltips (caso use bootstrap)
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Alternar entre criar novo contato ou escolher existente
    $("#toggle-contact-form").on("click", function () {
        if ($("#contact-form").is(":visible")) {
            $("#contact-form").hide();
            $("#select-contact-container").show();
            $("#spanIconCreateInvoices").text("person_add");
            $("#toggle-contact-form")
                .attr("data-bs-title", "Inserir novo Contato")
                .tooltip("dispose").tooltip();
        } else {
            $("#contact-form").show();
            $("#select-contact-container").hide();
            $("#contact-form input, #contact-form textarea, #contact-form select").prop("disabled", false).val("");
            $("#contact_id").val("");
            $("#spanIconCreateInvoices").text("close");
            $("#toggle-contact-form")
                .attr("data-bs-title", "Fechar formulário")
                .tooltip("dispose").tooltip();
        }
    });
});

// Função para carregar países no select
function selectCountry(selectedCountry = "", selectedCity = "") {
    const username = "israelsouza"; // Troca por seu username do GeoNames se precisar
    const countrySelect = $("#country");
    const citySelect = $("#city");

    countrySelect.html('<option value="">Carregando países...</option>').trigger("change");
    citySelect.html('<option value="">Escolha um país primeiro</option>').trigger("change");

    fetch(`https://secure.geonames.org/countryInfoJSON?username=${username}`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.geonames) throw new Error("API retornou dados inválidos");

            window.countryMap = {};
            let options = '<option value=""><?= t("Selecione um país") ?></option>';
            data.geonames.forEach((country) => {
                window.countryMap[country.countryName] = country.geonameId;
                options += `<option value="${country.countryName}">${country.countryName}</option>`;
            });

            countrySelect.html(options).trigger("change");
            countrySelect.select2({
                width: "100%",
                placeholder: "<?= t('Selecione um país') ?>",
                allowClear: false,
                dropdownParent: countrySelect.parent(),
            });

            if (selectedCountry) {
                countrySelect.val(selectedCountry).trigger("change");
                loadCities(selectedCountry, selectedCity);
            }
        })
        .catch((error) => {
            console.error("Erro ao carregar países:", error);
            countrySelect.html('<option value=""><?= t("Erro ao carregar") ?></option>').trigger("change");
        });
}

// Função para carregar cidades no select (baseado no país)
function loadCities(countryName, selectedCity = "") {
    const citySelect = $("#city");
    const countryId = window.countryMap ? window.countryMap[countryName] : null;

    if (!countryId) {
        citySelect.html('<option value=""><?= t("Escolha um país primeiro") ?></option>').trigger("change");
        return;
    }

    citySelect.html('<option value=""><?= t("Carregando cidades...") ?></option>').trigger("change");

    fetch(`https://secure.geonames.org/childrenJSON?geonameId=${countryId}&username=israelsouza`)
        .then((response) => response.json())
        .then((data) => {
            if (!data.geonames) throw new Error("API retornou dados inválidos");
            let options = '<option value=""><?= t("Selecione uma cidade") ?></option>';
            data.geonames.forEach((city) => {
                let isSelected = city.name === selectedCity ? "selected" : "";
                options += `<option value="${city.name}" ${isSelected}>${city.name}</option>`;
            });

            citySelect.html(options).trigger("change");
            citySelect.select2({
                width: "100%",
                placeholder: "<?= t('Selecione uma cidade') ?>",
                allowClear: false,
                dropdownParent: citySelect.parent(),
            });

            if (selectedCity) {
                citySelect.val(selectedCity).trigger("change");
            }
        })
        .catch((error) => {
            console.error("Erro ao carregar cidades:", error);
            citySelect.html('<option value=""><?= t("Erro ao carregar") ?></option>').trigger("change");
        });
}

function initializeTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
}


$(document).ready(function () {
    // Carregar opções de itens (AJAX igual invoices)
    loadSelect2Items();

    // Adiciona item ao selecionar
    $("#item_select").on("change", function () {
        let item = $(this).find(":selected").data("item");
        if (item && $(`#item-${item.id}`).length === 0) {
            let html = `
                <div class="row item-box align-items-center" id="item-${item.id}">
                    <div class="col-1"><input type="text" class="form-control" value="${item.code}" readonly></div>
                    <div class="col-4"><input type="text" class="form-control" value="${item.description_plain || item.description}" readonly></div>
                    <div class="col-2"><input type="number" class="form-control" value="${item.unit_price}" min="0" step="0.01"></div>
                    <div class="col-1"><input type="number" class="form-control" value="1" min="1"></div>
                    <div class="col-2">
                        <select class="form-select">
                            <option value="14" ${item.tax == "14" ? "selected" : ""}>14%</option>
                            <option value="0" ${item.tax == "exempt" ? "selected" : ""}>Isento</option>
                        </select>
                    </div>
                    <div class="col-1"><input type="number" class="form-control" value="0" step="0.01"></div>
                    <div class="col-1"><span class="remove-item" data-id="${item.id}">🗑️</span></div>
                </div>
            `;
            $("#items_list").append(html);
            updateSummary();
        }
    });

    // Remover item
    $(document).on("click", ".remove-item", function () {
        $(`#item-${$(this).data("id")}`).remove();
        updateSummary();
    });

    // Atualizar resumo quando alterar valores
    $(document).on("input change", ".item-box input, .item-box select", function () {
        updateSummary();
    });

    // Função para calcular o resumo dos itens
    function updateSummary() {
        let soma = 0, desconto = 0, iva = 0, total = 0, retencao = 0;
        let taxaRetencao = parseFloat($("input[name='retencao']").val()) || 0;

        $(".item-box").each(function () {
            let preco = parseFloat($(this).find("input:eq(2)").val()) || 0;
            let qtd = parseInt($(this).find("input:eq(3)").val()) || 1;
            let desc = parseFloat($(this).find("input:eq(5)").val()) || 0;
            let taxa = $(this).find("select").val();
            let valorItem = preco * qtd;
            let valorDesc = valorItem * (desc / 100);
            soma += valorItem;
            desconto += valorDesc;

            if (taxa == "14") {
                iva += (valorItem - valorDesc) * 0.14;
            }
        });
        total = (soma - desconto) + iva;
        if (taxaRetencao > 0) {
            retencao = (soma - desconto) * (taxaRetencao / 100);
            total -= retencao;
        }
        $("#total_sum").text(formatMoney(soma));
        $("#total_discount").text(formatMoney(desconto));
        $("#subtotal_without_tax").text(formatMoney(soma - desconto));
        $("#total_tax").text(formatMoney(iva));
        $("#retention_value").text(formatMoney(retencao));
        $("#final_total").text(formatMoney(total));

        $("input[name='total_sum']").val(soma.toFixed(2));
        $("input[name='total_discount']").val(desconto.toFixed(2));
        $("input[name='subtotal_without_tax']").val((soma - desconto).toFixed(2));
        $("input[name='total_tax']").val(iva.toFixed(2));
        $("input[name='retention_value']").val(retencao.toFixed(2));
        $("input[name='final_total']").val(total.toFixed(2));

    }

    // Helper para formatar valores (Kz fixo, adapta se quiser)
    function formatMoney(val) {
        return parseFloat(val).toLocaleString('pt-PT', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Kz';
    }

    // AJAX para carregar itens no select2 (ajusta o endpoint conforme teu backend)
    function loadSelect2Items() {
        $.ajax({
            url: "guides/ajax/get_items.php", // Crie este endpoint com retorno JSON igual invoices
            dataType: "json",
            success: function (data) {
                let opts = '<option value="">Selecione um produto/serviço...</option>';
                $.each(data, function (i, item) {
                    opts += `<option value="${item.id}" data-item='${JSON.stringify(item)}'>${item.code} - ${item.description_plain || item.description}</option>`;
                });
                $("#item_select").html(opts).select2();
            }
        });
    }

    // Salvando a guia
    $("#formGuia").on("submit", function (e) {
        e.preventDefault();
        // Validação básica (tem item?)
        if ($("#items_list .item-box").length === 0) {
            Swal.fire("Erro", "Adicione ao menos um produto/serviço antes de salvar.", "error");
            return;
        }
        let dados = {};
        $(this).serializeArray().forEach(function(item) {
            dados[item.name] = item.value;
        });

        let itens = [];
        $("#items_list .item-box").each(function () {
            itens.push({
                id: $(this).attr("id").replace("item-", ""),
                quantity: $(this).find("input:eq(3)").val(),
                unit_price: $(this).find("input:eq(2)").val(),
                discount: $(this).find("input:eq(5)").val(),
                tax: $(this).find("select").val(),
                description: $(this).find("input:eq(1)").val()
            });
        });
        $.ajax({
            url: "guides/ajax/save_guide.php",
            method: "POST",
            data: { guide: dados, items: itens },
            success: function (r) {
                Swal.fire("Salvo", "Guia salva com sucesso!", "success").then(() => {
                    window.location.reload();
                });
            },
            error: function () {
                Swal.fire("Erro", "Falha ao salvar a guia.", "error");
            }
        });
    });
});

// Carrega itens no select2 ao abrir a página
function loadGuideSelect2Items() {
    $.ajax({
        url: "guides/ajax/get_items.php", // Seu endpoint para buscar os itens
        dataType: "json",
        success: function (data) {
            let options = '<option value=""><?= t("Selecione um produto/serviço...") ?></option>';
            $.each(data, function (index, item) {
                options += `<option value="${item.id}" data-item='${JSON.stringify(item)}'>${item.code} - ${item.description_plain || item.description}</option>`;
            });
            $("#item_select").html(options);
            $("#item_select").select2({
                dropdownParent: $("#item_select").parent(),
                width: "100%"
            });
        }
    });
}

$(document).ready(function () {
    loadGuideSelect2Items();

    // Adicionar item ao selecionar
    $("#item_select").on("change", function () {
        let selected = $(this).find(":selected").data("item");
        if (selected && $(`#item-${selected.id}`).length === 0) {
            let html = `
                <div class="row item-box align-items-center" id="item-${selected.id}">
                    <div class="col-1 text-center">
                        <input type="text" class="form-control" value="${selected.code}" readonly>
                    </div>
                    <div class="col-4">
                        <input type="text" class="form-control" value="${selected.description}">
                    </div>
                    <div class="col-2 text-center">
                        <input type="number" class="form-control" value="${selected.unit_price}" min="0" step="0.01">
                    </div>
                    <div class="col-1 text-center">
                        <input type="number" class="form-control" value="1" min="1">
                    </div>
                    <div class="col-2 text-center">
                        <select class="form-select">
                            <option value="14" ${selected.tax == "14" ? "selected" : ""}>14% - Taxa</option>
                            <option value="0" ${selected.tax == "exempt" ? "selected" : ""}>0% - Isento</option>
                        </select>
                    </div>
                    <div class="col-1 text-center">
                        <input type="number" class="form-control" value="0.0" step="0.01">
                    </div>
                    <div class="col-1 text-center">
                        <span data-id="${selected.id}" class="material-icons-round remove-item cursor">
                            delete_forever
                        </span>
                    </div>
                </div>
            `;
            $("#items_list").append(html);
            updateGuideSummary();
        }
    });

    // Remover item
    $(document).on("click", ".remove-item", function () {
        $(`#item-${$(this).data("id")}`).remove();
        updateGuideSummary();
    });

    // Atualizar resumo quando mudar valor de item
    $(document).on("input change", ".item-box input, .item-box select", function () {
        updateGuideSummary();
    });
});

// Função para atualizar o resumo dos itens (igual invoices, pode adaptar se quiser)
function updateGuideSummary() {
    let soma = 0, desconto = 0, iva = 0, total = 0, retencao = 0;
    let taxaRetencao = parseFloat($("input[name='retencao']").val()) || 0;

    $(".item-box").each(function () {
        let preco = parseFloat($(this).find("input:eq(2)").val()) || 0;
        let qtd = parseInt($(this).find("input:eq(3)").val()) || 1;
        let desc = parseFloat($(this).find("input:eq(5)").val()) || 0;
        let taxa = $(this).find("select").val();
        let valorItem = preco * qtd;
        let valorDesc = valorItem * (desc / 100);
        soma += valorItem;
        desconto += valorDesc;

        if (taxa == "14") {
            iva += (valorItem - valorDesc) * 0.14;
        }
    });
    total = (soma - desconto) + iva;
    if (taxaRetencao > 0) {
        retencao = (soma - desconto) * (taxaRetencao / 100);
        total -= retencao;
    }
    $("#total_sum").text(formatGuideMoney(soma));
    $("#total_discount").text(formatGuideMoney(desconto));
    $("#subtotal_without_tax").text(formatGuideMoney(soma - desconto));
    $("#total_tax").text(formatGuideMoney(iva));
    $("#retention_value").text(formatGuideMoney(retencao));
    $("#final_total").text(formatGuideMoney(total));

    $("input[name='total_sum']").val(soma.toFixed(2));
    $("input[name='total_discount']").val(desconto.toFixed(2));
    $("input[name='subtotal_without_tax']").val((soma - desconto).toFixed(2));
    $("input[name='total_tax']").val(iva.toFixed(2));
    $("input[name='retention_value']").val(retencao.toFixed(2));
    $("input[name='final_total']").val(total.toFixed(2));

}

// Helper para formatar valores Kz
function formatGuideMoney(val) {
    return parseFloat(val).toLocaleString('pt-PT', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' Kz';
}

// Adiciona botão "Preencher Guia" no topo do form
$(document).ready(function(){
    $("#formGuia .fw-bold").after(`
        <button type="button" id="btnPreencherGuia" class="btn btn-warning btn-sm mb-3 ms-3" style="float:right">
            Preencher Guia
        </button>
    `);

    $("#btnPreencherGuia").on("click", function(){
        // Exibe formulário de novo contato
        if($("#contact-form").is(":hidden")) {
            $("#toggle-contact-form").trigger("click");
        }

        // Preenche os dados do cliente (form de novo contato)
        $("#name").val("José dos Santos");
        $("#contributor").val("REG-123456");
        $("#address").val("Rua Exemplo, 456 - Centro, Luanda");
        $("#email").val("jose@email.com");
        $("#po_box").val("7890");
        setTimeout(function() { // País/cidade precisam do select2 carregar
            $("#country").val("Angola").trigger("change");
            setTimeout(function() {
                $("#city").val("Luanda").trigger("change");
            }, 500);
        }, 500);

        // Dados do transporte
        $("input[name='matricula']").val("LU-00-AB");
        $("input[name='data_carga']").val("2025-08-25");
        $("input[name='observacoes']").val("Carga valiosa, entrega urgente");
        $("input[name='retencao']").val("5");

        // Locais de carga/entrega
        $("input[name='endereco_carga']").val("Porto de Luanda, Armazém 1");
        $("input[name='cidade_carga']").val("Luanda");
        $("input[name='caixa_postal_carga']").val("1111");
        $("input[name='endereco_entrega']").val("Avenida Brasil, 1234 - Benguela");
        $("input[name='cidade_entrega']").val("Benguela");
        $("input[name='caixa_postal_entrega']").val("2222");

        // Documento
        $("input[name='data_documento']").val("2025-08-25");
        $("input[name='referencia']").val("REF7890");
        $("input[name='serie']").val("2025");
        $("select[name='moeda']").val("AOA");

        // Limpa itens atuais
        $("#items_list").empty();

        // Cria 2 itens fake (assumindo que já carregou o select2 com os itens)
        setTimeout(function(){
            // Pega o primeiro e segundo item do select (ignora o option vazio)
            let $options = $("#item_select option");
            if($options.length > 2){
                // Seleciona e dispara o change pra adicionar na tela
                $("#item_select").val($options.eq(1).val()).trigger("change");
                setTimeout(function(){
                    // Preenche valores do primeiro item
                    let $item1 = $(".item-box").eq(0);
                    $item1.find("input:eq(2)").val("14000");
                    $item1.find("input:eq(3)").val("3");
                    $item1.find("select").val("14");
                    $item1.find("input:eq(5)").val("10"); // desconto
                }, 200);

                setTimeout(function(){
                    $("#item_select").val($options.eq(2).val()).trigger("change");
                    setTimeout(function(){
                        let $item2 = $(".item-box").eq(1);
                        $item2.find("input:eq(2)").val("5000");
                        $item2.find("input:eq(3)").val("1");
                        $item2.find("select").val("0");
                        $item2.find("input:eq(5)").val("0");
                    }, 200);
                }, 500);
            }
        }, 900);

        // Força atualização do resumo depois de tudo
        setTimeout(function(){ updateGuideSummary(); }, 1800);
    });
});

