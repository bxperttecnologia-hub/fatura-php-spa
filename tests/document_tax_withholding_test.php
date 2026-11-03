<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/document_tax.php';

function assertTaxValue(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.000001) {
        throw new RuntimeException("$message: expected $expected, got $actual");
    }
}

function assertTaxRejected(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException | RuntimeException $exception) {
        return;
    }

    throw new RuntimeException($message);
}

function summarizeDocumentTax(array $items, string $type, ?array $configuration = null, bool $confirmed = true): array
{
    $vatSummary = bx_document_tax_summary($items, 'Regime Geral', null, true);

    return bx_document_tax_withholding_summary(
        $items,
        $vatSummary,
        $type,
        $configuration,
        $confirmed,
        'Regime Geral'
    );
}

$service = [
    'item_type' => 'service',
    'tax_classification' => 'withholding_service',
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'tax' => 14,
    'retention_rate' => 0,
];
$enabledWithholding = ['rate' => 6.5, 'base_type' => 'net_subtotal', 'enabled' => 1];
$enabledIpu = ['rate' => 15, 'base_type' => 'net_subtotal', 'enabled' => 1];

$noTax = summarizeDocumentTax([$service], 'none');
assertTaxValue(0, $noTax['amount'], 'no document tax');
assertTaxValue(114, $noTax['gross_total'], 'no-tax gross total includes VAT');
assertTaxValue(114, $noTax['net_payable'], 'no-tax net payable');

$withholding = summarizeDocumentTax([$service], 'withholding_6_5', $enabledWithholding);
assertTaxValue(100, $withholding['base'], 'withholding base excludes VAT');
assertTaxValue(6.5, $withholding['amount'], 'withholding amount');
assertTaxValue(107.5, $withholding['net_payable'], 'withholding deducted once');

$rental = $service;
$rental['tax_classification'] = 'rental_income';
$ipu = summarizeDocumentTax([$rental], 'ipu_rent', $enabledIpu);
assertTaxValue(15, $ipu['amount'], 'IPU amount');
assertTaxValue(99, $ipu['net_payable'], 'IPU is separate from VAT');

$grossBase = summarizeDocumentTax(
    [$service],
    'withholding_6_5',
    ['rate' => 6.5, 'base_type' => 'gross_including_vat', 'enabled' => 1]
);
assertTaxValue(114, $grossBase['base'], 'gross-including-VAT configured base');
assertTaxValue(7.41, $grossBase['amount'], 'configured base is applied exactly once');

$discountedService = $service;
$discountedService['discount'] = 10;
$discounted = summarizeDocumentTax([$discountedService], 'withholding_6_5', $enabledWithholding);
assertTaxValue(90, $discounted['base'], 'discount reduces withholding base');
assertTaxValue(5.85, $discounted['amount'], 'discount recalculates withholding');

$changedVatService = $service;
$changedVatService['tax'] = 5;
$changedVat = summarizeDocumentTax([$changedVatService], 'withholding_6_5', $enabledWithholding);
assertTaxValue(6.5, $changedVat['amount'], 'VAT change does not change a net-subtotal base');
assertTaxValue(105, $changedVat['gross_total'], 'VAT change updates gross total');
assertTaxValue(98.5, $changedVat['net_payable'], 'VAT change updates net payable');

$merchandise = $rental;
$merchandise['item_type'] = 'product';
assertTaxRejected(
    fn() => summarizeDocumentTax([$merchandise], 'ipu_rent', $enabledIpu),
    'IPU must reject merchandise lines.'
);

$unqualifiedService = $service;
$unqualifiedService['tax_classification'] = 'standard';
assertTaxRejected(
    fn() => summarizeDocumentTax([$unqualifiedService], 'withholding_6_5', $enabledWithholding),
    'Withholding must reject unclassified services.'
);

$missingItemType = $service;
unset($missingItemType['item_type']);
assertTaxRejected(
    fn() => summarizeDocumentTax([$missingItemType], 'withholding_6_5', $enabledWithholding),
    'Withholding must reject lines without a server-resolved item type.'
);

assertTaxRejected(
    fn() => summarizeDocumentTax([$service], 'withholding_6_5', $enabledWithholding, false),
    'Unconfirmed eligibility must be rejected.'
);
assertTaxRejected(
    fn() => summarizeDocumentTax([$service], 'withholding_6_5', ['rate' => 6.5, 'base_type' => 'net_subtotal', 'enabled' => 0]),
    'A disabled tax configuration must be rejected.'
);
assertTaxRejected(
    fn() => summarizeDocumentTax([$service], 'withholding_6_5', ['rate' => 101, 'base_type' => 'net_subtotal', 'enabled' => 1]),
    'An invalid configured rate must be rejected.'
);

$duplicateRetention = $service;
$duplicateRetention['retention_rate'] = 2;
assertTaxRejected(
    fn() => summarizeDocumentTax([$duplicateRetention], 'withholding_6_5', $enabledWithholding),
    'Documentary withholding must reject duplicate line retention.'
);

$halfCentService = $service;
$halfCentService['unit_price'] = 0.335;
$halfCentService['quantity'] = 3;
$halfCentService['tax'] = 0;
$rounded = summarizeDocumentTax(
    [$halfCentService],
    'withholding_6_5',
    ['rate' => 100, 'base_type' => 'net_subtotal', 'enabled' => 1]
);
assertTaxValue(1.01, $rounded['amount'], 'tax rounds half up to cents');

echo "Document withholding tax tests passed.\n";
