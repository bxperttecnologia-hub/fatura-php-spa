<?php

declare(strict_types=1);

define('APP_ENV', 'test');
define('CRM_ORDER_SYNC_URL', 'https://crm.example.test/integrations/erp/subscription-orders');
define('CRM_ORDER_SYNC_SECRET', 'unit-test-order-sync-secret-with-more-than-32-characters');

require_once __DIR__ . '/../app/helpers/subscription_order_sync.php';

final class OrderSyncTestPdo extends PDO
{
  public array $updates = [];
  public function __construct(public array $rows) {}
  public function prepare(string $query, array $options = []): PDOStatement|false
  {
    return new OrderSyncTestStatement($this, $query);
  }
}

final class OrderSyncTestStatement extends PDOStatement
{
  private array $result = [];
  public function __construct(private OrderSyncTestPdo $pdo, private string $sql) {}
  public function execute(?array $params = null): bool
  {
    if (str_starts_with($this->sql, 'SELECT id, event_id, order_id, payload, attempts')) {
      $this->result = $this->pdo->rows;
    } else {
      $this->pdo->updates[] = ['sql' => $this->sql, 'params' => $params ?? []];
    }
    return true;
  }
  public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->result; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
  $assertions++;
  if (!$condition) throw new RuntimeException('Falha: ' . $message);
};

$assert(
  subscription_order_sync_is_confirmed_paid_order(['status' => 'paid', 'paid_at' => '2026-11-20 21:00:00']),
  'pedido pago com data de confirmação pode ser sincronizado'
);
$assert(
  !subscription_order_sync_is_confirmed_paid_order(['status' => 'paid', 'paid_at' => null]),
  'pedido sem data de confirmação financeira não pode ser sincronizado'
);
$assert(
  !subscription_order_sync_is_confirmed_paid_order(['status' => 'pending', 'paid_at' => null]),
  'pedido pendente não pode ser sincronizado'
);

$eventId = 'c0e801d3-83fd-41d9-b2d0-0183e335960a';
$pdo = new OrderSyncTestPdo([[
  'id' => 1,
  'order_id' => 42,
  'event_id' => $eventId,
  'payload' => json_encode(['order_id' => 42, 'event_type' => 'subscription_order.paid'], JSON_THROW_ON_ERROR),
  'attempts' => 0,
]]);
$transport = static function (string $url, string $body, array $auth) use ($eventId): array {
  $payload = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
  if ($url !== CRM_ORDER_SYNC_URL || $payload['event_id'] !== $eventId) {
    throw new RuntimeException('Payload ERP inválido.');
  }
  $expected = hash_hmac(
    'sha256',
    $auth['timestamp'] . '.' . $eventId . '.' . $body,
    CRM_ORDER_SYNC_SECRET
  );
  if (!hash_equals($expected, $auth['signature'])) throw new RuntimeException('Assinatura ERP inválida.');
  $responseBody = '{"accepted":true,"duplicate":false,"outcome":"accepted"}';
  $responseTimestamp = (string)time();
  return [
    'status' => 200,
    'body' => $responseBody,
    'headers' => [
      'x-bxpert-response-timestamp' => $responseTimestamp,
      'x-bxpert-response-signature' => hash_hmac(
        'sha256',
        $responseTimestamp . '.' . $responseBody,
        CRM_ORDER_SYNC_SECRET
      ),
    ],
  ];
};

$result = subscription_order_sync_flush($pdo, null, 25, $transport);
$assert($result === ['processed' => 1, 'sent' => 1, 'failed' => 0], 'pedido é entregue após confirmação CRM autenticada');
$assert(str_contains($pdo->updates[0]['sql'], 'delivered_at = UTC_TIMESTAMP()'), 'entrega fica registada no outbox ERP');

$failedPdo = new OrderSyncTestPdo([[
  'id' => 2, 'order_id' => 43, 'event_id' => $eventId, 'payload' => '{}', 'attempts' => 0,
]]);
$failed = subscription_order_sync_flush(
  $failedPdo,
  null,
  25,
  static fn() => ['status' => 503, 'body' => '{"code":"erp_company_mapping_missing"}', 'headers' => []]
);
$assert($failed['failed'] === 1 && $failed['sent'] === 0, 'falha CRM deixa o evento pendente para repetição');
$assert(str_contains($failedPdo->updates[0]['sql'], 'attempts = attempts + 1'), 'tentativas falhadas ficam registadas');
$assert(str_contains($failedPdo->updates[0]['params'][1], 'erp_company_mapping_missing'), 'motivo da falha fica visível ao administrador');

echo "OK: {$assertions} verificações de outbox pedidos ERP → CRM\n";
