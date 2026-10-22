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

$plans = subscription_plans();
$renewalQuote = subscription_payment_quote($plans, 'BXPERT_BAZA', '');
$assert($renewalQuote['plan_code'] === 'BXPERT_BAZA', 'renovação usa o plano actual');
$assert($renewalQuote['amount'] === 15900.0 && $renewalQuote['period_months'] === 3, 'preço e período vêm da configuração');
$upgradeQuote = subscription_payment_quote($plans, 'BXPERT_BAZA', 'XPERT');
$assert($upgradeQuote['operation'] === 'upgrade', 'pedido de plano superior é identificado como upgrade');
$throws(
  static fn() => subscription_payment_quote($plans, 'ENTERPRISE', 'BXPERT_BASE'),
  'actualização para plano inferior é rejeitada'
);
$throws(
  static fn() => subscription_payment_quote($plans, 'BXPERT_BAZA', 'INVALID'),
  'plano solicitado inválido é rejeitado'
);
$invalidPricePlans = $plans;
$invalidPricePlans['BXPERT_BAZA']['price'] = 0;
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

$futureRenewal = subscription_payment_expiration(
  ['plan_code' => 'BXPERT_BAZA', 'plan_expires_at' => '2026-12-31'],
  'BXPERT_BAZA',
  '2026-10-21'
);
$assert($futureRenewal['operation'] === 'renewal', 'renovação preserva a operação');
$assert($futureRenewal['started_at'] === '2026-10-21' && $futureRenewal['expires_at'] === '2027-03-31', 'renovação preserva o tempo restante');
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
  'amount' => '15900.00',
  'payment_method' => 'REF',
];
$successfulCharge = [
  'code' => 200,
  'data' => [
    'id' => 'charge-123',
    'merchantTransactionId' => 'S0123456789ABCDE',
    'amount' => 15900,
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
      'amount' => 15900,
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
  ['id' => 'wrong-charge', 'merchantTransactionId' => 'S0123456789ABCDE', 'amount' => 15900, 'currency' => 'AOA'],
  ['id' => 'charge-123', 'merchantTransactionId' => 'other-transaction', 'amount' => 15900, 'currency' => 'AOA'],
  ['id' => 'charge-123', 'merchantTransactionId' => 'S0123456789ABCDE', 'amount' => 1, 'currency' => 'AOA'],
  ['id' => 'charge-123', 'merchantTransactionId' => 'S0123456789ABCDE', 'amount' => 15900, 'currency' => 'USD'],
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
      'amount' => 15900,
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
    $assert($requestBody['currency'] === 'AOA' && $requestBody['amount'] === 15900, 'payload usa moeda e valor do servidor');
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
