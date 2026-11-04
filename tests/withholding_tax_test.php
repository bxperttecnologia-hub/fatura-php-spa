<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/document_tax.php';

function expectRetentionValue(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.000001) {
        throw new RuntimeException("$message: expected $expected, got $actual");
    }
}

$noConfiguredRate = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'retention_rate' => 0,
]], 'Regime Geral', null, true);
expectRetentionValue(0.0, $noConfiguredRate['retention'], 'no configured rate');

$configuredRate = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'retention_rate' => 6.5,
]], 'Regime Geral', null, true);
expectRetentionValue(6.5, $configuredRate['retention'], 'configured rate');

$discounted = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 10,
    'retention_rate' => 6.5,
]], 'Regime Geral', null, true);
expectRetentionValue(5.85, $discounted['retention'], 'discount reduces base');

$quantity = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 2,
    'discount' => 0,
    'retention_rate' => 6.5,
]], 'Regime Geral', null, true);
expectRetentionValue(13.0, $quantity['retention'], 'quantity scales base');

$vatExcludedFromBase = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'tax' => 14,
    'retention_rate' => 6.5,
]], 'Regime Geral', null, true);
expectRetentionValue(6.5, $vatExcludedFromBase['retention'], 'VAT is not in configured-rate base');
expectRetentionValue(107.5, $vatExcludedFromBase['final_total'], 'final total subtracts retention once');

$zeroRateLine = bx_document_tax_summary([[
    'item_type' => 'product',
    'unit_price' => 300,
    'quantity' => 1,
    'discount' => 0,
    'retention_rate' => 0,
]], 'Regime Geral', null, true);
expectRetentionValue(0.0, $zeroRateLine['retention'], 'zero-rate item');

$differentConfiguredRates = bx_document_tax_summary([
    ['unit_price' => 100, 'quantity' => 1, 'discount' => 0, 'retention_rate' => 6.5],
    ['unit_price' => 200, 'quantity' => 1, 'discount' => 0, 'retention_rate' => 2],
], 'Regime Geral', null, true);
expectRetentionValue(10.5, $differentConfiguredRates['retention'], 'rates are computed per line');

$mixedRatesAndTaxes = bx_document_tax_summary([
    ['unit_price' => 100, 'quantity' => 1, 'discount' => 0, 'tax' => 14, 'retention_rate' => 6.5],
    ['unit_price' => 200, 'quantity' => 1, 'discount' => 0, 'tax' => 5, 'retention_rate' => 2],
], 'Regime Geral', null, true);
expectRetentionValue(10.5, $mixedRatesAndTaxes['retention'], 'mixed rates remain line-based');
expectRetentionValue(300.0, $mixedRatesAndTaxes['retention_base'], 'retention base excludes VAT');
expectRetentionValue(313.5, $mixedRatesAndTaxes['final_total'], 'mixed rates and taxes reconcile');
if (count($mixedRatesAndTaxes['rows']) !== 2) {
    throw new RuntimeException('Mixed VAT rates must remain separate in the output summary.');
}

$negativeConfiguredRate = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'retention_rate' => -5,
]], 'Regime Geral', null, true);
expectRetentionValue(0.0, $negativeConfiguredRate['retention'], 'negative configured rate is ignored');

$halfCent = bx_document_retention_total([[
    'unit_price' => 0.335,
    'quantity' => 3,
    'discount' => 0,
    'retention_rate' => 100,
]]);
expectRetentionValue(1.01, $halfCent, 'retention rounds half up to cents');

$clientSuppliedRate = bx_document_apply_retention_rates([[
    'id' => 42,
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'retention_rate' => 99,
]], [42 => 6.5]);
expectRetentionValue(6.5, $clientSuppliedRate[0]['retention_rate'], 'catalog rate overrides client rate');
expectRetentionValue(6.5, bx_document_retention_total($clientSuppliedRate), 'resolved rate controls retained amount');

$invalidConfiguredRateRejected = false;
try {
    bx_document_apply_retention_rates([[
        'id' => 42,
        'unit_price' => 100,
        'quantity' => 1,
        'discount' => 0,
    ]], [42 => 100.01]);
} catch (InvalidArgumentException $e) {
    $invalidConfiguredRateRejected = true;
}
if (!$invalidConfiguredRateRejected) {
    throw new RuntimeException('An invalid catalog withholding rate must be rejected.');
}

echo "Withholding calculation tests passed.\n";
