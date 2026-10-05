<style>
    /* =========================================================
   MODAL - SELECIONAR CLIENTE
========================================================= */

    .contact-modal {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 25px 70px rgba(15, 23, 42, .18);
    }


    /* HEADER */

    .contact-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;

        padding: 24px 26px 18px;
    }

    .contact-modal-header h5 {
        margin: 0;

        font-size: 17px;
        font-weight: 700;

        color: #111827;
    }

    .contact-modal-header p {
        margin: 5px 0 0;

        font-size: 13px;
        color: #6b7280;
    }


    /* CLOSE */

    .contact-modal-close {
        width: 34px;
        height: 34px;

        display: flex;
        align-items: center;
        justify-content: center;

        border: 0;
        border-radius: 8px;

        background: transparent;
        color: #6b7280;

        cursor: pointer;

        transition: .2s ease;
    }

    .contact-modal-close:hover {
        background: #f3f4f6;
        color: #111827;
    }


    /* SEARCH */

    .contact-search-wrapper {
        position: relative;

        margin: 0 26px 18px;
    }

    .contact-search-wrapper>i {
        position: absolute;

        left: 13px;
        top: 50%;

        transform: translateY(-50%);

        color: #9ca3af;

        pointer-events: none;
    }

    .contact-search {
        width: 100%;
        height: 42px;

        padding: 0 14px 0 38px;

        border: 1px solid #e5e7eb;
        border-radius: 8px;

        background: #fff;

        color: #111827;

        font-size: 13px;

        outline: none;

        transition: .2s ease;
    }

    .contact-search:focus {
        border-color: #0a7fc9;

        box-shadow:
            0 0 0 3px rgba(10, 127, 201, .08);
    }


    /* LISTA */

    .contacts-list {
        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 10px;

        max-height: 420px;

        overflow-y: auto;

        padding: 0 26px 26px;
    }


    /* CONTACTO */

    .contact-item {
        position: relative;

        min-height: 76px;

        display: flex;
        align-items: center;

        gap: 13px;

        padding: 12px;

        border: 1px solid #e5e7eb;
        border-radius: 10px;

        background: #fff;

        cursor: pointer;

        transition:
            border-color .18s ease,
            background .18s ease,
            box-shadow .18s ease,
            transform .18s ease;
    }

    .contact-item:hover {
        border-color: #cbd5e1;

        background: #fafafa;

        box-shadow:
            0 5px 15px rgba(15, 23, 42, .06);

        transform: translateY(-1px);
    }


    /* LOGO */

    .contact-avatar {
        width: 42px;
        height: 42px;

        flex: 0 0 42px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 9px;

        overflow: hidden;

        background: #f1f5f9;

        color: #64748b;
    }

    .contact-avatar img {
        width: 100%;
        height: 100%;

        object-fit: contain;
    }

    .contact-avatar i {
        font-size: 20px;
    }


    /* INFORMAÇÃO */

    .contact-info {
        min-width: 0;
        flex: 1;
    }

    .contact-name {
        font-size: 13px;
        font-weight: 600;

        color: #111827;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .contact-nif {
        margin-top: 4px;

        font-size: 11px;

        color: #6b7280;
    }


    /* CHECK */

    .contact-check {
        width: 22px;
        height: 22px;

        flex: 0 0 22px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 50%;

        background: #0a7fc9;

        color: #fff;

        opacity: 0;

        transform: scale(.7);

        transition: .18s ease;
    }

    .contact-check i {
        font-size: 12px;
    }


    /* SELECTED */

    .contact-item.selected {
        border-color: #0a7fc9;

        background: rgba(10, 127, 201, .035);

        box-shadow:
            0 0 0 1px rgba(10, 127, 201, .08);
    }

    .contact-item.selected .contact-check {
        opacity: 1;
        transform: scale(1);
    }


    /* STATES */

    .contacts-state {
        min-height: 150px;

        display: flex;
        flex-direction: column;

        align-items: center;
        justify-content: center;

        gap: 7px;

        padding: 30px;

        color: #6b7280;

        text-align: center;

        font-size: 13px;
    }

    .contacts-state strong {
        color: #374151;
    }

    .contacts-empty-icon {
        width: 44px;
        height: 44px;

        margin-bottom: 5px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 50%;

        background: #f3f4f6;

        font-size: 19px;
    }


    /* SCROLL */

    .contacts-list::-webkit-scrollbar {
        width: 5px;
    }

    .contacts-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .contacts-list::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 10px;
    }


    /* RESPONSIVE */

    @media (max-width: 650px) {

        .contacts-list {
            grid-template-columns: 1fr;
        }

        .contact-modal-header {
            padding: 20px;
        }

        .contact-search-wrapper {
            margin-left: 20px;
            margin-right: 20px;
        }

        .contacts-list {
            padding-left: 20px;
            padding-right: 20px;
        }
    }
</style>
<!-- =========================================================
     MODAL - SELECIONAR CLIENTE

     NOTA: este ficheiro define APENAS o modal #contactSelectModal.
     O botão que o abre (.inv-select-btn / #btn-select-contact), o
     input escondido #contact-select e a label #contact-select-label
     já existem em create_invoices.php — não os repetir aqui, ou os
     IDs ficam duplicados na página e o JS passa a apanhar sempre o
     primeiro elemento (o errado) em vez do real.
========================================================= -->
<div class="modal fade"
    id="contactSelectModal"
    tabindex="-1"
    aria-labelledby="contactSelectModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content contact-modal">

            <!-- HEADER -->
            <div class="contact-modal-header">

                <div>
                    <h5 id="contactSelectModalLabel">
                        <?= t('Selecionar cliente') ?>
                    </h5>

                    <p>
                        <?= t('Escolha o cliente para associar ao documento.') ?>
                    </p>
                </div>

                <button type="button"
                    class="contact-modal-close"
                    data-bs-dismiss="modal"
                    aria-label="<?= t('Fechar') ?>">

                    <i class="bi bi-x-lg"></i>

                </button>

            </div>


            <!-- PESQUISA -->
            <div class="contact-search-wrapper">

                <i class="bi bi-search"></i>

                <input type="text"
                    id="contactSearch"
                    class="contact-search"
                    placeholder="<?= t('Pesquisar por nome ou NIF...') ?>"
                    autocomplete="off">

            </div>


            <!-- LOADING -->
            <div id="contactsLoading" class="contacts-state">

                <div class="spinner-border spinner-border-sm"
                    role="status">
                </div>

                <span><?= t('Carregando clientes...') ?></span>

            </div>


            <!-- LISTA -->
            <div id="contactsList"
                class="contacts-list">
            </div>


            <!-- EMPTY -->
            <div id="contactsEmpty"
                class="contacts-state d-none">

                <div class="contacts-empty-icon">
                    <i class="bi bi-person-x"></i>
                </div>

                <strong>
                    <?= t('Nenhum cliente encontrado') ?>
                </strong>

                <span>
                    <?= t('Tente pesquisar por outro nome ou NIF.') ?>
                </span>

            </div>

        </div>

    </div>
</div>