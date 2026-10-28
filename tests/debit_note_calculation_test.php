<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/debit_note_calculation.php';

function expectSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("$message: expected " . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

$plain = bx_calculate_debit_note_totals([[
    'description' => 'Encargo',
    'quantity' => 2,
    'unit_price' => 1000,
    'discount' => 0,
    'tax' => 0,
]]);
expectSameValue(2000.0, $plain['subtotal_without_tax'], 'plain subtotal');
expectSameValue(0.0, $plain['total_discount'], 'plain discount');
expectSameValue(0.0, $plain['total_tax'], 'plain tax');
expectSameValue(2000.0, $plain['final_total'], 'plain total');

$discounted = bx_calculate_debit_note_totals([[
    'description' => 'Encargo',
    'quantity' => 2,
    'unit_price' => 1000,
    'discount' => 10,
    'tax' => 0,
]]);
expectSameValue(1800.0, $discounted['subtotal_without_tax'], 'discounted subtotal');
expectSameValue(200.0, $discounted['total_discount'], 'discounted amount');
expectSameValue(1800.0, $discounted['final_total'], 'discounted total');

$taxed = bx_calculate_debit_note_totals([[
    'description' => 'Encargo',
    'quantity' => 2,
    'unit_price' => 1000,
    'discount' => 10,
    'tax' => 14,
]]);
expectSameValue(1800.0, $taxed['subtotal_without_tax'], 'tax base');
expectSameValue(200.0, $taxed['total_discount'], 'taxed discount');
expectSameValue(252.0, $taxed['total_tax'], 'tax amount');
expectSameValue(2052.0, $taxed['final_total'], 'taxed total');

$multiple = bx_calculate_debit_note_totals([
    ['description' => 'Geral', 'quantity' => 2, 'unit_price' => 1000, 'discount' => 10, 'tax' => 14],
    ['description' => 'Isento', 'quantity' => 1, 'unit_price' => 100, 'discount' => 0, 'tax' => 0],
    ['description' => 'Taxa reduzida', 'quantity' => 1, 'unit_price' => 50, 'discount' => 0, 'tax' => 5],
]);
expectSameValue(1950.0, $multiple['subtotal_without_tax'], 'multiple subtotal');
expectSameValue(200.0, $multiple['total_discount'], 'multiple discount');
expectSameValue(254.5, $multiple['total_tax'], 'multiple tax');
expectSameValue(2204.5, $multiple['final_total'], 'multiple total');

$fractional = bx_calculate_debit_note_totals([[
    'description' => 'Quantidade fraccionária',
    'quantity' => 0.3334,
    'unit_price' => 3,
    'discount' => 0,
    'tax' => 0,
]]);
expectSameValue(0.333, $fractional['lines'][0]['quantity'], 'stored quantity precision');
expectSameValue(1.0, $fractional['final_total'], 'fractional total');

$integerSchemaRejectedFraction = false;
try {
    bx_calculate_debit_note_totals([[
        'description' => 'Quantidade não suportada pelo schema',
        'quantity' => 1.5,
        'unit_price' => 3,
        'discount' => 0,
        'tax' => 0,
    ]], 0, 99999.99);
} catch (InvalidArgumentException $e) {
    $integerSchemaRejectedFraction = str_contains($e->getMessage(), 'deve ser inteira');
}
expectSameValue(true, $integerSchemaRejectedFraction, 'integer schema rejects fractional quantity');

$fractionalSchema = bx_calculate_debit_note_totals([[
    'description' => 'Quantidade fraccionária',
    'quantity' => 0.3334,
    'unit_price' => 3,
    'discount' => 0,
    'tax' => 0,
]], 3, 99999.99);
expectSameValue(0.333, $fractionalSchema['lines'][0]['quantity'], 'decimal schema quantity scale');

$limitRejected = false;
try {
    bx_calculate_debit_note_totals([[
        'description' => 'Acima do limite',
        'quantity' => 1,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
    ]], 0, 99999.99);
} catch (InvalidArgumentException $e) {
    $limitRejected = str_contains($e->getMessage(), 'preço unitário inválido');
}
expectSameValue(true, $limitRejected, 'float schema amount limit');

$rounded = bx_calculate_debit_note_totals([[
    'description' => 'Arredondamento',
    'quantity' => 1,
    'unit_price' => 0.05,
    'discount' => 10,
    'tax' => 0,
]]);
expectSameValue(0.01, $rounded['total_discount'], 'half-up discount');
expectSameValue(0.04, $rounded['final_total'], 'half-up final total');

echo "Debit note calculation tests passed.\n";
