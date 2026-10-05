<?php
require_once '../app/views/layout_creation.php';
?>
<main>
    <div class="container mt-5">
        <div class="guide-container mt-4 mb-4">
            <h3 class="fw-bold mb-4"><?= t('Guias Emitidas') ?></h3>
            <div class="table-responsive">
                <table id="guidesTable" class="table table-striped align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= t('Empresa') ?></th>
                            <th><?= t('Placa do veículo') ?></th>
                            <th><?= t('Data do carregamento') ?></th>
                            <th><?= t('Total') ?></th>
                            <th><?= t('Status') ?></th>
                            <th><?= t('Criado em') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- DataTables JS -->

<script>
    $(document).ready(function() {
        $('#guidesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: 'guides/ajax/list_guides.php',
                dataSrc: ''
            },
            columns: [{
                    data: 'id'
                },
                {
                    data: 'client_name'
                },
                {
                    data: 'vehicle_plate'
                },
                {
                    data: 'cargo_date'
                },
                {
                    data: 'final_total'
                },
                {
                    data: 'status'
                },
                {
                    data: 'created_at'
                },
                {
                    data: 'actions',
                    orderable: false,
                    searchable: false
                }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
            },
            pageLength: 10,
            order: [
                [0, "desc"]
            ]
        });
    });
</script>
<?php require_once '../app/views/footer.php'; ?>