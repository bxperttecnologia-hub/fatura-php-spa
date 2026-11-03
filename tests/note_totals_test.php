<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/note_totals.php';

function expectNoteValue(float $expected, float $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("$message: expected $expected, got $actual");
    }
}

$totals = bx_note_totals([[
    'description' => 'Linha normalizada',
    'quantity' => 0.3336,
    'unit_price' => 1.005,
    'discount' => 12.345,
    'tax' => 14,
]]);

expectNoteValue(0.334, $totals['lines'][0]['quantity'], 'stored quantity precision');
expectNoteValue(1.01, $totals['lines'][0]['unit_price'], 'stored price precision');
expectNoteValue(12.35, $totals['lines'][0]['discount'], 'stored discount precision');
expectNoteValue(0.34, $totals['total_sum'], 'gross subtotal');
expectNoteValue(0.04, $totals['total_discount'], 'discount amount');
expectNoteValue(0.30, $totals['subtotal'], 'subtotal after discount');
expectNoteValue(0.04, $totals['total_tax'], 'tax amount');
expectNoteValue(0.34, $totals['final_total'], 'final total');

$multiple = bx_note_totals([
    ['description' => 'Taxada', 'quantity' => 2, 'unit_price' => 1000, 'discount' => 10, 'tax' => 14],
    ['description' => 'Isenta', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax' => 0],
]);
expectNoteValue(2152.0, $multiple['final_total'], 'multiple rates total');
expectNoteValue(200.0, $multiple['total_discount'], 'multiple rates discount');

$legacy = bx_note_totals([[
    'description' => 'Pedido de versão anterior sem desconto',
    'quantity' => 1,
    'unit_price' => 100,
    'tax' => 14,
]]);
expectNoteValue(0.0, $legacy['total_discount'], 'missing discount defaults to zero');
expectNoteValue(114.0, $legacy['final_total'], 'legacy request total');

$invalidTaxRejected = false;
try {
    bx_note_totals([[
        'description' => 'Taxa não admitida',
        'quantity' => 1,
        'unit_price' => 10,
        'discount' => 0,
        'tax' => 21,
    ]]);
} catch (InvalidArgumentException $e) {
    $invalidTaxRejected = str_contains($e->getMessage(), 'IVA inválido');
}
if (!$invalidTaxRejected) {
    throw new RuntimeException('An unsupported tax rate must be rejected.');
}

echo "Note totals tests passed.\n";
