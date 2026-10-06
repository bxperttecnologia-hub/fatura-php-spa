<?php

declare(strict_types=1);

require __DIR__ . '/src/Database.php';
require __DIR__ . '/src/Mailer.php';
require __DIR__ . '/src/NotificationRepository.php';
require __DIR__ . '/src/Checks/InvoiceCheck.php';
require __DIR__ . '/src/Checks/SubscriptionCheck.php';
require __DIR__ . '/src/Checks/PasswordChangeCheck.php';

use Cron\Database;
use Cron\Mailer;
use Cron\NotificationRepository;
use Cron\Checks\InvoiceCheck;
use Cron\Checks\SubscriptionCheck;
use Cron\Checks\PasswordChangeCheck;

$config = require __DIR__ . '/config.php';

$pdo    = Database::get($config);
$mailer = new Mailer($config['mail']);
$repo   = new NotificationRepository($pdo, $mailer);

$log = function (string $msg): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$msg}\n");
};

try {
    $log('Início do ciclo de atualização/notificações');

    (new InvoiceCheck($pdo, $repo, $config['invoice_due_soon_days']))->run();
    $log('Faturas verificadas.');

    (new SubscriptionCheck(
        $pdo,
        $repo,
        $config['subscription_expiring_days'],
        $config['subscription_order_stale_hours']
    ))->run();
    $log('Subscrições e plano verificados.');

    (new PasswordChangeCheck($pdo, $repo))->run();
    $log('Alterações de password verificadas.');

    $log('Ciclo concluído com sucesso.');
} catch (\Throwable $e) {
    $log('ERRO: ' . $e->getMessage());
    exit(1);
}
