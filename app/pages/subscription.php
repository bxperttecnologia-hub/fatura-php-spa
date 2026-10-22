<?php
require_once '../app/config/db.php';
require_once '../app/helpers/functions.php';
require_once '../app/helpers/authentication.php';
require_once '../app/helpers/subscription.php';

// Importante: qualquer redirect precisa acontecer ANTES de imprimir HTML.
// layout_creation.php inclui head/nav e já envia output.

$company_id = (int)($_SESSION['user']['company_id'] ?? 0);
$user_id = (int)($_SESSION['user']['id'] ?? 0);

// A troca de empresa acontece via sessão (navbar). Esta tela sempre segue a empresa ativa.
// Se alguém chegar com company_id via GET, limpamos a URL (evita confusão e cache).
if (isset($_GET['company_id'])) {
  header('Location: subscription.php');
  exit;
}

if (!$company_id) {
  die('Empresa inválida');
}

$membership = $pdo->prepare('SELECT role FROM company_has_user WHERE user_id = ? AND company_id = ? LIMIT 1');
$membership->execute([$user_id, $company_id]);
$companyRole = $membership->fetchColumn();
if (!is_string($companyRole)) {
  http_response_code(403);
  die('Acesso negado');
}
$canManageSubscription = in_array($companyRole, ['owner', 'admin'], true);

require_once '../app/views/layout_creation.php';

$c = subscription_get_company($pdo, $company_id);
$plans = subscription_plans();
$planCode = isset($c['plan_code'], $plans[$c['plan_code']]) ? $c['plan_code'] : 'BXPERT_BAZA';
$plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
$planCodes = array_keys($plans);
$currentPlanRank = array_search($planCode, $planCodes, true);
$hasUpgradePlans = count($planCodes) > $currentPlanRank + 1;
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

  .usage-limits {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
  }

  .usage-limit {
    min-width: 0;
    padding: 12px;
    border-radius: 12px;
    background: #f9fafc;
  }

  @media (max-width: 767.98px) {
    .usage-limits {
      grid-template-columns: 1fr;
    }
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

  #modalPayMethod .subscription-payment-dialog,
  #modalPlans .subscription-plans-dialog {
    left: 0;
  }

  #modalPayMethod .subscription-payment-dialog {
    max-width: 520px;
  }
</style>

<main class="main-content">
  <div class="container mt-5">

    <!-- CARD PRINCIPAL -->
    <div>
      <div>

        <?php
        $fmtExp = ($c['plan_expires_at'] ?? null) ? date('d/m/Y', strtotime($c['plan_expires_at'])) : '-';
        $fmtStart = ($c['plan_started_at'] ?? null) ? date('d/m/Y', strtotime($c['plan_started_at'])) : '-';
        $inf = '∞';
        $limInv = $plan['invoice_limit_month'] === null ? $inf : (int)$plan['invoice_limit_month'];
        $limUsers = $plan['user_limit'] === null ? $inf : (int)$plan['user_limit'];
        $limCompanies = $plan['company_limit'] === null ? $inf : (int)$plan['company_limit'];
        $limRh = $plan['rh_employee_limit'] === null ? $inf : (int)$plan['rh_employee_limit'];
        $companyCount = subscription_owned_company_count($pdo, (int)($_SESSION['user']['id'] ?? 0));
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

          <?php if ($canManageSubscription): ?>
            <div class="d-flex gap-2 flex-wrap">
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnUpdatePlan">
                <i class="bi bi-arrow-up-right-circle"></i> Atualizar plano
              </button>
              <button type="button" class="btn btn-sm btn-outline-success" id="btnRenew">
                <i class="bi bi-arrow-repeat"></i> Renovar subscrição
              </button>
            </div>
          <?php endif; ?>
        </div>

        <!-- USO + LIMITES -->
        <div class="row g-3">

          <!-- USO -->
          <div class="col-md-12">
            <div class="soft-box h-100">
              <h6 class="mb-3 fw-bold h-title"><i class="bi bi-bar-chart"></i> Uso do mês (<?= htmlspecialchars($usage['ym']) ?>)</h6>

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

              $companyPct = ($limCompanies === '∞' || empty($limCompanies))
                ? 0
                : min(100, ($companyCount / $limCompanies) * 100);
              ?>

              <div class="usage-limits">
                <div class="usage-limit">
                  <div class="d-flex justify-content-between gap-2">
                    <span>Facturas</span>
                    <strong><?= (int)$usage['invoice_count'] ?> / <?= $limInv ?></strong>
                  </div>
                  <div class="progress mt-2">
                    <div class="progress-bar" style="width: <?= $invoicePct ?>%"></div>
                  </div>
                </div>
                <div class="usage-limit">
                  <div class="d-flex justify-content-between gap-2">
                    <span>Utilizadores</span>
                    <strong><?= (int)$usage['user_count'] ?> / <?= $limUsers ?></strong>
                  </div>
                  <div class="progress mt-2">
                    <div class="progress-bar" style="width: <?= $userPct ?>%"></div>
                  </div>
                </div>
                <div class="usage-limit">
                  <div class="d-flex justify-content-between gap-2">
                    <span>RH</span>
                    <strong><?= (int)$usage['employee_count'] ?> / <?= $limRh ?></strong>
                  </div>
                  <div class="progress mt-2">
                    <div class="progress-bar" style="width: <?= $rhPct ?>%"></div>
                  </div>
                </div>
                <div class="usage-limit">
                  <div class="d-flex justify-content-between gap-2">
                    <span>Empresas</span>
                    <strong><?= $companyCount ?> / <?= $limCompanies ?></strong>
                  </div>
                  <div class="progress mt-2">
                    <div class="progress-bar" style="width: <?= $companyPct ?>%"></div>
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

          <?php if ($canManageSubscription): ?>
            <button class="btn btn-outline-secondary btn-sm" id="btnSyncOrders">
              <i class="bi bi-arrow-repeat"></i> Atualizar status
            </button>
          <?php endif; ?>
        </div>

        <small class="text-muted d-block mb-2">
          Histórico de cobranças de subscrição
        </small>

        <div class="table-responsive">
          <table class="table bx-list-table" id="tblOrders">
            <thead>
              <tr>
                <th>#</th>
                <th>Plano</th>
                <th>Operação</th>
                <th>Método</th>
                <th>Referência</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Status AppyPay</th>
                <th>Transação</th>
                <th>Acção</th>
                <th>Data</th>
                <th>Pago em</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="12" class="text-muted">Carregando...</td>
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

  <?php if ($canManageSubscription): ?>
  <!-- MODAL: Alterar plano -->
  <div class="modal fade" id="modalPlans" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered subscription-plans-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Escolha um plano</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (!$hasUpgradePlans): ?>
            <div class="alert alert-info mb-0">A empresa já está no plano mais alto disponível. Pode renovar a subscrição pelo botão “Renovar subscrição”.</div>
          <?php endif; ?>
          <div class="row g-3">
            <?php foreach ($plans as $code => $p): ?>
              <?php $planRank = array_search($code, $planCodes, true); ?>
              <div class="col-md-4">
                <div class="border rounded-4 p-3 h-100 d-flex flex-column <?= $code === $planCode ? 'border-primary border-2' : '' ?>">
                  <h6 class="fw-bold"><?= htmlspecialchars($p['name']) ?></h6>
                  <div class="fs-5 fw-bold mb-2"><?= number_format((float)$p['price'], 2, ',', '.') ?> Kz</div>
                  <small class="text-muted mb-2">por 3 meses</small>
                  <ul class="small text-muted flex-grow-1 ps-3 mb-3">
                    <li>Facturas/mês: <?= $p['invoice_limit_month'] ?? '∞' ?></li>
                    <li>Utilizadores: <?= $p['user_limit'] ?? '∞' ?></li>
                    <li>Empresas: <?= $p['company_limit'] ?? '∞' ?></li>
                    <li>RH: <?= $p['rh_employee_limit'] ?? '∞' ?></li>
                    <!-- <li>Stock: <?= $p['stock_item_limit'] ?? '∞' ?></li> -->
                  </ul>
                  <?php if ($code === $planCode): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>Plano atual</button>
                  <?php elseif ($planRank < $currentPlanRank): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>Plano inferior</button>
                  <?php else: ?>
                    <button type="button" class="btn btn-primary btn-sm btnChoosePlan" data-code="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                      Actualizar para este plano
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
    <div class="modal-dialog modal-dialog-centered subscription-payment-dialog">
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

          <div id="boxPhone" style="display:none">
            <label class="form-label">Nº de telefone Multicaixa Express</label>
            <input type="tel" class="form-control" id="payPhone" placeholder="923456789">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-success" id="btnConfirmPay">Gerar cobrança</button>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>

</main>


<script>
  const CAN_MANAGE_SUBSCRIPTION = <?= $canManageSubscription ? 'true' : 'false' ?>;
  const CURRENT_PLAN_CODE = <?= json_encode($planCode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const PLAN_NAMES = <?= json_encode(array_map(static fn(array $p): string => $p['name'], $plans), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  let SELECTED_PLAN_CODE = CURRENT_PLAN_CODE;

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

  function escapeHtml(value) {
    return String(value ?? '-').replace(/[&<>"']/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    })[char]);
  }

  let currentPage = 1;
  const limit = 10;
  let totalPages = 1;
  let syncRequest = null;
  const subscriptionCsrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function subscriptionPost(url, data) {
    return $.ajax({
      url,
      method: 'POST',
      data,
      dataType: 'json',
      headers: { 'X-CSRF-Token': subscriptionCsrfToken }
    });
  }

  /**
   * =========================
   * RENDER ORDERS TABLE
   * =========================
   */
  function renderOrders(rows) {
    const $tb = $('#tblOrders tbody');

    if (!rows || !rows.length) {
      $tb.html('<tr>      <td colspan="12" class="text-muted">Nenhuma transação encontrada.</td></tr>');
      return;
    }

    let html = '';

    rows.forEach(r => {
      const st = String(r.status || '-');
      const gw = String(r.gateway_status || '-');
      const operation = String(r.operation || 'legacy');

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

      const operationLabel = operation === 'renewal'
        ? 'Renovação'
        : operation === 'upgrade'
          ? 'Upgrade'
          : operation === 'downgrade'
            ? 'Alteração para plano inferior'
            : 'Histórico anterior';
      const reconcileAction = CAN_MANAGE_SUBSCRIPTION && Number(r.can_reconcile) === 1
        ? `<button type="button" class="btn btn-sm btn-outline-warning btnReconcileCharge" data-order-id="${escapeHtml(r.id)}">Associar ID</button>`
        : '-';

      // REFERÊNCIA
      let refTxt = '-';

      try {
        const pr = r.payment_ref ? JSON.parse(r.payment_ref) : null;

        if (pr && (pr.entity || pr.referenceNumber)) {
          const ent = pr.entity || '';
          const ref = pr.referenceNumber || '';
          const due = pr.dueDate ? formatDue(pr.dueDate) : '';

          refTxt = `<span style="font-family:monospace">${escapeHtml(ent)} / ${escapeHtml(ref)}</span>`;

          if (due && due !== '-') {
            refTxt += `<div class="text-muted" style="font-size:0.75rem">Vence: ${escapeHtml(due)}</div>`;
          }
        }
      } catch (e) {}

      html += `
      <tr>
        <td>${escapeHtml(r.id)}</td>
        <td style="font-size:0.8rem;">${escapeHtml(PLAN_NAMES[r.plan_code] || r.plan_code)}</td>
        <td>${escapeHtml(operationLabel)}</td>
        <td>${escapeHtml(r.payment_method)}</td>
        <td>${refTxt}</td>
        <td>${escapeHtml((Number(r.amount || 0) || 0).toFixed(2))} AOA</td>
        <td><span class="badge bg-${badge}">${escapeHtml(stLabel)}</span></td>
        <td>${escapeHtml(gwLabel)}</td>
        <td style="font-family:monospace; font-size:0.85rem;">${escapeHtml(r.merchant_transaction_id)}</td>
        <td>${reconcileAction}</td>
        <td>${escapeHtml(formatDt(r.created_at))}</td>
        <td>${escapeHtml(formatDt(r.paid_at))}</td>
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
      <td colspan="12" class="text-muted">Carregando...</td>
    </tr>
  `);

    return $.getJSON('subscription/ajax/list_orders.php', {
        page: currentPage,
        limit: limit
      })
      .done(resp => {
        if (!resp || !resp.success) {
          $('#tblOrders tbody').html(`
        <tr><td colspan="12" class="text-danger">Erro ao carregar dados</td></tr>
      `);
          return;
        }

        renderOrders(resp.orders || []);

        totalPages = parseInt(resp.total_pages || 1);

        renderPagination(resp.total || 0);
      })
      .fail(() => {
        $('#tblOrders tbody').html(`
      <tr><td colspan="12" class="text-danger">Falha na comunicação com o servidor</td></tr>
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

  function syncOrders() {
    if (syncRequest) return syncRequest;

    syncRequest = subscriptionPost('subscription/ajax/sync_orders.php', {})
      .done(resp => {
        if (resp && resp.success) {
          if ((resp.paid || 0) > 0) {
            Swal.fire('Pagamento aprovado', 'Encontramos pagamento(s) aprovado(s). O plano foi creditado e os módulos/limites foram liberados.', 'success')
              .then(() => location.reload());
          }
        }
      })
      .always(() => {
        syncRequest = null;
      });
    return syncRequest;
  }

  $('#btnSyncOrders').on('click', function() {
    $(this).prop('disabled', true).text('Atualizando...');
    syncOrders().always(() => {
      $('#btnSyncOrders').prop('disabled', false).text('Atualizar status');
      loadOrders();
    });
  });

  $(document).on('click', '.btnReconcileCharge', function() {
    const orderId = $(this).data('order-id');
    Swal.fire({
      title: 'Reconciliar cobrança',
      text: 'Introduza o ID da cobrança obtido no Portal AppyPay. O servidor verificará a transacção, o valor, a moeda e o estado directamente na API.',
      input: 'text',
      inputAttributes: { maxlength: 80, autocapitalize: 'off', autocorrect: 'off' },
      inputPlaceholder: 'ID da cobrança AppyPay',
      showCancelButton: true,
      confirmButtonText: 'Verificar',
      cancelButtonText: 'Cancelar',
      preConfirm: value => {
        const chargeId = String(value || '').trim();
        if (!/^[A-Za-z0-9-]{1,80}$/.test(chargeId)) {
          Swal.showValidationMessage('Informe um ID de cobrança válido.');
          return false;
        }
        return chargeId;
      }
    }).then(result => {
      if (!result.isConfirmed) return;
      subscriptionPost('subscription/ajax/reconcile_unknown.php', {
        order_id: orderId,
        charge_id: result.value
      }).done(resp => {
        if (resp && resp.success) {
          Swal.fire(
            resp.paid ? 'Pagamento confirmado' : 'Cobrança associada',
            resp.message || 'O estado foi actualizado.',
            resp.paid ? 'success' : 'info'
          ).then(() => {
            if (resp.paid) location.reload();
            else loadOrders();
          });
        } else {
          Swal.fire('Não foi possível reconciliar', resp?.message || 'A cobrança não corresponde ao pedido.', 'error');
        }
      }).fail(xhr => {
        Swal.fire('Não foi possível reconciliar', xhr.responseJSON?.message || 'Falha na comunicação com a AppyPay.', 'error');
      });
    });
  });

  if (CAN_MANAGE_SUBSCRIPTION) {
    // Sincroniza uma vez ao abrir; actualizações seguintes são manuais ou após uma cobrança.
    syncOrders().always(() => loadOrders(1));
  } else {
    loadOrders(1);
  }

  if (CAN_MANAGE_SUBSCRIPTION) {
  const payModal = new bootstrap.Modal(document.getElementById('modalPayMethod'));
  const plansModalElement = document.getElementById('modalPlans');
  const plansModal = plansModalElement ? bootstrap.Modal.getOrCreateInstance(plansModalElement) : null;
  let pendingPaymentPlan = null;

  $('#btnUpdatePlan').on('click', function() {
    plansModal?.show();
  });

  function openPaymentModal(planCode, planName, planPrice) {
    SELECTED_PLAN_CODE = planCode;
    $('#payPlanLabel').text(planName);
    $('#payAmountLabel').text(planPrice);
    $('#payMethod').val('REF').trigger('change');
    $('#payPhone').val('');
    payModal.show();
  }

  if (plansModalElement) {
    plansModalElement.addEventListener('hidden.bs.modal', function() {
      if (!pendingPaymentPlan) return;
      const paymentPlan = pendingPaymentPlan;
      pendingPaymentPlan = null;
      openPaymentModal(paymentPlan.code, paymentPlan.name, paymentPlan.price);
    });
  }

  $('#payMethod').on('change', function() {
    const m = $(this).val();
    $('#boxPhone').toggle(m === 'GPO');
  });

  $('#btnRenew').on('click', function() {
    const currentPrice = $('.btnChoosePlan[data-code="' + CURRENT_PLAN_CODE + '"]')
      .closest('.col-md-4').find('.fs-5').text();
    const fallbackPrice = $('#modalPlans .border-primary .fs-5').first().text() ||
      $('#payAmountLabel').text();
    openPaymentModal(
      CURRENT_PLAN_CODE,
      PLAN_NAMES[CURRENT_PLAN_CODE] || CURRENT_PLAN_CODE,
      currentPrice || fallbackPrice
    );
  });

  $('#btnConfirmPay').on('click', function() {
    const method = $('#payMethod').val();
    const phone = $('#payPhone').val();

    $(this).prop('disabled', true).text('Gerando...');

    subscriptionPost('subscription/ajax/create_charge.php', {
      method,
      phone,
      plan_code: SELECTED_PLAN_CODE
    }).done(function(resp) {
      $('#btnConfirmPay').prop('disabled', false).text('Gerar cobrança');

      if (resp.success) {
        payModal.hide();

        // depois de gerar cobrança, faz sync + recarrega histórico (para aparecer imediatamente)
        syncOrders().always(loadOrders);

        let extra = '';
        if ((resp.method || '') === 'REF' && resp.ref && resp.ref.entity && resp.ref.referenceNumber) {
          extra = '<hr><div class="text-start">' +
            '<div><b>Entidade:</b> ' + escapeHtml(resp.ref.entity) + '</div>' +
            '<div><b>Referência:</b> ' + escapeHtml(resp.ref.referenceNumber) + '</div>' +
            (resp.ref.dueDate ? '<div><b>Vencimento:</b> ' + escapeHtml(formatDue(resp.ref.dueDate)) + '</div>' : '') +
            '</div>';
        }

        Swal.fire({
          title: 'Cobrança gerada',
          html: '<div class="text-start"><b>Plano:</b> ' + escapeHtml(PLAN_NAMES[SELECTED_PLAN_CODE] || SELECTED_PLAN_CODE) + '<br><b>Transação:</b> ' + escapeHtml(resp.merchantTransactionId) + '<br><b>Método:</b> ' + escapeHtml(resp.method) + '</div>' +
            extra +
            '<div class="text-muted mt-2">A subscrição será actualizada após confirmação do pagamento pela AppyPay.</div>',
          icon: 'success',
          confirmButtonText: 'Ok'
        });
      } else {
        Swal.fire('Erro', resp.message || 'Falha ao gerar cobrança.', 'error');
      }
    }).fail(function() {
      $('#btnConfirmPay').prop('disabled', false).text('Gerar cobrança');
      Swal.fire('Erro', 'Falha na requisição.', 'error');
    });
  });

  $(document).on('click', '.btnChoosePlan', function() {
    const code = $(this).data('code');
    const name = $(this).closest('.col-md-4').find('h6').text();
    const price = $(this).closest('.col-md-4').find('.fs-5').text();

    pendingPaymentPlan = { code, name, price };
    if (plansModal) {
      plansModal.hide();
    } else {
      const paymentPlan = pendingPaymentPlan;
      pendingPaymentPlan = null;
      openPaymentModal(paymentPlan.code, paymentPlan.name, paymentPlan.price);
    }
  });
  }
</script>

<?php require_once '../app/views/footer.php'; ?>