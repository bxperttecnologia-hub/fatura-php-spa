<?php
require_once '../app/views/layout_creation.php';
?>
<main>
    <div class="container mt-5">
        <div class="guide-container mt-4 mb-4">
            <h3 class="fw-bold mb-4"><?= t('Guias Emitidas') ?></h3>
            <div id="guidesTableControls"></div>
            <div class="table-responsive">
                <table id="guidesTable" class="table table-striped align-middle bx-list-table" style="width:100%">
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
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
    $(document).ready(function() {
        BootstrapTable.create({
            table: '#guidesTable',
            toolbar: '#guidesTableControls',
            ajax: {
                url: 'guides/ajax/list_guides.php',
                method: 'GET'
            },
            dataSource: response => Array.isArray(response) ? response : response.data,
            initialSort: { index: 0, direction: 'desc' },
            emptyText: 'Nenhuma guia emitida encontrada.',
            columns: [
                { data: 'id' },
                { data: 'client_name' },
                { data: 'vehicle_plate' },
                { data: 'cargo_date' },
                { data: 'final_total' },
                { data: 'status' },
                { data: 'created_at' },
                { data: 'actions', sortable: false, searchable: false, render: value => value ?? '' }
            ],
        });
    });
</script>
<?php require_once '../app/views/footer.php'; ?>