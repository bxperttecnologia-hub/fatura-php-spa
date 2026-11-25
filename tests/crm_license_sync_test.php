<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/crm_license_sync.php';

final class LicenseSyncTestPdo extends PDO
{
  public array $state;
  public array $writes = [];
  public ?string $failOn = null;
  public int $rollbacks = 0;
  private bool $transaction = false;
  private ?array $snapshot = null;

  public function __construct(array $state)
  {
    $this->state = $state;
  }

  public function prepare(string $query, array $options = []): PDOStatement|false
  {
    return new LicenseSyncTestStatement($this, $query);
  }

  public function beginTransaction(): bool
  {
    $this->snapshot = $this->state;
    $this->transaction = true;
    return true;
  }

  public function commit(): bool
  {
    $this->transaction = false;
    $this->snapshot = null;
    return true;
  }

  public function rollBack(): bool
  {
    if ($this->snapshot !== null) $this->state = $this->snapshot;
    $this->transaction = false;
    $this->snapshot = null;
    $this->rollbacks++;
    return true;
  }

  public function inTransaction(): bool
  {
    return $this->transaction;
  }

  public function lastInsertId(?string $name = null): string|false
  {
    return '89';
  }

  public function run(string $sql, array $params): array
  {
    if ($this->failOn !== null && str_starts_with($sql, $this->failOn)) {
      throw new RuntimeException('simulated database failure');
    }
    if (str_starts_with($sql, 'SELECT GET_LOCK')) return [['lock' => 1]];
    if (str_starts_with($sql, 'SELECT RELEASE_LOCK')) return [['released' => 1]];
    if (str_starts_with($sql, 'SELECT id FROM plans WHERE legacy_code')) {
      return array_values(array_filter($this->state['plans'], static fn(array $plan): bool =>
        $plan['legacy_code'] === $params[0] && (int)$plan['duration_months'] === (int)$params[1]
      ));
    }
    if (str_starts_with($sql, 'SELECT subscription_id FROM subscription_license_links')) {
      foreach ($this->state['links'] as $link) {
        if ((int)$link['crm_subscription_id'] === (int)$params[0]
          && (int)$link['company_id'] === (int)$params[1]) return [['subscription_id' => $link['subscription_id']]];
      }
      return [];
    }
    if (str_contains($sql, 'FROM subscription_license_sync_events WHERE event_id')) {
      $event = $this->state['events'][$params[0]] ?? null;
      return $event ? [$event] : [];
    }
    if (str_contains($sql, 'FROM subscription_orders WHERE id')) {
      return (int)$params[0] === (int)$this->state['order']['id'] ? [$this->state['order']] : [];
    }
    if (str_starts_with($sql, 'SELECT 1 FROM subscription_license_links')) {
      return !empty($this->state['has_active_link']) ? [['1' => 1]] : [];
    }
    if (str_contains($sql, 'FROM subscription_license_links')) {
      foreach ($this->state['links'] as $link) {
        if ((int)$link['license_id'] === (int)$params[0]
          || (isset($params[1]) && $params[1] !== null && (int)$link['source_order_id'] === (int)$params[1])) {
          return [$link];
        }
      }
      return [];
    }
    if (str_contains($sql, 'FROM subscriptions WHERE id')) {
      return (int)$params[0] === (int)$this->state['subscription']['id']
        ? [$this->state['subscription']] : [];
    }
    if (str_contains($sql, 'FROM companies WHERE id')) {
      return (int)$params[0] === (int)$this->state['company']['id'] ? [$this->state['company']] : [];
    }
    if (str_starts_with($sql, 'SELECT license_id FROM subscription_license_links')) {
      foreach ($this->state['links'] as $link) {
        if ((int)$link['license_id'] === (int)$params[0]) return [['license_id' => $link['license_id']]];
      }
      return [];
    }

    $this->writes[] = [$sql, $params];
    if (str_starts_with($sql, 'INSERT INTO subscriptions')) {
      $this->state['subscription'] = [
        'id' => 89, 'company_id' => $params[0], 'plan_id' => $params[1],
        'status' => 'awaiting_sync', 'license_sync_version' => 0,
      ];
    } elseif (str_starts_with($sql, 'INSERT INTO subscription_license_links')) {
      [$licenseId, $subscriptionId, $crmSubscriptionId, $orderId, $companyId, $planCode, $licenseStatus, $startDate, $expiresAt, $keyHash] = $params;
      $this->state['links'][] = [
        'license_id' => $licenseId, 'subscription_id' => $subscriptionId, 'crm_subscription_id' => $crmSubscriptionId,
        'source_order_id' => $orderId,
        'company_id' => $companyId, 'plan_code' => $planCode, 'license_status' => $licenseStatus,
        'start_date' => $startDate, 'expires_at' => $expiresAt, 'license_key_hash' => $keyHash,
      ];
    } elseif (str_starts_with($sql, 'UPDATE subscription_license_links')) {
      [$subscriptionId, $crmSubscriptionId, $orderId, $companyId, $planCode, $licenseStatus, $startDate, $expiresAt, $keyHash, $licenseId] = $params;
      foreach ($this->state['links'] as &$link) {
        if ((int)$link['license_id'] === (int)$licenseId) {
          $link = array_merge($link, [
            'subscription_id' => $subscriptionId, 'crm_subscription_id' => $crmSubscriptionId,
            'source_order_id' => $orderId, 'company_id' => $companyId,
            'plan_code' => $planCode, 'license_status' => $licenseStatus, 'start_date' => $startDate,
            'expires_at' => $expiresAt, 'license_key_hash' => $keyHash,
          ]);
        }
      }
      unset($link);
    } elseif (str_starts_with($sql, 'UPDATE subscriptions SET plan_id')) {
      [$planId, $startDate, $endDate, $status, $version] = $params;
      $this->state['subscription'] = array_merge($this->state['subscription'], [
        'plan_id' => $planId, 'start_date' => $startDate, 'end_date' => $endDate,
        'status' => $status, 'license_sync_version' => $version,
      ]);
    } elseif (str_starts_with($sql, 'UPDATE subscriptions SET status')) {
      $this->state['subscription']['status'] = $params[0];
      $this->state['subscription']['license_sync_version'] = $params[1];
    } elseif (str_starts_with($sql, 'UPDATE subscriptions SET license_sync_version')) {
      $this->state['subscription']['license_sync_version'] = $params[0];
    } elseif (str_starts_with($sql, 'UPDATE companies SET plan_code')) {
      [$planCode, $startedAt, $expiresAt] = $params;
      $this->state['company'] = array_merge($this->state['company'], [
        'plan_code' => $planCode, 'plan_started_at' => $startedAt,
        'plan_expires_at' => $expiresAt, 'plan_status' => 'active',
      ]);
    } elseif (str_starts_with($sql, 'UPDATE subscription_orders SET subscription_status')) {
      $this->state['order']['subscription_status'] = str_contains($sql, "'sync_failed'") ? 'sync_failed'
        : (str_contains($sql, "'license_rejected'") ? 'license_rejected' : $params[0]);
    } elseif (str_starts_with($sql, 'UPDATE subscription_orders SET plan_id')) {
      $this->state['order']['plan_id'] = $params[0];
    } elseif (str_starts_with($sql, 'INSERT INTO subscription_license_sync_events')) {
      [$eventId, $hash, $licenseId, $orderId, $version, $outcome, $reason] = $params;
      $attempts = isset($this->state['events'][$eventId])
        ? $this->state['events'][$eventId]['attempts'] + 1 : 1;
      $this->state['events'][$eventId] = [
        'payload_hash' => $hash, 'outcome' => $outcome, 'reason' => $reason, 'attempts' => $attempts,
      ];
    } elseif (str_starts_with($sql, 'INSERT INTO subscription_license_sync_attempts')) {
      $this->state['attempts'][] = [
        'event_id' => $params[0], 'payload_hash' => $params[1], 'outcome' => $params[2], 'reason' => $params[3],
      ];
    }
    return [];
  }
}

final class LicenseSyncTestStatement extends PDOStatement
{
  private array $rows = [];

  public function __construct(private LicenseSyncTestPdo $pdo, private string $sql) {}

  public function execute(?array $params = null): bool
  {
    $this->rows = $this->pdo->run($this->sql, $params ?? []);
    return true;
  }

  public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
  {
    return array_shift($this->rows) ?? false;
  }

  public function fetchColumn(int $column = 0): mixed
  {
    $row = $this->fetch();
    return $row === false ? false : (array_values($row)[$column] ?? false);
  }

  public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
  {
    $rows = $this->rows;
    $this->rows = [];
    return $rows;
  }
}

function licenseSyncFixture(): array
{
  $now = time();
  return [
    'now' => $now,
    'event' => [
      'event_id' => '3f2b1c9e-6a4d-4e0b-9a51-0d6f3c1e7a42',
      'event_type' => 'license.activate',
      'license_id' => 7,
      'license_key' => 'BX-2026-AAAAA-BBBBB-CCCCC-DDDDDD',
      'subscription_id' => 88,
      'subscription_order_id' => 32,
      'company_id' => 5,
      'crm_company_id' => 105,
      'plan_code' => 'BASE',
      'period_months' => 3,
      'operation_type' => 'RENOVACAO',
      'start_date' => gmdate('Y-m-d\TH:i:s\Z', $now - 3600),
      'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $now + 30 * 86400),
      'license_status' => 'active',
      'sync_version' => 1,
    ],
    'state' => [
      'order' => [
        'id' => 32, 'company_id' => 5, 'subscription_id' => 88, 'plan_id' => 21,
        'plan_code' => 'BASE', 'period_months' => 3,
        'operation' => 'renewal', 'status' => 'paid', 'subscription_status' => 'awaiting_sync',
      ],
      'subscription' => ['id' => 88, 'company_id' => 5, 'plan_id' => 21, 'status' => 'awaiting_sync', 'license_sync_version' => 0],
      'plans' => [['id' => 21, 'legacy_code' => 'BASE', 'duration_months' => 3]],
      'company' => ['id' => 5, 'plan_code' => null, 'plan_expires_at' => null],
      'links' => [],
      'events' => [],
      'attempts' => [],
      'has_active_link' => false,
    ],
    'crm_license' => [
      'license_id' => 7, 'license_key' => 'BX-2026-AAAAA-BBBBB-CCCCC-DDDDDD',
      'subscription_id' => 88, 'subscription_order_id' => 32, 'company_id' => 5,
      'erp_company_id' => 5, 'crm_company_id' => 105,
      'plan_code' => 'BASE', 'period_months' => 3, 'operation_type' => 'RENOVACAO',
      'start_date' => gmdate('Y-m-d\TH:i:s\Z', $now - 3600),
      'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $now + 30 * 86400), 'license_status' => 'pending',
    ],
  ];
}

function postLicenseSync(LicenseSyncTestPdo $pdo, array $event, int $now, callable $validator): array
{
  $secret = 'license-sync-test-secret-with-at-least-32-characters';
  $body = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  $timestamp = (string)$now;
  return crm_license_sync_handle($pdo, $body, [
    'X-BXPERT-Timestamp' => $timestamp,
    'X-BXPERT-Event-Id' => $event['event_id'],
    'X-BXPERT-Signature' => hash_hmac('sha256', $timestamp . '.' . $event['event_id'] . '.' . $body, $secret),
  ], $secret, $now, $validator);
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
  $assertions++;
  if (!$condition) throw new RuntimeException('Falha: ' . $message);
};

$fixture = licenseSyncFixture();
$pdo = new LicenseSyncTestPdo($fixture['state']);
$validatorCalls = 0;
$validator = static function () use (&$validatorCalls, $fixture): array {
  $validatorCalls++;
  return $fixture['crm_license'];
};
$result = postLicenseSync($pdo, $fixture['event'], $fixture['now'], $validator);
$assert($result['status'] === 200 && $result['body']['accepted'] === true, 'licença confirmada pelo CRM é aceite');
$assert($pdo->state['subscription']['status'] === 'active', 'subscrição é activada só após validação');
$assert($pdo->state['subscription']['plan_id'] === 21, 'subscrição usa o plano ERP mapeado por código e duração');
$assert($pdo->state['company']['plan_status'] === 'active', 'empresa é actualizada após validação');
$assert($pdo->state['company']['plan_code'] === 'BXPERT_BASE', 'empresa preserva o código interno do plano validado');
$assert($pdo->state['company']['plan_started_at'] === crm_license_sync_date($fixture['event']['start_date']), 'início usa a data confirmada');
$assert($pdo->state['company']['plan_expires_at'] === crm_license_sync_date($fixture['event']['expires_at']), 'expiração usa a data confirmada');
$assert($pdo->state['order']['status'] === 'paid', 'sincronização não altera estado financeiro');
$assert($pdo->state['order']['subscription_status'] === 'active', 'pedido recebe estado operacional activo');
$assert(count($pdo->state['links']) === 1 && count($pdo->state['events']) === 1, 'associação e evento são registados');
$assert($pdo->state['attempts'][0]['outcome'] === 'accepted', 'licença CRM pendente autentica a activação remota');

$duplicate = postLicenseSync($pdo, $fixture['event'], $fixture['now'], $validator);
$assert($duplicate['body']['duplicate'] === true, 'repetição do evento é idempotente');
$assert(count($pdo->state['links']) === 1 && $validatorCalls === 1, 'repetição não cria dados nem consulta novamente o CRM');
$changedEvent = $fixture['event'];
$changedEvent['plan_code'] = 'ENTERPRISE';
$changed = postLicenseSync($pdo, $changedEvent, $fixture['now'], $validator);
$assert($changed['status'] === 409, 'event_id não pode ser reutilizado com payload diferente');

$wrongLicense = $fixture['crm_license'];
$wrongLicense['license_key'] = 'OTHER-KEY';
$wrongKeyPdo = new LicenseSyncTestPdo($fixture['state']);
$wrongKeyResult = postLicenseSync(
  $wrongKeyPdo, $fixture['event'], $fixture['now'], static fn() => $wrongLicense
);
$assert($wrongKeyResult['status'] === 422, 'chave não correspondente à licença no CRM é rejeitada');
$assert($wrongKeyResult['body']['reason'] === 'crm_license_mismatch', 'rejeição remota tem motivo explícito');

$wrongCompanyEvent = $fixture['event'];
$wrongCompanyEvent['company_id'] = 6;
$wrongCompanyPdo = new LicenseSyncTestPdo($fixture['state']);
$wrongCompanyResult = postLicenseSync(
  $wrongCompanyPdo, $wrongCompanyEvent, $fixture['now'], static fn() => $fixture['crm_license']
);
$assert($wrongCompanyResult['status'] === 422, 'empresa discordante é rejeitada');
$assert($wrongCompanyPdo->state['subscription']['status'] === 'awaiting_sync', 'empresa discordante não activa a subscrição');

$unpaidState = $fixture['state'];
$unpaidState['order']['status'] = 'pending';
$unpaidPdo = new LicenseSyncTestPdo($unpaidState);
$unpaidResult = postLicenseSync($unpaidPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($unpaidResult['body']['reason'] === 'source_order_mismatch', 'pedido não pago não activa uma licença');

$issuedFixture = licenseSyncFixture();
$issuedFixture['event']['event_id'] = '8e3f7a10-9b25-4c64-8b2a-0d7e5f1a3c42';
$issuedFixture['event']['event_type'] = 'license.issued';
$issuedFixture['event']['license_status'] = 'pending';
$issuedFixture['crm_license']['license_status'] = 'pending';
$issuedPdo = new LicenseSyncTestPdo($issuedFixture['state']);
$issuedResult = postLicenseSync(
  $issuedPdo, $issuedFixture['event'], $issuedFixture['now'], static fn() => $issuedFixture['crm_license']
);
$assert($issuedResult['body']['accepted'] === true, 'licença emitida pendente é associada');
$assert($issuedPdo->state['subscription']['status'] === 'awaiting_sync', 'emissão pendente não activa a subscrição');
$assert($issuedPdo->state['order']['subscription_status'] === 'license_issued', 'estado emissão é distinto de activo');

$renewalFixture = licenseSyncFixture();
$renewalFixture['event']['event_id'] = '8e3f7a10-9b25-4c64-8b2a-0d7e5f1a3c42';
$renewalFixture['event']['event_type'] = 'license.issued';
$renewalFixture['event']['license_status'] = 'pending';
$renewalFixture['crm_license']['license_status'] = 'pending';
$renewalFixture['state']['subscription']['status'] = 'active';
$renewalFixture['state']['company']['plan_code'] = 'BXPERT_BASE';
$renewalFixture['state']['company']['plan_status'] = 'active';
$renewalFixture['state']['has_active_link'] = true;
$renewalFixture['state']['links'][] = [
  'license_id' => 6, 'subscription_id' => 88, 'crm_subscription_id' => 88,
  'source_order_id' => 31, 'company_id' => 5, 'plan_code' => 'BASE',
  'license_status' => 'active', 'start_date' => '2026-01-01 00:00:00',
  'expires_at' => '2027-01-01 00:00:00', 'license_key_hash' => hash('sha256', 'old-key'),
];
$renewalPdo = new LicenseSyncTestPdo($renewalFixture['state']);
$renewalIssued = postLicenseSync(
  $renewalPdo, $renewalFixture['event'], $renewalFixture['now'], static fn() => $renewalFixture['crm_license']
);
$assert($renewalIssued['body']['accepted'] === true, 'licença de renovação é recebida');
$assert($renewalPdo->state['subscription']['status'] === 'active', 'licença pendente de renovação preserva a subscrição activa');
$assert($renewalPdo->state['company']['plan_status'] === 'active', 'licença pendente não desactiva o plano vigente');

$staleState = $fixture['state'];
$staleState['subscription']['license_sync_version'] = 2;
$stalePdo = new LicenseSyncTestPdo($staleState);
$staleResult = postLicenseSync($stalePdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($staleResult['body']['outcome'] === 'stale', 'evento fora de ordem é aceite sem substituir o estado mais recente');
$assert($stalePdo->state['links'] === [] && $stalePdo->state['subscription']['status'] === 'awaiting_sync', 'evento obsoleto não associa nem activa licença');

$retryPdo = new LicenseSyncTestPdo($fixture['state']);
$failure = postLicenseSync($retryPdo, $fixture['event'], $fixture['now'], static function (): never {
  throw new RuntimeException('simulated CRM timeout');
});
$assert($failure['status'] === 503, 'falha de comunicação é temporária e reportada');
$assert($retryPdo->state['order']['subscription_status'] === 'sync_failed', 'falha é visível no pedido');
$assert($retryPdo->state['attempts'][0]['outcome'] === 'failed', 'falha fica registada com motivo');
$reprocessed = postLicenseSync($retryPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($reprocessed['body']['accepted'] === true, 'evento falhado pode ser reprocessado');
$assert($retryPdo->state['subscription']['status'] === 'active', 'reprocessamento aplica a activação');

$rollbackPdo = new LicenseSyncTestPdo($fixture['state']);
$rollbackPdo->failOn = 'UPDATE subscriptions SET plan_id';
$rollback = postLicenseSync($rollbackPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($rollback['status'] === 503, 'falha durante activação é reportada');
$assert($rollbackPdo->rollbacks === 1 && $rollbackPdo->state['links'] === [], 'falha reverte associação e activação');

$unmappedState = $fixture['state'];
$unmappedState['plans'] = [];
$unmappedPdo = new LicenseSyncTestPdo($unmappedState);
$unmapped = postLicenseSync($unmappedPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($unmapped['body']['reason'] === 'plan_mapping_missing', 'plano sem correspondência ERP é rejeitado');
$assert($unmappedPdo->state['subscription']['status'] === 'awaiting_sync', 'plano não mapeado não activa a subscrição');

$ambiguousState = $fixture['state'];
$ambiguousState['plans'][] = ['id' => 22, 'legacy_code' => 'BASE', 'duration_months' => 3];
$ambiguousPdo = new LicenseSyncTestPdo($ambiguousState);
$ambiguous = postLicenseSync($ambiguousPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($ambiguous['body']['reason'] === 'plan_mapping_missing', 'mapeamento ERP ambíguo é rejeitado');

$wrongPlanState = $fixture['state'];
$wrongPlanState['order']['plan_id'] = 22;
$wrongPlanPdo = new LicenseSyncTestPdo($wrongPlanState);
$wrongPlan = postLicenseSync($wrongPlanPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($wrongPlan['body']['reason'] === 'source_order_plan_mismatch', 'pedido não pode usar outro plano ERP');

$unmappedOrderState = $fixture['state'];
$unmappedOrderState['order']['plan_id'] = null;
$unmappedOrderPdo = new LicenseSyncTestPdo($unmappedOrderState);
$unmappedOrder = postLicenseSync($unmappedOrderPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($unmappedOrder['body']['accepted'] === true, 'plano local do pedido legado pode ser resolvido sem inferir IDs CRM');
$assert($unmappedOrderPdo->state['order']['plan_id'] === 21, 'pedido legado recebe o ID ERP resolvido');

$renewalPlanState = $fixture['state'];
$renewalPlanState['plans'] = [['id' => 23, 'legacy_code' => 'BASE', 'duration_months' => 3]];
$renewalPlanState['order']['plan_id'] = 23;
$renewalPlanState['subscription']['plan_id'] = 21;
$renewalPlanPdo = new LicenseSyncTestPdo($renewalPlanState);
$renewalPlan = postLicenseSync($renewalPlanPdo, $fixture['event'], $fixture['now'], static fn() => $fixture['crm_license']);
$assert($renewalPlan['body']['accepted'] === true, 'renovação validada aplica o plano ERP associado ao novo período');
$assert($renewalPlanPdo->state['subscription']['plan_id'] === 23, 'renovação actualiza plan_id sem criar subscrição');

$manualFixture = licenseSyncFixture();
$manualFixture['event']['event_id'] = '8e3f7a10-9b25-4c64-8b2a-0d7e5f1a3c43';
$manualFixture['event']['subscription_order_id'] = null;
$manualFixture['event']['operation_type'] = 'NOVA_CONTRATACAO';
$manualFixture['event']['event_type'] = 'license.issued';
$manualFixture['event']['license_status'] = 'pending';
$manualFixture['crm_license']['subscription_order_id'] = null;
$manualFixture['crm_license']['operation_type'] = 'NOVA_CONTRATACAO';
$manualFixture['crm_license']['license_status'] = 'pending';
$manualPdo = new LicenseSyncTestPdo($manualFixture['state']);
$manualResult = postLicenseSync(
  $manualPdo, $manualFixture['event'], $manualFixture['now'], static fn() => $manualFixture['crm_license']
);
$assert($manualResult['body']['accepted'] === true, 'nova contratação API sem pedido cria subscrição ERP própria');
$assert($manualPdo->state['subscription']['id'] === 89, 'ID CRM não é reutilizado como ID da subscrição ERP');
$assert($manualPdo->state['subscription']['status'] === 'awaiting_sync', 'subscrição API aguarda validação da licença');
$assert($manualPdo->state['subscription']['plan_id'] === 21, 'subscrição manual recebe o plano ERP mapeado');

echo "OK: {$assertions} verificações de sincronização CRM/ERP\n";
