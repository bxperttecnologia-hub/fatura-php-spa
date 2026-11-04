<?php
/**
 * Cálculo de totais das notas de débito (e outras notas) — fonte única de verdade.
 *
 * Convenção (igual à fatura, ver invoice_public.php):
 *   base      = quantidade × preço unitário
 *   desconto  = base × desconto%
 *   líquido   = base − desconto
 *   IVA       = líquido × IVA%
 *   total     = líquido + IVA
 * Cada valor é arredondado a 2 casas (meio para cima) POR LINHA e os totais do
 * documento são a soma das linhas já arredondadas, para que o PDF, a base de
 * dados e o ficheiro enviado à AGT nunca difiram em cêntimos.
 */

declare(strict_types=1);

if (!function_exists('bx_round2')) {
    function bx_round2(float $v): float
    {
        // O pré-arredondamento a 8 casas elimina o erro binário (1.005 → 1.00).
        return round(round($v, 8), 2, PHP_ROUND_HALF_UP);
    }
}

if (!function_exists('bx_note_line')) {
    /** @return array{base:float,discount:float,net:float,tax:float,total:float,discount_rate:float,tax_rate:float} */
    function bx_note_line(float $qty, float $unitPrice, float $discountRate, float $taxRate): array
    {
        $base     = bx_round2($qty * $unitPrice);
        $discount = bx_round2($base * $discountRate / 100);
        $net      = bx_round2($base - $discount);
        $tax      = bx_round2($net * $taxRate / 100);

        return [
            'base'          => $base,
            'discount'      => $discount,
            'net'           => $net,
            'tax'           => $tax,
            'total'         => bx_round2($net + $tax),
            'discount_rate' => $discountRate,
            'tax_rate'      => $taxRate,
        ];
    }
}

if (!function_exists('bx_note_totals')) {
    /**
     * Valida as linhas recebidas do cliente e calcula os totais.
     *
     * @param  array<int,mixed> $rawItems
     * @return array{lines:array<int,array<string,mixed>>,total_sum:float,subtotal:float,total_discount:float,total_tax:float,final_total:float}
     * @throws InvalidArgumentException com mensagem pronta para o utilizador
     */
    function bx_note_totals(array $rawItems): array
    {
        $rawItems = array_values($rawItems);
        if (!$rawItems) {
            throw new InvalidArgumentException('Adicione pelo menos uma linha.');
        }
        if (count($rawItems) > 200) {
            throw new InvalidArgumentException('Máximo de 200 linhas por nota.');
        }

        $num = static function (mixed $v, string $label, int $n, float $min, float $max, bool $minExclusive = false): float {
            if (is_string($v)) {
                $v = str_replace(',', '.', trim($v));
            }
            if ($v === null || $v === '' || !is_numeric($v) || !is_finite((float)$v)) {
                throw new InvalidArgumentException("Linha {$n}: {$label} inválido.");
            }
            $f = (float)$v;
            if (($minExclusive && $f <= $min) || (!$minExclusive && $f < $min) || $f > $max) {
                throw new InvalidArgumentException("Linha {$n}: {$label} fora do intervalo permitido.");
            }
            return $f;
        };

        $lines = [];
        $sum = ['gross' => 0.0, 'net' => 0.0, 'discount' => 0.0, 'tax' => 0.0];

        foreach ($rawItems as $i => $it) {
            $n = $i + 1;
            if (!is_array($it)) {
                throw new InvalidArgumentException("Linha {$n}: formato inválido.");
            }
            $description = trim((string)($it['description'] ?? ''));
            if ($description === '') {
                throw new InvalidArgumentException("Linha {$n}: indique a descrição.");
            }
            if (mb_strlen($description) > 500) {
                throw new InvalidArgumentException("Linha {$n}: descrição demasiado longa (máx. 500).");
            }

            $qty      = $num($it['quantity']   ?? null, 'quantidade',     $n, 0.0, 999999.0, true);
            $price    = $num($it['unit_price'] ?? null, 'preço unitário', $n, 0.0, 999999999999.0, true);
            $discount = $num($it['discount']   ?? 0,    'desconto',       $n, 0.0, 100.0);
            $tax      = $num($it['tax']        ?? 0,    'IVA',            $n, 0.0, 100.0);

            $qty = round($qty, 3);
            $discount = round($discount, 2, PHP_ROUND_HALF_UP);
            $tax = round($tax, 2, PHP_ROUND_HALF_UP);
            if (!in_array($tax, [0.0, 5.0, 7.0, 14.0], true)) {
                throw new InvalidArgumentException("Linha {$n}: IVA inválido.");
            }
            $c = bx_note_line($qty, $price, $discount, $tax);

            $sum['gross']    += $c['base'];
            $sum['net']      += $c['net'];
            $sum['discount'] += $c['discount'];
            $sum['tax']      += $c['tax'];

            $lines[] = [
                'description' => $description,
                'quantity'    => $qty,
                'unit_price'  => bx_round2($price),
                'discount'    => $discount,
                'tax'         => $tax,
                'calc'        => $c,
            ];
        }

        $final = bx_round2($sum['net'] + $sum['tax']);
        if ($final <= 0) {
            throw new InvalidArgumentException('O total da nota de débito tem de ser superior a zero.');
        }
        if ($final > 9999999999999999.99) {
            throw new InvalidArgumentException('O total excede o limite permitido.');
        }

        return [
            'lines'          => $lines,
            'total_sum'      => bx_round2($sum['gross']),
            'subtotal'       => bx_round2($sum['net']),     // sem IVA, já líquido de desconto
            'total_discount' => bx_round2($sum['discount']),
            'total_tax'      => bx_round2($sum['tax']),
            'final_total'    => $final,
        ];
    }
}
