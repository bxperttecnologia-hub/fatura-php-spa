<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    .profile-cover {
        height: 200px;
        background: linear-gradient(135deg, #005a87, #007abd);
        border-radius: 0 0 20px 20px;
        margin-top: 50px;
    }

    .mt-n5 {
        margin-top: -80px;
    }

    .card {
        border-radius: 14px;
        transition: 0.2s;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    .card-header {
        font-size: 15px;
    }

    button.btn-outline-primary {
        border-radius: 8px;
        border: #007abd solid 1.5px !important;
    }

    button.btn-primary {
        background: #007abd !important;
    }

    button.btn-outline-primary:hover {
        background: #007abd !important;
    }

    label {
        font-weight: bold;
        opacity: .5;
        font-size: 0.9rem;
    }


    input[type="text"],
    input[type="email"],
    select.form-select {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 8px 12px;
        transition: all 0.2s;
        font-size: 0.9rem !important;
    }
</style>

<body>

    <main class="bg-light min-vh-100">

        <!-- HEADER / COVER -->
        <div class="profile-cover position-relative">
            <div class="container" style="position:relative;z-index:1; margin-top: 50px;"><br><br>
                <h1 class="text-white fs-2">Editar Empresa</h1>
            </div>
        </div>

        <div class="container mt-n5">

            <div class="row">

                <!-- 🔹 ASIDE ESQUERDO -->
                <div class="col-lg-3 mb-4">
                    <div class="card shadow-sm border-0 text-center p-3">

                        <!-- LOGO -->
                        <div class="position-relative mx-auto mb-3">

                            <div id="companyLogoSpinner"
                                class="position-absolute top-50 start-50 translate-middle"
                                style="display:none;">
                                <div class="spinner-border text-primary"></div>
                            </div>

                            <img id="companyLogo"
                                class="rounded-circle border"
                                style="width:100px;height:100px;object-fit:cover;opacity:0;">
                        </div>

                        <h5 id="companyName">Empresa</h5>
                        <small class="text-muted" id="companyEmail">email@empresa.com</small>

                        <hr>

                        <div class="mb-3">
                            <label for="logo" class="btn btn-outline-primary mt-2 d-block opacity-100">Alterar Logo <i class="bi-camera"></i></label>
                            <input type="file" class="form-control d-none" id="logo" name="logo" accept="image/*">
                        </div>

                    </div>
                </div>

                <!-- Modal de Corte/Enquadramento (Logo) -->
                <div class="modal fade" id="logoCropModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Ajustar logo</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <style>
                                    #logoCropStage {
                                        height: 65vh;
                                        min-height: 320px;
                                    }
                                </style>
                                <div id="logoCropStage">
                                    <img id="logoCropImage" alt="Cortar logo" style="max-width:100%; display:block;" />
                                </div>
                                <div class="small text-muted mt-2">Arraste para reenquadrar e use scroll/pinch para zoom. O corte é livre (sem proporção fixa).</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" id="saveLogoCropBtn" class="btn btn-primary">Usar este corte</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 🔹 CONTEÚDO DIREITO -->
                <div class="col-lg-9">

                    <form id="editCompanyForm" enctype="multipart/form-data">

                        <input type="hidden" id="id" name="id">

                        <!-- DADOS EMPRESA -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white fw-bold fs-5">
                                <?= t('Dados da Empresa'); ?>
                            </div>

                            <div class="card-body">
                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label for="name" class="form-label"><?= t('Nome da Empresa'); ?></label>
                                        <input type="text" class="form-control" id="nameComp" name="name">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="registration_number" class="form-label"><?= t('CNPJ'); ?></label>
                                        <input type="text" class="form-control" id="registration_number" name="registration_number">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label"><?= t('Telefone'); ?></label>
                                        <div class="d-flex gap-2">
                                            <select class="form-select countryPhone tel" name="phone_ddi" id="phone_ddi"></select>
                                            <input type="text" class="form-control telnumber" name="phone" id="phone">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email" class="form-label"><?= t('Email'); ?></label>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="website" class="form-label"><?= t('Website'); ?></label>
                                        <input type="text" class="form-control" id="website" name="website">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="address" class="form-label"><?= t('Endereço'); ?></label>
                                        <input type="text" class="form-control" id="address" name="address">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="country" class="form-label"><?= t('País'); ?></label>
                                        <select class="form-select" id="country" name="country"></select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="city" class="form-label"><?= t('Cidade'); ?></label>
                                        <select class="form-select" id="city" name="city"></select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="currency" class="form-label"><?= t('Moeda'); ?></label>
                                        <select class="form-select" id="currency" name="currency">
                                            <?= currencySelects(); ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="goods_services" class="form-label">Categoria</label>
                                        <select name="goods_services" id="goods_services" class="form-select">
                                            <option value="">- Selecione -</option>
                                            <option value="servicos">Prestação de Serviços</option>
                                            <option value="produtos">Produtos</option>
                                        </select>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- DADOS FISCAIS -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white fw-bold fs-5">Dados Fiscais</div>

                            <div class="card-body row g-3">

                                <!-- REGIME -->
                                <div class="col-md-6">
                                    <label class="form-label">Regime de IVA</label>
                                    <select name="vat_regime" id="vat_regime" class="form-select">
                                        <option value="">- Selecione -</option>
                                        <option value="geral">Regime Geral</option>
                                        <option value="simplificado">Regime Simplificado</option>
                                        <option value="isento">Exclusão</option>
                                    </select>
                                </div>

                                <!-- IVA DEFAULT -->
                                <div class="col-md-6">
                                    <label class="form-label">Taxa IVA (%)</label>
                                    <input type="number" step="0.01" name="default_vat_rate" id="default_vat_rate" class="form-control" value="14" readonly>
                                </div>

                                <!-- RETENÇÃO ATIVA -->
                                <!-- <div class="col-md-4">
                                    <label class="form-label">Aplicar Retenção?</label>
                                    <select name="withholding_enabled" id="withholding_enabled" class="form-select">
                                        <option value="0">Não</option>
                                        <option value="1">Sim</option>
                                    </select>
                                </div>

                                TAXA RETENÇÃO -->
                                <!-- <div class="col-md-6">
                                    <label class="form-label">Taxa de Retenção (%)</label>
                                    <select name="withholding_rate" id="withholding_rate" class="form-select">
                                        <option value="0">Sem retenção</option>
                                        <option value="6.5">6,5% (Serviços ≥ 20.000 Kz)</option>
                                        <option value="10">10% (Outros casos)</option>
                                    </select>
                                </div> -->

                            </div>
                        </div>

                        <!-- BANCÁRIO -->
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white fw-bold fs-5">Dados Bancários</div>

                            <div class="card-body row g-3">
                                <div class="col-md-6">
                                    <label for="bank_name" class="form-label">Banco Principal</label>
                                    <select class="form-select" id="bank_name" name="bank_name">
                                        <option value="" selected>- Selecione -</option>
                                        <option value="Banco Angolano de Investimentos (BAI)">Banco Angolano de Investimentos (BAI)</option>
                                        <option value="Banco de Fomento Angola (BFA)">Banco de Fomento Angola (BFA)</option>
                                        <option value="Banco BIC">Banco BIC</option>
                                        <option value="Banco Millennium Atlântico">Banco Millennium Atlântico</option>
                                        <option value="Banco de Poupança e Crédito (BPC)">Banco de Poupança e Crédito (BPC)</option>
                                        <option value="Banco de Comércio e Indústria (BCI)">Banco de Comércio e Indústria (BCI)</option>
                                        <option value="Banco Caixa Geral Angola">Banco Caixa Geral Angola</option>
                                        <option value="Banco Comercial Angolano (BCA)">Banco Comercial Angolano (BCA)</option>
                                        <option value="Banco Sol">Banco Sol</option>
                                        <option value="Banco Económico">Banco Económico</option>
                                        <option value="Banco de Negócios Internacional (BNI)">Banco de Negócios Internacional (BNI)</option>
                                        <option value="Banco Keve">Banco Keve</option>
                                        <option value="Banco Valor">Banco Valor</option>
                                        <option value="Access Bank Angola">Access Bank Angola</option>
                                        <option value="Banco de Investimento Rural (BIR)">Banco de Investimento Rural (BIR)</option>
                                        <option value="Banco Comercial do Huambo">Banco Comercial do Huambo</option>
                                        <option value="Banco de Crédito do Sul (BCS)">Banco de Crédito do Sul (BCS)</option>
                                        <option value="Banco Yetu">Banco Yetu</option>
                                        <option value="Standard Bank de Angola">Standard Bank de Angola</option>
                                        <option value="Standard Chartered Bank de Angola">Standard Chartered Bank de Angola</option>
                                    </select>
                                </div>

                                <div class="col-md-6 d-none">
                                    <label for="iban" class="form-label">IBAN</label>
                                    <input type="text" class="form-control" id="iban" name="iban">
                                </div>

                                <div class="col-md-6 d-none">
                                    <label for="bank_name_optional" class="form-label">Banco Secundário</label>
                                    <select class="form-select" id="bank_name_optional" name="bank_name_optional">
                                        <option value="" selected>- Selecione -</option>
                                        <option value="Banco Angolano de Investimentos (BAI)">Banco Angolano de Investimentos (BAI)</option>
                                        <option value="Banco de Fomento Angola (BFA)">Banco de Fomento Angola (BFA)</option>
                                        <option value="Banco BIC">Banco BIC</option>
                                        <option value="Banco Millennium Atlântico">Banco Millennium Atlântico</option>
                                        <option value="Banco de Poupança e Crédito (BPC)">Banco de Poupança e Crédito (BPC)</option>
                                        <option value="Banco de Comércio e Indústria (BCI)">Banco de Comércio e Indústria (BCI)</option>
                                        <option value="Banco Caixa Geral Angola">Banco Caixa Geral Angola</option>
                                        <option value="Banco Comercial Angolano (BCA)">Banco Comercial Angolano (BCA)</option>
                                        <option value="Banco Sol">Banco Sol</option>
                                        <option value="Banco Económico">Banco Económico</option>
                                        <option value="Banco de Negócios Internacional (BNI)">Banco de Negócios Internacional (BNI)</option>
                                        <option value="Banco Keve">Banco Keve</option>
                                        <option value="Banco Valor">Banco Valor</option>
                                        <option value="Access Bank Angola">Access Bank Angola</option>
                                        <option value="Banco de Investimento Rural (BIR)">Banco de Investimento Rural (BIR)</option>
                                        <option value="Banco Comercial do Huambo">Banco Comercial do Huambo</option>
                                        <option value="Banco de Crédito do Sul (BCS)">Banco de Crédito do Sul (BCS)</option>
                                        <option value="Banco Yetu">Banco Yetu</option>
                                        <option value="Standard Bank de Angola">Standard Bank de Angola</option>
                                        <option value="Standard Chartered Bank de Angola">Standard Chartered Bank de Angola</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="iban_optional" class="form-label">IBAN</label>
                                    <input type="text" class="form-control" id="iban_optional" name="iban_optional">
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm border-0 p-3 mb-4">
                            <div class="col-md-12 mb-3">
                                <label for="invoice_impression" class="form-label">Impressão da Factura</label>
                                <select name="invoice_impression" class="form-select" id="invoice_impression" name="invoice_impression" placeholder="Ex: Impressão em papel, digital, etc.">
                                    <option value="">- Selecione -</option>
                                    <option value="A4">A4</option>
                                    <option value="A5">A5</option>
                                    <option value="Recibo">Recibo</option>
                                </select>
                            </div>
                        </div>

                        <!-- BOTÃO -->
                        <div class="text-end mb-5">
                            <button type="submit" class="btn btn-primary"><?= t('Salvar Alterações'); ?></button>
                        </div>

                    </form>

                </div>

            </div>

        </div>
    </main>


    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
    <script src="edit_company/edit_company.js?v=1.0"></script>

    <script>
        function formatAngolaIban(rawValue) {
            let value = String(rawValue ?? '')
                .toUpperCase()
                .replace(/[^A-Z0-9]/g, '');

            if (!value) return '';

            // Remove o prefixo AO
            if (value.startsWith('AO')) {
                value = value.substring(2);
            }

            // Se começar pelos dígitos de controlo 06, remove-os
            if (value.startsWith('06')) {
                value = value.substring(2);
            }

            // Mantém somente os 21 dígitos da parte bancária
            value = value.replace(/\D/g, '').slice(0, 21);

            // Não preencher com zeros!
            if (!value) {
                return 'AO06';
            }

            // Agrupa de 4 em 4
            const groups = [];

            for (let i = 0; i < value.length; i += 4) {
                groups.push(value.substring(i, i + 4));
            }

            return `AO06 ${groups.join(' ')}`;
        }


        function formatIbanField(selector) {
            const field = $(selector);

            if (!field.length) return;

            field.val(formatAngolaIban(field.val()));
        }


        $(document).ready(function() {

            function updateFiscalRules() {
                const regime = $("#vat_regime").val();
                const ivaInput = $("#default_vat_rate");

                if (!ivaInput.length) return;

                if (regime === "geral") {
                    ivaInput.val(14);
                } else if (regime === "simplificado") {
                    ivaInput.val(7);
                } else if (regime === "isento") {
                    ivaInput.val(0);
                } else {
                    ivaInput.val(14);
                }
            }


            function handleIbanFormatting() {
                ["#iban", "#iban_optional"].forEach(function(selector) {
                    const field = $(selector);

                    if (field.length && field.val()) {
                        field.val(formatAngolaIban(field.val()));
                    }
                });
            }


            $("#vat_regime").on("change", updateFiscalRules);


            $("#bank_name").on("change", handleIbanFormatting);


            // Formatação enquanto o utilizador escreve
            $("#iban, #iban_optional").on("input", function() {
                const field = $(this);

                const cursorPosition = this.selectionStart;
                const oldValue = field.val();

                const formatted = formatAngolaIban(oldValue);

                field.val(formatted);

                // Coloca o cursor no fim.
                // Isso evita comportamentos estranhos ao editar.
                this.setSelectionRange(formatted.length, formatted.length);
            });


            // Formata valores que já vieram preenchidos da BD
            $("#iban, #iban_optional").each(function() {
                if ($(this).val()) {
                    $(this).val(formatAngolaIban($(this).val()));
                }
            });


            updateFiscalRules();
            handleIbanFormatting();
        });
    </script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>