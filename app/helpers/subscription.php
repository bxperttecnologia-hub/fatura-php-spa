<?php
// app/helpers/subscription.php

function subscription_plans(): array
{
  return [
    'BXPERT_BAZA' => [
      'name' => 'BAZA',
      'price' => 19900,
      'prices' => [3 => 19900, 6 => 35820, 12 => 67656],
      'invoice_limit_month' => 20,
      'user_limit' => 2,
      'company_limit' => 1,
      'rh_employee_limit' => 0,
      'stock_item_limit' => 0,
      'features' => [
        'rh' => false,
        'stock' => false,
        // 'daily'  -> só vê o relatório diário no dashboard
        // 'full'   -> vê evolução anual/mensal, comparativos, etc.
        'reports' => 'daily',
      ],
    ],
    'BXPERT_BASE' => [
      'name' => 'BASE',
      'price' => 26900,
      'prices' => [3 => 26900, 6 => 48420, 12 => 91464],
      'invoice_limit_month' => 50,
      'user_limit' => 3,
      'company_limit' => 2,
      'rh_employee_limit' => 5,
      'stock_item_limit' => 0,
      'features' => [
        'rh' => true,
        'stock' => false,
        'reports' => 'daily',
      ],
    ],
    'XPERT' => [
      'name' => 'XPERT',
      'price' => 38900,
      'prices' => [3 => 38900, 6 => 70020, 12 => 132264],
      'invoice_limit_month' => 150,
      'user_limit' => 4,
      'company_limit' => 5,
      'rh_employee_limit' => 15,
      'stock_item_limit' => 50,
      'features' => [
        'rh' => true,
        'stock' => true,
        'reports' => 'full',
      ],
    ],
    'ENTERPRISE' => [
      'name' => 'PRIME',
      'price' => 57900,
      'prices' => [3 => 57900, 6 => 104220, 12 => 196860],
      'invoice_limit_month' => null, // ilimitado
      'user_limit' => 10,
      'company_limit' => null, // ilimitado
      'rh_employee_limit' => 80,
      'stock_item_limit' => 300,
      'features' => [
        'rh' => true,
        'stock' => true,
        'reports' => 'full',
      ],
    ],
  ];
}

function subscription_internal_plan_code(string $planCode): ?string
{
  return [
    'BXPERT_BAZA' => 'BXPERT_BAZA',
    'BAZA' => 'BXPERT_BAZA',
    'BXPERT_BASE' => 'BXPERT_BASE',
    'BASE' => 'BXPERT_BASE',
    'XPERT' => 'XPERT',
    'ENTERPRISE' => 'ENTERPRISE',
    'PRIME' => 'ENTERPRISE',
  ][$planCode] ?? null;
}

function subscription_erp_plan_code(string $planCode): ?string
{
  $internalCode = subscription_internal_plan_code($planCode);
  if ($internalCode === null) {
    return null;
  }
  return [
    'BXPERT_BAZA' => 'BAZA',
    'BXPERT_BASE' => 'BASE',
    'XPERT' => 'XPERT',
    'ENTERPRISE' => 'PRIME',
  ][$internalCode];
}

function subscription_owned_company_count(PDO $pdo, int $user_id): int
{
  if ($user_id <= 0) return 0;

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM company_has_user WHERE user_id = ? AND role IN ('3', 'owner')");
  $stmt->execute([$user_id]);
  return (int)$stmt->fetchColumn();
}

function subscription_get_company(PDO $pdo, int $company_id): array
{
  $stmt = $pdo->prepare("SELECT id, plan_code, plan_started_at, plan_expires_at, plan_status, is_active FROM companies WHERE id = ? LIMIT 1");
  $stmt->execute([$company_id]);
  $c = $stmt->fetch(PDO::FETCH_ASSOC);
  return $c ?: [];
}

/** Pagamentos confirmados que ainda aguardam emissão ou sincronização da licença CRM. */
function subscription_orders_awaiting_crm(PDO $pdo, int $company_id): array
{
  try {
    $stmt = $pdo->prepare(
      "SELECT so.id, so.plan_code, so.period_months, so.amount, so.paid_at, so.subscription_status,
              (SELECT a.outcome
               FROM subscription_license_sync_attempts a
               WHERE a.event_id = (
                 SELECT e.event_id FROM subscription_license_sync_events e
                 WHERE e.source_order_id = so.id ORDER BY e.received_at DESC, e.id DESC LIMIT 1
               )
               ORDER BY a.attempted_at DESC, a.id DESC LIMIT 1) AS sync_result,
              (SELECT a.reason
               FROM subscription_license_sync_attempts a
               WHERE a.event_id = (
                 SELECT e.event_id FROM subscription_license_sync_events e
                 WHERE e.source_order_id = so.id ORDER BY e.received_at DESC, e.id DESC LIMIT 1
               )
               ORDER BY a.attempted_at DESC, a.id DESC LIMIT 1) AS sync_reason,
              (SELECT a.attempted_at
               FROM subscription_license_sync_attempts a
               WHERE a.event_id = (
                 SELECT e.event_id FROM subscription_license_sync_events e
                 WHERE e.source_order_id = so.id ORDER BY e.received_at DESC, e.id DESC LIMIT 1
               )
               ORDER BY a.attempted_at DESC, a.id DESC LIMIT 1) AS last_sync_at
       FROM subscription_orders so
       WHERE company_id = ? AND status = 'paid' AND paid_at IS NOT NULL
         AND subscription_status IN ('pending','awaiting_license','awaiting_sync','license_issued','sync_failed','license_rejected')
       ORDER BY paid_at ASC, so.id ASC"
    );
    $stmt->execute([$company_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    error_log('[subscription] Could not load CRM license sync status: ' . $e->getMessage());
    throw $e;
  }
}

/**
 * Devolve o código do plano contratado pela empresa.
 * Cai em 'BXPERT_BAZA' (o mais restrito) sempre que não for
 * possível determinar o plano, para nunca expor funcionalidades
 * a mais por omissão.
 */
function subscription_get_plan_code(PDO $pdo, int $company_id): string
{
  if ($company_id <= 0) {
    return 'BXPERT_BAZA';
  }

  $company = subscription_get_company($pdo, $company_id);
  $planCode = subscription_internal_plan_code((string)($company['plan_code'] ?? ''));
  return $planCode ?? 'BXPERT_BAZA';
}

/**
 * Devolve a definição completa do plano contratado pela empresa,
 * já com 'code' incluído.
 */
function subscription_get_plan(PDO $pdo, int $company_id): array
{
  $plans = subscription_plans();
  $planCode = subscription_get_plan_code($pdo, $company_id);

  $plan = $plans[$planCode];
  $plan['code'] = $planCode;

  return $plan;
}

/**
 * 'daily' (apenas relatório diário) ou 'full' (relatórios completos:
 * evolução anual, comparativos mensais, etc.)
 */
function subscription_reports_scope(array $plan): string
{
  return $plan['features']['reports'] ?? 'full';
}

function subscription_days_left(?string $expires_at): ?int
{
  if (!$expires_at) return null;
  $exp = strtotime($expires_at);
  if (!$exp) return null;
  $diff = (int)floor(($exp - time()) / 86400);
  return $diff;
}

function subscription_usage(PDO $pdo, int $company_id, ?string $ym = null): array
{
  $ym = $ym ?: date('Y-m');
  $firstDay = $ym . '-01';
  $lastDay = date('Y-m-t', strtotime($firstDay));

  // faturas do mês (issue_date)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE company_id = ? AND issue_date BETWEEN ? AND ?");
  $stmt->execute([$company_id, $firstDay, $lastDay]);
  $invoiceCount = (int)$stmt->fetchColumn();

  // usuários vinculados
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM company_has_user WHERE company_id = ?");
  $stmt->execute([$company_id]);
  $userCount = (int)$stmt->fetchColumn();

  // funcionários RH
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'ativo'");
  $stmt->execute([$company_id]);
  $employeeCount = (int)$stmt->fetchColumn();

  // itens de stock (total em todos os stocks)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_items si JOIN stocks s ON s.id = si.stock_id WHERE s.company_id = ?");
  $stmt->execute([$company_id]);
  $stockItems = (int)$stmt->fetchColumn();

  return [
    'ym' => $ym,
    'invoice_count' => $invoiceCount,
    'user_count' => $userCount,
    'employee_count' => $employeeCount,
    'stock_item_count' => $stockItems,
  ];
}

function subscription_assert_active(PDO $pdo, int $company_id): void
{
  $c = subscription_get_company($pdo, $company_id);
  $exp = $c['plan_expires_at'] ?? null;
  if ($exp && strtotime($exp) < strtotime(date('Y-m-d'))) {
    // marca expirado (best-effort)
    $stmt = $pdo->prepare("UPDATE companies SET plan_status='expired', is_active=0 WHERE id=?");
    $stmt->execute([$company_id]);
    throw new Exception('Plano expirado. Renove para continuar emitindo documentos.');
  }
  if (($c['plan_status'] ?? null) !== 'active') {
    throw new Exception('A subscrição não está activa. Aguarde a validação da licença pelo BXPERT CRM.');
  }

  try {
    $stmt = $pdo->prepare(
      "SELECT 1
       FROM subscriptions s
       INNER JOIN subscription_license_links l ON l.subscription_id = s.id
       WHERE s.company_id = ? AND s.status = 'active'
         AND s.end_date > UTC_TIMESTAMP()
         AND l.company_id = s.company_id AND l.license_status = 'active'
         AND (l.start_date IS NULL OR l.start_date <= UTC_TIMESTAMP())
         AND (l.expires_at IS NULL OR l.expires_at > UTC_TIMESTAMP())
         AND l.plan_code = ?
       LIMIT 1"
    );
    $stmt->execute([
      $company_id,
      subscription_erp_plan_code((string)($c['plan_code'] ?? '')) ?? '',
    ]);
    if (!$stmt->fetchColumn()) {
      throw new Exception('Não existe uma licença BXPERT CRM sincronizada e válida para esta subscrição.');
    }
  } catch (PDOException $e) {
    error_log('[subscription] Could not validate CRM license for company ' . $company_id . ': ' . $e->getMessage());
    throw new RuntimeException('Não foi possível validar a licença BXPERT CRM. O acesso foi bloqueado.');
  }
}

function subscription_feature_allowed(array $plan, string $feature): bool
{
  $f = $plan['features'] ?? [];
  return (bool)($f[$feature] ?? false);
}

function subscription_require_feature(PDO $pdo, int $company_id, string $feature): void
{
  subscription_assert_active($pdo, $company_id);

  $c = subscription_get_company($pdo, $company_id);
  $planCode = subscription_internal_plan_code((string)($c['plan_code'] ?? '')) ?? 'BXPERT_BAZA';
  $plans = subscription_plans();
  $plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];

  if (!subscription_feature_allowed($plan, $feature)) {
    throw new Exception('Recurso indisponível no seu plano. Faça upgrade para acessar.');
  }
}

function subscription_check_limit(PDO $pdo, int $company_id, string $kind): void
{
  subscription_assert_active($pdo, $company_id);
  $c = subscription_get_company($pdo, $company_id);
  $planCode = subscription_internal_plan_code((string)($c['plan_code'] ?? '')) ?? 'BXPERT_BAZA';
  $plans = subscription_plans();
  $plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];
  $usage = subscription_usage($pdo, $company_id);

  if ($kind === 'invoice') {
    $limit = $plan['invoice_limit_month'];
    if ($limit !== null && $usage['invoice_count'] >= (int)$limit) {
      throw new Exception('Limite mensal de emissão de faturas atingido para o seu plano.');
    }
  }

  if ($kind === 'user') {
    $limit = $plan['user_limit'];
    if ($limit !== null && $usage['user_count'] >= (int)$limit) {
      throw new Exception('Limite de utilizadores atingido para o seu plano.');
    }
  }

  if ($kind === 'rh_employee') {
    $limit = (int)($plan['rh_employee_limit'] ?? 0);
    if ($limit > 0 && $usage['employee_count'] >= $limit) {
      throw new Exception('Limite de funcionários (RH) atingido para o seu plano.');
    }
    if ($limit === 0) {
      throw new Exception('Recurso de RH indisponível no seu plano. Faça upgrade.');
    }
  }

  if ($kind === 'stock_item') {
    $limit = (int)($plan['stock_item_limit'] ?? 0);
    if ($limit > 0 && $usage['stock_item_count'] >= $limit) {
      throw new Exception('Limite de itens de stock atingido para o seu plano.');
    }
    if ($limit === 0) {
      throw new Exception('Recurso de Stock indisponível no seu plano. Faça upgrade.');
    }
  }
}
