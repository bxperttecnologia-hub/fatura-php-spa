<?php
require_once '../app/config/db.php';
require_once '../app/helpers/functions.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

// Importante: qualquer redirect precisa acontecer ANTES de imprimir HTML.
// layout_creation.php inclui head/nav e já envia output.

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);

// A troca de empresa acontece via sessão (navbar). Esta tela sempre segue a empresa ativa.
// Se alguém chegar com company_id via GET, limpamos a URL (evita confusão e cache).
if (isset($_GET['company_id'])) {
  header('Location: subscription.php');
  exit;
}

if (!$company_id) {
  die('Empresa inválida');
}

require_once '../app/views/layout_creation.php';

$c = subscription_get_company($pdo, $company_id);
$plans = subscription_plans();
$planCode = $c['plan_code'] ?? 'BXPERT_BAZA';
$plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
$usage = subscription_usage($pdo, $company_id);
$daysLeft = subscription_days_left($c['plan_expires_at'] ?? null);

?>

<style>
  body {
    background: #f6f8fc;
  }

  .plan-card-top {
    background: #fff;
    border-radius: 18px;
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.06);
  }

  .plan-info {
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .plan-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: #eef4ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2f6bff;
    font-weight: 700;
  }

  .badge-days {
    background: #e8fff1;
    color: #16a34a;
    border: 1px solid #bbf7d0;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
  }

  .section-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.05);
    border: 1px solid #eef0f6;
  }

  .soft-box {
    background: #fff;
    border: 1px solid #edf0f5;
    border-radius: 14px;
    padding: 14px;
  }

  .progress {
    height: 10px;
    border-radius: 50px;
    background: #e9edf5;
  }

  .progress-bar {
    background: #3b82f6;
  }

  .limit-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 12px;
    background: #f9fafc;
    border-radius: 12px;
    margin-bottom: 10px;
  }

  .progress {
    height: 8px;
    border-radius: 999px;
    background: #e9edf5;
    overflow: hidden;
  }

  .progress-bar {
    background: linear-gradient(90deg, #005a87b4, #0398e8e3);
    border-radius: 999px;
    transition: width 0.6s ease;
  }

  /* Estilos para transformar a tabela em cards no mobile */

  /* ===== TABELA ESTILO ===== */
  #tblOrders {
    border-collapse: separate;
    border-spacing: 0 12px;
    width: 100%;
  }

  /* HEADER */
  #tblOrders thead th {
    border: none;
    font-size: 12px;
    color: #9ca3af;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 12px 16px;
    text-align: left;
    border-right: 1px solid #e5e7eb57;
  }

  #tblOrders thead th:last-child {
    border-right: none;
  }

  #tblOrders tbody tr td {
    border-right: 1px solid #e5e7eb57;
  }

  /* ROW */
  #tblOrders tbody tr {
    background: #fff !important;
    border-radius: 14px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    text-align: left !important;
  }


  /* HOVER PRO */
  #tblOrders tbody tr:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
  }

  /* CELLS */
  #tblOrders tbody td {
    border: none;
    padding: 18px 16px;
    vertical-align: middle;
    font-size: 0.95rem;
    background: #fff !important;
    text-align: left !important;
  }

  #tblOrders thead td {
    background: #111 !important;
    display: none;
    max-width: 80px !important;
  }

  /* BORDAS ARREDONDADAS */
  #tblOrders tbody td:first-child {
    border-top-left-radius: 14px;
    border-bottom-left-radius: 14px;
    background: #fff !important;
  }

  #tblOrders tbody th {
    text-align: left !important;
  }

  #tblOrders tbody td:last-child {
    border-top-right-radius: 14px;
    border-bottom-right-radius: 14px;
    text-align: right;
    padding-right: 24px;
  }

  /* ===== NOME (PRINCIPAL) ===== */
  #tblOrders tbody td:first-child {
    font-weight: 600;
    color: #111;
  }

  /* SUBINFO */
  #tblOrders tbody td small {
    display: block;
    color: #6b7280;
  }

  .pagination .page-link {
    border-radius: 10px !important;
    margin: 0 2px;
    border: none;
    background: #f3f6fb;
    color: #333;
    font-size: 13px;
  }

  .pagination .page-item.active .page-link {
    background: #2f6bff;
    color: #fff;
  }

  .pagination .page-item.disabled .page-link {
    opacity: 0.5;
  }

  .h-title {
    color: #007abd;
    border: 2px solid #007bbd41;
    border-radius: 8px;
    padding: 5px;
    font-weight: bolder;
    width: auto;
    margin: 0;
    font-size: 0.9rem;
  }
</style>

<main class="main-content">
  <div class="container mt-5">

    <!-- CARD PRINCIPAL -->
    <div">
      <div>

        <?php
        $fmtExp = ($c['plan_expires_at'] ?? null) ? date('d/m/Y', strtotime($c['plan_expires_at'])) : '-';
        $fmtStart = ($c['plan_started_at'] ?? null) ? date('d/m/Y', strtotime($c['plan_started_at'])) : '-';
        $inf = '∞';
        $limInv = $plan['invoice_limit_month'] === null ? $inf : (int)$plan['invoice_limit_month'];
        $limUsers = $plan['user_limit'] === null ? $inf : (int)$plan['user_limit'];
        $limRh = $plan['rh_employee_limit'] === null ? $inf : (int)$plan['rh_employee_limit'];
        // $limStock = $plan['stock_item_limit'] === null ? $inf : (int)$plan['stock_item_limit'];
        ?>

        <!-- HEADER INFO PLAN -->
        <div class="bg-white p-4 shadow-md rounded border-0 d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
          <div>
            <h5 class="mb-1 fw-bold fs-6 bg-gradient-to-r from-blue-500 to-blue-700 text-transparent bg-clip-text">
              Plano <?= htmlspecialchars($plan['name']) ?>
            </h5>

            <div class="text-muted d-flex align-items-center gap-1" style="font-size:0.9rem;">
              <div>Início: <strong><?= $fmtStart ?></strong></div> <i class="bi bi-dot text-muted fs-2 opacity-25"></i>
              <div>Vencimento: <strong><?= $fmtExp ?></strong></div> <i class="bi bi-dot text-muted fs-2 opacity-25"></i>
              <div>
                <?php if ($daysLeft !== null): ?>
                  <span class="badge-days ms-2">
                    <?= (int)$daysLeft ?> dias restantes
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button class="btn-sm btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPlans">
              Alterar plano
            </button>
            <button class="btn-sm btn btn-outline-success" id="btnRenew">
              Renovar
            </button>
          </div>
        </div>

        <!-- USO + LIMITES -->
        <div class="row g-3">

          <!-- USO -->
          <div class="col-md-12">
            <div class="soft-box h-100">
              <div class="d-flex">
                <h6 class="mb-3 fw-bold h-title" style="margin-left: 10px;"><i class="bi bi-bar-chart"></i> Uso do mês (<?= htmlspecialchars($usage['ym']) ?>)</h6>
              </div>

              <?php

              $invoicePct = ($limInv === '∞' || empty($limInv))
                ? 0
                : min(100, (($usage['invoice_count'] ?? 0) / $limInv) * 100);

              $userPct = ($limUsers === '∞' || empty($limUsers))
                ? 0
                : min(100, (($usage['user_count'] ?? 0) / $limUsers) * 100);

              $rhPct = ($limRh === '∞' || empty($limRh))
                ? 0
                : min(100, (($usage['employee_count'] ?? 0) / $limRh) * 100);
              ?>

              <div class="d-flex col-md-12 justify-content-between">
                <!-- Faturas -->
                <div class="mb-3 col-6 p-2">
                  <div class="d-flex justify-content-between">
                    <span>Faturas</span>
                    <strong><?= (int)$usage['invoice_count'] ?> / <?= $limInv ?></strong>
                  </div>
                  <div class="progress mt-1">
                    <div class="progress-bar" style="width: <?= $invoicePct ?>%"></div>
                  </div>
                </div>

                <!-- Utilizadores -->
                <div class="mb-3 col-6 p-2">
                  <div class="d-flex justify-content-between">
                    <span>Utilizadores</span>
                    <strong><?= (int)$usage['user_count'] ?> / <?= $limUsers ?></strong>
                  </div>
                  <div class="progress mt-1">
                    <div class="progress-bar" style="width: <?= $userPct ?>%"></div>
                  </div>
                </div>
              </div>

              <div class="d-flex col-md-12 justify-content-between">
                <!-- RH -->
                <div class="mb-3 col-md-6 p-2">
                  <div class="d-flex justify-content-between">
                    <span>RH</span>
                    <strong><?= (int)$usage['employee_count'] ?> / <?= $limRh ?></strong>
                  </div>
                  <div class="progress mt-1">
                    <div class="progress-bar" style="width: <?= $rhPct ?>%"></div>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>

        <!-- HISTÓRICO -->
        <hr class="my-4">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="mb-0 h-title mb-2"><i class="bi bi-clock-history"></i> Histórico de pagamentos</h5>

          <button class="btn btn-outline-secondary btn-sm" id="btnSyncOrders">
            <i class="bi bi-arrow-repeat"></i> Atualizar status
          </button>
        </div>

        <small class="text-muted d-block mb-2">
          Últimas 30 transações do sistema
        </small>

        <div class="table-responsive">
          <table class="table" id="tblOrders">
            <thead>
              <tr>
                <th>#</th>
                <th>Plano</th>
                <th>Método</th>
                <th>Referência</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Transação</th>
                <th>Data</th>
                <th>Pago em</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="9" class="text-muted">Carregando...</td>
              </tr>
            </tbody>
          </table>
          <div class="d-flex justify-content-between align-items-center mt-3">

            <!-- INFO -->
            <small class="text-muted" id="ordersInfo">
              Página 1
            </small>

            <!-- PAGINATION -->
            <nav>
              <ul class="pagination pagination-sm mb-0" id="ordersPagination"></ul>
            </nav>

          </div>

        </div>

      </div>
  </div>
  </div>

  <!-- MODAL: Alterar plano -->
  <div class="modal fade" id="modalPlans" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Escolha um plano</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <?php foreach ($plans as $code => $p): ?>
              <div class="col-md-4">
                <div class="border rounded-4 p-3 h-100 d-flex flex-column <?= $code === $planCode ? 'border-primary border-2' : '' ?>">
                  <h6 class="fw-bold"><?= htmlspecialchars($p['name']) ?></h6>
                  <div class="fs-5 fw-bold mb-2"><?= $p['price'] ?> Kz</div>
                  <ul class="small text-muted flex-grow-1 ps-3 mb-3">
                    <li>Faturas/mês: <?= $p['invoice_limit_month'] ?? '∞' ?></li>
                    <li>Utilizadores: <?= $p['user_limit'] ?? '∞' ?></li>
                    <li>RH: <?= $p['rh_employee_limit'] ?? '∞' ?></li>
                    <!-- <li>Stock: <?= $p['stock_item_limit'] ?? '∞' ?></li> -->
                  </ul>
                  <?php if ($code === $planCode): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>Plano atual</button>
                  <?php else: ?>
                    <button class="btn btn-primary btn-sm btnChoosePlan" data-code="<?= $code ?>">
                      Selecionar
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: Método de pagamento -->
  <div class="modal fade" id="modalPayMethod" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Pagamento</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">
            Plano: <strong id="payPlanLabel"><?= htmlspecialchars($plan['name']) ?></strong>
            — <strong id="payAmountLabel"><?= number_format($plan['price'], 2, ',', '.') ?> Kz</strong>
          </p>

          <label class="form-label">Método de pagamento</label>
          <select class="form-select mb-3" id="payMethod">
            <option value="REF">Referência Multicaixa (ATM / Internet Banking)</option>
            <option value="GPO">Multicaixa Express</option>
          </select>

          <div id="boxPhone">
            <label class="form-label">Nº de telefone Multicaixa Express</label>
            <input type="tel" class="form-control" id="payPhone" placeholder="923456789">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-link text-muted" id="btnMarkPaid">Já paguei / confirmar manualmente</button>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-success" id="btnConfirmPay">Gerar cobrança</button>
        </div>
      </div>
    </div>
  </div>


</main>


<script>
  const COMPANY_ID = <?= (int)$company_id ?>;
  let SELECTED_PLAN_CODE = '<?= htmlspecialchars($planCode) ?>';

  // Se veio de um módulo bloqueado, mostra aviso de upgrade
  const params = new URLSearchParams(window.location.search);
  const up = params.get('upgrade');
  // if (up) {
  //   const label = (up === 'stock') ? 'Stock' : 'RH';
  //   setTimeout(() => {
  //     Swal.fire('Upgrade necessário', `Seu plano atual não inclui ${label}. Selecione um plano superior e realize o pagamento para liberar o acesso.`, 'info');
  //   }, 400);
  // }

  function formatDt(x) {
    if (!x) return '-';
    try {
      return new Date(String(x).replace(' ', 'T')).toLocaleString('pt-PT');
    } catch (e) {
      return String(x);
    }
  }

  function formatDue(x) {
    if (!x) return '-';
    try {
      // aceita ISO com timezone (ex: 2026-03-11T12:54:31.560507+00:00)
      const d = new Date(String(x));
      if (String(d) !== 'Invalid Date') return d.toLocaleString('pt-PT');
    } catch (e) {}
    // fallback simples
    try {
      return String(x).replace('T', ' ').replace('+00:00', '');
    } catch (e) {
      return String(x);
    }
  }

  let currentPage = 1;
  const limit = 10;
  let totalPages = 1;

  /**
   * =========================
   * RENDER ORDERS TABLE
   * =========================
   */
  function renderOrders(rows) {
    const $tb = $('#tblOrders tbody');

    if (!rows || !rows.length) {
      $tb.html('<tr><td colspan="9" class="text-muted">Nenhuma transação encontrada.</td></tr>');
      return;
    }

    let html = '';

    rows.forEach(r => {
      const st = String(r.status || '-');
      const gw = String(r.gateway_status || '-');

      // STATUS INTERNO
      let stLabel = st;
      let badge = 'secondary';

      if (st === 'paid') {
        stLabel = 'Pago';
        badge = 'success';
      } else if (st === 'pending') {
        stLabel = 'Pendente';
        badge = 'warning text-dark';
      } else if (st === 'canceled') {
        stLabel = 'Cancelado';
        badge = 'danger';
      }

      // GATEWAY STATUS
      let gwLabel = gw;
      const gwL = gw.toLowerCase();

      if (['success', 'paid', 'completed', 'approved', 'succeeded'].includes(gwL)) gwLabel = 'Sucesso';
      else if (gwL === 'pending') gwLabel = 'Pendente';
      else if (['failed', 'failure'].includes(gwL)) gwLabel = 'Falhou';
      else if (['canceled', 'cancelled'].includes(gwL)) gwLabel = 'Cancelado';

      const gwTxt = (gw && gw !== '-') ? ` <span class="text-muted">(${gwLabel})</span>` : '';

      // REFERÊNCIA
      let refTxt = '-';

      try {
        const pr = r.payment_ref ? JSON.parse(r.payment_ref) : null;

        if (pr && (pr.entity || pr.referenceNumber)) {
          const ent = pr.entity || '';
          const ref = pr.referenceNumber || '';
          const due = pr.dueDate ? formatDue(pr.dueDate) : '';

          refTxt = `<span style="font-family:monospace">${ent} / ${ref}</span>`;

          if (due && due !== '-') {
            refTxt += `<div class="text-muted" style="font-size:0.75rem">Vence: ${due}</div>`;
          }
        }
      } catch (e) {}

      html += `
      <tr>
        <td>${r.id}</td>
        <td style="font-size:0.8rem;">${r.plan_code || '-'}</td>
        <td>${r.payment_method || '-'}</td>
        <td>${refTxt}</td>
        <td>${Number(r.amount || 0).toFixed(2)} AOA</td>
        <td><span class="badge bg-${badge}">${stLabel}</span>${gwTxt}</td>
        <td style="font-family:monospace; font-size:0.85rem;">${r.merchant_transaction_id || '-'}</td>
        <td>${formatDt(r.created_at)}</td>
        <td>${formatDt(r.paid_at)}</td>
      </tr>
    `;
    });

    $tb.html(html);
  }

  /**
   * =========================
   * LOAD ORDERS
   * =========================
   */
  function loadOrders(page = 1) {
    page = parseInt(page);
    if (isNaN(page) || page < 1) page = 1;

    currentPage = page;

    $('#tblOrders tbody').html(`
    <tr>
      <td colspan="9" class="text-muted">Carregando...</td>
    </tr>
  `);

    return $.getJSON('subscription/ajax/list_orders.php', {
        company_id: COMPANY_ID,
        page: currentPage,
        limit: limit
      })
      .done(resp => {
        if (!resp || !resp.success) {
          $('#tblOrders tbody').html(`
        <tr><td colspan="9" class="text-danger">Erro ao carregar dados</td></tr>
      `);
          return;
        }

        renderOrders(resp.orders || []);

        totalPages = parseInt(resp.total_pages || 1);

        renderPagination(resp.total || 0);
      })
      .fail(() => {
        $('#tblOrders tbody').html(`
      <tr><td colspan="9" class="text-danger">Falha na comunicação com o servidor</td></tr>
    `);
      });
  }

  /**
   * =========================
   * PAGINATION RENDER
   * =========================
   */
  function renderPagination(totalItems = 0) {
    const $pg = $('#ordersPagination');
    $pg.empty();

    $('#ordersInfo').text(
      `Página ${currentPage} de ${totalPages} • ${totalItems} registos`
    );

    const prev = currentPage - 1;
    const next = currentPage + 1;

    // PREV
    $pg.append(`
    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${prev}">‹</a>
    </li>
  `);

    // PAGES (janela inteligente)
    for (let i = 1; i <= totalPages; i++) {
      if (i === currentPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
        $pg.append(`
        <li class="page-item ${i === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" data-page="${i}">${i}</a>
        </li>
      `);
      }
    }

    // NEXT
    $pg.append(`
    <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
      <a class="page-link" href="#" data-page="${next}">›</a>
    </li>
  `);
  }

  /**
   * =========================
   * CLICK PAGINATION
   * =========================
   */
  $(document).on('click', '#ordersPagination a', function(e) {
    e.preventDefault();

    const $item = $(this).closest('.page-item');

    if ($item.hasClass('disabled')) return;

    const page = parseInt($(this).data('page'));

    if (!isNaN(page)) {
      loadOrders(page);
    }
  });

  /**
   * =========================
   * INIT
   * =========================
   */
  $(document).ready(function() {
    loadOrders(1);
  });


  function syncOrders() {
    return $.post('subscription/ajax/sync_orders.php', {
        company_id: COMPANY_ID
      }, null, 'json')
      .done(resp => {
        if (resp && resp.success) {
          if ((resp.paid || 0) > 0) {
            Swal.fire('Pagamento aprovado', 'Encontramos pagamento(s) aprovado(s). O plano foi creditado e os módulos/limites foram liberados.', 'success')
              .then(() => location.reload());
          }
        }
      });
  }

  $('#btnSyncOrders').on('click', function() {
    $(this).prop('disabled', true).text('Atualizando...');
    syncOrders().always(() => {
      $('#btnSyncOrders').prop('disabled', false).text('Atualizar status');
      loadOrders();
    });
  });

  // Ao abrir a página: carrega histórico primeiro (rápido), depois sincroniza em background.
  loadOrders();
  syncOrders().always(loadOrders);

  // sincronização periódica (a cada 3 min) enquanto estiver na página
  setInterval(() => {
    syncOrders().always(loadOrders);
  }, 180000);

  const payModal = new bootstrap.Modal(document.getElementById('modalPayMethod'));

  $('#payMethod').on('change', function() {
    const m = $(this).val();
    $('#boxPhone').toggle(m === 'GPO');
  });

  $('#btnRenew').on('click', function() {
    // Renovar plano atual (ou o selecionado, se tiver vindo de Alterar plano)
    $('#payMethod').val('REF').trigger('change');
    $('#payPhone').val('');
    payModal.show();
  });

  $('#btnConfirmPay').on('click', function() {
    const method = $('#payMethod').val();
    const phone = $('#payPhone').val();

    $(this).prop('disabled', true).text('Gerando...');

    $.post('subscription/ajax/create_charge.php', {
      company_id: COMPANY_ID,
      method,
      phone,
      plan_code: SELECTED_PLAN_CODE
    }, function(resp) {
      $('#btnConfirmPay').prop('disabled', false).text('Gerar cobrança');

      if (resp.success) {
        payModal.hide();

        // depois de gerar cobrança, faz sync + recarrega histórico (para aparecer imediatamente)
        syncOrders().always(loadOrders);

        let extra = '';
        if ((resp.method || '') === 'REF' && resp.ref && resp.ref.entity && resp.ref.referenceNumber) {
          extra = '<hr><div class="text-start">' +
            '<div><b>Entidade:</b> ' + resp.ref.entity + '</div>' +
            '<div><b>Referência:</b> ' + resp.ref.referenceNumber + '</div>' +
            (resp.ref.dueDate ? '<div><b>Vencimento:</b> ' + formatDue(resp.ref.dueDate) + '</div>' : '') +
            '</div>';
        }

        const details = JSON.stringify(resp.charge || {}, null, 2);
        Swal.fire({
          title: 'Cobrança gerada',
          html: '<div class="text-start"><b>Plano:</b> ' + (SELECTED_PLAN_CODE || '-') + '<br><b>Transação:</b> ' + (resp.merchantTransactionId || '-') + '<br><b>Método:</b> ' + (resp.method || '-') + '</div>' +
            extra +
            '<details class="mt-2"><summary>Detalhes técnicos</summary>' +
            '<pre style="text-align:left; max-height:220px; overflow:auto; background:#0b1220; color:#e5e7eb; padding:12px; border-radius:8px;">' + details + '</pre>' +
            '</details>',
          icon: 'success',
          confirmButtonText: 'Ok'
        });
      } else {
        Swal.fire('Erro', resp.message || 'Falha ao gerar cobrança.', 'error');
      }
    }, 'json').fail(function() {
      $('#btnConfirmPay').prop('disabled', false).text('Gerar cobrança');
      Swal.fire('Erro', 'Falha na requisição.', 'error');
    });
  });

  $('#btnMarkPaid').on('click', function() {
    $.post('subscription/ajax/mark_paid.php', {
      company_id: COMPANY_ID
    }, function(resp) {
      if (resp.success) {
        Swal.fire('Ok', 'Renovação confirmada. Plano estendido.', 'success').then(() => location.reload());
      } else {
        Swal.fire('Erro', resp.message || 'Falha ao confirmar.', 'error');
      }
    }, 'json');
  });

  $(document).on('click', '.btnChoosePlan', function() {
    const code = $(this).data('code');
    const name = $(this).closest('.col-md-4').find('h6').text();
    const price = $(this).closest('.col-md-4').find('.fs-5').text();

    SELECTED_PLAN_CODE = code;
    $('#payPlanLabel').text(name);
    $('#payAmountLabel').text(price);

    const plansEl = document.getElementById('modalPlans');
    const plansInst = plansEl ? bootstrap.Modal.getInstance(plansEl) : null;
    if (plansInst) plansInst.hide();

    $('#payMethod').val('REF').trigger('change');
    $('#payPhone').val('');

    setTimeout(() => payModal.show(), 250);
  });
</script>

<?php require_once '../app/views/footer.php'; ?>