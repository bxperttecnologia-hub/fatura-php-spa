<?php
/**
 * Migração do módulo de RH (v2) — só acrescenta colunas/tabelas/índices em falta.
 *
 *   php public/rh/migrations/migrate_rh_v2.php            -> aplica
 *   php public/rh/migrations/migrate_rh_v2.php --dry-run  -> só mostra o que faria
 *
 * Idempotente. Não apaga dados. Bloqueado no browser (router.php / .htaccess negam /rh/migrations).
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Só pela linha de comandos.\n");
}

require_once __DIR__ . '/../../../app/config/db.php';
require_once __DIR__ . '/../lib/rh_schema.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
echo "== RH v2: migração de esquema" . ($dryRun ? ' (dry-run)' : '') . " ==\n";

try {
    $log = rh_schema_migrate($pdo, $dryRun);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
    exit(1);
}

if (!$log) {
    echo "Nada a fazer — o esquema já está actualizado.\n";
} else {
    foreach ($log as $line) {
        echo ' - ' . $line . "\n";
    }
}
echo "Concluído.\n";
