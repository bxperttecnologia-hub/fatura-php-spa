<?php
// app/helpers/subscription.php

function subscription_plans(): array
{
  return [
    'BXPERT_BAZA' => [
      'name' => 'BXPERT BAZA',
      'price' => 15900,
      'invoice_limit_month' => 15,
      'user_limit' => 2,
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
      'name' => 'BXPERT BASE',
      'price' => 17900,
      'invoice_limit_month' => 30,
      'user_limit' => 3,
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
      'price' => 23600,
      'invoice_limit_month' => 70,
      'user_limit' => 4,
      'rh_employee_limit' => 15,
      'stock_item_limit' => 50,
      'features' => [
        'rh' => true,
        'stock' => true,
        'reports' => 'full',
      ],
    ],
    'ENTERPRISE' => [
      'name' => 'ENTERPRISE',
      'price' => 28000,
      'invoice_limit_month' => null, // ilimitado
      'user_limit' => 10,
      'rh_employee_limit' => 70,
      'stock_item_limit' => 300,
      'features' => [
        'rh' => true,
        'stock' => true,
        'reports' => 'full',
      ],
    ],
  ];
}

function subscription_get_company(PDO $pdo, int $company_id): array
{
  $stmt = $pdo->prepare("SELECT id, plan_code, plan_started_at, plan_expires_at, plan_status, is_active FROM companies WHERE id = ? LIMIT 1");
  $stmt->execute([$company_id]);
  $c = $stmt->fetch(PDO::FETCH_ASSOC);
  return $c ?: [];
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
  $planCode = $company['plan_code'] ?? 'BXPERT_BAZA';

  $plans = subscription_plans();

  return isset($plans[$planCode]) ? $planCode : 'BXPERT_BAZA';
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
  $planCode = $c['plan_code'] ?? 'BXPERT_BAZA';
  $plans = subscription_plans();
  $plan = $plans[$planCode] ?? $plans['BXPERT_BAZA'];

  if (!subscription_feature_allowed($plan, $feature)) {
    throw new Exception('Recurso indisponível no seu plano. Faça upgrade para acessar.');
  }
}

function subscription_check_limit(PDO $pdo, int $company_id, string $kind): void
{
  $c = subscription_get_company($pdo, $company_id);
  $planCode = $c['plan_code'] ?? 'BXPERT_BAZA';
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
