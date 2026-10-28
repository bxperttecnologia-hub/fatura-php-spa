<?php
declare(strict_types=1);

/**
 * Cálculo único para notas de débito e de crédito.
 * Desconto e IVA são percentagens. O IVA incide sobre a base já com desconto.
 * Arredondamento a 2 casas em cada passo da linha.
 */

if (!function_exists('bx_note_line')) {
    function bx_note_line(float $qty, float $unitPrice, float $discountRate, float $taxRate): array
    {
        $base     = round($qty * $unitPrice, 2);
        $discount = round($base * $discountRate / 100, 2);
        $taxable  = round($base - $discount, 2);
        $tax      = round($taxable * $taxRate / 100, 2);
        return [
            'base'     => $base,
            'discount' => $discount,
            'taxable'  => $taxable,
            'tax'      => $tax,
            'total'    => round($taxable + $tax, 2),
        ];
    }
}

if (!function_exists('bx_note_totals')) {
    /**
     * @param array<int, array<string, mixed>> $items linhas com quantity, unit_price, discount, tax
     * @return array{total_sum: float, total_discount: float, subtotal: float, total_tax: float, final_total: float}
     */
    function bx_note_totals(array $items): array
    {
        $sum = $discount = $subtotal = $tax = 0.0;
        foreach ($items as $it) {
            $l = bx_note_line(
                (float)($it['quantity'] ?? 0),
                (float)($it['unit_price'] ?? 0),
                (float)($it['discount'] ?? 0),
                (float)($it['tax'] ?? 0)
            );
            $sum      += $l['base'];
            $discount += $l['discount'];
            $subtotal += $l['taxable'];
            $tax      += $l['tax'];
        }
        $subtotal = round($subtotal, 2);
        $tax      = round($tax, 2);
        return [
            'total_sum'      => round($sum, 2),
            'total_discount' => round($discount, 2),
            'subtotal'       => $subtotal,
            'total_tax'      => $tax,
            'final_total'    => round($subtotal + $tax, 2),
        ];
    }
}

if (!function_exists('bx_note_allowed_tax_rates')) {
    /**
     * Taxas de IVA admitidas em notas (Código do IVA, art. 19.º): 14% geral, 7%, 5%, 1% (Cabinda) e 0% (isento).
     * Manter igual a NOTE_TAX_RATES em invoice.js.
     */
    function bx_note_allowed_tax_rates(): array
    {
        return [14.0, 7.0, 5.0, 1.0, 0.0];
    }
}