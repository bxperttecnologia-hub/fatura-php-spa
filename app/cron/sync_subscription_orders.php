<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/subscription_order_sync.php';

$result = subscription_order_sync_flush($pdo);
fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
if ($result['failed'] > 0) exit(1);
