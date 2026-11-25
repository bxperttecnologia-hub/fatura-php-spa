<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/crm_orders_api.php';
require_once __DIR__ . '/../app/core/Permission.php';

final class CrmOrdersApiTestPdo extends PDO
{
  public array $queries = [];

  public function __construct(public array $rows) {}

  public function prepare(string $query, array $options = []): PDOStatement|false
  {
    $this->queries[] = $query;
    return new CrmOrdersApiTestStatement($this, $query);
  }

  public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
  {
    $this->queries[] = $query;
    $statement = new CrmOrdersApiTestStatement($this, $query);
    $statement->execute();
    return $statement;
  }
}

final class CrmOrdersApiTestStatement extends PDOStatement
{
  private array $rows = [];
  private int $total = 1;

  public function __construct(private CrmOrdersApiTestPdo $pdo, private string $query) {}

  public function execute(?array $params = null): bool
  {
    if (str_starts_with($this->query, 'SELECT COUNT(*)')) {
      $this->total = count($this->pdo->rows);
    } else {
      $this->rows = $this->pdo->rows;
    }
    return true;
  }

  public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
  {
    return array_shift($this->rows) ?: false;
  }

  public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
  {
    return $this->rows;
  }

  public function fetchColumn(int $column = 0): mixed
  {
    return $this->total;
  }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
  $assertions++;
  if (!$condition) throw new RuntimeException('Falha: ' . $message);
};

$secret = 'unit-test-order-sync-secret-with-more-than-32-characters';
$row = [
  'id' => '42',
  'erp_company_id' => '5',
  'company_name' => 'Empresa',
  'registration_number' => '5000000000',
  'currency' => 'AOA',
  'customer_name' => 'Cliente',
  'customer_email' => 'email-invalido',
  'plan_code' => 'XPERT',
  'plan_name' => 'Xpert',
  'operation' => 'new',
  'period_months' => '3',
  'amount' => '25900.00',
  'payment_status' => 'pending',
  'subscription_status' => 'awaiting_license',
  'created_at' => '2026-01-10 09:00:00',
  'paid_at' => null,
  'license_issued_at' => null,
  'crm_subscription_id' => null,
];
$pdo = new CrmOrdersApiTestPdo([$row]);
$payload = json_encode(['action' => 'list', 'page' => 1, 'limit' => 25, 'payment_status' => 'pending'], JSON_THROW_ON_ERROR);
$timestamp = (string)time();
$headers = [
  'X-BXPERT-Timestamp' => $timestamp,
  'X-BXPERT-Signature' => hash_hmac('sha256', $timestamp . '.' . $payload, $secret),
];

$result = crm_orders_api_handle($pdo, $payload, $headers, $secret);
$assert($result['status'] === 200, 'pedido autenticado é consultado');
$assert($result['body']['orders'][0]['id'] === 42, 'IDs são normalizados como inteiros');
$assert($result['body']['orders'][0]['customer_email'] === null, 'email inválido não contamina a resposta API');
$assert(str_contains($pdo->queries[0], 'so.status = ?'), 'filtro financeiro usa o estado real do pedido');

$planPdo = new CrmOrdersApiTestPdo([[
  'name' => 'Plano ERP', 'legacy_code' => 'XPERT', 'duration_months' => '3', 'price' => '38900.00',
]]);
$plansPayload = json_encode(['action' => 'plans'], JSON_THROW_ON_ERROR);
$plansSignature = hash_hmac('sha256', $timestamp . '.' . $plansPayload, $secret);
$planResult = crm_orders_api_handle($planPdo, $plansPayload, [
  'X-BXPERT-Timestamp' => $timestamp, 'X-BXPERT-Signature' => $plansSignature,
], $secret);
$assert($planResult['status'] === 200, 'catálogo ERP autenticado é consultado');
$assert($planResult['body']['erp_plans'][0]['duration_months'] === 3, 'duração ERP é normalizada');
$assert(str_contains($planPdo->queries[0], 'FROM plans'), 'catálogo não consulta por nome nem por ID');

$badAuth = crm_orders_api_handle($pdo, $payload, [], $secret);
$assert($badAuth['status'] === 401, 'pedido sem HMAC válido é recusado');

$invalidPage = json_encode(['action' => 'list', 'page' => 1, 'limit' => 1000], JSON_THROW_ON_ERROR);
$invalidTimestamp = (string)time();
$invalidHeaders = [
  'X-BXPERT-Timestamp' => $invalidTimestamp,
  'X-BXPERT-Signature' => hash_hmac('sha256', $invalidTimestamp . '.' . $invalidPage, $secret),
];
$badPagination = crm_orders_api_handle($pdo, $invalidPage, $invalidHeaders, $secret);
$assert($badPagination['status'] === 400, 'paginação fora do limite é recusada');

$_SERVER['SCRIPT_NAME'] = '/subscription/webhook/crm-orders.php';
$assert(Permission::requestModule() === null, 'endpoint de integração não exige sessão ERP; a autenticação é HMAC');

echo "OK: {$assertions} verificações da API autenticada de pedidos ERP\n";
