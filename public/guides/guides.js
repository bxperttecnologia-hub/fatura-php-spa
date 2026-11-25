$(document).ready(function () {
    let selectedItems = new Map();
    let loadedOriginId = null;

    $('#cancelGuideBtn, #cancelGuideBtnBottom').on('click', function () {
        window.location.href = $(this).data('href');
    });

    $.ajax({
        url: '/contacts/ajax/fetch_contacts.php',
        dataType: 'json',
        success: function (response) {
            const contacts = Array.isArray(response) ? response : (response.data || []);
            const $select = $('#contact-select');
            contacts.forEach(contact => {
                $select.append(new Option(`${contact.name || ''}${contact.contributor ? ' — ' + contact.contributor : ''}`, contact.id, false, false));
            });
            $select.select2({ width: '100%', dropdownParent: $('#guidePage'), placeholder: 'Selecionar cliente', allowClear: true });
        }
    });

    $('#contact-select').on('change', function () {
        const id = $(this).val();
        if (!id) {
            $('#contact-summary').hide();
            return;
        }
        $.post('/contacts/ajax/get_contact.php', { id }, function (contact) {
            $('#contact-name').text(contact.name || '—');
            $('#contact-nif').text(contact.contributor || '—');
            $('#contact-phone').text(contact.telephone || contact.po_box || '—');
            $('#destination_address').val(contact.address || '');
            $('#destination_city').val(contact.city || '');
            $('#contact-address').text(contact.address || '—');
            $('#contact-summary').show();
        }, 'json');
    });

    $.ajax({
        url: '/guides/ajax/get_items.php',
        dataType: 'json',
        success: function (items) {
            const $select = $('#item_select');
            $select.empty().append('<option value="">Selecionar artigo</option>');
            items.forEach(item => {
                const label = `${item.code || ''} — ${item.description_plain || item.description || ''}`.trim();
                const option = new Option(label, item.id, false, false);
                $(option).data('item', item);
                $select.append(option);
            });
            $select.select2({ width: '100%', dropdownParent: $('#guidePage'), placeholder: 'Selecionar artigo', allowClear: true });
        }
    });

    const $originSelect = $('#origin_document_select');
    $originSelect.select2({
        width: '100%',
        dropdownParent: $('#guidePage'),
        minimumInputLength: 0,
        placeholder: 'Pesquisar factura ou proforma',
        allowClear: true,
        ajax: {
            url: '/invoices/ajax/search_origin_documents.php',
            dataType: 'json',
            delay: 250,
            data: params => ({ q: params.term || '', limit: 20 }),
            processResults: response => ({
                results: (response.data || []).map(doc => ({
                    id: `${doc.type}:${doc.id}`,
                    text: doc.label,
                    document: doc
                }))
            }),
            cache: true
        }
    });

    $originSelect.on('select2:select', function (event) {
        const selected = event.params.data.document;
        if (!selected) return;
        if (selectedItems.size > 0 && !window.confirm('Existem dados carregados. Substituir pelo novo documento de origem?')) {
            $originSelect.val(null).trigger('change');
            return;
        }
        loadOriginDocument(selected.type, selected.id);
    });

    $originSelect.on('select2:clear', function () {
        if (selectedItems.size > 0 && !window.confirm('Limpar os dados carregados pelo documento de origem?')) return;
        $('#origin_document_id').val('');
        $('#origin_document_type').val('');
        selectedItems.clear();
        $('#items_list').empty();
        updateSummary();
    });

    function loadOriginDocument(type, id) {
        $originSelect.prop('disabled', true);
        $('#lines_count').text('...');
        $.getJSON('/invoices/ajax/load_origin_document.php', { type, id })
            .done(response => {
                const doc = response.data;
                $('#origin_document_id').val(String(id));
                $('#origin_document_type').val(type === 'factura' ? 'factura' : 'proforma');
                $('#contact-select').val(String(doc.contact.id)).trigger('change');
                $('#contact-name').text(doc.contact.name || '—');
                $('#contact-nif').text(doc.contact.contributor || '—');
                $('#contact-phone').text(doc.contact.telephone || doc.contact.po_box || '—');
                $('#contact-address').text(doc.contact.address || '—');
                $('#contact-summary').show();
                $('#destination_address').val(doc.contact.address || '');
                $('#destination_city').val(doc.contact.city || '');
                $('#observacoes').val(doc.observation || '');
                selectedItems.clear();
                $('#items_list').empty();
                doc.items.forEach(line => {
                    if (line.pending_quantity <= 0) return;
                    selectedItems.set(String(line.item_id), line);
                    const html = `
                        <div class="guide-line" id="item-${line.item_id}" data-source-item-id="${line.source_item_id}">
                            <div>
                                <input type="text" class="guide-line-desc" value="${$('<div>').text(line.description || '').html()}">
                                <span class="guide-line-code">${$('<div>').text(line.code || '').html()} · Original: ${line.original_quantity} · Entregue: ${line.delivered_quantity} · Pendente: ${line.pending_quantity}</span>
                            </div>
                            <input type="number" class="guide-line-input" value="${line.suggested_quantity}" min="0.001" max="${line.pending_quantity}" step="0.001">
                            <button type="button" class="guide-trash" data-id="${line.item_id}"><i class="bi bi-trash"></i></button>
                        </div>`;
                    $('#items_list').append(html);
                });
                updateSummary();
                Swal.fire({ icon: 'success', title: 'Dados carregados', text: 'Cliente e bens foram preenchidos a partir do documento de origem.', timer: 1800, showConfirmButton: false });
            })
            .fail(xhr => {
                const message = xhr.responseJSON?.error || 'Não foi possível carregar o documento.';
                Swal.fire('Documento indisponível', message, 'warning');
                $originSelect.val(null).trigger('change');
            })
            .always(() => $originSelect.prop('disabled', false));
    }

    $('#item_select').on('change', function () {
        const id = $(this).val();
        if (!id) return;
        const item = $(this).find(':selected').data('item');
        if (!item || selectedItems.has(String(id))) {
            $(this).val('').trigger('change');
            return;
        }
        selectedItems.set(String(id), item);
        addLine(item);
        $(this).val('').trigger('change');
    });

    function addLine(item) {
        const id = item.id;
        const description = item.description_plain || item.description || '';
        const html = `
            <div class="guide-line" id="item-${id}">
                <div>
                    <input type="text" class="guide-line-desc" value="${$('<div>').text(description).html()}" aria-label="Descrição">
                    <span class="guide-line-code">${$('<div>').text(item.code || '').html()}</span>
                </div>
                <input type="number" class="guide-line-input" value="1" min="0.001" step="0.001" aria-label="Quantidade">
                <button type="button" class="guide-trash" data-id="${id}" aria-label="Remover linha">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
        $('#items_list').append(html);
        updateSummary();
    }

    $(document).on('click', '.guide-trash', function () {
        const id = String($(this).data('id'));
        selectedItems.delete(id);
        $(`#item-${id}`).remove();
        updateSummary();
    });
    $(document).on('input', '.guide-line-input', updateSummary);

    function updateSummary() {
        let quantity = 0;
        const lines = $('#items_list .guide-line').length;
        $('#items_list .guide-line').each(function () {
            quantity += parseFloat($(this).find('.guide-line-input').val()) || 0;
        });
        $('#guidePage').toggleClass('has-lines', lines > 0);
        $('#lines_count').text(lines);
        $('#summary_lines').text(lines);
        $('#summary_quantity').text(quantity.toLocaleString('pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 3 }));
    }

    $('#formGuia').on('submit', function (event) {
        event.preventDefault();
        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }
        if (!$('#items_list .guide-line').length) {
            Swal.fire('Erro', 'Adicione pelo menos um bem transportado.', 'error');
            return;
        }
        const data = {};
        $(this).serializeArray().forEach(field => { data[field.name] = field.value; });
        const items = [];
        $('#items_list .guide-line').each(function () {
            const id = $(this).attr('id').replace('item-', '');
            items.push({
                id,
                description: $(this).find('.guide-line-desc').val(),
                quantity: $(this).find('.guide-line-input').val(),
                unit_price: 0,
                discount: 0,
                tax: ''
            });
        });
        $.ajax({
            url: '/guides/ajax/save_guide.php',
            method: 'POST',
            data: { guide: data, items },
            dataType: 'json'
        }).done(response => {
            Swal.fire('Salvo', 'Guia criada como rascunho.', 'success').then(() => {
                window.location.href = response.guide_id ? `/guides/view?id=${response.guide_id}` : '/guides';
            });
        }).fail(xhr => {
            const message = xhr.responseJSON?.error || xhr.responseJSON?.message || 'Falha ao guardar a guia.';
            Swal.fire('Erro', message, 'error');
        });
    });

    updateSummary();
});
