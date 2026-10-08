<?php

function bx_document_effective_tax_rate($rate, ?string $regime): float
{
    $rate = (float)$rate;
    $normalizedRegime = mb_strtolower(trim((string)$regime), 'UTF-8');
    $normalizedRegime = strtr($normalizedRegime, [
        'á' => 'a',
        'à' => 'a',
        'ã' => 'a',
        'â' => 'a',
        'é' => 'e',
        'ê' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ú' => 'u',
    ]);

    if (($normalizedRegime !== '' && !str_contains($normalizedRegime, 'geral')) || $rate === 7.0) {
        return 0.0;
    }

    return $rate;
}

function bx_document_tax_summary(array $items, ?string $regime, float $retentionTotal = 0.0): array
{
    $groups = [];
    $totalSum = 0.0;
    $totalDiscount = 0.0;
    $totalTax = 0.0;

    foreach ($items as $item) {
        $base = (float)($item['unit_price'] ?? 0) * (float)($item['quantity'] ?? 0);
        $discount = min($base, $base * ((float)($item['discount'] ?? 0) / 100));
        $taxableBase = $base - $discount;
        $rate = bx_document_effective_tax_rate($item['tax'] ?? $item['tax_rate'] ?? 0, $regime);
        $tax = $taxableBase * ($rate / 100);
        $key = number_format($rate, 4, '.', '');

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'rate' => $rate,
                'base' => 0.0,
                'iva' => 0.0,
                'retention' => 0.0,
                'net' => 0.0,
            ];
        }

        $groups[$key]['base'] += $taxableBase;
        $groups[$key]['iva'] += $tax;
        $groups[$key]['net'] += $taxableBase + $tax;
        $totalSum += $base;
        $totalDiscount += $discount;
        $totalTax += $tax;
    }

    $retentionTotal = max(0.0, $retentionTotal);
    $baseTotal = array_sum(array_column($groups, 'base'));
    $allocatedRetention = 0.0;
    $rows = array_values($groups);
    usort($rows, static fn(array $a, array $b): int => $a['rate'] <=> $b['rate']);

    foreach ($rows as $index => &$row) {
        $share = $baseTotal > 0
            ? ($index === count($rows) - 1
                ? $retentionTotal - $allocatedRetention
                : $retentionTotal * $row['base'] / $baseTotal)
            : 0.0;
        $allocatedRetention += $share;
        $row['retention'] = $share;
        $row['net'] -= $share;
    }
    unset($row);

    return [
        'rows' => $rows,
        'total_sum' => $totalSum,
        'total_discount' => $totalDiscount,
        'subtotal' => $totalSum - $totalDiscount,
        'total_tax' => $totalTax,
        'retention' => $retentionTotal,
        'final_total' => max(0.0, $totalSum - $totalDiscount + $totalTax - $retentionTotal),
    ];
}
