<?php
require_once '../app/config/db.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

try {
    subscription_require_feature($pdo, (int)($_SESSION['user']['company_id'] ?? 0), 'rh');
} catch (Exception $e) {
    $cid = (int)($_SESSION['user']['company_id'] ?? 0);
    header('Location: subscription.php?company_id=' . $cid . '&upgrade=rh');
    exit;
}

require_once '../app/views/layout_creation.php';
?>

<style>
    #departmentsPage {
        --department-ink: #172033;
        --department-muted: #64748b;
        --department-line: #e2e8f0;
        color: var(--department-ink);
    }

    #departmentsPage .department-header,
    #departmentsPage .department-toolbar,
    #departmentsPage .department-view-controls,
    #departmentsPage .department-stats {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    #departmentsPage .department-header,
    #departmentsPage .department-toolbar {
        justify-content: space-between;
    }

    #departmentsPage .department-header {
        align-items: flex-start;
        margin-bottom: 20px;
    }

    #departmentsPage .department-title {
        margin: 0;
        font-size: clamp(1.35rem, 2.2vw, 1.75rem);
        font-weight: 700;
        letter-spacing: -.025em;
    }

    #departmentsPage .department-subtitle {
        margin: 5px 0 0;
        color: var(--department-muted);
        font-size: .92rem;
    }

    #departmentsPage .department-search {
        position: relative;
        width: min(100%, 520px);
    }

    #departmentsPage .department-search .form-control {
        min-height: 44px;
        padding-left: 42px;
        border-color: var(--department-line);
        border-radius: 10px;
    }

    #departmentsPage .department-search .bi-search {
        position: absolute;
        top: 50%;
        left: 15px;
        color: var(--department-muted);
        transform: translateY(-50%);
    }

    #departmentsPage .department-stats {
        flex-wrap: wrap;
        margin: 0 0 18px;
    }

    #departmentsPage .department-stat {
        min-width: 150px;
        padding: 11px 15px;
        border: 1px solid var(--department-line);
        border-radius: 10px;
        background: #fff;
    }

    #departmentsPage .department-stat strong {
        display: block;
        font-size: 1.15rem;
        line-height: 1.25;
    }

    #departmentsPage .department-stat span {
        color: var(--department-muted);
        font-size: .78rem;
    }

    #departmentsPage .department-view-controls {
        justify-content: space-between;
        flex-wrap: wrap;
        margin-bottom: 14px;
    }

    #departmentsPage .department-view-switch {
        display: inline-flex;
        padding: 3px;
        border: 1px solid var(--department-line);
        border-radius: 10px;
        background: #f8fafc;
    }

    #departmentsPage .department-view-switch button {
        min-height: 36px;
        padding: 0 13px;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #475569;
    }

    #departmentsPage .department-view-switch button.active {
        background: #fff;
        color: #075985;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .12);
    }

    #departmentsPage .department-view-panel[hidden] {
        display: none !important;
    }

    #departmentsPage [hidden] {
        display: none !important;
    }

    #departmentsPage .department-table-wrap {
        overflow-x: auto;
        border: 1px solid #edf0f4;
        border-radius: 12px;
        background: #fff;
    }

    #departmentsPage #departmentsTable {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    #departmentsPage #departmentsTable thead th {
        padding: 13px 16px;
        border-bottom: 1px solid var(--department-line);
        color: #64748b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    #departmentsPage #departmentsTable tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #edf0f4;
        vertical-align: middle;
    }

    #departmentsPage #departmentsTable tbody tr:last-child td {
        border-bottom: 0;
    }

    #departmentsPage #departmentsTable tbody tr:hover {
        background: #f8fafc;
    }

    #departmentsPage .department-name-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 190px;
        font-weight: 600;
    }

    #departmentsPage .department-name-icon {
        display: inline-grid;
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        place-items: center;
        border-radius: 9px;
        background: #eff6ff;
        color: #2563eb;
    }

    #departmentsPage .department-actions {
        display: flex;
        justify-content: flex-end;
        gap: 5px;
        white-space: nowrap;
    }

    #departmentsPage .department-actions .btn {
        width: 34px;
        height: 34px;
        padding: 0;
        border-radius: 8px;
    }

    #departmentsPage .department-count {
        display: inline-flex;
        min-width: 32px;
        justify-content: center;
        padding: 4px 9px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        font-weight: 600;
    }

    #departmentsPage .department-chart-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
    }

    #departmentsPage .department-chart-modes,
    #departmentsPage .department-chart-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    #departmentsPage .department-chart-modes .active {
        background: #0d6efd;
        color: #fff;
    }

    #departmentOrgChart {
        min-height: 380px;
        max-height: min(68vh, 760px);
        overflow: auto;
        overscroll-behavior: contain;
        padding: 24px;
        border: 1px solid var(--department-line);
        border-radius: 12px;
        background:
            radial-gradient(#dbe4ef .8px, transparent .8px) 0 0 / 20px 20px,
            #f8fafc;
    }

    #departmentOrgCanvas {
        min-width: max-content;
        transform-origin: top center;
        transition: transform .16s ease;
    }

    #departmentOrgCanvas > .department-tree {
        justify-content: center;
    }

    .department-tree,
    .department-tree ul {
        display: flex;
        justify-content: center;
        position: relative;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .department-tree ul {
        padding-top: 28px;
    }

    .department-tree li {
        position: relative;
        padding: 26px 10px 0;
        text-align: center;
    }

    .department-tree li::before,
    .department-tree li::after {
        position: absolute;
        top: 0;
        width: 50%;
        height: 26px;
        border-top: 1px solid #cbd5e1;
        content: "";
    }

    .department-tree li::before {
        left: 0;
        border-right: 1px solid #cbd5e1;
    }

    .department-tree li::after {
        right: 0;
        border-left: 1px solid #cbd5e1;
    }

    .department-tree li:only-child::before,
    .department-tree li:only-child::after,
    .department-tree > li::before,
    .department-tree > li::after {
        display: none;
    }

    .department-tree li:only-child,
    .department-tree > li {
        padding-top: 0;
    }

    .department-tree li:first-child::before,
    .department-tree li:last-child::after {
        border: 0;
    }

    .department-tree li > ul::before {
        position: absolute;
        top: 0;
        left: 50%;
        height: 28px;
        border-left: 1px solid #cbd5e1;
        content: "";
    }

    .department-node {
        position: relative;
        display: inline-flex;
        min-width: 180px;
        max-width: 260px;
        align-items: center;
        gap: 10px;
        padding: 12px;
        border: 1px solid #dbe3ed;
        border-radius: 11px;
        background: #fff;
        box-shadow: 0 2px 7px rgba(15, 23, 42, .05);
        text-align: left;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .department-node:hover,
    .department-node:focus-visible {
        border-color: #60a5fa;
        box-shadow: 0 7px 18px rgba(15, 23, 42, .1);
        outline: none;
        transform: translateY(-2px);
    }

    .department-node[draggable="true"] {
        cursor: grab;
    }

    .department-node.dragging {
        opacity: .48;
    }

    .department-node.drop-target {
        border: 2px dashed #2563eb;
        background: #eff6ff;
    }

    .department-node.search-match {
        border-color: #f59e0b;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, .2);
    }

    .department-node-icon {
        display: inline-grid;
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        place-items: center;
        border-radius: 9px;
        background: #eff6ff;
        color: #2563eb;
    }

    .department-node-copy {
        min-width: 0;
    }

    .department-node-name {
        display: block;
        overflow: hidden;
        color: #172033;
        font-size: .9rem;
        font-weight: 650;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .department-node-meta {
        display: block;
        color: var(--department-muted);
        font-size: .76rem;
    }

    .department-node-menu {
        position: absolute;
        top: 4px;
        right: 4px;
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border: 0;
        border-radius: 7px;
        background: transparent;
        color: #64748b;
    }

    .department-node-menu:hover,
    .department-node-menu:focus-visible {
        background: #f1f5f9;
        color: #172033;
    }

    @media (max-width: 767.98px) {
        #departmentsPage .department-header {
            align-items: stretch;
        }

        #departmentsPage .department-header > .btn {
            align-self: flex-start;
            white-space: nowrap;
        }

        #departmentsPage .department-stats {
            gap: 8px;
        }

        #departmentsPage .department-stat {
            min-width: calc(50% - 4px);
            flex: 1;
        }

        #departmentOrgChart {
            min-height: 320px;
            padding: 16px;
        }
    }

    @media (max-width: 480px) {
        #departmentsPage .department-header {
            flex-direction: column;
        }

        #departmentsPage .department-header > .btn {
            align-self: stretch;
        }

        #departmentsPage .department-view-switch button {
            padding: 0 9px;
            font-size: .86rem;
        }
    }
</style>

<main class="main-content">
    <div class="container-fluid mt-4" id="departmentsPage">
        <div class="department-header">
            <div>
                <h1 class="department-title">Departamentos</h1>
                <p class="department-subtitle">Gerencie a estrutura organizacional da empresa.</p>
            </div>
            <button type="button" class="btn btn-primary" id="btnNewDepartment">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Novo Departamento
            </button>
        </div>

        <div class="department-toolbar mb-3">
            <label class="department-search mb-0">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span class="visually-hidden">Pesquisar departamentos</span>
                <input type="search" id="departmentSearch" class="form-control" placeholder="Pesquisar departamento..." autocomplete="off" aria-controls="departmentsTable">
            </label>
        </div>

        <div class="department-stats" aria-label="Indicadores da organização">
            <div class="department-stat"><strong id="departmentCount">—</strong><span>Departamentos</span></div>
            <div class="department-stat"><strong id="employeeCount">—</strong><span>Funcionários</span></div>
            <div class="department-stat"><strong id="subdepartmentCount">—</strong><span>Subdepartamentos</span></div>
        </div>

        <div class="department-view-controls">
            <div class="department-view-switch" role="group" aria-label="Modo de visualização">
                <button type="button" class="active" data-department-view="table" aria-pressed="true"><i class="bi bi-table" aria-hidden="true"></i> Tabela</button>
                <button type="button" data-department-view="chart" aria-pressed="false"><i class="bi bi-diagram-3" aria-hidden="true"></i> Organograma</button>
            </div>
            <span class="small text-muted" id="departmentStatus" role="status" aria-live="polite">A carregar departamentos...</span>
        </div>

        <section id="departmentTableView" class="department-view-panel" aria-label="Tabela de departamentos">
            <div id="departmentEmptyState" class="alert alert-light border d-flex align-items-center justify-content-between gap-3" hidden>
                <span>Ainda não existem departamentos.</span>
                <button type="button" class="btn btn-sm btn-primary" data-create-first-department><i class="bi bi-plus-lg" aria-hidden="true"></i> Criar primeiro departamento</button>
            </div>
            <div class="department-table-wrap">
                <table id="departmentsTable" class="table align-middle">
                    <thead>
                        <tr>
                            <th>Departamento</th>
                            <th>Departamento-pai</th>
                            <th>Funcionários</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>

        <section id="departmentChartView" class="department-view-panel" aria-label="Organograma de departamentos" hidden>
            <div class="department-chart-toolbar">
                <div class="department-chart-modes btn-group btn-group-sm" role="group" aria-label="Modo do organograma">
                    <button type="button" class="btn btn-outline-secondary active" data-chart-mode="view" aria-pressed="true">Visualizar</button>
                    <button type="button" class="btn btn-outline-secondary" data-chart-mode="edit" aria-pressed="false">Editar estrutura</button>
                </div>
                <div class="department-chart-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="departmentZoomOut" aria-label="Diminuir zoom" title="Diminuir zoom"><i class="bi bi-zoom-out" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="departmentZoomReset" aria-label="Repor zoom" title="Repor zoom">100%</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="departmentZoomIn" aria-label="Aumentar zoom" title="Aumentar zoom"><i class="bi bi-zoom-in" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="departmentCenter" title="Centralizar organograma"><i class="bi bi-bullseye" aria-hidden="true"></i><span class="visually-hidden">Centralizar</span></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="departmentFullscreen" title="Ecrã inteiro"><i class="bi bi-fullscreen" aria-hidden="true"></i><span class="visually-hidden">Ecrã inteiro</span></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="departmentAutoArrange" title="Organizar visualmente sem alterar a hierarquia"><i class="bi bi-diagram-3" aria-hidden="true"></i> Organizar</button>
                </div>
            </div>
            <div id="departmentUndo" class="alert alert-success py-2 d-flex align-items-center justify-content-between gap-2" role="status" hidden>
                <span>Estrutura atualizada.</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="departmentUndoButton">Desfazer</button>
            </div>
            <div id="departmentOrgChart" tabindex="0" aria-label="Organograma. Use zoom ou scroll para navegar.">
                <div id="departmentOrgCanvas"></div>
            </div>
        </section>
    </div>
</main>

<div class="modal fade" id="modalDepartment" tabindex="-1" aria-labelledby="departmentModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formDepartment">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="departmentModalTitle">Novo Departamento</h2>
                        <p class="small text-muted mb-0">Defina o nome e, se necessário, o departamento-pai.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="departmentName" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="departmentName" class="form-control" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label for="selectParentDepartment" class="form-label">Departamento-pai</label>
                        <select name="parent_department_id" class="form-select" id="selectParentDepartment">
                            <option value="">Nenhum (raiz)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="departmentSaveButton">Criar Departamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="dropdown-menu" id="departmentContextMenu" role="menu">
    <button type="button" class="dropdown-item" data-department-action="edit">Editar</button>
    <button type="button" class="dropdown-item" data-department-action="child">Adicionar subdepartamento</button>
    <button type="button" class="dropdown-item" data-department-action="employees">Ver funcionários</button>
    <button type="button" class="dropdown-item" data-department-action="parent">Alterar departamento-pai</button>
    <div class="dropdown-divider"></div>
    <button type="button" class="dropdown-item text-danger" data-department-action="delete">Eliminar</button>
</div>

<script>
(() => {
    const root = document.getElementById('departmentsPage');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    const endpoint = 'rh/ajax/list_departments.php';
    const tableElement = document.getElementById('departmentsTable');
    const tableView = document.getElementById('departmentTableView');
    const chartView = document.getElementById('departmentChartView');
    const chartViewport = document.getElementById('departmentOrgChart');
    const chartCanvas = document.getElementById('departmentOrgCanvas');
    const search = document.getElementById('departmentSearch');
    const status = document.getElementById('departmentStatus');
    const menu = document.getElementById('departmentContextMenu');
    const modalElement = document.getElementById('modalDepartment');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);

    let departments = [];
    let table = null;
    let chartMode = 'view';
    let zoom = 1;
    let searchTimer = null;
    let requestId = 0;
    let detectedHierarchyCycle = false;
    let contextDepartmentId = null;
    let undoState = null;
    let autoArrange = false;

    const departmentById = (id) => departments.find((department) => String(department.id) === String(id));
    const parentLabel = (department) => department.parent_name || '—';
    const normalized = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt');

    function setStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('text-danger', isError);
        status.classList.toggle('text-muted', !isError);
    }

    function updateStats() {
        const employees = departments.reduce((total, department) => total + (Number(department.employee_count) || 0), 0);
        document.getElementById('departmentCount').textContent = String(departments.length);
        document.getElementById('employeeCount').textContent = String(employees);
        document.getElementById('subdepartmentCount').textContent = String(departments.filter((department) => department.parent_department_id).length);
    }

    function makeTree() {
        const nodes = new Map();
        const parents = new Map();
        departments.forEach((department) => {
            const id = String(department.id);
            nodes.set(id, { ...department, children: [] });
            parents.set(id, department.parent_department_id ? String(department.parent_department_id) : '');
        });

        detectedHierarchyCycle = false;
        const inspected = new Set();
        const path = [];
        const inspectParentChain = (id) => {
            if (inspected.has(id)) return;
            const cycleAt = path.indexOf(id);
            if (cycleAt !== -1) {
                const edgeToBreak = path[path.length - 1];
                parents.set(edgeToBreak, '');
                detectedHierarchyCycle = true;
                return;
            }
            path.push(id);
            const parentId = parents.get(id);
            if (parentId && nodes.has(parentId)) inspectParentChain(parentId);
            path.pop();
            inspected.add(id);
        };
        nodes.forEach((_, id) => inspectParentChain(id));

        const roots = [];
        nodes.forEach((node) => {
            const parentId = parents.get(String(node.id));
            const parent = parentId ? nodes.get(parentId) : null;
            if (parent && parent.id !== node.id) parent.children.push(node);
            else roots.push(node);
        });
        const sort = (items) => {
            items.sort((a, b) => autoArrange
                ? (Number(b.employee_count) - Number(a.employee_count)) || String(a.name).localeCompare(String(b.name), 'pt')
                : String(a.name).localeCompare(String(b.name), 'pt'));
            items.forEach((item) => sort(item.children));
        };
        sort(roots);
        return roots;
    }

    function hasDescendant(parentId, candidateId, visited = new Set()) {
        const key = String(parentId);
        if (visited.has(key)) return false;
        visited.add(key);
        return departments.some((department) => {
            if (String(department.parent_department_id || '') !== key) return false;
            return String(department.id) === String(candidateId) || hasDescendant(department.id, candidateId, visited);
        });
    }

    function renderNode(department) {
        const children = department.children || [];
        const canEdit = chartMode === 'edit';
        return `<li data-department-id="${escapeHtml(department.id)}">
            <div class="department-node" data-department-node="${escapeHtml(department.id)}" tabindex="0" role="group" aria-label="${escapeHtml(department.name)}; ${Number(department.employee_count) || 0} funcionários" ${canEdit ? 'draggable="true"' : ''}>
                <span class="department-node-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                <span class="department-node-copy"><span class="department-node-name">${escapeHtml(department.name)}</span><span class="department-node-meta">${Number(department.employee_count) || 0} funcionários</span></span>
                <button type="button" class="department-node-menu" data-open-department-menu="${escapeHtml(department.id)}" aria-label="Ações de ${escapeHtml(department.name)}" title="Ações"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
            </div>
            ${children.length ? `<ul>${children.map(renderNode).join('')}</ul>` : ''}
        </li>`;
    }

    function applyChartSearch() {
        if (chartView.hidden) return;
        const query = normalized(search.value.trim());
        let firstMatch = null;
        chartCanvas.querySelectorAll('.department-node').forEach((node) => {
            const match = query && normalized(node.innerText).includes(query);
            node.classList.toggle('search-match', Boolean(match));
            if (match && !firstMatch) firstMatch = node;
        });
        firstMatch?.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    }

    function renderChart() {
        if (!departments.length) {
            chartCanvas.innerHTML = '<p class="text-muted mb-0">Ainda não existem departamentos. <button type="button" class="btn btn-link p-0" data-create-first-department>Criar o primeiro departamento</button></p>';
            return;
        }
        const tree = makeTree();
        chartCanvas.innerHTML = `<ul class="department-tree">${tree.map(renderNode).join('')}</ul>`;
        applyChartSearch();
    }

    function refreshLocalViews() {
        updateStats();
        document.getElementById('departmentEmptyState').hidden = departments.length > 0;
        renderChart();
        if (table) {
            table.setRows(orderRowsByTree());
        }
        fillParentOptions(contextDepartmentId);
        setStatus(detectedHierarchyCycle
            ? 'Foi detetado um ciclo na hierarquia existente. A visualização foi protegida; edite os departamentos para corrigir.'
            : `${departments.length} departamento${departments.length === 1 ? '' : 's'}`,
            detectedHierarchyCycle);
    }

    function loadDepartments({ showLoading = true } = {}) {
        const currentRequest = ++requestId;
        if (showLoading) setStatus('A carregar departamentos...');
        return $.getJSON(endpoint)
            .then((response) => {
                if (currentRequest !== requestId) return;
                if (!Array.isArray(response.data)) throw new Error('Resposta de departamentos inválida.');
                departments = response.data;
                refreshLocalViews();
            })
            .catch((error) => {
                if (currentRequest !== requestId) return;
                console.error('Não foi possível carregar os departamentos:', error);
                setStatus('Não foi possível carregar os departamentos. Tente novamente.', true);
                chartCanvas.innerHTML = '<div class="alert alert-danger mb-0">Erro ao carregar a estrutura. <button type="button" class="btn btn-sm btn-outline-danger ms-2" data-reload-departments>Tentar novamente</button></div>';
                throw error;
            });
    }

    function fillParentOptions(excludeId, selectedId = '') {
        const select = document.getElementById('selectParentDepartment');
        const options = departments
            .filter((department) => String(department.id) !== String(excludeId || '') && (!excludeId || !hasDescendant(excludeId, department.id)))
            .map((department) => `<option value="${escapeHtml(department.id)}">${escapeHtml(department.name)}</option>`)
            .join('');
        select.innerHTML = `<option value="">Nenhum (raiz)</option>${options}`;
        select.value = selectedId ? String(selectedId) : '';
    }

    function openDepartmentForm(department = null, parentId = '') {
        const form = document.getElementById('formDepartment');
        form.reset();
        form.querySelector('[name="id"]')?.remove();
        contextDepartmentId = department?.id ?? null;
        fillParentOptions(department?.id, parentId || department?.parent_department_id || '');
        document.getElementById('departmentName').value = department?.name || '';
        document.getElementById('departmentModalTitle').textContent = department ? 'Editar Departamento' : (parentId ? 'Novo Subdepartamento' : 'Novo Departamento');
        document.getElementById('departmentSaveButton').textContent = department ? 'Guardar alterações' : 'Criar Departamento';
        if (department) {
            const id = document.createElement('input');
            id.type = 'hidden';
            id.name = 'id';
            id.value = department.id;
            form.append(id);
        }
        modal.show();
        window.setTimeout(() => document.getElementById('departmentName').focus(), 100);
    }

    function closeContextMenu() {
        menu.classList.remove('show');
        menu.style.display = '';
        menu.removeAttribute('data-bs-popper');
    }

    function showContextMenu(button, id) {
        contextDepartmentId = id;
        const rect = button.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.left = `${Math.min(rect.left, window.innerWidth - 240)}px`;
        menu.style.top = `${Math.min(rect.bottom, window.innerHeight - 240)}px`;
        menu.style.zIndex = '1080';
        menu.classList.add('show');
        menu.style.display = 'block';
    }

    menu.addEventListener('click', (event) => {
        const actionButton = event.target.closest('[data-department-action]');
        const department = departmentById(contextDepartmentId);
        if (!actionButton || !department) return;
        const action = actionButton.dataset.departmentAction;
        closeContextMenu();
        if (action === 'edit' || action === 'parent') openDepartmentForm(department);
        else if (action === 'child') openDepartmentForm(null, department.id);
        else if (action === 'employees') {
            window.location.assign(`/employees?department_id=${encodeURIComponent(department.id)}`);
        } else if (action === 'delete') {
            deleteDepartment(department);
        }
    });

    function switchView(view) {
        const showChart = view === 'chart';
        tableView.hidden = showChart;
        chartView.hidden = !showChart;
        root.querySelectorAll('[data-department-view]').forEach((button) => {
            const active = button.dataset.departmentView === view;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        if (showChart) renderChart();
        else table?.columns.adjust();
    }

    function setChartMode(mode) {
        chartMode = mode;
        root.querySelectorAll('[data-chart-mode]').forEach((button) => {
            const active = button.dataset.chartMode === mode;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        renderChart();
    }

    function setZoom(value) {
        zoom = Math.max(.45, Math.min(1.8, value));
        chartCanvas.style.transform = `scale(${zoom})`;
        document.getElementById('departmentZoomReset').textContent = `${Math.round(zoom * 100)}%`;
    }

    function showToast(icon, title) {
        if (window.Swal?.fire) {
            return Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        }
        console[icon === 'error' ? 'error' : 'info'](title);
        return Promise.resolve();
    }

    function moveDepartment(id, newParentId) {
        const department = departmentById(id);
        if (!department) return;
        const parentId = newParentId ? String(newParentId) : '';
        if (String(id) === parentId || (parentId && hasDescendant(id, parentId))) {
            showToast('error', 'Esta alteração criaria uma hierarquia inválida.');
            renderChart();
            return;
        }
        if (String(department.parent_department_id || '') === parentId) return;
        const oldParent = department.parent_department_id || '';
        const parent = parentId ? departmentById(parentId) : null;
        const confirmMove = () => Swal.fire({
            title: 'Alterar departamento-pai?',
            text: `${department.name} passará a pertencer a ${parent?.name || 'nenhum departamento (raiz)'}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Alterar estrutura',
            cancelButtonText: 'Cancelar',
        });
        confirmMove().then((result) => {
            if (!result.isConfirmed) {
                renderChart();
                return;
            }
            persistMove(department, oldParent, parentId);
        });
    }

    function persistMove(department, oldParent, newParent, recordUndo = true) {
        setStatus('A atualizar a hierarquia...');
        $.ajax({
            url: 'rh/ajax/save_department.php',
            method: 'POST',
            dataType: 'json',
            data: { id: department.id, name: department.name, parent_department_id: newParent },
        }).done((response) => {
            if (!response.success) {
                showToast('error', response.message || 'Não foi possível alterar a hierarquia.');
                loadDepartments({ showLoading: false }).catch(() => {});
                return;
            }
            if (recordUndo) {
                undoState = { id: department.id, name: department.name, parent_department_id: oldParent };
                document.getElementById('departmentUndo').hidden = false;
            } else {
                undoState = null;
                document.getElementById('departmentUndo').hidden = true;
            }
            loadDepartments({ showLoading: false }).then(() => showToast('success', 'Estrutura atualizada.'));
        }).fail((xhr) => {
            showToast('error', xhr.responseJSON?.message || 'Não foi possível alterar a hierarquia.');
            loadDepartments({ showLoading: false }).catch(() => {});
        });
    }

    table = BootstrapTable.create({
        table: tableElement,
        data: [],
        searchInput: search,
        bindSearch: false,
        pageSize: 10,
        pageSizes: [10],
        columns: [
            {
                data: 'name',
                render: (value, row) => `<span class="department-name-cell" style="padding-left:${Math.min(Number(row.depth || 0), 6) * 18}px"><span class="department-name-icon"><i class="bi bi-diagram-3" aria-hidden="true"></i></span><span>${escapeHtml(value)}</span></span>`,
            },
            { data: 'parent_name', render: value => escapeHtml(value || '—') },
            { data: 'employee_count', render: value => `<span class="department-count">${Number(value) || 0}</span>`, sortValue: row => Number(row.employee_count) || 0 },
            {
                data: null,
                sortable: false,
                searchable: false,
                render: (_, row) => `<div class="department-actions">
                    <button type="button" class="btn btn-light text-primary editDepartment" data-id="${escapeHtml(row.id)}" aria-label="Editar ${escapeHtml(row.name)}" title="Editar"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-light text-secondary addChildDepartment" data-id="${escapeHtml(row.id)}" aria-label="Adicionar subdepartamento a ${escapeHtml(row.name)}" title="Adicionar subdepartamento"><i class="bi bi-diagram-2" aria-hidden="true"></i></button>
                    <button type="button" class="btn btn-light text-danger deleteDepartment" data-id="${escapeHtml(row.id)}" aria-label="Eliminar ${escapeHtml(row.name)}" title="Eliminar"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </div>`,
            },
        ],
    });

    function flattenTree(nodes, depth = 0, flat = []) {
        nodes.forEach((node) => {
            flat.push({ ...node, depth });
            flattenTree(node.children, depth + 1, flat);
        });
        return flat;
    }

    function orderRowsByTree() {
        return flattenTree(makeTree()).map((node) => ({ ...node }));
    }

    $('#departmentSearch').on('input', function() {
        clearTimeout(searchTimer);
        const value = this.value;
        searchTimer = window.setTimeout(() => {
                table.search(value);
            applyChartSearch();
        }, 140);
    });

    root.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-department-view]');
        if (viewButton) return switchView(viewButton.dataset.departmentView);
        if (event.target.closest('#btnNewDepartment, [data-create-first-department]')) return openDepartmentForm();
        const reloadButton = event.target.closest('[data-reload-departments]');
        if (reloadButton) return loadDepartments().catch(() => {});
        const editButton = event.target.closest('.editDepartment, [data-open-department-menu][data-action="edit"]');
        if (editButton) {
            const department = departmentById(editButton.dataset.id || contextDepartmentId);
            if (department) openDepartmentForm(department);
            return;
        }
        const childButton = event.target.closest('.addChildDepartment, [data-open-department-menu][data-action="child"]');
        if (childButton) {
            const parent = departmentById(childButton.dataset.id || contextDepartmentId);
            if (parent) openDepartmentForm(null, parent.id);
            return;
        }
        const menuButton = event.target.closest('[data-open-department-menu]');
        if (menuButton) {
            showContextMenu(menuButton, menuButton.dataset.openDepartmentMenu);
            return;
        }
        const departmentNode = event.target.closest('[data-department-node]');
        if (departmentNode) {
            const nodeMenu = departmentNode.querySelector('[data-open-department-menu]');
            if (nodeMenu) showContextMenu(nodeMenu, nodeMenu.dataset.openDepartmentMenu);
            return;
        }
        const actionButton = event.target.closest('[data-department-action]');
        if (actionButton) {
            const action = actionButton.dataset.departmentAction;
            const department = departmentById(contextDepartmentId);
            closeContextMenu();
            if (!department) return;
            if (action === 'edit') return openDepartmentForm(department);
            if (action === 'child') return openDepartmentForm(null, department.id);
            if (action === 'parent') return openDepartmentForm(department);
            if (action === 'employees') {
                document.dispatchEvent(new CustomEvent('departments:filter-employees', { detail: { departmentId: department.id } }));
                window.location.assign(`/employees?department_id=${encodeURIComponent(department.id)}`);
                return;
            }
            if (action === 'delete') return deleteDepartment(department);
        }
        const deleteButton = event.target.closest('.deleteDepartment');
        if (deleteButton) {
            const department = departmentById(deleteButton.dataset.id);
            if (department) deleteDepartment(department);
        }
        if (!event.target.closest('#departmentContextMenu')) closeContextMenu();
    });

    root.addEventListener('keydown', (event) => {
        if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('.department-node')) {
            event.preventDefault();
            const menuButton = event.target.querySelector('[data-open-department-menu]');
            if (menuButton) showContextMenu(menuButton, menuButton.dataset.openDepartmentMenu);
        }
        if (event.key === 'Escape') closeContextMenu();
    });

    root.addEventListener('click', (event) => {
        const modeButton = event.target.closest('[data-chart-mode]');
        if (modeButton) setChartMode(modeButton.dataset.chartMode);
    });

    chartCanvas.addEventListener('dragstart', (event) => {
        const node = event.target.closest('[data-department-node]');
        if (!node || chartMode !== 'edit') return event.preventDefault();
        event.dataTransfer.setData('text/plain', node.dataset.departmentNode);
        event.dataTransfer.effectAllowed = 'move';
        node.classList.add('dragging');
    });
    chartCanvas.addEventListener('dragend', (event) => {
        event.target.closest('[data-department-node]')?.classList.remove('dragging');
        chartCanvas.querySelectorAll('.drop-target').forEach((node) => node.classList.remove('drop-target'));
    });
    chartCanvas.addEventListener('dragover', (event) => {
        const node = event.target.closest('[data-department-node]');
        if (chartMode !== 'edit' || !node) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        node.classList.add('drop-target');
    });
    chartCanvas.addEventListener('dragleave', (event) => event.target.closest('[data-department-node]')?.classList.remove('drop-target'));
    chartCanvas.addEventListener('drop', (event) => {
        const target = event.target.closest('[data-department-node]');
        if (chartMode !== 'edit' || !target) return;
        event.preventDefault();
        target.classList.remove('drop-target');
        moveDepartment(event.dataTransfer.getData('text/plain'), target.dataset.departmentNode);
    });

    document.getElementById('departmentZoomIn').addEventListener('click', () => setZoom(zoom + .1));
    document.getElementById('departmentZoomOut').addEventListener('click', () => setZoom(zoom - .1));
    document.getElementById('departmentZoomReset').addEventListener('click', () => setZoom(1));
    document.getElementById('departmentCenter').addEventListener('click', () => chartViewport.scrollTo({ left: (chartViewport.scrollWidth - chartViewport.clientWidth) / 2, top: 0, behavior: 'smooth' }));
    document.getElementById('departmentFullscreen').addEventListener('click', async () => {
        try {
            if (!document.fullscreenElement) await chartViewport.requestFullscreen();
            else await document.exitFullscreen();
        } catch (error) {
            console.error('Não foi possível alternar o ecrã inteiro:', error);
            showToast('error', 'O ecrã inteiro não está disponível neste navegador.');
        }
    });
    document.getElementById('departmentAutoArrange').addEventListener('click', () => {
        autoArrange = !autoArrange;
        renderChart();
        showToast('success', autoArrange
            ? 'Organograma organizado por número de funcionários; a hierarquia não foi alterada.'
            : 'Organograma reposto à ordem alfabética; a hierarquia não foi alterada.');
    });
    document.getElementById('departmentUndoButton').addEventListener('click', () => {
        if (!undoState) return;
        const previous = undoState;
        undoState = null;
        document.getElementById('departmentUndo').hidden = true;
        persistMove(previous, departmentById(previous.id)?.parent_department_id || '', previous.parent_department_id, false);
    });

    document.getElementById('formDepartment').addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const submit = document.getElementById('departmentSaveButton');
        submit.disabled = true;
        submit.textContent = 'A guardar...';
        $.ajax({
            url: 'rh/ajax/save_department.php',
            method: 'POST',
            data: $(form).serialize(),
            dataType: 'json',
        }).done((response) => {
            if (!response.success) {
                showToast('error', response.message || 'Não foi possível guardar o departamento.');
                return;
            }
            modal.hide();
            form.querySelector('[name="id"]')?.remove();
            loadDepartments({ showLoading: false }).then(() => showToast('success', 'Departamento guardado com sucesso.')).catch(() => {});
        }).fail((xhr) => showToast('error', xhr.responseJSON?.message || 'Não foi possível guardar o departamento.'))
            .always(() => {
                submit.disabled = false;
                submit.textContent = form.querySelector('[name="id"]') ? 'Guardar alterações' : 'Criar Departamento';
            });
    });

    function deleteDepartment(department) {
        Swal.fire({
            title: 'Eliminar departamento?',
            text: `Tem a certeza de que deseja eliminar "${department.name}"? A operação só será permitida se não houver funcionários, subdepartamentos ou cargos associados.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        }).then((result) => {
            if (!result.isConfirmed) return;
            setStatus('A eliminar departamento...');
            $.ajax({ url: 'rh/ajax/delete_department.php', method: 'POST', data: { id: department.id }, dataType: 'json' })
                .done((response) => {
                    if (!response.success) {
                        setStatus(`${departments.length} departamento${departments.length === 1 ? '' : 's'}`);
                        return showToast('error', response.message || 'Não foi possível eliminar o departamento.');
                    }
                    loadDepartments({ showLoading: false }).then(() => showToast('success', 'Departamento eliminado.')).catch(() => {});
                })
                .fail((xhr) => {
                    setStatus(`${departments.length} departamento${departments.length === 1 ? '' : 's'}`);
                    showToast('error', xhr.responseJSON?.message || 'Não foi possível eliminar o departamento.');
                });
        });
    }

    $('#modalDepartment').on('hidden.bs.modal', () => {
        document.getElementById('formDepartment').querySelector('[name="id"]')?.remove();
        contextDepartmentId = null;
    });
    $(document).on('click.departmentContext', (event) => {
        if (!event.target.closest('#departmentContextMenu, [data-open-department-menu]')) closeContextMenu();
    });
    $(document).on('keydown.departmentContext', (event) => {
        if (event.key === 'Escape') closeContextMenu();
    });

    loadDepartments().catch(() => {});
    setChartMode('view');
})();
</script>

<?php require_once '../app/views/footer.php'; ?>
