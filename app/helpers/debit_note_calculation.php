<?php
declare(strict_types=1);

function bx_debit_note_round(float $value, int $precision = 2): float
{
    return round($value, $precision, PHP_ROUND_HALF_UP);
}

function bx_debit_note_schema_limits(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
               DATA_TYPE AS data_type, NUMERIC_PRECISION AS numeric_precision,
               NUMERIC_SCALE AS numeric_scale
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND ((TABLE_NAME = 'debit_note_items' AND COLUMN_NAME IN ('quantity', 'unit_price'))
            OR (TABLE_NAME = 'debit_notes' AND COLUMN_NAME IN
                ('subtotal_without_tax', 'total_discount', 'total_tax', 'final_total')))
    ");
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['table_name'] . '.' . $column['column_name']] = $column;
    }

    $quantityColumn = $columns['debit_note_items.quantity'] ?? null;
    if (!$quantityColumn) {
        throw new RuntimeException('Não foi possível verificar a precisão da quantidade das notas de débito.');
    }
    $quantityType = strtolower((string)$quantityColumn['data_type']);
    if (in_array($quantityType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true)) {
        $quantityScale = 0;
    } elseif (in_array($quantityType, ['decimal', 'numeric', 'float', 'double', 'real'], true)) {
        $quantityScale = (int)($quantityColumn['numeric_scale'] ?? 0);
        if ($quantityScale < 0 || $quantityScale > 3) {
            throw new RuntimeException('A precisão fraccionária da quantidade não é suportada.');
        }
    } else {
        throw new RuntimeException('O tipo de quantidade das notas de débito não é suportado.');
    }

    $maxAmount = 1000000000000.0;
    foreach ([
        'debit_note_items.unit_price',
        'debit_notes.subtotal_without_tax',
        'debit_notes.total_discount',
        'debit_notes.total_tax',
        'debit_notes.final_total',
    ] as $key) {
        $column = $columns[$key] ?? null;
        if (!$column) {
            throw new RuntimeException('Não foi possível verificar os limites monetários das notas de débito.');
        }
        $precision = (int)($column['numeric_precision'] ?? 0);
        $scale = (int)($column['numeric_scale'] ?? 0);
        $type = strtolower((string)$column['data_type']);
        if (!in_array($type, ['decimal', 'numeric', 'float', 'double', 'real'], true)
            || $precision <= $scale || $scale < 2) {
            throw new RuntimeException('O tipo monetário das notas de débito não é suportado.');
        }
        $maxAmount = min($maxAmount, (10 ** ($precision - $scale)) - 0.01);
    }

    return ['quantity_scale' => $quantityScale, 'max_amount' => $maxAmount];
}

function bx_calculate_debit_note_totals(
    array $rawItems,
    int $quantityScale = 3,
    float $maxAmount = 1000000000000.0
): array
{
    if ($quantityScale < 0 || $quantityScale > 3 || $maxAmount <= 0) {
        throw new InvalidArgumentException('A precisão numérica da nota de débito é inválida.');
    }
    if (!$rawItems || count($rawItems) > 50) {
        throw new InvalidArgumentException(
            $rawItems ? 'Máximo de 50 linhas por nota de débito.' : 'Adicione pelo menos uma linha à nota de débito.'
        );
    }
    if (array_keys($rawItems) !== range(0, count($rawItems) - 1)) {
        throw new InvalidArgumentException('Adicione pelo menos uma linha à nota de débito.');
    }

    $lines = [];
    $subtotal = 0.0;
    $totalDiscount = 0.0;
    $totalTax = 0.0;

    foreach ($rawItems as $index => $raw) {
        $lineNumber = $index + 1;
        if (!is_array($raw)) {
            throw new InvalidArgumentException("Linha $lineNumber: dados inválidos.");
        }

        $description = trim((string)($raw['description'] ?? ''));
        $quantityInput = $raw['quantity'] ?? null;
        $unitPriceInput = $raw['unit_price'] ?? null;
        $discountInput = $raw['discount'] ?? 0;
        $taxInput = $raw['tax'] ?? 0;

        $descriptionLength = function_exists('mb_strlen')
            ? mb_strlen($description, 'UTF-8')
            : strlen($description);
        if ($description === '' || $descriptionLength > 500) {
            throw new InvalidArgumentException("Linha $lineNumber: descrição obrigatória (máx. 500 caracteres).");
        }
        if (!is_numeric($quantityInput) || !is_numeric($unitPriceInput)
            || !is_numeric($discountInput) || !is_numeric($taxInput)) {
            throw new InvalidArgumentException("Linha $lineNumber: valores numéricos inválidos.");
        }

        $rawQuantity = (float)$quantityInput;
        if ($quantityScale === 0 && $rawQuantity !== floor($rawQuantity)) {
            throw new InvalidArgumentException("Linha $lineNumber: a quantidade deve ser inteira no schema actual.");
        }
        $quantity = bx_debit_note_round($rawQuantity, $quantityScale);
        $unitPrice = bx_debit_note_round((float)$unitPriceInput, 2);
        $discount = bx_debit_note_round((float)$discountInput, 2);
        $tax = bx_debit_note_round((float)$taxInput, 2);

        if ($quantity <= 0 || $quantity > 1000000) {
            throw new InvalidArgumentException("Linha $lineNumber: quantidade inválida.");
        }
        if ($unitPrice <= 0 || $unitPrice > $maxAmount) {
            throw new InvalidArgumentException("Linha $lineNumber: preço unitário inválido.");
        }
        if ($discount < 0 || $discount > 100) {
            throw new InvalidArgumentException("Linha $lineNumber: desconto deve estar entre 0 e 100.");
        }
        if ($tax < 0 || $tax > 100) {
            throw new InvalidArgumentException("Linha $lineNumber: IVA deve estar entre 0 e 100.");
        }

        $gross = bx_debit_note_round($quantity * $unitPrice);
        $discountValue = bx_debit_note_round($gross * $discount / 100);
        $taxableBase = bx_debit_note_round($gross - $discountValue);
        $taxValue = bx_debit_note_round($taxableBase * $tax / 100);
        $lineTotal = bx_debit_note_round($taxableBase + $taxValue);
        if ($gross > $maxAmount || $discountValue > $maxAmount
            || $taxableBase > $maxAmount || $taxValue > $maxAmount || $lineTotal > $maxAmount) {
            throw new InvalidArgumentException("Linha $lineNumber: valor acima do limite monetário suportado.");
        }

        $subtotal += $taxableBase;
        $totalDiscount += $discountValue;
        $totalTax += $taxValue;
        if ($subtotal > $maxAmount || $totalDiscount > $maxAmount || $totalTax > $maxAmount
            || $subtotal + $totalTax > $maxAmount) {
            throw new InvalidArgumentException('Os totais excedem o limite monetário suportado.');
        }
        $lines[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'tax' => $tax,
            'gross' => $gross,
            'discount_value' => $discountValue,
            'subtotal' => $taxableBase,
            'tax_value' => $taxValue,
            'final_total' => $lineTotal,
        ];
    }

    return [
        'lines' => $lines,
        'subtotal_without_tax' => bx_debit_note_round($subtotal),
        'total_discount' => bx_debit_note_round($totalDiscount),
        'total_tax' => bx_debit_note_round($totalTax),
        'final_total' => bx_debit_note_round($subtotal + $totalTax),
    ];
}
