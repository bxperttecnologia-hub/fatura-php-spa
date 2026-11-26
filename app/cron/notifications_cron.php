<?php

declare(strict_types=1);

/**
 * Ciclo de notificações (faturas, subscrições, alterações de password).
 *
 *   php app/cron/notifications_cron.php
 *
 * Sugestão de agendamento: de hora a hora. É idempotente — cada aviso tem uma dedupe_key,
 * por isso correr mais vezes não duplica notificações.
 *
 * .env (opcional): NOTIFY_INVOICE_DUE_SOON_DAYS=3, NOTIFY_INVOICE_OVERDUE_MAX_DAYS=90,
 * NOTIFY_SUBSCRIPTION_EXPIRING_DAYS=7, NOTIFY_SUBSCRIPTION_ORDER_STALE_HOURS=24,
 * NOTIFICATIONS_SEND_EMAIL=0
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/env.php';
require_once __DIR__ . '/NotificationRepository.php';
require_once __DIR__ . '/Invoicecheck.php';
require_once __DIR__ . '/Subscriptioncheck.php';
require_once __DIR__ . '/Passwordchangecheck.php';

use Cron\Checks\InvoiceCheck;
use Cron\Checks\PasswordChangeCheck;
use Cron\Checks\SubscriptionCheck;
use Cron\NotificationRepository;

$envInt = static function (string $name, int $default): int {
    $value = bx_env($name);
    return is_numeric($value) && (int)$value > 0 ? (int)$value : $default;
};

$log = static function (string $msg): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$msg}\n");
};

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ');
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
};

$repo = new NotificationRepository($pdo);
$failed = false;

// Cada verificação corre isolada: um erro numa não impede as outras.
$checks = [
    'Faturas' => static fn() => (new InvoiceCheck(
        $pdo,
        $repo,
        $envInt('NOTIFY_INVOICE_DUE_SOON_DAYS', 3),
        $envInt('NOTIFY_INVOICE_OVERDUE_MAX_DAYS', 90)
    ))->run(),
    'Subscrições' => static fn() => (new SubscriptionCheck(
        $pdo,
        $repo,
        $envInt('NOTIFY_SUBSCRIPTION_EXPIRING_DAYS', 7),
        $envInt('NOTIFY_SUBSCRIPTION_ORDER_STALE_HOURS', 24)
    ))->run(),
    'Passwords' => static function () use ($pdo, $repo, $columnExists, $log) {
        if (!$columnExists($pdo, 'users', 'password_changed_at')) {
            $log('Passwords: ignorado (a coluna users.password_changed_at não existe).');
            return;
        }
        (new PasswordChangeCheck($pdo, $repo))->run();
    },
];

$log('Início do ciclo de notificações');
foreach ($checks as $name => $run) {
    try {
        $run();
        $log("{$name}: verificado.");
    } catch (Throwable $e) {
        $failed = true;
        $log("{$name}: ERRO: " . $e->getMessage());
    }
}
$log($failed ? 'Ciclo concluído com erros.' : 'Ciclo concluído.');
exit($failed ? 1 : 0);
