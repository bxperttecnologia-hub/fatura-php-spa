<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'migration SQL exists' => is_file($root . '/app/migrations/20261125_delivery_transport_documents.sql'),
    'migration JS exists' => is_file($root . '/database/migrations/20261125_delivery_transport_documents.js'),
    'guide save is transactional' => str_contains(
        file_get_contents($root . '/app/pages/guides/ajax/save_guide.php'),
        '$pdo->beginTransaction()'
    ),
    'draft guide does not move stock' => !str_contains(
        file_get_contents($root . '/app/pages/guides/ajax/save_guide.php'),
        'stock_movements'
    ),
    'guide dispatch writes stock movement' => str_contains(
        file_get_contents($root . '/app/pages/guides/ajax/document_action.php'),
        "'saida'"
    ),
    'guide delivery supports partial quantities' => str_contains(
        file_get_contents($root . '/app/pages/guides/ajax/document_action.php'),
        'entrega_parcial'
    ),
    'delivery note supports refusal' => str_contains(
        file_get_contents($root . '/app/pages/invoices/ajax/delivery_note_action.php'),
        'RECUSADA'
    ),
    'delivery note supports returns' => str_contains(
        file_get_contents($root . '/app/pages/invoices/ajax/delivery_note_action.php'),
        'DEVOLVIDA'
    ),
    'cancelled documents are audited' => str_contains(
        file_get_contents($root . '/app/pages/guides/ajax/document_action.php'),
        'CANCELADA'
    ) && str_contains(
        file_get_contents($root . '/app/pages/invoices/ajax/delivery_note_action.php'),
        'CANCELADA'
    ),
    'guide creation follows invoice document editor structure' => str_contains(
        file_get_contents($root . '/app/pages/guides.php'),
        'guide-top'
    ) && str_contains(
        file_get_contents($root . '/app/pages/guides.php'),
        'guide-card'
    ),
    'guide form has no invoice payment or tax summary' => !str_contains(
        file_get_contents($root . '/app/pages/guides.php'),
        'IVA'
    ) && !str_contains(
        file_get_contents($root . '/app/pages/guides.php'),
        'Pagamento previsto'
    ),
    'origin document search endpoint exists' => is_file(
        $root . '/app/pages/invoices/ajax/search_origin_documents.php'
    ) && is_file(
        $root . '/app/pages/invoices/ajax/load_origin_document.php'
    ),
    'origin loader calculates pending quantities' => str_contains(
        file_get_contents($root . '/app/pages/invoices/ajax/load_origin_document.php'),
        'pending_quantity'
    ) && str_contains(
        file_get_contents($root . '/app/pages/invoices/ajax/load_origin_document.php'),
        'delivered_quantity'
    ),
    'backend revalidates origin quantities' => str_contains(
        file_get_contents($root . '/app/pages/guides/ajax/save_guide.php'),
        'Quantidade superior ao saldo pendente'
    ),
    'guide emission updates visible status without reloading' => str_contains(
        file_get_contents($root . '/public/guides/view_guides.js'),
        'renderGuideStatus(response.status, guideTransportStatus)'
    ) && str_contains(
        file_get_contents($root . '/public/guides/view_guides.js'),
        "dataType: 'json'"
    ) && !str_contains(
        file_get_contents($root . '/public/guides/view_guides.js'),
        'location.reload()'
    ),
];

$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
exit($failed === 0 ? 0 : 1);
