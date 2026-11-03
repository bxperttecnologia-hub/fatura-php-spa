<div class="modal fade" id="documentHistoryModal" tabindex="-1" aria-labelledby="documentHistoryTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="documentHistoryTitle">Histórico documental</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="documentHistoryLoading" class="text-center py-3 d-none" role="status">
                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    <span class="ms-2">A carregar documentos...</span>
                </div>
                <div id="documentHistoryError" class="alert alert-danger d-none" role="alert"></div>
                <div id="documentHistoryList" class="list-group"></div>
            </div>
        </div>
    </div>
</div>
