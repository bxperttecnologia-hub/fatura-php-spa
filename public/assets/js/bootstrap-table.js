(function (window, document, $) {
    'use strict';

    function create(options) {
        const table = typeof options.table === 'string'
            ? document.querySelector(options.table)
            : options.table;
        if (!table || !table.tHead || !table.tBodies.length) {
            throw new Error('BootstrapTable precisa de uma tabela com thead e tbody.');
        }
        if (!$ || (!options.ajax && !Array.isArray(options.data)) || (options.ajax && !options.ajax.url) || !Array.isArray(options.columns)) {
            throw new Error('BootstrapTable requer jQuery, dados ou um endpoint AJAX e as colunas da tabela.');
        }

        const body = table.tBodies[0];
        const headerCells = Array.from(table.tHead.rows[0].cells);
        if (headerCells.length !== options.columns.length) {
            throw new Error('O número de colunas configuradas não corresponde ao cabeçalho da tabela.');
        }

        const pageSizes = options.pageSizes || [10, 25, 50, 100];
        const responsiveContainer = table.closest('.table-responsive') || table.parentElement;
        if (!responsiveContainer) {
            throw new Error('A tabela Bootstrap precisa de um contentor pai para controlos e paginação.');
        }
        const state = {
            rows: [],
            search: '',
            page: 1,
            pageSize: options.pageSize || pageSizes[0],
            sortIndex: options.initialSort?.index ?? null,
            sortDirection: options.initialSort?.direction === 'desc' ? 'desc' : 'asc',
            request: null,
            requestId: 0,
        };

        let toolbar = typeof options.toolbar === 'string'
            ? document.querySelector(options.toolbar)
            : options.toolbar;
        if (!toolbar) {
            toolbar = document.createElement('div');
            responsiveContainer.before(toolbar);
        }

        toolbar.classList.add('bootstrap-table-toolbar', 'd-flex', 'flex-wrap', 'gap-3', 'align-items-center', 'justify-content-between', 'mb-3');
        toolbar.innerHTML = `
            ${options.searchInput ? '' : `<label class="d-flex align-items-center gap-2 mb-0">
                <span class="small text-muted">Pesquisar</span>
                <input type="search" class="form-control form-control-sm" data-table-search placeholder="Pesquisar..." aria-label="Pesquisar na tabela">
            </label>`}
            <label class="d-flex align-items-center gap-2 mb-0">
                <span class="small text-muted">Linhas</span>
                <select class="form-select form-select-sm" data-table-page-size aria-label="Quantidade de linhas por página">
                    ${pageSizes.map(size => `<option value="${Number(size)}"${Number(size) === state.pageSize ? ' selected' : ''}>${Number(size)}</option>`).join('')}
                </select>
            </label>
        `;

        const searchInput = options.searchInput
            ? (typeof options.searchInput === 'string' ? document.querySelector(options.searchInput) : options.searchInput)
            : toolbar.querySelector('[data-table-search]');
        if (!searchInput && options.searchInput) {
            throw new Error('O campo de pesquisa indicado para BootstrapTable não existe.');
        }
        const pageSizeInput = toolbar.querySelector('[data-table-page-size]');
        let pagination = responsiveContainer.nextElementSibling;
        if (!pagination || !pagination.matches('[data-bootstrap-table-pagination]')) {
            pagination = document.createElement('div');
            pagination.dataset.bootstrapTablePagination = '';
            pagination.className = 'd-flex flex-wrap justify-content-between align-items-center gap-2 mt-3';
            responsiveContainer.after(pagination);
        }

        headerCells.forEach((cell, index) => {
            const column = options.columns[index];
            const label = cell.textContent.trim();
            cell.textContent = '';
            if (column.sortable === false) {
                cell.textContent = label;
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link link-secondary text-decoration-none p-0 fw-semibold';
            button.dataset.tableSort = String(index);
            button.dataset.label = label;
            button.textContent = label;
            button.setAttribute('aria-label', `Ordenar por ${label}`);
            cell.append(button);
        });

        let searchTimer;
        if (searchInput && options.bindSearch !== false) {
            searchInput.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => {
                    state.search = searchInput.value.trim().toLocaleLowerCase();
                    state.page = 1;
                    render();
                }, options.searchDebounce ?? 180);
            });
        }

        pageSizeInput.addEventListener('change', () => {
            state.pageSize = Number(pageSizeInput.value);
            state.page = 1;
            render();
        });

        table.tHead.addEventListener('click', event => {
            const button = event.target.closest('[data-table-sort]');
            if (!button) return;
            const index = Number(button.dataset.tableSort);
            if (state.sortIndex === index) {
                state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                state.sortIndex = index;
                state.sortDirection = 'asc';
            }
            state.page = 1;
            render();
        });

        pagination.addEventListener('click', event => {
            const button = event.target.closest('[data-table-page]');
            if (!button || button.disabled) return;
            state.page = Number(button.dataset.tablePage);
            render();
        });

        if (options.ajax && options.autoLoad !== false) {
            reload();
        } else {
            state.rows = options.data || [];
            render();
        }

        function valueFor(row, column) {
            if (typeof column.sortValue === 'function') return column.sortValue(row);
            if (typeof column.data === 'function') return column.data(row);
            return column.data ? row[column.data] : '';
        }

        function filteredRows() {
            let rows = typeof options.filter === 'function'
                ? state.rows.filter(options.filter)
                : state.rows;
            if (state.search) {
                rows = rows.filter(row => options.columns.some(column => {
                    if (column.searchable === false) return false;
                    const value = typeof column.searchValue === 'function'
                        ? column.searchValue(row)
                        : valueFor(row, column);
                    return String(value ?? '').toLocaleLowerCase().includes(state.search);
                }));
            }
            if (state.sortIndex !== null) {
                const column = options.columns[state.sortIndex];
                rows = rows.slice().sort((left, right) => {
                    const a = valueFor(left, column);
                    const b = valueFor(right, column);
                    let comparison;
                    if (a == null || b == null) comparison = a == null ? (b == null ? 0 : -1) : 1;
                    else if (typeof a === 'number' && typeof b === 'number') comparison = a - b;
                    else comparison = String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
                    return state.sortDirection === 'asc' ? comparison : -comparison;
                });
            }
            return rows;
        }

        function render() {
            const rows = filteredRows();
            const pageCount = Math.max(1, Math.ceil(rows.length / state.pageSize));
            state.page = Math.min(state.page, pageCount);
            const start = (state.page - 1) * state.pageSize;
            const pageRows = rows.slice(start, start + state.pageSize);
            body.replaceChildren();

            if (pageRows.length === 0) {
                const row = body.insertRow();
                const cell = row.insertCell();
                cell.colSpan = options.columns.length;
                cell.className = 'text-center text-muted py-4';
                cell.textContent = state.rows.length
                    ? (options.noMatchesText || 'Nenhum registo corresponde à pesquisa.')
                    : (options.emptyText || 'Nenhum registo encontrado.');
            } else {
                pageRows.forEach(rowData => {
                    const row = body.insertRow();
                    options.columns.forEach(column => {
                        const cell = row.insertCell();
                        const value = valueFor(rowData, column);
                        if (typeof column.render === 'function') {
                            cell.innerHTML = column.render(value, rowData);
                        } else {
                            cell.textContent = value ?? '';
                        }
                    });
                });
            }

            headerCells.forEach((cell, index) => {
                const button = cell.querySelector('[data-table-sort]');
                if (!button) return;
                const sorted = state.sortIndex === index;
                cell.setAttribute('aria-sort', sorted
                    ? (state.sortDirection === 'asc' ? 'ascending' : 'descending')
                    : 'none');
                button.textContent = `${button.dataset.label}${sorted ? (state.sortDirection === 'asc' ? ' ↑' : ' ↓') : ''}`;
            });

            const first = rows.length ? start + 1 : 0;
            const last = Math.min(start + pageRows.length, rows.length);
            pagination.innerHTML = `
                <span class="small text-muted" role="status">${first}–${last} de ${rows.length}</span>
                <nav aria-label="Paginação da tabela">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item${state.page <= 1 ? ' disabled' : ''}"><button type="button" class="page-link" data-table-page="1" aria-label="Primeira página"${state.page <= 1 ? ' disabled' : ''}>«</button></li>
                        <li class="page-item${state.page <= 1 ? ' disabled' : ''}"><button type="button" class="page-link" data-table-page="${state.page - 1}" aria-label="Página anterior"${state.page <= 1 ? ' disabled' : ''}>‹</button></li>
                        <li class="page-item disabled"><span class="page-link">${state.page} / ${pageCount}</span></li>
                        <li class="page-item${state.page >= pageCount ? ' disabled' : ''}"><button type="button" class="page-link" data-table-page="${state.page + 1}" aria-label="Página seguinte"${state.page >= pageCount ? ' disabled' : ''}>›</button></li>
                        <li class="page-item${state.page >= pageCount ? ' disabled' : ''}"><button type="button" class="page-link" data-table-page="${pageCount}" aria-label="Última página"${state.page >= pageCount ? ' disabled' : ''}>»</button></li>
                    </ul>
                </nav>
            `;
        }

        function showMessage(message, isError) {
            body.replaceChildren();
            const row = body.insertRow();
            const cell = row.insertCell();
            cell.colSpan = options.columns.length;
            cell.className = `text-center py-4${isError ? ' text-danger' : ' text-muted'}`;
            cell.setAttribute('role', isError ? 'alert' : 'status');
            cell.textContent = message;
            pagination.replaceChildren();
        }

        function reload() {
            if (!options.ajax) {
                render();
                return;
            }
            if (state.request) state.request.abort();
            const requestId = ++state.requestId;
            showMessage(options.loadingText || 'A carregar...', false);
            const data = typeof options.ajax.data === 'function'
                ? options.ajax.data()
                : (options.ajax.data || {});
            state.request = $.ajax({
                url: options.ajax.url,
                method: options.ajax.method || 'GET',
                data,
                dataType: 'json',
            }).done(response => {
                if (requestId !== state.requestId) return;
                const rows = typeof options.dataSource === 'function'
                    ? options.dataSource(response)
                    : (Array.isArray(response?.data) ? response.data : response);
                if (!Array.isArray(rows)) {
                    const error = new Error('A resposta do endpoint não contém uma lista de registos.');
                    console.error('BootstrapTable response error:', error, response);
                    showMessage(options.errorText || 'Não foi possível carregar os dados. Verifique a ligação e tente novamente.', true);
                    if (typeof options.onError === 'function') options.onError(error);
                    return;
                }
                state.rows = rows;
                render();
            }).fail((xhr, status, error) => {
                if (requestId !== state.requestId || status === 'abort') return;
                console.error('BootstrapTable AJAX error:', error || xhr.statusText, xhr);
                showMessage(options.errorText || 'Não foi possível carregar os dados. Verifique a ligação e tente novamente.', true);
                const row = body.rows[0];
                if (row) {
                    const cell = row.cells[0];
                    const retry = document.createElement('button');
                    retry.type = 'button';
                    retry.className = 'btn btn-sm btn-outline-danger ms-2';
                    retry.textContent = 'Tentar novamente';
                    retry.addEventListener('click', reload);
                    cell.append(retry);
                }
                if (typeof options.onError === 'function') options.onError(xhr);
            });
            return state.request;
        }

        return {
            reload,
            refresh: render,
            search(value) {
                state.search = String(value ?? '').trim().toLocaleLowerCase();
                if (searchInput) searchInput.value = value ?? '';
                state.page = 1;
                render();
            },
            getRows: () => state.rows.slice(),
            getPage: () => state.page,
            getPageSize: () => state.pageSize,
            setRows(rows) {
                if (!Array.isArray(rows)) throw new TypeError('setRows requer um array.');
                state.rows = rows;
                state.page = 1;
                render();
            },
        };
    }

    window.BootstrapTable = { create };
})(window, document, window.jQuery);
