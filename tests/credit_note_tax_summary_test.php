<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/document_tax.php';

function expectCreditNoteTotal(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.000001) {
        throw new RuntimeException("$message: expected $expected, got $actual");
    }
}

$integer = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 3,
    'discount' => 0,
    'tax' => 0,
]], 'Regime Geral', 0, true);
expectCreditNoteTotal(300.0, $integer['total_sum'], 'integer total sum');
expectCreditNoteTotal(300.0, $integer['final_total'], 'integer final total');

$decimal = bx_document_tax_summary([[
    'unit_price' => 0.335,
    'quantity' => 3,
    'discount' => 0,
    'tax' => 0,
]], 'Regime Geral', 0, true);
expectCreditNoteTotal(1.01, $decimal['total_sum'], 'decimal total sum');
expectCreditNoteTotal(1.01, $decimal['final_total'], 'decimal final total');

$discountedAndTaxed = bx_document_tax_summary([[
    'unit_price' => 19.99,
    'quantity' => 3,
    'discount' => 10,
    'tax' => 14,
]], 'Regime Geral', 0, true);
expectCreditNoteTotal(59.97, $discountedAndTaxed['total_sum'], 'gross total');
expectCreditNoteTotal(6.0, $discountedAndTaxed['total_discount'], 'discount');
expectCreditNoteTotal(53.97, $discountedAndTaxed['subtotal'], 'taxable subtotal');
expectCreditNoteTotal(7.56, $discountedAndTaxed['total_tax'], 'tax');
expectCreditNoteTotal(61.53, $discountedAndTaxed['final_total'], 'discounted taxed total');

$retained = bx_document_tax_summary([[
    'unit_price' => 100,
    'quantity' => 1,
    'discount' => 0,
    'tax' => 14,
]], 'Regime Geral', 5.005, true);
expectCreditNoteTotal(5.01, $retained['retention'], 'retention');
expectCreditNoteTotal(108.99, $retained['final_total'], 'total after retention');

$legacySummary = bx_document_tax_summary([[
    'unit_price' => 0.335,
    'quantity' => 3,
    'discount' => 0,
    'tax' => 0,
]], 'Regime Geral');
expectCreditNoteTotal(1.005, $legacySummary['final_total'], 'default keeps existing unrounded behavior');

echo "Credit note tax summary tests passed.\n";
