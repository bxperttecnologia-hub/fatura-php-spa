<?php
declare(strict_types=1);

// Executar a cada minuto:  * * * * * php /caminho/invoices/cron/agt_sync_debit_notes.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Submissão ND bloqueada até validação do enquadramento e do esquema oficial.
