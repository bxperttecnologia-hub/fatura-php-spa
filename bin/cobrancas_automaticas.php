<?php
/**
 * Cobranças automáticas — correr uma vez por dia (cron), por exemplo às 09:00:
 *   0 9 * * * php /caminho/fatura-php-spa/bin/cobrancas_automaticas.php
 *
 * Opções: --dry-run (só mostra o que seria enviado), --company=ID (só uma empresa).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] ??= 'localhost';
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/helpers/collections.php';

$options = getopt('', ['dry-run', 'company:']);
$dryRun = isset($options['dry-run']);
$onlyCompany = isset($options['company']) ? (int) $options['company'] : null;

$log = static fn(string $msg) => fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$msg}\n");

$sql = 'SELECT company_id FROM collection_settings WHERE enabled = 1';
$params = [];
if ($onlyCompany) {
    $sql .= ' AND company_id = ?';
    $params[] = $onlyCompany;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll(PDO::FETCH_COLUMN);

$log('Cobranças automáticas' . ($dryRun ? ' (simulação)' : '') . ': ' . count($companies) . ' empresa(s) ativa(s).');
$exit = 0;
foreach ($companies as $companyId) {
    try {
        $r = bx_collection_run_company($pdo, (int) $companyId, $dryRun, 'cron');
        $log("Empresa {$companyId}: enviados {$r['sent']}, falhados {$r['failed']}, sem e-mail {$r['skipped']}" . ($dryRun ? ', por enviar ' . count(array_filter($r['items'], fn($i) => $i['status'] === 'pending')) : '') . '.');
        foreach ($r['items'] as $item) {
            $log(sprintf('  %s · %s · etapa %+d · %s%s', $item['reference'], $item['email'] ?: '-', $item['stage_day'], $item['status'], !empty($item['error']) ? ' — ' . $item['error'] : ''));
        }
    } catch (Throwable $e) {
        $exit = 1;
        $log("Empresa {$companyId}: ERRO " . $e->getMessage());
    }
}
exit($exit);
