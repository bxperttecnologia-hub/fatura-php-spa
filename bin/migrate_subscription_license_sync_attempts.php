<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este comando só pode ser executado pela linha de comandos.\n");
    exit(1);
}

$arguments = array_slice($argv, 1);
$unknownArguments = array_diff($arguments, ['--dry-run', '--apply']);
if ($unknownArguments !== [] || (in_array('--dry-run', $arguments, true) && in_array('--apply', $arguments, true))) {
    fwrite(STDERR, "Uso: php bin/migrate_subscription_license_sync_attempts.php [--dry-run|--apply]\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/core/bootstrap.php';

try {
    if (DB_NAME === '') {
        throw new RuntimeException('DB_NAME não está configurado.');
    }

    $pdo = Database::pdo();
    $selectedDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($selectedDatabase) || $selectedDatabase !== DB_NAME) {
        throw new RuntimeException('A base de dados selecionada não corresponde a DB_NAME.');
    }

    $migrationPath = dirname(__DIR__) . '/app/migrations/20261122_create_subscription_license_sync_attempts.sql';
    $migrationSql = file_get_contents($migrationPath);
    if ($migrationSql === false) {
        throw new RuntimeException('Não foi possível ler a migração ' . $migrationPath);
    }

    echo "Base ERP selecionada: {$selectedDatabase}\n";
    if (!in_array('--apply', $arguments, true)) {
        echo "Simulação concluída; nada foi alterado. Use --apply para executar a migração.\n";
        exit(0);
    }

    $pdo->exec($migrationSql);
    echo "Migração concluída: subscription_license_sync_attempts existe na base selecionada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha na migração: ' . $e->getMessage() . "\n");
    exit(1);
}
