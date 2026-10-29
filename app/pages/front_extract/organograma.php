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
    #orgChartWrapper {
        overflow: auto;
        background: #f8fafc;
        border-radius: 14px;
        padding: 24px;
        min-height: 60vh;
    }

    #orgChartZoom {
        transform-origin: top left;
        transition: transform 0.15s ease;
    }

    .org-tree ul {
        list-style: none;
        padding-top: 24px;
        position: relative;
        display: flex;
    }

    .org-tree li {
        list-style: none;
        text-align: center;
        position: relative;
        padding: 24px 12px 0 12px;
    }

    /* linhas de ligação entre nós */
    .org-tree li::before,
    .org-tree li::after {
        content: '';
        position: absolute;
        top: 0;
        border-top: 2px solid #cbd5e1;
        width: 50%;
        height: 24px;
    }

    .org-tree li::before {
        left: 0;
        border-right: 2px solid #cbd5e1;
    }

    .org-tree li::after {
        right: 0;
        border-left: 2px solid #cbd5e1;
    }

    .org-tree li:only-child::before,
    .org-tree li:only-child::after {
        display: none;
    }

    .org-tree li:only-child {
        padding-top: 0;
    }

    .org-tree li:first-child::before,
    .org-tree li:last-child::after {
        border: 0 none;
    }

    .org-tree li:last-child::before {
        border-right: 2px solid #cbd5e1;
        border-radius: 0 0 0 0;
    }

    .org-tree li:first-child::after {
        border-radius: 0;
    }

    .org-tree > ul > li::before,
    .org-tree > ul > li::after,
    .org-tree > ul > li {
        border: 0 none;
    }

    .org-node {
        display: inline-block;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        padding: 10px 16px;
        cursor: pointer;
        min-width: 160px;
        transition: all .2s ease;
    }

    .org-node:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        border-color: #93c5fd;
    }

    .org-node .name {
        font-weight: 600;
        font-size: 13px;
        color: #111827;
    }

    .org-node .role {
        font-size: 11px;
        color: #6b7280;
    }

    .org-toggle {
        font-size: 10px;
        color: #2563eb;
        cursor: pointer;
        display: block;
        margin-top: 4px;
    }
</style>

<main class="main-content">
<div class="container-fluid mt-5">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0">Organograma</h4>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-sm btn-outline-secondary" id="btnZoomOut"><i class="bi bi-zoom-out"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="btnZoomReset">100%</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnZoomIn"><i class="bi bi-zoom-in"></i></button>
            <button class="btn btn-sm btn-outline-secondary" id="btnExpandAll">Expandir tudo</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnCollapseAll">Colapsar tudo</button>
        </div>
    </div>

    <p class="text-muted small">
        Gerado automaticamente a partir da chefia direta e do departamento de cada funcionário.
        Para reatribuir chefia, edita o funcionário na ficha normal (campo "Chefia direta") — este diagrama é apenas de leitura.
    </p>

    <div id="orgChartWrapper">
        <div id="orgChartZoom">
            <div id="orgChartRoot" class="org-tree"></div>
        </div>
    </div>
</div>
</main>

<!-- PAINEL LATERAL (offcanvas) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="orgNodePanel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Detalhe do funcionário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body" id="orgNodePanelBody">
        <!-- preenchido via JS -->
    </div>
</div>

<script>
    let zoomLevel = 1;
    let orgTreeData = [];

    function applyZoom() {
        $('#orgChartZoom').css('transform', `scale(${zoomLevel})`);
    }

    $('#btnZoomIn').on('click', () => { zoomLevel = Math.min(2, zoomLevel + 0.1); applyZoom(); });
    $('#btnZoomOut').on('click', () => { zoomLevel = Math.max(0.4, zoomLevel - 0.1); applyZoom(); });
    $('#btnZoomReset').on('click', () => { zoomLevel = 1; applyZoom(); });

    function renderNode(node) {
        const hasChildren = node.children && node.children.length > 0;
        const childrenHtml = hasChildren
            ? `<ul class="org-children">${node.children.map(renderNode).join('')}</ul>`
            : '';

        return `
            <li data-id="${node.id}">
                <div class="org-node" data-id="${node.id}">
                    <div class="name">${node.name}</div>
                    <div class="role">${node.position || '—'}${node.department_name ? ' · ' + node.department_name : ''}</div>
                    ${hasChildren ? `<span class="org-toggle" data-action="toggle">▾ recolher</span>` : ''}
                </div>
                ${childrenHtml}
            </li>
        `;
    }

    function findNode(nodes, id) {
        for (const n of nodes) {
            if (String(n.id) === String(id)) return n;
            if (n.children) {
                const found = findNode(n.children, id);
                if (found) return found;
            }
        }
        return null;
    }

    function loadOrgChart() {
        $.getJSON('rh/ajax/get_org_chart.php', function(resp) {
            if (!resp.success) {
                $('#orgChartRoot').html('<p class="text-danger">Não foi possível carregar o organograma.</p>');
                return;
            }
            orgTreeData = resp.data || [];
            if (orgTreeData.length === 0) {
                $('#orgChartRoot').html('<p class="text-muted">Sem funcionários ativos para mostrar (ou nenhum tem chefia/subordinados definidos ainda).</p>');
                return;
            }
            $('#orgChartRoot').html(`<ul>${orgTreeData.map(renderNode).join('')}</ul>`);
        });
    }

    $('#orgChartRoot').on('click', '.org-node', function(e) {
        if ($(e.target).data('action') === 'toggle') return;
        const id = $(this).data('id');
        const node = findNode(orgTreeData, id);
        if (!node) return;

        $('#orgNodePanelBody').html(`
            <div class="text-center mb-3">
                ${node.photo_url
                    ? `<img src="${node.photo_url}" class="rounded-circle mb-2" width="80" height="80" style="object-fit:cover">`
                    : `<div class="rounded-circle bg-light mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:28px;">${node.name.charAt(0)}</div>`}
                <h5 class="mb-0">${node.name}</h5>
                <div class="text-muted">${node.position || '—'}</div>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><strong>Departamento:</strong> ${node.department_name || '—'}</li>
                <li class="list-group-item"><strong>Telefone:</strong> ${node.phone || '—'}</li>
                <li class="list-group-item"><strong>E-mail:</strong> ${node.email || '—'}</li>
                <li class="list-group-item"><strong>Subordinados diretos:</strong> ${(node.children || []).length}</li>
            </ul>
        `);
        new bootstrap.Offcanvas('#orgNodePanel').show();
    });

    $('#orgChartRoot').on('click', '[data-action="toggle"]', function(e) {
        e.stopPropagation();
        const li = $(this).closest('li');
        const childrenUl = li.children('ul.org-children');
        childrenUl.slideToggle(150);
        $(this).text($(this).text().includes('recolher') ? '▸ expandir' : '▾ recolher');
    });

    $('#btnExpandAll').on('click', () => $('.org-children').show());
    $('#btnCollapseAll').on('click', () => $('.org-children').hide());

    $(document).ready(loadOrgChart);
</script>

<?php require_once '../app/views/footer.php'; ?>