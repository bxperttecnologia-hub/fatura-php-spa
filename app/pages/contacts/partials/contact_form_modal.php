<?php
/**
 * Modal de registo / edição de cliente (contacto).
 *
 * Incluído por contacts.php. O formulário reutiliza os endpoints e o fluxo
 * AJAX existentes em contacts/register_contact.js.
 */
$isLocal = $isLocal ?? false;
?>
<style>
    #contactFormModal .modal-content {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
    }

    #contactFormModal .modal-dialog {
        max-width: 720px;
    }

    #contactFormModal .modal-header,
    #contactFormModal .modal-footer {
        padding: .75rem 1rem;
    }

    #contactFormModal .modal-body {
        max-height: calc(100vh - 11rem);
        overflow-y: auto;
        padding: 1rem;
    }

    #contactFormModal .form-section {
        padding: 1rem 1.1rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    #contactFormModal .form-section-title {
        display: flex;
        align-items: center;
        gap: .35rem;
        margin-bottom: .85rem;
        color: #176b9c;
        font-size: .82rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    #contactFormModal .form-label {
        margin-bottom: .3rem;
    }

    #contactFormModal .optional-field {
        position: relative;
    }

    #contactFormModal .optional-block {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px dashed #e5e7eb;
    }

    #contactFormModal .optional-remove {
        position: absolute;
        top: 0;
        right: 0;
        z-index: 1;
        padding: .15rem .4rem;
        color: #6b7280;
        line-height: 1;
    }

    #contactFormModal .optional-remove:hover {
        color: #b91c1c;
    }

    #contactFormModal .optional-add[hidden],
    #contactFormModal [data-optional-field][hidden],
    #contactFormModal #advancedSettings[hidden],
    #contactFormModal #customSettings[hidden] {
        display: none !important;
    }

    #contactFormModal .modal-footer {
        position: relative;
        z-index: 2;
    }

    @media (max-width: 575.98px) {
        #contactFormModal .modal-dialog {
            margin: .5rem;
            max-width: none;
        }

        #contactFormModal .modal-body {
            max-height: calc(100dvh - 9rem);
            padding: .875rem;
        }
    }
</style>

<div class="modal fade" id="contactFormModal" tabindex="-1"
    aria-labelledby="contactFormModalTitle" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false" data-bs-focus="false"
    data-title-new="<?= t('Novo cliente') ?>"
    data-title-edit="<?= t('Editar cliente') ?>">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-white">
                <div>
                    <h5 class="modal-title fw-bold" id="contactFormModalTitle"><?= t('Novo cliente') ?></h5>
                    <small class="text-muted" id="contactFormModalSubtitle"><?= t('Comece pelo NIF: os dados são preenchidos a partir da AGT') ?></small>
                </div>
                <button type="button" class="btn-close" data-contact-dismiss aria-label="Fechar"></button>
            </div>

            <div class="modal-body bg-light">
                <form id="contactForm" novalidate>
                    <input type="hidden" name="company_id" value="<?= (int) ($_SESSION['user']['company_id'] ?? 0) ?>">
                    <input type="hidden" name="observations" id="observations" value="">
                    <input type="hidden" name="due_date" id="due_date" value="0">
                    <input type="hidden" name="language" id="language" value="AO">
                    <input type="hidden" name="currency" id="currency" value="AOA">

                    <?php if ($isLocal): ?>
                        <button type="button" id="btnFillContact" class="btn btn-outline-warning btn-sm d-none mb-3">
                            <i class="material-icons-round align-middle fs-6">science</i> Preencher (Teste)
                        </button>
                    <?php endif; ?>

                    <section class="form-section mb-3" aria-labelledby="companySectionTitle">
                        <h6 class="form-section-title" id="companySectionTitle">
                            <i class="bi bi-building" aria-hidden="true"></i><?= t('Identificação') ?>
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="contributor" class="form-label text-muted small fw-bold required"><?= t('NIF') ?></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="contributor" name="contributor"
                                        placeholder="Ex: 5000000000" autocomplete="off" required>
                                    <button type="button" class="btn btn-outline-primary" id="btnConsultNif"
                                        title="<?= t('Consultar NIF na AGT') ?>" aria-label="<?= t('Consultar NIF na AGT') ?>">
                                        <i class="material-icons-round align-middle fs-6">search</i>
                                    </button>
                                </div>
                                <div id="nifStatus" class="form-text" aria-live="polite"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="companyName" class="form-label text-muted small fw-bold required"><?= t('Nome / Empresa') ?></label>
                                <input type="text" class="form-control" id="companyName" name="name"
                                    placeholder="Nome completo ou designação social" required>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label text-muted small fw-bold required"><?= t('Endereço') ?></label>
                                <textarea class="form-control" id="address" name="address" rows="2"
                                    placeholder="Rua, Número, Bairro..." required></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="form-section mb-3" aria-labelledby="contactSectionTitle">
                        <h6 class="form-section-title" id="contactSectionTitle">
                            <i class="bi bi-telephone" aria-hidden="true"></i><?= t('Contactos') ?>
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="telephone" class="form-label text-muted small fw-bold required"><?= t('Telefone') ?></label>
                                <input type="text" name="telephone" id="telephone" class="form-control"
                                    inputmode="numeric" maxlength="9" pattern="[29][0-9]{8}"
                                    placeholder="9XXXXXXXX" autocomplete="off" required>
                                <div class="form-text"><?= t('9 dígitos, começa por 2 ou 9') ?></div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label text-muted small fw-bold"><?= t('Email') ?></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="geral@empresa.co.ao">
                                <div class="form-text"><?= t('Opcional. Usado para enviar as faturas.') ?></div>
                            </div>
                        </div>
                    </section>

                    <section class="form-section mb-3" aria-labelledby="moreSectionTitle">
                        <h6 class="form-section-title" id="moreSectionTitle">
                            <i class="bi bi-plus-circle" aria-hidden="true"></i><?= t('Mais informações') ?>
                            <span class="text-muted fw-normal text-lowercase ms-1">(<?= t('opcional') ?>)</span>
                        </h6>
                        <div class="d-flex flex-wrap gap-2" id="optionalActions">
                            <button type="button" class="btn btn-sm btn-outline-secondary optional-add" data-add-optional="website">+ <?= t('Website') ?></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary optional-add" data-add-optional="person">+ <?= t('Pessoa de contacto') ?></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary optional-add" data-add-optional="observations">+ <?= t('Observações') ?></button>
                        </div>

                        <div class="optional-field optional-block" data-optional-field="website" hidden>
                            <button type="button" class="btn btn-sm optional-remove" data-remove-optional="website" aria-label="Remover website">×</button>
                            <label for="website" class="form-label text-muted small fw-bold"><?= t('Website') ?></label>
                            <input type="text" class="form-control" id="website" name="website" placeholder="www.empresa.co.ao">
                        </div>

                        <div class="row g-3 optional-block" data-optional-field="person" hidden>
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h6 class="mb-2 fw-semibold"><?= t('Pessoa de contacto') ?></h6>
                                    <button type="button" class="btn btn-sm optional-remove" data-remove-optional="person" aria-label="Remover pessoa de contacto">×</button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="pref_name" class="form-label text-muted small fw-bold"><?= t('Nome') ?></label>
                                <input type="text" class="form-control" id="pref_name" name="pref_name">
                            </div>
                            <div class="col-md-4">
                                <label for="pref_email" class="form-label text-muted small fw-bold"><?= t('Email') ?></label>
                                <input type="email" class="form-control" id="pref_email" name="pref_email">
                            </div>
                            <div class="col-md-4">
                                <label for="pref_telephone" class="form-label text-muted small fw-bold"><?= t('Telefone') ?></label>
                                <input type="text" class="form-control" name="pref_telephone" id="pref_telephone"
                                    inputmode="numeric" maxlength="9" pattern="[29][0-9]{8}" placeholder="9XXXXXXXX">
                            </div>
                            <div class="col-md-4 d-none">
                                <label for="pref_cellphone" class="form-label text-muted small fw-bold"><?= t('Telemóvel Direto') ?></label>
                                <input type="text" class="form-control" name="pref_cellphone" id="pref_cellphone"
                                    inputmode="numeric" maxlength="9" pattern="[29][0-9]{8}">
                            </div>
                        </div>

                        <div class="optional-block" data-optional-field="observations" hidden>
                            <div class="d-flex align-items-center justify-content-between">
                                <label for="observationsEditor" class="form-label text-muted small fw-bold"><?= t('Observações') ?></label>
                                <button type="button" class="btn btn-sm optional-remove" data-remove-optional="observations" aria-label="Remover observações">×</button>
                            </div>
                            <textarea class="form-control" id="observationsEditor" rows="3"
                                placeholder="Notas ou observações sobre este cliente..."></textarea>
                        </div>

                        <div class="row g-3 d-none" aria-hidden="true">
                            <div class="col-md-6">
                                <label for="po_box" class="form-label"><?= t('Caixa Postal') ?></label>
                                <input type="text" class="form-control" id="po_box" name="po_box">
                            </div>
                            <div class="col-md-6">
                                <label for="fax" class="form-label"><?= t('Fax') ?></label>
                                <input type="text" class="form-control" id="fax" name="fax">
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <button type="button" class="btn btn-link p-0 text-decoration-none form-section-title mb-0" id="advancedSettingsToggle"
                            aria-expanded="false" aria-controls="advancedSettings">
                            <i class="bi bi-gear" aria-hidden="true"></i><?= t('Definições de faturação') ?>
                            <i class="bi bi-chevron-down small" aria-hidden="true"></i>
                        </button>
                        <div id="advancedSettings" class="mt-3" hidden>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" checked type="checkbox" id="usar_definicoes">
                                <label class="form-check-label small" for="usar_definicoes"><?= t('Usar definições padrão da conta') ?></label>
                            </div>
                            <p class="form-text mt-0 mb-3" id="defaultSettingsMessage"><?= t('Serão utilizadas as configurações padrão da conta.') ?></p>
                            <div class="row g-3" id="customSettings" hidden>
                                <div class="col-sm-6 col-lg-4">
                                    <label for="numberCopys" class="form-label text-muted small fw-bold"><?= t('Nº de cópias') ?></label>
                                    <?= numberCopysSelect(); ?>
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <label class="form-label text-muted small fw-bold"><?= t('Vencimento Padrão') ?></label>
                                    <input type="text" class="form-control" value="<?= t('Pronto pagamento') ?>" readonly tabindex="-1">
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <label class="form-label text-muted small fw-bold"><?= t('Idioma') ?></label>
                                    <input type="text" class="form-control" value="<?= t('Português') ?>" readonly tabindex="-1">
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <label for="payment_method" class="form-label text-muted small fw-bold required"><?= t('Método de Pagamento') ?></label>
                                    <select class="form-select" name="payment_method" required id="payment_method">
                                        <option value="NU" selected><?= t('Dinheiro') ?></option>
                                        <option value="MB"><?= t('Multicaixa') ?></option>
                                        <option value="TB"><?= t('Transferência') ?></option>
                                    </select>
                                </div>
                                <div class="col-sm-6 col-lg-4">
                                    <label class="form-label text-muted small fw-bold"><?= t('Moeda Preferencial') ?></label>
                                    <input type="text" class="form-control" value="<?= t('Kwanza') ?> (AOA)" readonly tabindex="-1">
                                </div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>

            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-outline-secondary me-auto" data-contact-dismiss><?= t('Cancelar') ?></button>
                <button type="button" class="btn btn-primary" id="saveChangesContact"><?= t('Guardar cliente') ?></button>
            </div>
        </div>
    </div>
</div>
