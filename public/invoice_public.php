<?php
require_once '../app/helpers/translation.php';
require_once '../app/config/db.php'; 
require_once '../app/helpers/functions.php'; 
require_once '../app/views/head.php';
 

?> 
<style>
    .invoice-header {
        border-bottom: 2px solid #ddd;
        padding-bottom: 20px;
        margin-bottom: 20px;
    }

    .invoice-section {
        margin-bottom: 20px;
    }

    .table-summary td {
        font-weight: bold;
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>

<body>
    <main class="m-0">

        <div class="row">
            <button id="generatePdf" class="btn btn-primary">Baixar Fatura em PDF</button>
            <div class="col-md-12">



                <div class="container px-5 py-2 mt-4 invoiceContainer">
                    <!-- Cabeçalho da Fatura -->
                    <div class="invoice-header d-flex justify-content-center align-items-center text-center">
                        <div class="d-flex flex-column">
                            <h1 class="mb-0 mt-4"><?= t('Fatura') ?> <span class="mt-0" id="invoice-number"></span></h1>
                            <h4 class="mb-0" id="invoice-status"></h4>
                        </div>
                    </div>
                    <div id="invoice_logoCompanies">

                    </div>
                    <!-- Informações da Empresa e Cliente -->
                    <div class="row">
                        <div class="col-md-6" id="company-info">
                            <!-- Informações da empresa carregadas via AJAX -->
                        </div>
                        <div class="col-md-6 text-end" id="client-info">
                            <!-- Informações do cliente carregadas via AJAX -->
                        </div>
                    </div>

                    <!-- Detalhes da Fatura -->

                    <p class="mt-4 mb-0" id="codeInvoice"></p>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><?= t('Data') ?></th>
                                <th><?= t('Vencimento') ?></th>
                                <th><?= t('Contribuinte') ?></th>
                                <th><?= t('V/ Ref.') ?></th>
                            </tr>
                        </thead>
                        <tbody id="invoice-details">
                            <!-- Detalhes da fatura carregados via AJAX -->
                        </tbody>
                    </table>

                    <!-- Itens da Fatura -->
                    <table class="table table-striped mt-4">
                        <thead>
                            <tr>
                                <th><?= t('Código') ?></th>
                                <th><?= t('Descrição') ?></th>
                                <th><?= t('Preço Unitário') ?></th>
                                <th><?= t('Qtd') ?></th>
                                <th><?= t('Taxa') ?>/<?= t('IVA') ?></th>
                                <th><?= t('Desconto') ?></th>
                            </tr>
                        </thead>
                        <tbody id="invoice-items">
                            <!-- Itens da fatura carregados via AJAX -->
                        </tbody>
                    </table>

                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?= t('Taxa') ?>/<?= t('IVA') ?>/<?= t('Retenção') ?></th>
                                        <th><?= t('Incidência') ?></th>
                                        <th><?= t('Valor') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="invoice-tax">
                                    <!-- Detalhes da fatura carregados via AJAX -->
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th colspan="3"><?= t('Resumo') ?></th>

                                    </tr>
                                </thead>
                                <tbody id="invoice-sumary">
                                    <!-- Detalhes da fatura carregados via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Resumo -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5><?= t('Observações') ?>:</h5>
                            <p id="invoice-observations"></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <table class="table table-summary">
                                <tr class="invoice-final">
                                    <td><?= t('Total a Pagar') ?>:</td>
                                    <td id="invoice-total"></td>
                                </tr>
                                <tr id="invoice-currency" class="d-none">
                                    <td><?= t('Total a Pagar ') ?><span id="currency-converted"></span>:</td>
                                    <td id="converted-total"></td>
                                </tr>
                            </table>

                        </div>
                    </div>
                </div>

            </div>
            

        </div>

    </main>
  
    <script src="invoices/invoice.js"></script>

    <?php require_once '../app/views/footer.php'; ?>
</body>

</html>