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
$planRanks = array_flip(array_keys($plans));
$currentRank = $planRanks[$planCode] ?? 0;

// Planos inferiores já pagos que esperam o fim do plano actual ("por activar"),
// com a data em que cada um começa (um a seguir ao outro).
$scheduled = subscription_scheduled_plans($pdo, $company_id);
$nextStartTs = !empty($c['plan_expires_at'])
  ? max(strtotime(date('Y-m-d')), strtotime($c['plan_expires_at'] . ' +1 day'))
  : strtotime(date('Y-m-d'));
foreach ($scheduled as &$sp) {
  $sp['starts_at'] = date('Y-m-d', $nextStartTs);
  $nextStartTs = strtotime($sp['starts_at'] . ' +' . max(1, (int)$sp['period_months']) . ' months');
  $sp['ends_at'] = date('Y-m-d', $nextStartTs);
}
unset($sp);
$nextStartLabel = date('d/m/Y', $nextStartTs);

// Token CSRF exigido pelos endpoints de pagamento (cabeçalho X-CSRF-Token).
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

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
    <div>
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

        <?php if ($scheduled): ?>
          <!-- PLANOS POR ATIVAR -->
          <hr class="my-4">
          <h5 class="mb-1 h-title"><i class="bi bi-hourglass-split"></i> Planos por ativar</h5>
          <small class="text-muted d-block mb-3">Já pagos. Cada um começa automaticamente quando terminar o anterior.</small>
          <div class="row g-3">
            <?php foreach ($scheduled as $sp): $spPlan = $plans[$sp['plan_code']] ?? ['name' => $sp['plan_code']]; ?>
              <div class="col-md-4">
                <div class="border rounded-4 p-3 h-100">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($spPlan['name']) ?></h6>
                    <span class="badge bg-info text-dark">Por ativar</span>
                  </div>
                  <div class="small text-muted">
                    Início: <strong><?= date('d/m/Y', strtotime($sp['starts_at'])) ?></strong><br>
                    Vencimento: <strong><?= date('d/m/Y', strtotime($sp['ends_at'])) ?></strong><br>
                    Pago: <?= number_format((float)$sp['amount'], 2, ',', '.') ?> Kz
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

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
                  <div class="fs-5 fw-bold mb-2" data-price="<?= (float)$p['price'] ?>"><?= number_format((float)$p['price'], 0, ',', '.') ?> Kz</div>
                  <ul class="small text-muted flex-grow-1 ps-3 mb-3">
                    <li>Faturas/mês: <?= $p['invoice_limit_month'] ?? '∞' ?></li>
                    <li>Utilizadores: <?= $p['user_limit'] ?? '∞' ?></li>
                    <li>RH: <?= $p['rh_employee_limit'] ?? '∞' ?></li>
                    <!-- <li>Stock: <?= $p['stock_item_limit'] ?? '∞' ?></li> -->
                  </ul>
                  <?php if ($code === $planCode): ?>
                    <button class="btn btn-outline-secondary btn-sm" disabled>Plano atual</button>
                  <?php elseif (($planRanks[$code] ?? 0) < $currentRank): ?>
                    <button class="btn btn-outline-primary btn-sm btnChoosePlan" data-code="<?= $code ?>" data-starts="<?= $nextStartLabel ?>">
                      Selecionar
                    </button>
                    <small class="text-muted d-block mt-2" style="font-size:.75rem">
                      Plano inferior: fica <b>por ativar</b> e começa a <?= $nextStartLabel ?>, quando terminar o plano atual.
                    </small>
                  <?php else: ?>
                    <button class="btn btn-primary btn-sm btnChoosePlan" data-code="<?= $code ?>">
                      Selecionar
                    </button>
                    <small class="text-muted d-block mt-2" style="font-size:.75rem">Começa assim que o pagamento for confirmado.</small>
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
          <div class="alert alert-info py-2 small" id="payScheduledNote" style="display:none"></div>

          <label class="form-label">Escolha o método de pagamento</label>
          <div class="d-grid gap-2 mb-3" id="payMethodList">
            <label class="border rounded-3 p-3 d-flex gap-2 align-items-start" style="cursor:pointer">
              <input class="form-check-input mt-1" type="radio" name="payMethod" value="GPO" checked>
              <span>
                <strong>Multicaixa Express</strong>
                <small class="d-block text-muted">Recebe o pedido na app Multicaixa Express do seu telemóvel e aprova lá.</small>
              </span>
            </label>
            <label class="border rounded-3 p-3 d-flex gap-2 align-items-start" style="cursor:pointer">
              <input class="form-check-input mt-1" type="radio" name="payMethod" value="REF">
              <span>
                <strong>Pagamento por referência</strong>
                <small class="d-block text-muted">Recebe entidade e referência para pagar no ATM, Multicaixa Express ou Internet Banking.</small>
              </span>
            </label>
          </div>

          <div id="boxPhone">
            <label class="form-label">Nº de telefone Multicaixa Express</label>
            <input type="tel" class="form-control" id="payPhone" placeholder="923456789" maxlength="16" autocomplete="tel">
            <small class="text-muted">Número ligado à sua conta Multicaixa Express.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-success" id="btnConfirmPay">Continuar</button>
        </div>
      </div>
    </div>
  </div>


</main>


<script>
  const COMPANY_ID = <?= (int)$company_id ?>;
  const CSRF_TOKEN = <?= json_encode((string)$_SESSION['csrf']) ?>;
  const CURRENT_PLAN_CODE = <?= json_encode($planCode) ?>;
  const CURRENT_PLAN_NAME = <?= json_encode($plan['name']) ?>;
  const CURRENT_PLAN_PRICE = <?= (float)$plan['price'] ?>;
  let SELECTED_PLAN_CODE = CURRENT_PLAN_CODE;

  $.ajaxSetup({
    headers: {
      'X-CSRF-Token': CSRF_TOKEN
    }
  });

  function esc(x) {
    return String(x ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    } [c]));
  }

  function fmtKz(v) {
    return Number(v || 0).toLocaleString('pt-PT', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }) + ' Kz';
  }

  function ajaxMessage(xhr, fallback) {
    return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback;
  }

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
      const d = new Date(String(x));
      if (String(d) !== 'Invalid Date') return d.toLocaleString('pt-PT');
    } catch (e) {}
    return String(x).replace('T', ' ').replace('+00:00', '');
  }

  let currentPage = 1;
  const limit = 10;
  let totalPages = 1;

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

      let stLabel = st;
      let badge = 'secondary';
      if (st === 'paid' && Number(r.awaiting_activation) === 1) {
        stLabel = 'Pago · por ativar';
        badge = 'info text-dark';
      } else if (st === 'paid') {
        stLabel = 'Pago';
        badge = 'success';
      } else if (st === 'pending') {
        stLabel = 'Pendente';
        badge = 'warning text-dark';
      } else if (st === 'canceled') {
        stLabel = 'Cancelado';
        badge = 'danger';
      }

      let gwLabel = gw;
      const gwL = gw.toLowerCase();
      if (['success', 'paid', 'completed', 'approved', 'succeeded'].includes(gwL)) gwLabel = 'Sucesso';
      else if (gwL === 'pending') gwLabel = 'Pendente';
      else if (['failed', 'failure'].includes(gwL)) gwLabel = 'Falhou';
      else if (['canceled', 'cancelled'].includes(gwL)) gwLabel = 'Cancelado';
      else if (gwL === 'unknown') gwLabel = 'A verificar';
      else if (gwL === 'canceled_by_user') gwLabel = 'Cancelado pelo utilizador';

      const gwTxt = (gw && gw !== '-') ? ` <span class="text-muted">(${esc(gwLabel)})</span>` : '';

      let refTxt = '-';
      try {
        const pr = r.payment_ref ? JSON.parse(r.payment_ref) : null;
        if (pr && (pr.entity || pr.referenceNumber)) {
          refTxt = `<span style="font-family:monospace">${esc(pr.entity)} / ${esc(pr.referenceNumber)}</span>`;
          const due = pr.dueDate ? formatDue(pr.dueDate) : '';
          if (due && due !== '-') {
            refTxt += `<div class="text-muted" style="font-size:0.75rem">Vence: ${esc(due)}</div>`;
          }
        }
      } catch (e) {}

      const reconcileBtn = Number(r.can_reconcile) === 1 ?
        `<div><button class="btn btn-link btn-sm p-0 btnReconcile" data-order="${Number(r.id)}">Associar ID</button></div>` :
        '';
      const cancelBtn = Number(r.can_cancel) === 1 ?
        `<div><button class="btn btn-link btn-sm p-0 text-danger btnCancelOrder" data-order="${Number(r.id)}">Cancelar</button></div>` :
        '';

      html += `
      <tr>
        <td>${Number(r.id)}</td>
        <td style="font-size:0.8rem;">${esc(r.plan_code || '-')}</td>
        <td>${esc(r.payment_method || '-')}</td>
        <td>${refTxt}</td>
        <td>${Number(r.amount || 0).toFixed(2)} AOA</td>
        <td><span class="badge bg-${badge}">${esc(stLabel)}</span>${gwTxt}${reconcileBtn}${cancelBtn}</td>
        <td style="font-family:monospace; font-size:0.85rem;">${esc(r.merchant_transaction_id || '-')}</td>
        <td>${esc(formatDt(r.created_at))}</td>
        <td>${esc(formatDt(r.paid_at))}</td>
      </tr>`;
    });

    $tb.html(html);
  }

  function loadOrders(page) {
    page = parseInt(page || currentPage);
    if (isNaN(page) || page < 1) page = 1;
    currentPage = page;

    return $.getJSON('subscription/ajax/list_orders.php', {
        company_id: COMPANY_ID,
        page: currentPage,
        limit: limit
      })
      .done(resp => {
        if (!resp || !resp.success) {
          $('#tblOrders tbody').html('<tr><td colspan="9" class="text-danger">Erro ao carregar dados</td></tr>');
          return;
        }
        renderOrders(resp.orders || []);
        totalPages = parseInt(resp.total_pages || 1);
        renderPagination(resp.total || 0);
      })
      .fail(() => {
        $('#tblOrders tbody').html('<tr><td colspan="9" class="text-danger">Falha na comunicação com o servidor</td></tr>');
      });
  }

  function renderPagination(totalItems) {
    const $pg = $('#ordersPagination');
    $pg.empty();
    $('#ordersInfo').text(`Página ${currentPage} de ${totalPages} • ${totalItems || 0} registos`);

    $pg.append(`<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">‹</a></li>`);
    for (let i = 1; i <= totalPages; i++) {
      if (i >= currentPage - 2 && i <= currentPage + 2) {
        $pg.append(`<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
      }
    }
    $pg.append(`<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">›</a></li>`);
  }

  $(document).on('click', '#ordersPagination a', function(e) {
    e.preventDefault();
    if ($(this).closest('.page-item').hasClass('disabled')) return;
    const page = parseInt($(this).data('page'));
    if (!isNaN(page)) loadOrders(page);
  });

  let paidNotified = false;

  function syncOrders() {
    return $.post('subscription/ajax/sync_orders.php', {
        company_id: COMPANY_ID
      }, null, 'json')
      .done(resp => {
        if (resp && resp.success && (resp.paid || 0) > 0 && !paidNotified) {
          paidNotified = true;
          Swal.fire('Pagamento confirmado', 'O pagamento foi confirmado. A subscrição foi actualizada (um plano inferior fica em "Planos por ativar").', 'success')
            .then(() => location.reload());
        }
      });
  }

  // Depois de gerar uma cobrança, consulta a AppyPay a cada 6 s (até 5 min).
  let pollTimer = null;

  function startPolling() {
    stopPolling();
    let n = 0;
    pollTimer = setInterval(() => {
      if (++n > 50) return stopPolling();
      syncOrders().always(() => loadOrders());
    }, 6000);
  }

  function stopPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
  }

  $('#btnSyncOrders').on('click', function() {
    $(this).prop('disabled', true).text('Atualizando...');
    syncOrders().always(() => {
      $('#btnSyncOrders').prop('disabled', false).text('Atualizar status');
      loadOrders();
    });
  });

  $(document).ready(function() {
    loadOrders(1);
    syncOrders().always(() => loadOrders());
    setInterval(() => syncOrders().always(() => loadOrders()), 180000);
  });

  // ---------- Pagamento ----------
  const payModal = new bootstrap.Modal(document.getElementById('modalPayMethod'));

  function selectedMethod() {
    return $('input[name="payMethod"]:checked').val() || 'GPO';
  }

  function openPayModal(planCode, planName, price, startsAt) {
    SELECTED_PLAN_CODE = planCode;
    $('#payScheduledNote')
      .html(startsAt ? 'Este plano é inferior ao atual: depois de pago fica <b>por ativar</b> e começa a <b>' + esc(startsAt) + '</b>, quando terminar o plano atual.' : '')
      .toggle(!!startsAt);
    $('#payPlanLabel').text(planName);
    $('#payAmountLabel').text(fmtKz(price));
    $('input[name="payMethod"][value="GPO"]').prop('checked', true).trigger('change');
    $('#payPhone').val('');
    $('#btnConfirmPay').prop('disabled', false).text('Continuar');
    payModal.show();
  }

  $(document).on('change', 'input[name="payMethod"]', function() {
    $('#boxPhone').toggle(selectedMethod() === 'GPO');
  });

  // Renovar = sempre o plano ACTUAL (mesmo que antes tenha aberto "Alterar plano").
  $('#btnRenew').on('click', function() {
    openPayModal(CURRENT_PLAN_CODE, CURRENT_PLAN_NAME, CURRENT_PLAN_PRICE);
  });

  $(document).on('click', '.btnChoosePlan', function() {
    const $card = $(this).closest('.col-md-4');
    const code = $(this).data('code');
    const name = $card.find('h6').text();
    const price = parseFloat($card.find('[data-price]').data('price'));
    const startsAt = $(this).data('starts') || '';

    const plansEl = document.getElementById('modalPlans');
    const plansInst = plansEl ? bootstrap.Modal.getInstance(plansEl) : null;
    if (plansInst) plansInst.hide();

    setTimeout(() => openPayModal(code, name, price, startsAt), 300);
  });

  function showChargeResult(resp) {
    if (resp.method === 'REF') {
      const ref = resp.ref || {};
      const hasRef = ref.entity && ref.referenceNumber;
      Swal.fire({
        title: hasRef ? 'Referência gerada' : 'Cobrança criada',
        icon: 'success',
        html: hasRef ?
          '<div class="text-start">' +
          '<div><b>Entidade:</b> <span style="font-family:monospace">' + esc(ref.entity) + '</span></div>' +
          '<div><b>Referência:</b> <span style="font-family:monospace">' + esc(ref.referenceNumber) + '</span></div>' +
          '<div><b>Valor:</b> ' + esc(fmtKz($('#payAmountLabel').text().replace(/[^\d,]/g, '').replace(',', '.'))) + '</div>' +
          (ref.dueDate ? '<div><b>Válida até:</b> ' + esc(formatDue(ref.dueDate)) + '</div>' : '') +
          '<hr><small>Pague no ATM, Multicaixa Express ou Internet Banking. A subscrição é actualizada automaticamente após a confirmação do pagamento.</small></div>' :
          '<div class="text-start">A referência ainda está a ser gerada. Aparecerá no histórico de pagamentos em instantes (use “Atualizar status”).</div>',
        confirmButtonText: 'Ok'
      });
    } else {
      Swal.fire({
        title: 'Aprove o pagamento no telemóvel',
        icon: 'info',
        html: '<div class="text-start">Enviámos o pedido para a app <b>Multicaixa Express</b>. Abra a app, confirme o pagamento e introduza o seu PIN.<hr><small>A subscrição é actualizada automaticamente assim que o pagamento for confirmado.</small></div>',
        confirmButtonText: 'Ok'
      });
    }
  }

  $('#btnConfirmPay').on('click', function() {
    const method = selectedMethod();
    const phone = $('#payPhone').val();
    const $btn = $(this);

    if (method === 'GPO' && !String(phone).replace(/\D/g, '').length) {
      Swal.fire('Telefone em falta', 'Indique o número associado ao Multicaixa Express.', 'warning');
      return;
    }

    $btn.prop('disabled', true).text('A processar...');

    $.post('subscription/ajax/create_charge.php', {
        company_id: COMPANY_ID,
        method,
        phone,
        plan_code: SELECTED_PLAN_CODE
      }, null, 'json')
      .done(resp => {
        $btn.prop('disabled', false).text('Continuar');
        payModal.hide();
        if (resp && resp.success) {
          loadOrders(1);
          showChargeResult(resp);
          startPolling();
        } else {
          // HTTP 202: cobrança possivelmente criada, resultado desconhecido.
          loadOrders(1);
          Swal.fire('Atenção', (resp && resp.message) || 'Não foi possível confirmar a cobrança.', 'warning');
        }
      })
      .fail(xhr => {
        $btn.prop('disabled', false).text('Continuar');
        Swal.fire('Erro', ajaxMessage(xhr, 'Falha ao gerar o pagamento.'), 'error');
        loadOrders(1);
      });
  });

  // Associar ID de cobrança a um pedido "unknown"
  $(document).on('click', '.btnCancelOrder', function() {
    const orderId = Number($(this).data('order'));
    Swal.fire({
      icon: 'warning',
      title: 'Cancelar este pagamento?',
      text: 'A referência deixa de ser usada e pode gerar uma nova. Se ela for paga na mesma, o pagamento é aceite e a subscrição é actualizada.',
      showCancelButton: true,
      confirmButtonText: 'Sim, cancelar',
      cancelButtonText: 'Voltar',
      confirmButtonColor: '#d33'
    }).then(res => {
      if (!res.isConfirmed) return;
      $.post('subscription/ajax/cancel_order.php', { order_id: orderId }, null, 'json')
        .done(r => {
          Swal.fire(r.success ? 'Cancelado' : 'Atenção', r.message || '', r.success ? 'success' : 'warning')
            .then(() => (r.paid ? location.reload() : loadOrders()));
        })
        .fail(xhr => Swal.fire('Erro', ajaxMessage(xhr, 'Não foi possível cancelar o pagamento.'), 'error')
          .then(() => loadOrders()));
    });
  });

  $(document).on('click', '.btnReconcile', function() {
    const orderId = $(this).data('order');
    Swal.fire({
      title: 'Associar ID da cobrança',
      input: 'text',
      inputLabel: 'ID da cobrança no Portal AppyPay',
      inputAttributes: {
        maxlength: 80,
        autocapitalize: 'off'
      },
      showCancelButton: true,
      confirmButtonText: 'Associar',
      cancelButtonText: 'Cancelar'
    }).then(res => {
      if (!res.isConfirmed || !res.value) return;
      $.post('subscription/ajax/reconcile_unknown.php', {
          order_id: orderId,
          charge_id: String(res.value).trim()
        }, null, 'json')
        .done(r => {
          Swal.fire(r.success ? 'Ok' : 'Atenção', r.message || '', r.success ? 'success' : 'warning')
            .then(() => (r.paid ? location.reload() : loadOrders()));
        })
        .fail(xhr => Swal.fire('Erro', ajaxMessage(xhr, 'Falha ao associar a cobrança.'), 'error'));
    });
  });
</script>