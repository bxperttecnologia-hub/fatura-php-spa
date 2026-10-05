<?php
require_once '../app/views/layout_creation.php';
?>

<style>
    .profile-cover {
        height: 280px;
        transform: translateY(50px);
        background: linear-gradient(135deg, #005a87, #007abd);
        border-radius: 0 0 20px 20px;
    }

    .mt-n5 {
        margin-top: -80px;
    }

    .card {
        border-radius: 12px;
    }

    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
    }

    .nav-tabs .nav-link.active {
        color: #4e73df;
        border-bottom: 2px solid #4e73df;
        background: transparent;
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

<main class="bg-light min-vh-100">

    <!-- HEADER / COVER -->
    <div class="profile-cover position-relative">
        <div class="overlay container"><br><br>
            <h1 class="text-white fs-2">Perfil</h1>
        </div>
        <!-- <button class="btn btn-sm btn-light position-absolute top-0 end-0 m-3">
            <i class="bi bi-image"></i> Change Cover
        </button> -->
    </div>

    <div class="container mt-n5">

        <div class="row">

            <!-- SIDEBAR -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm border-0 text-center p-3">

                    <img id="profileImage" src="assets/img/profiles/<?= $_SESSION['user']['image'] ?>?t=<?= time() ?>"
                        class="rounded-circle mx-auto mb-3"
                        style="width:100px;height:100px;object-fit:cover;">

                    <h5 class="mb-0"><?= $_SESSION['user']['name'] ?></h5>
                    <small class="text-muted"><?= $_SESSION['user']['email'] ?></small>

                    <hr>
                    <input type="file" id="profileInput" class="d-none" accept="image/*">
                    <label for="profileInput" class="btn btn-outline-primary mt-2 d-block">Alterar Foto <i class="bi-camera"></i></label>

                </div>
            </div>

            <!-- Modal de Corte/Enquadramento (Cropper) -->
            <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Ajustar foto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <style>
                            /* Crop modal: melhora visual no celular */
                            #cropStage {
                                height: 65vh;
                                min-height: 320px;
                            }

                            @media (max-width: 576px) {
                                #cropStage {
                                    height: 60vh;
                                    min-height: 360px;
                                }

                                #cropPreviewWrap {
                                    display: none;
                                }
                            }
                        </style>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12 col-lg-8">
                                    <div id="cropStage">
                                        <img id="cropImage" alt="Cortar" style="max-width: 100%; display:block;">
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4" id="cropPreviewWrap">
                                    <div class="mb-2"><strong>Prévia</strong></div>
                                    <div class="rounded-circle border overflow-hidden" style="width:150px;height:150px;">
                                        <img id="cropPreview" alt="Prévia" style="width:100%;height:100%;object-fit:cover;" />
                                    </div>
                                    <div class="small text-muted mt-2">Dica: arraste para reenquadrar, use scroll/pinch para zoom.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" id="saveCropBtn" class="btn btn-primary">Salvar</button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- MAIN CONTENT -->
            <div class="col-lg-9">

                <div class="card shadow-sm border-0">

                    <!-- FORM -->
                    <div class="card-body">

                        <form id="profileForm">

                            <h3 class="fs-5 fw-bold">Dados Pessoais</h3>
                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">Nome</label>
                                    <input type="text" class="form-control" name="name"
                                        value="<?= $_SESSION['user']['name'] ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">Usuário</label>
                                    <input type="text" class="form-control" name="username"
                                        value="<?= $_SESSION['user']['username'] ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">Telefone</label>
                                    <input type="text" class="form-control" name="phone"
                                        value="<?= $_SESSION['user']['phone'] ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">E-mail</label>
                                    <input type="email" class="form-control" name="email"
                                        value="<?= $_SESSION['user']['email'] ?>">
                                </div>

                            </div>

                            <hr style="background: #6c757d; margin: 30px 0; height: 2px;">

                            <h3 class="fs-5 fw-bold">Localização</h3>
                            <div class="row g-3 mt-2">

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">País</label>
                                    <select class="form-select" id="country" name="country"></select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">Cidade</label>
                                    <!-- name alterado de "city" para "address_district", que é a coluna real no banco -->
                                    <select class="form-select" id="city" name="address_district"></select>
                                </div>


                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">Endereço</label>
                                    <input type="text" class="form-control" name="address"
                                        value="<?= $_SESSION['user']['address'] ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold opacity-50">Idioma</label>
                                    <select id="lang-select" class="form-select" name="lang">
                                        <?php $idioma = $_SESSION['user']['lang'] ?? ''; ?>
                                        <?php if (empty($idioma)) : ?>
                                            <option value="" selected><?= t('Selecione um Idioma...') ?></option>
                                        <?php endif; ?>

                                        <option value="angola" <?= ($idioma === 'angola') ? 'selected' : '' ?>><?= t('Angola') ?></option>
                                        <option value="brasil" <?= ($idioma === 'brasil') ? 'selected' : '' ?>><?= t('Brasil') ?></option>
                                    </select>
                                </div>

                            </div>

                            <div class="mt-4">
                                <button id="update-profile" type="submit" class="btn btn-primary"><?= t('Actualizar') ?></button>

                            </div>

                        </form>

                    </div>
                </div>

            </div>

        </div>

    </div>
</main>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

<script src="perfil/perfil.js?v=1.9"></script>
<script>
    var selectedCountry = "<?php echo isset($_SESSION['user']['country']) ? $_SESSION['user']['country'] : ''; ?>";
    var selectedCity = "<?php echo isset($_SESSION['user']['address_district']) ? $_SESSION['user']['address_district'] : ''; ?>";
    selectCountry(selectedCountry, selectedCity);
</script>
<?php require_once '../app/views/footer.php'; ?>