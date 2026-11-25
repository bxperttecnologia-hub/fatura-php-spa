const params = new URLSearchParams(window.location.search);
const guideId = params.get('id');
let guideDocumentStatus = '';
let guideTransportStatus = '';

function postGuideAction(action, extra = {}) {
    return $.ajax({
        url: '/guides/ajax/document_action.php',
        method: 'POST',
        data: Object.assign({ id: guideId, action }, extra),
        dataType: 'json'
    });
}

function statusClass(status) {
    status = String(status || '').toLowerCase();
    if (status.includes('entregue')) return 'is-delivered';
    if (status.includes('transito') || status.includes('parcial')) return 'is-transit';
    if (status.includes('cancel')) return 'is-cancelled';
    return '';
}

function statusLabel(status) {
    const labels = {
        rascunho: 'Rascunho',
        emitido: 'Emitido',
        em_preparacao: 'Em preparação',
        em_transito: 'Em trânsito',
        entrega_parcial: 'Entrega parcial',
        entregue: 'Entregue',
        cancelado: 'Cancelado'
    };
    return labels[status] || String(status || '-').replaceAll('_', ' ');
}

function renderGuideStatus(documentStatus, transportStatus) {
    guideDocumentStatus = String(documentStatus || '').toLowerCase();
    guideTransportStatus = String(transportStatus || '').toLowerCase();
    const displayedStatus = ['em_transito', 'entrega_parcial', 'entregue', 'cancelado'].includes(guideTransportStatus)
        ? guideTransportStatus
        : guideDocumentStatus || guideTransportStatus || 'rascunho';

    $('#guideStatus')
        .text(statusLabel(displayedStatus))
        .removeClass('is-transit is-delivered is-cancelled')
        .addClass(statusClass(displayedStatus));
    $('#btnEmitGuide').prop('disabled', guideDocumentStatus === 'emitido' || guideDocumentStatus === 'cancelado');
    $('#btnDepartGuide').prop('disabled', guideDocumentStatus !== 'emitido' || guideTransportStatus === 'em_transito');
}

function showActionError(xhr, fallback) {
    const response = xhr.responseJSON || {};
    Swal.fire('Erro', response.error || response.message || fallback, 'error');
}

$(document).ready(function () {
    if (!guideId) {
        $('#guide-container').html('<div class="p-4 text-danger">Guia não encontrada.</div>');
        return;
    }
    $('#preloader').removeClass('d-none');
    $('#guide-container').load(`/guides/ajax/guide_public.php?id=${encodeURIComponent(guideId)}`, function (response, status) {
        $('#preloader').addClass('d-none');
        if (status !== 'success') $('#guide-container').html('<div class="p-4 text-danger">Não foi possível carregar a guia.</div>');
    });
    $.getJSON('/guides/ajax/get_guide.php', { id: guideId }).done(response => {
        const guide = response.data;
        const number = `${guide.series || ''} ${guide.number || guide.id}`.trim();
        $('#guideNumber').text(number);
        $('#guideClient').text(guide.client_name || '-');
        renderGuideStatus(guide.status, guide.transport_status);
        $('#email_guide_id').val(guide.id);
        $('input[name="to"]').val(guide.client_email || '');
        $('input[name="subject"]').val(`Guia de transporte ${number}`);
        $('textarea[name="body"]').val(`Segue anexa a guia de transporte ${number}.`);
    });
    $('#generateGuidePdf').on('click', function () {
        if (!window.html2pdf) return Swal.fire('Erro', 'O gerador de PDF não está disponível.', 'error');
        window.html2pdf().set({
            margin: [5, 5, 5, 5],
            filename: `Guia_${guideId}.pdf`,
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(document.getElementById('guide-container')).save();
    });
    $('#btnEmitGuide').on('click', function () {
        $(this).prop('disabled', true);
        postGuideAction('emit')
            .done(response => {
                if (!response.success) {
                    $(this).prop('disabled', false);
                    Swal.fire('Erro', response.error || 'Não foi possível emitir.', 'error');
                    return;
                }
                renderGuideStatus(response.status, guideTransportStatus);
                Swal.fire('Guia emitida', 'A guia foi emitida com sucesso.', 'success');
            })
            .fail(xhr => {
                $(this).prop('disabled', false);
                showActionError(xhr, 'Não foi possível emitir a guia.');
            });
    });
    $('#btnDepartGuide').on('click', function () {
        $(this).prop('disabled', true);
        postGuideAction('depart')
            .done(response => {
                if (!response.success) {
                    $(this).prop('disabled', false);
                    Swal.fire('Erro', response.error || 'Não foi possível iniciar o transporte.', 'error');
                    return;
                }
                renderGuideStatus(guideDocumentStatus, response.status);
                Swal.fire('Transporte iniciado', 'A guia está em trânsito.', 'success');
            })
            .fail(xhr => {
                $(this).prop('disabled', false);
                showActionError(xhr, 'Não foi possível iniciar o transporte.');
            });
    });
    $('#btnDeliverGuide').on('click', () => {
        const receiverName = window.prompt('Nome de quem recebe:', '');
        if (receiverName === null) return;
        postGuideAction('deliver', { receiver_name: receiverName, receiver_document: window.prompt('Documento de identificação:', '') || '' })
            .done(response => {
                if (!response.success) {
                    Swal.fire('Erro', response.error || 'Não foi possível registar a entrega.', 'error');
                    return;
                }
                renderGuideStatus(guideDocumentStatus, response.status);
                Swal.fire('Entrega registada', 'A entrega foi registada com sucesso.', 'success');
            })
            .fail(xhr => showActionError(xhr, 'Não foi possível registar a entrega.'));
    });
    $('#formSendGuide').on('submit', function (event) {
        event.preventDefault();
        if (!this.checkValidity()) { this.classList.add('was-validated'); return; }
        $.post('/guides/ajax/send_guide.php', $(this).serialize())
            .done(() => Swal.fire('Enviado', 'Guia enviada por e-mail.', 'success'))
            .fail(xhr => Swal.fire('Erro', xhr.responseJSON?.error || 'Não foi possível enviar.', 'error'));
    });
});
