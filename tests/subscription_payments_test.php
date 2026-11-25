<?php

declare(strict_types=1);

putenv('APPYPAY_ENV=sandbox');
putenv('APPYPAY_CLIENT_ID_SANDBOX=subscription-test-client');
putenv('APPYPAY_CLIENT_SECRET_SANDBOX=subscription-test-secret');
putenv('APPYPAY_RESOURCE_SANDBOX=subscription-test-resource');
putenv('APPYPAY_PAYMENT_METHOD_REF_SANDBOX=REF_subscription-test');
putenv('APPYPAY_PAYMENT_METHOD_GPO_SANDBOX=GPO_subscription-test');
putenv('APPYPAY_API_BASE_SANDBOX=https://gwy-api-tst.appypay.co.ao/v2.0');
putenv('APPYPAY_TOKEN_URL_SANDBOX=https://login.microsoftonline.com/appypaydev.onmicrosoft.com/oauth2/token');

require_once __DIR__ . '/../app/helpers/subscription_payments.php';
require_once __DIR__ . '/../app/helpers/crm_license_sync.php';

date_default_timezone_set('UTC');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
  $assertions++;
  if (!$condition) {
    throw new RuntimeException('Falha: ' . $message);
  }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
  try {
    $callback();
  } catch (Throwable) {
    $assert(true, $message);
    return;
  }
  $assert(false, $message);
};

$schemaFailure = subscription_payment_sync_schema_failure('42S22');
$assert(
  $schemaFailure !== null
    && $schemaFailure['status'] === 503
    && str_contains($schemaFailure['message'], 'migrate_subscription_license_sync_attempts.php'),
  'esquema CRM incompleto é reportado como integração indisponível'
);
$assert(
  subscription_payment_sync_schema_failure('HY000') === null,
  'erros de base de dados não relacionados ao esquema mantêm o tratamento genérico'
);
$assert(
  subscription_payment_plan_id_from_matches([['id' => 17]]) === 17,
  'correspondência única por código e duração devolve o ID ERP local'
);
$throws(
  static fn() => subscription_payment_plan_id_from_matches([]),
  'plano ERP sem correspondência não pode criar um pedido'
);
$throws(
  static fn() => subscription_payment_plan_id_from_matches([['id' => 17], ['id' => 18]]),
  'mapeamento ERP ambíguo não pode criar um pedido'
);

$plans = subscription_plans();
$assert(subscription_erp_plan_code('BXPERT_BAZA') === 'BAZA', 'código interno BAZA resolve explicitamente para o código ERP');
$assert(subscription_erp_plan_code('BXPERT_BASE') === 'BASE', 'código interno BASE resolve explicitamente para o código ERP');
$assert(subscription_erp_plan_code('ENTERPRISE') === 'PRIME', 'código interno Enterprise resolve explicitamente para o código ERP PRIME');
$assert(subscription_internal_plan_code('PRIME') === 'ENTERPRISE', 'código ERP PRIME mantém o código interno do plano');
$canonicalQuote = subscription_payment_quote($plans, 'BAZA', 'PRIME', 12);
$assert(
  $canonicalQuote['current_plan_code'] === 'BXPERT_BAZA'
    && $canonicalQuote['plan_code'] === 'ENTERPRISE'
    && $canonicalQuote['amount'] === 196860.0,
  'códigos ERP canónicos preservam operação e preço interno aprovado'
);
$assert($plans['BXPERT_BAZA']['name'] === 'BAZA'
  && $plans['BXPERT_BAZA']['prices'] === [3 => 19900, 6 => 35820, 12 => 67656]
  && $plans['BXPERT_BAZA']['invoice_limit_month'] === 20, 'BAZA usa nome, preços e limite do catálogo aprovado');
$assert($plans['BXPERT_BASE']['name'] === 'BASE'
  && $plans['BXPERT_BASE']['prices'] === [3 => 26900, 6 => 48420, 12 => 91464]
  && $plans['BXPERT_BASE']['user_limit'] === 3, 'BASE usa preços e limite de utilizadores aprovados');
$assert($plans['XPERT']['prices'] === [3 => 38900, 6 => 70020, 12 => 132264]
  && $plans['XPERT']['user_limit'] === 4 && $plans['XPERT']['stock_item_limit'] === 50, 'XPERT usa preços aprovados e preserva acesso ao stock');
$assert($plans['ENTERPRISE']['name'] === 'PRIME'
  && $plans['ENTERPRISE']['prices'] === [3 => 57900, 6 => 104220, 12 => 196860]
  && $plans['ENTERPRISE']['rh_employee_limit'] === 80
  && $plans['ENTERPRISE']['stock_item_limit'] === 300, 'PRIME usa preços e limites aprovados');
$renewalQuote = subscription_payment_quote($plans, 'BXPERT_BAZA', '');
$assert($renewalQuote['plan_code'] === 'BXPERT_BAZA', 'renovação usa o plano actual');
$assert($renewalQuote['amount'] === 19900.0 && $renewalQuote['period_months'] === 3, 'preço e período trimestrais vêm da configuração');
$semiannualQuote = subscription_payment_quote($plans, 'BXPERT_BAZA', '', 6);
$assert($semiannualQuote['amount'] === 35820.0 && $semiannualQuote['period_months'] === 6, 'preço e período semestrais vêm da configuração');
$annualQuote = subscription_payment_quote($plans, 'BXPERT_BAZA', '', 12);
$assert($annualQuote['amount'] === 67656.0 && $annualQuote['period_months'] === 12, 'preço e período anuais vêm da configuração');
$upgradeQuote = subscription_payment_quote($plans, 'BXPERT_BAZA', 'XPERT');
$assert($upgradeQuote['operation'] === 'upgrade', 'pedido de plano superior é identificado como upgrade');
$downgradeQuote = subscription_payment_quote($plans, 'ENTERPRISE', 'BXPERT_BASE');
$assert($downgradeQuote['operation'] === 'downgrade', 'downgrade continua a aguardar o processamento e emissão pelo CRM');
$throws(
  static fn() => subscription_payment_quote($plans, 'BXPERT_BAZA', 'INVALID'),
  'plano solicitado inválido é rejeitado'
);
$throws(
  static fn() => subscription_payment_quote($plans, 'BXPERT_BAZA', '', 9),
  'período não configurado é rejeitado'
);
$invalidPricePlans = $plans;
$invalidPricePlans['BXPERT_BAZA']['prices'][3] = 0;
$throws(
  static fn() => subscription_payment_quote($invalidPricePlans, 'BXPERT_BAZA', ''),
  'preço nulo é rejeitado'
);

$assert(subscription_payment_role_can_manage('owner'), 'proprietário pode gerir a subscrição');
$assert(subscription_payment_role_can_manage('admin'), 'administrador pode gerir a subscrição');
$assert(!subscription_payment_role_can_manage('employee'), 'utilizador comum não pode gerir a subscrição');
$assert(!subscription_payment_role_can_manage('viewer'), 'visualizador não pode gerir a subscrição');
$assert(!subscription_payment_role_can_manage(false), 'ausência de associação é recusada');
$assert(subscription_payment_csrf_is_valid('same-token', 'same-token'), 'token CSRF correcto é aceite');
$assert(!subscription_payment_csrf_is_valid('expected', 'wrong'), 'token CSRF incorrecto é recusado');
$assert(!subscription_payment_csrf_is_valid('', ''), 'token CSRF ausente é recusado');

$id = subscription_payment_new_transaction_id();
$assert((bool)preg_match('/^[A-Z0-9]{15}$/', $id), 'identificador AppyPay tem 15 caracteres alfanuméricos');
$ids = array_map(static fn(): string => subscription_payment_new_transaction_id(), range(1, 20));
$assert(count(array_unique($ids)) === 20, 'identificadores gerados não colidem no lote de teste');
$referenceInfo = subscription_payment_build_payment_info('REF');
$assert(
  (bool)preg_match('/^\d{9}$/', $referenceInfo['referenceNumber']),
  'referência REF é gerada com nove dígitos'
);
$dueDate = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $referenceInfo['dueDate']);
$assert($dueDate !== false, 'data de vencimento REF usa o formato aceite pela AppyPay');
$assert(
  subscription_payment_build_payment_info('GPO', '900000000') === ['phoneNumber' => '900000000'],
  'paymentInfo GPO usa o telefone validado'
);
$throws(
  static fn() => subscription_payment_build_payment_info('INVALID'),
  'método desconhecido não gera dados de pagamento'
);
$assert(subscription_payment_gateway_charge_is_terminal(404, 'unavailable'), 'cobrança inexistente pode ser encerrada após confirmação HTTP 404');
$assert(subscription_payment_gateway_charge_is_terminal(200, 'Expired'), 'cobrança expirada deixa de bloquear uma nova tentativa');
$assert(subscription_payment_gateway_charge_is_terminal(200, 'Failed'), 'cobrança falhada deixa de bloquear uma nova tentativa');
$assert(!subscription_payment_gateway_charge_is_terminal(200, 'Pending'), 'cobrança ainda pendente continua a bloquear duplicação');
$assert(subscription_payment_order_blocks_new_charge([
  'status' => 'paid', 'paid_at' => '2026-11-25 10:00:00', 'subscription_status' => 'awaiting_license',
]), 'pagamento confirmado à espera da licença bloqueia uma nova cobrança');
$assert(subscription_payment_order_blocks_new_charge([
  'status' => 'paid', 'paid_at' => null, 'gateway_charge_id' => 'existing-charge',
  'subscription_status' => 'pending',
]), 'cobrança anterior sem timestamp mas com ID remoto bloqueia duplicação e exige reconciliação');
$assert(!subscription_payment_order_blocks_new_charge([
  'status' => 'paid', 'paid_at' => null, 'subscription_status' => 'pending',
]), 'estado paid sem confirmação financeira não é tratado como pagamento confirmado');
$assert(!subscription_payment_order_blocks_new_charge([
  'status' => 'paid', 'paid_at' => '2026-11-25 10:00:00', 'subscription_status' => 'active',
]), 'subscrição já activa permite uma nova operação comercial');

$futureRenewal = subscription_payment_expiration(
  ['plan_code' => 'BXPERT_BAZA', 'plan_expires_at' => '2026-12-31'],
  'BXPERT_BAZA',
  '2026-10-21'
);
$assert($futureRenewal['operation'] === 'renewal', 'renovação preserva a operação');
$assert($futureRenewal['started_at'] === '2026-10-21' && $futureRenewal['expires_at'] === '2027-03-31', 'renovação preserva o tempo restante');
$semiannualRenewal = subscription_payment_expiration(
  ['plan_code' => 'BXPERT_BAZA', 'plan_expires_at' => '2026-12-31'],
  'BXPERT_BAZA',
  '2026-10-21',
  6
);
$assert($semiannualRenewal['expires_at'] === '2027-06-30', 'renovação semestral preserva o tempo restante');
$expiredRenewal = subscription_payment_expiration(
  ['plan_code' => 'BXPERT_BAZA', 'plan_expires_at' => '2026-10-01'],
  'BXPERT_BAZA',
  '2026-10-21'
);
$assert($expiredRenewal['expires_at'] === '2027-01-21', 'renovação expirada começa na data actual');
$upgradeDates = subscription_payment_expiration(
  ['plan_code' => 'BXPERT_BAZA', 'plan_expires_at' => '2027-06-30'],
  'XPERT',
  '2026-10-21'
);
$assert($upgradeDates['operation'] === 'upgrade' && $upgradeDates['expires_at'] === '2027-01-21', 'upgrade inicia período novo sem creditar dias anteriores');

$order = [
  'gateway_charge_id' => 'charge-123',
  'merchant_transaction_id' => 'S0123456789ABCDE',
  'amount' => '19900.00',
  'payment_method' => 'REF',
];
$successfulCharge = [
  'code' => 200,
  'data' => [
    'id' => 'charge-123',
    'merchantTransactionId' => 'S0123456789ABCDE',
    'amount' => 19900,
    'currency' => 'AOA',
    'responseStatus' => ['successful' => true, 'status' => 'Success'],
  ],
];
$assert(subscription_payment_verify_remote_charge($successfulCharge, $order)['confirmed'], 'cobrança efectivamente confirmada é aceite');
$assert(subscription_payment_verify_remote_charge($successfulCharge, $order)['confirmed'], 'repetição de consulta mantém o mesmo resultado sem alterar a subscrição');
$getChargeShape = [
  'code' => 200,
  'data' => [
    'payment' => [
      'id' => 'charge-123',
      'merchantTransactionId' => 'S0123456789ABCDE',
      'amount' => 19900,
      'currency' => 'AOA',
      'status' => 'Success',
      'paymentMethod' => 'REF',
      'transactionEvents' => [
        ['responseStatus' => ['successful' => true]],
      ],
    ],
  ],
];
$assert(
  subscription_payment_verify_remote_charge($getChargeShape, $order)['confirmed'],
  'resposta GET com payment, método curto e transactionEvents confirma cobrança paga'
);
$pendingGetChargeShape = $getChargeShape;
$pendingGetChargeShape['data']['payment']['status'] = 'Pending';
$pendingGetChargeShape['data']['payment']['transactionEvents'][0]['responseStatus']['successful'] = false;
$assert(
  !subscription_payment_verify_remote_charge($pendingGetChargeShape, $order)['confirmed'],
  'resposta GET com estado pendente não confirma pagamento'
);
$assert(
  !subscription_payment_verify_remote_charge([
    'code' => 200,
    'data' => ['id' => 'charge-123', 'responseStatus' => ['successful' => true, 'status' => 'Success']],
  ], $order, true)['confirmed'],
  'reconciliação manual exige identificador, valor e moeda da AppyPay'
);
$assert(
  !subscription_payment_verify_remote_charge([
    'code' => 200,
    'data' => ['id' => 'charge-123', 'responseStatus' => ['successful' => false, 'status' => 'Pending']],
  ], $order)['confirmed'],
  'cobrança pendente não activa o plano'
);
$assert(
  !subscription_payment_verify_remote_charge([
    'code' => 200,
    'data' => ['id' => 'charge-123', 'responseStatus' => ['successful' => false, 'status' => 'Success']],
  ], $order)['confirmed'],
  'sucesso visual sem successful=true não activa o plano'
);
foreach ([
  ['id' => 'wrong-charge', 'merchantTransactionId' => 'S0123456789ABCDE', 'amount' => 19900, 'currency' => 'AOA'],
  ['id' => 'charge-123', 'merchantTransactionId' => 'other-transaction', 'amount' => 19900, 'currency' => 'AOA'],
  ['id' => 'charge-123', 'merchantTransactionId' => 'S0123456789ABCDE', 'amount' => 1, 'currency' => 'AOA'],
  ['id' => 'charge-123', 'merchantTransactionId' => 'S0123456789ABCDE', 'amount' => 19900, 'currency' => 'USD'],
] as $mismatch) {
  $invalidCharge = [
    'code' => 200,
    'data' => $mismatch + [
      'responseStatus' => ['successful' => true, 'status' => 'Success'],
    ],
  ];
  $assert(!subscription_payment_verify_remote_charge($invalidCharge, $order)['confirmed'], 'identidade/valor/moeda discordantes são recusados');
}
$assert(
  !subscription_payment_verify_remote_charge(['code' => 503, 'data' => []], $order)['confirmed'],
  'falha AppyPay não é considerada pagamento'
);

$assert(
  crm_license_sync_datetime('2026-11-18T10:00:00.000Z') === '2026-11-18 10:00:00',
  'data ISO do CRM é normalizada para UTC'
);
$assert(
  crm_license_sync_datetime('2026-02-31T10:00:00.000Z') === null,
  'data de licença impossível é rejeitada'
);
$assert(
  crm_license_sync_date('2026-11-18T23:00:00.000Z') === '2026-11-19',
  'datas da subscrição são guardadas no calendário local de Luanda'
);
$syncPdo = new class extends PDO {
  public function __construct() {}
};
$eventId = '3f2b1c9e-6a4d-4e0b-9a51-0d6f3c1e7a42';
$timestamp = '1700000000';
$syncBody = '{}';
$syncHeaders = [
  'X-BXPERT-Timestamp' => $timestamp,
  'X-BXPERT-Event-Id' => $eventId,
  'X-BXPERT-Signature' => hash_hmac('sha256', $timestamp . '.' . $eventId . '.' . $syncBody, 'sync-test-secret'),
];
$assert(
  crm_license_sync_handle($syncPdo, $syncBody, $syncHeaders, 'sync-test-secret', 1700000000)['status'] === 401,
  'ID do evento assinado tem de corresponder ao corpo'
);
$assert(
  crm_license_sync_handle($syncPdo, $syncBody, $syncHeaders, 'sync-test-secret', 1700000301)['status'] === 401,
  'evento CRM fora da janela temporal é rejeitado'
);
$invalidSignatureHeaders = $syncHeaders;
$invalidSignatureHeaders['X-BXPERT-Signature'] = str_repeat('0', 64);
$assert(
  crm_license_sync_handle($syncPdo, $syncBody, $invalidSignatureHeaders, 'sync-test-secret', 1700000000)['status'] === 401,
  'assinatura HMAC inválida é rejeitada antes de aceder à base de dados'
);

$cachePath = appypay_token_cache_path();
if (is_file($cachePath)) {
  unlink($cachePath);
}

try {
  $requests = [];
  appypay_set_http_transport_for_testing(
    static function (string $url, array $options) use (&$requests): array {
      $requests[] = [$url, $options];
      if ($url === APPYPAY_TOKEN_URL) {
        return [
          'body' => json_encode(['access_token' => 'mock-token', 'expires_in' => 3600]),
          'code' => 200,
          'error' => '',
        ];
      }
      return [
        'body' => json_encode([
          'id' => 'mock-charge',
          'responseStatus' => ['successful' => true, 'status' => 'Success', 'reference' => ['entity' => '00123']],
        ]),
        'code' => 200,
        'error' => '',
      ];
    }
  );

  foreach ([
    ['kind' => 'REF', 'paymentInfo' => $referenceInfo],
    ['kind' => 'GPO', 'paymentInfo' => subscription_payment_build_payment_info('GPO', '900000000')],
  ] as $payment) {
    $payload = [
      'amount' => 19900,
      'currency' => 'AOA',
      'description' => 'Renovacao BXPERT',
      'merchantTransactionId' => subscription_payment_new_transaction_id(),
      'paymentMethod' => appypay_method_id($payment['kind']),
      'paymentInfo' => $payment['paymentInfo'],
    ];
    $result = appypay_create_charge($payload);
    $assert($result['code'] === 200 && $result['data']['id'] === 'mock-charge', 'cobrança REF/GPO usa a resposta simulada');
    $requestBody = json_decode((string)$requests[array_key_last($requests)][1][CURLOPT_POSTFIELDS], true);
    $request = $requests[array_key_last($requests)];
    $assert(str_starts_with($request[0], 'https://gwy-api-tst.appypay.co.ao/v2.0/'), 'cobrança é enviada ao Sandbox oficial');
    $assert($requestBody['currency'] === 'AOA' && $requestBody['amount'] === 19900, 'payload usa moeda e valor do servidor');
    $assert($requestBody['paymentInfo'] === $payment['paymentInfo'], 'payload contém paymentInfo correcto para o método');
    $assert($request[1][CURLOPT_SSL_VERIFYPEER] === true && $request[1][CURLOPT_SSL_VERIFYHOST] === 2, 'TLS é validado na chamada à API');
    $headers = $request[1][CURLOPT_HTTPHEADER];
    $assert(
      count(array_filter($headers, static fn(string $header): bool => str_starts_with($header, 'Authorization: Bearer '))) === 1,
      'access token é enviado como Bearer'
    );
  }

  file_put_contents($cachePath, json_encode(['access_token' => 'expired-token', 'expires_at' => time() - 1]));
  $tokenCalls = 0;
  appypay_set_http_transport_for_testing(
    static function (string $url) use (&$tokenCalls): array {
      $tokenCalls++;
      return ['body' => json_encode(['access_token' => 'refreshed-token', 'expires_in' => 3600]), 'code' => 200, 'error' => ''];
    }
  );
  $assert(appypay_get_token() === 'refreshed-token' && $tokenCalls === 1, 'token expirado é renovado antes do uso');

  unlink($cachePath);
  appypay_set_http_transport_for_testing(
    static fn(): array => ['body' => json_encode(['error' => 'invalid_client']), 'code' => 401, 'error' => '']
  );
  $throws(static fn() => appypay_get_token(), 'credenciais recusadas não geram token');

  file_put_contents($cachePath, json_encode(['access_token' => 'cached-test-token', 'expires_at' => time() + 3600]));
  appypay_set_http_transport_for_testing(
    static function (): array {
      return ['body' => '', 'code' => 0, 'error' => 'timeout'];
    }
  );
  $throws(static fn() => appypay_api_request('GET', '/charges/mock-charge'), 'timeout não é interpretado como pagamento');
} finally {
  appypay_set_http_transport_for_testing(null);
  if (is_file($cachePath)) {
    unlink($cachePath);
  }
}

echo "OK: {$assertions} verificações de subscrição/AppyPay\n";
