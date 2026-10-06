<?php
/**
 * Modal de registo / edição de cliente (contacto).
 *
 * Incluído por contacts.php. Substitui a antiga página register_contact.php:
 * em vez de navegar para outra página, o formulário abre num modal e, ao
 * gravar, a lista de clientes é atualizada sem recarregar a página
 * (ver contacts/register_contact.js -> evento "contact:saved").
 *
 * Depende de t(), numberCopysSelect() e $isLocal, definidos em layout_creation.php.
 */
$isLocal = $isLocal ?? false;
?>
<style>
    /* ===== STEP UI ===== */

    .step-content {
        display: none;
        animation: fadeSlide .3s ease;
    }

    .step-content.active {
        display: block;
    }

    @keyframes fadeSlide {
        from {
            opacity: 0;
            transform: translateX(15px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Progress */
    .step-progress {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        position: relative;
    }

    .step-progress::before {
        content: '';
        position: absolute;
        top: 50%;
        width: 100%;
        height: 3px;
        background: #e5e7eb;
        transform: translateY(-50%);
    }

    .step-bar {
        position: absolute;
        top: 50%;
        height: 3px;
        background: #007abd;
        width: 0%;
        transform: translateY(-50%);
        transition: .4s;
    }

    .step {
        z-index: 2;
        background: white;
        border: 2px solid #e5e7eb;
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .step.active {
        background: #007abd;
        color: white;
        border-color: #007abd;
    }

    /* Aside fixo */
    .sticky-aside {
        position: sticky;
        top: 20px;
    }

    /* Buttons */
    .step-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
    }

    .btn-step {
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
    }

    .btn-next {
        background: #007abd;
        color: white;
    }

    .btn-prev {
        background: #e5e7eb;
    }

    #side-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
    }

    #side-card input,
    #side-card select,
    #side-card textarea {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        transition: 0.2s;
        font-size: .8rem !important;
        padding: 0px 10px !important;
        height: 20px !important;
    }

    /* Botão de notas (piscante enquanto não há notas) */
    .btn-notes {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #f59e0b;
        background: #fffbeb;
        color: #92400e;
        border-radius: 999px;
        padding: 8px 16px;
        font-size: .85rem;
        font-weight: 600;
        animation: notesBlink 1.4s ease-in-out infinite;
    }

    .btn-notes .material-icons-round {
        font-size: 20px;
    }

    .btn-notes.has-notes {
        border-color: #16a34a;
        background: #f0fdf4;
        color: #166534;
        animation: none;
    }

    @keyframes notesBlink {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(245, 158, 11, .55);
            opacity: 1;
        }

        50% {
            box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
            opacity: .55;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .btn-notes {
            animation: none;
        }
    }

    /* Modal: o corpo rola, o rodapé (botões de etapa) fica sempre visível */
    #contactFormModal .modal-content {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
    }

    #contactFormModal .modal-footer .btn-step {
        min-width: 110px;
    }
</style>

<div class="modal fade" id="contactFormModal" tabindex="-1"
    aria-labelledby="contactFormModalTitle" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false" data-bs-focus="false"
    data-title-new="<?= t('Adicionar Nova Empresa') ?>"
    data-title-edit="<?= t('Editar Empresa') ?>">
    <!-- data-bs-focus="false": sem isto o Bootstrap "prende" o foco dentro do modal
         e o campo de texto do SweetAlert das notas não deixa escrever. -->
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-white">
                <div>
                    <h5 class="modal-title fw-bold" id="contactFormModalTitle"><?= t('Adicionar Nova Empresa') ?></h5>
                    <small class="text-muted" id="contactFormModalSubtitle"><?= t('Cadastro por etapas') ?></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body bg-light">
                <form id="contactForm">

                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
                        <button type="button" id="btnNotes" class="btn-notes" title="<?= t('Acrescentar notas ou observações importantes') ?>">
                            <i class="material-icons-round" id="btnNotesIcon">edit_note</i>
                            <span id="btnNotesText"><?= t('Acrescentar notas ou observações importantes') ?></span>
                        </button>
                        <?php if ($isLocal): // Renderiza o botão apenas se estiver em localhost ?>
                            <button type="button" id="btnFillContact" class="btn btn-outline-warning btn-sm d-none">
                                <i class="material-icons-round align-middle fs-6">science</i> Preencher (Teste)
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- company_id, created_at e updated_at são definidos no servidor
                         (save_contact.php / update_contact.php); o company_id abaixo é só informativo. -->
                    <input type="hidden" name="company_id" value="<?= (int) ($_SESSION['user']['company_id'] ?? 0) ?>">
                    <input type="hidden" name="observations" id="observations" value="">

                <div class="row g-4">

                    <!-- LEFT (STEPS) -->
                    <div class="col-lg-9">

                        <!-- PROGRESS -->
                        <div class="step-progress">
                            <div class="step-bar" id="stepBar"></div>
                            <div class="step active">1</div>
                            <div class="step">2</div>
                            <div class="step">3</div>
                        </div>

                        <!-- STEP 1 -->
                        <div class="step-content active">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                    <h5 class="card-title fw-bold text-primary d-flex align-items-center">
                                        <span class="material-icons-round me-2">business</span> <?= t('Dados da Empresa') ?>
                                    </h5>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="contributor"
                                                class="form-label text-muted small fw-bold required"><?= t('NIF / Registro') ?></label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="contributor" name="contributor"
                                                    placeholder="Ex: 5000000000" autocomplete="off" required>
                                                <button type="button" class="btn btn-outline-primary" id="btnConsultNif"
                                                    title="<?= t('Consultar NIF na AGT') ?>">
                                                    <i class="material-icons-round align-middle fs-6">search</i>
                                                </button>
                                            </div>
                                            <div id="nifStatus" class="form-text"></div>
                                        </div>

                                        <div class="col-md-6 col-12">
                                            <label for="name"
                                                class="form-label text-muted small fw-bold required"><?= t('Nome da Empresa') ?></label>
                                            <input type="text" class="form-control form-control-lg" id="companyName" name="name"
                                                placeholder="Nome comercial completo" required>
                                        </div>

                                        <div class="col-12">
                                            <label for="address"
                                                class="form-label text-muted small fw-bold required"><?= t('Endereço Completo') ?></label>
                                            <textarea class="form-control" id="address" name="address" rows="2"
                                                placeholder="Rua, Número, Bairro..." required></textarea>
                                        </div>
                                        <!-- 
                                        <div class="col-md-6">
                                            <label for="type"
                                                class="form-label text-muted small fw-bold"><?= t('Tipo de Cliente') ?></label>
                                            <select class="form-select" id="type" name="type">
                                                <option value="<?= t('Normal') ?>"><?= t('Normal') ?></option>
                                                <option value="<?= t('Autofaturação') ?>"><?= t('Autofaturação') ?></option>
                                            </select>
                                        </div> -->

                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2 -->
                        <div class="step-content">
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                    <h5 class="card-title fw-bold text-primary d-flex align-items-center">
                                        <span class="material-icons-round me-2">place</span>
                                        <?= t('Contatos') ?>
                                    </h5>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="row g-3">

                                        <div class="col-md-4 d-none">
                                            <label for="po_box"
                                                class="form-label text-muted small fw-bold"><?= t('Caixa Postal') ?></label>
                                            <input type="text" class="form-control" id="po_box" name="po_box">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="email"
                                                class="form-label text-muted small fw-bold"><?= t('Email Corporativo') ?></label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                placeholder="contato@empresa.com">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="website"
                                                class="form-label text-muted small fw-bold"><?= t('Website') ?></label>
                                            <input type="text" class="form-control" id="website" name="website"
                                                placeholder="www.empresa.com">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="telephone"
                                                class="form-label text-muted small fw-bold required"><?= t('Telefone') ?></label>
                                            <input type="text" name="telephone" id="telephone" class="form-control"
                                                inputmode="numeric" maxlength="9" pattern="[29][0-9]{8}"
                                                placeholder="9XXXXXXXX" autocomplete="off" required>
                                            <div class="form-text"><?= t('9 dígitos, começa por 2 ou 9') ?></div>
                                        </div>
                                        <div class="col-md-6 d-none">
                                            <label for="fax"
                                                class="form-label text-muted small fw-bold"><?= t('Fax') ?></label>
                                            <input type="text" class="form-control" id="fax" name="fax">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- STEP 3 -->
                        <div class="step-content">
                            <!-- Card: Contato Preferencial -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                    <h5 class="card-title fw-bold text-primary d-flex align-items-center">
                                        <span class="material-icons-round me-2">person</span> <?= t('Pessoa de Contato') ?>
                                    </h5>
                                </div>
                                <div class="card-body pt-3">
                                    <div class="mb-3">
                                        <label for="pref_name"
                                            class="form-label text-muted small fw-bold"><?= t('Nome do Responsável') ?></label>
                                        <input type="text" class="form-control" id="pref_name" name="pref_name">
                                    </div>
                                    <div class="mb-3">
                                        <label for="pref_email"
                                            class="form-label text-muted small fw-bold"><?= t('Email Pessoal') ?></label>
                                        <input type="email" class="form-control" id="pref_email" name="pref_email">
                                    </div>
                                    <div class="mb-3">
                                        <label
                                            class="form-label text-muted small fw-bold"><?= t('Telefone Direto') ?></label>
                                        <div class="input-group flex-nowrap">
                                            <input type="text" class="form-control" name="pref_telephone" id="pref_telephone"
                                                inputmode="numeric" maxlength="9" pattern="[29][0-9]{8}" placeholder="9XXXXXXXX">
                                        </div>
                                    </div>
                                    <div class="mb-3 d-none">
                                        <label
                                            class="form-label text-muted small fw-bold"><?= t('Telemóvel Direto') ?></label>
                                        <div class="input-group flex-nowrap">
                                            <input type="text" class="form-control" name="pref_cellphone" id="pref_cellphone"
                                                inputmode="numeric" maxlength="9" pattern="[29][0-9]{8}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /col-lg-9 -->

                    <!-- RIGHT (ASIDE ORIGINAL) -->
                    <!-- Coluna Direita: Preferências e Contato Pessoal -->
                    <div class="col-lg-3 d-none d-lg-block">
                        <!-- Card: Configurações -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                                <h5 class="card-title fw-bold text-primary d-flex align-items-center">
                                    <span class="bi bi-gear"></span>&nbsp;<?= t('Configurações') ?>
                                </h5>
                            </div>
                            <div class="card-body pt-3" id="side-card">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" checked type="checkbox" id="usar_definicoes">
                                    <label class="form-check-label small"
                                        for="usar_definicoes"><?= t('Usar definições padrão da conta') ?></label>
                                </div>
                                <div class="mb-3">
                                    <label for="num_copias"
                                        class="form-label text-muted small fw-bold"><?= t('Nº de cópias') ?></label>
                                    <?= numberCopysSelect(); ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold"><?= t('Vencimento Padrão') ?></label>
                                    <input type="text" class="form-control" value="<?= t('Pronto pagamento') ?>" readonly tabindex="-1">
                                    <input type="hidden" name="due_date" id="due_date" value="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold"><?= t('Idioma') ?></label>
                                    <input type="text" class="form-control" value="<?= t('Português') ?>" readonly tabindex="-1">
                                    <input type="hidden" name="language" id="language" value="AO">
                                </div>
                                <div class="mb-3">
                                    <label for="payment_method"
                                        class="form-label text-muted small fw-bold required"><?= t('Método de Pagamento') ?></label>
                                    <select class="form-select" name="payment_method" required id="payment_method">
                                        <option value="NU" selected><?= t('Dinheiro') ?></option>
                                        <option value="MB"><?= t('Multicaixa') ?></option>
                                        <option value="TB"><?= t('Transferência') ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold"><?= t('Moeda Preferencial') ?></label>
                                    <input type="text" class="form-control" value="<?= t('Kwanza') ?> (AOA)" readonly tabindex="-1">
                                    <input type="hidden" name="currency" id="currency" value="AOA">
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /row -->
                </form>
            </div>

            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal"><?= t('Cancelar') ?></button>
                <button type="button" class="btn-step btn-prev" id="prevBtn">Voltar</button>
                <button type="button" class="btn-step btn-next" id="nextBtn">Próximo</button>
                <button type="button" class="btn-step btn-next d-none" id="saveChangesContact">Salvar</button>
            </div>

        </div>
    </div>
</div>
