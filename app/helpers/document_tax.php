<?php

function bx_document_normalize_regime_name($regime): string
{
    $raw = $regime;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = $decoded['regime'] ?? $decoded['name'] ?? $decoded['value'] ?? $raw;
        }
    }

    $normalized = trim((string)$raw);
    $normalized = mb_strtolower($normalized, 'UTF-8');
    $normalized = strtr($normalized, [
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
        'ç' => 'c',
    ]);

    return $normalized;
}

function bx_document_effective_tax_rate($rate, ?string $regime): float
{
    $rate = (float)$rate;
    $normalizedRegime = bx_document_normalize_regime_name($regime);

    if (($normalizedRegime !== '' && !str_contains($normalizedRegime, 'geral')) || $rate === 7.0) {
        return 0.0;
    }

    return $rate;
}

function bx_document_line_taxable_base(array $item): float
{
    $base = (float)($item['unit_price'] ?? 0) * (float)($item['quantity'] ?? 0);
    $discount = min($base, $base * ((float)($item['discount'] ?? 0) / 100));

    return $base - $discount;
}

function bx_document_line_retention(array $item, ?float $taxableBase = null): float
{
    $taxableBase ??= bx_document_line_taxable_base($item);
    $rate = max(0.0, (float)($item['retention_rate'] ?? 0));

    return max(0.0, $taxableBase) * ($rate / 100);
}

function bx_document_retention_total(array $items, bool $roundToCents = true): float
{
    $retention = 0.0;
    foreach ($items as $item) {
        $retention += bx_document_line_retention($item);
    }

    return $roundToCents ? round($retention, 2, PHP_ROUND_HALF_UP) : $retention;
}

function bx_document_apply_retention_rates(array $items, array $ratesByItemId): array
{
    foreach ($items as &$item) {
        $itemId = (int)($item['id'] ?? $item['item_id'] ?? 0);
        if ($itemId <= 0 || !array_key_exists($itemId, $ratesByItemId)) {
            throw new RuntimeException('Um ou mais itens não pertencem a esta empresa ou estão indisponíveis.');
        }

        $rate = $ratesByItemId[$itemId];
        if (!is_numeric($rate) || !is_finite((float)$rate) || (float)$rate < 0 || (float)$rate > 100) {
            throw new InvalidArgumentException('A taxa de retenção configurada no catálogo é inválida.');
        }

        $item['retention_rate'] = round(
            (float)$rate,
            4,
            PHP_ROUND_HALF_UP
        );
    }
    unset($item);

    return $items;
}

function bx_document_assign_retention_rates(PDO $pdo, array $items, int $companyId): array
{
    if ($companyId <= 0 || $items === []) {
        throw new InvalidArgumentException('Empresa ou itens inválidos.');
    }

    $itemIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Item inválido.');
        }

        $itemId = (int)($item['id'] ?? $item['item_id'] ?? 0);
        if ($itemId <= 0) {
            throw new InvalidArgumentException('Item inválido.');
        }

        foreach ([
            'quantity' => [PHP_FLOAT_MAX, true],
            'unit_price' => [PHP_FLOAT_MAX, false],
            'discount' => [100.0, false],
        ] as $field => [$maximum, $minExclusive]) {
            $value = $item[$field] ?? ($field === 'discount' ? 0 : null);
            if ($value === null || !is_numeric($value) || !is_finite((float)$value)) {
                throw new InvalidArgumentException('Um ou mais valores dos itens são inválidos.');
            }
            $number = (float)$value;
            if (($minExclusive && $number <= 0) || (!$minExclusive && $number < 0) || $number > $maximum) {
                throw new InvalidArgumentException('Um ou mais valores dos itens estão fora do intervalo permitido.');
            }
        }

        $itemIds[] = $itemId;
    }

    $itemIds = array_values(array_unique($itemIds));
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, retention, tax, tax_classification, item_type FROM items WHERE company_id = ? AND id IN ($placeholders) AND status != 'archived'"
    );
    $stmt->execute(array_merge([$companyId], $itemIds));

    $rates = [];
    $taxRates = [];
    $classifications = [];
    $itemTypes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $itemId = (int)$row['id'];
        // IVA e retenção são opcionais no catálogo: vazio/NULL conta como 0%.
        $retentionRate = trim((string)($row['retention'] ?? '')) === '' ? 0 : $row['retention'];
        $taxRate = trim((string)($row['tax'] ?? '')) === '' ? 0 : $row['tax'];
        if (
            !is_numeric($retentionRate) || !is_finite((float)$retentionRate)
            || (float)$retentionRate < 0 || (float)$retentionRate > 100
            || !is_numeric($taxRate) || !is_finite((float)$taxRate)
            || (float)$taxRate < 0 || (float)$taxRate > 100
        ) {
            throw new InvalidArgumentException('As taxas fiscais configuradas no catálogo são inválidas.');
        }
        $rates[$itemId] = (float)$retentionRate;
        $taxRates[$itemId] = (float)$taxRate;
        $classifications[$itemId] = (string)($row['tax_classification'] ?? 'standard');
        $itemTypes[$itemId] = (string)$row['item_type'];
    }

    if (count($rates) !== count($itemIds)) {
        throw new RuntimeException('Um ou mais itens não pertencem a esta empresa ou estão indisponíveis.');
    }

    $items = bx_document_apply_retention_rates($items, $rates);
    foreach ($items as &$item) {
        $itemId = (int)($item['id'] ?? $item['item_id'] ?? 0);
        $item['tax'] = $taxRates[$itemId];
        $item['tax_classification'] = $classifications[$itemId];
        $item['item_type'] = $itemTypes[$itemId];
    }
    unset($item);

    return $items;
}

function bx_document_has_retention_snapshots(array $items): bool
{
    foreach ($items as $item) {
        if (array_key_exists('retention_rate', $item) && $item['retention_rate'] !== null) {
            return true;
        }
    }

    return false;
}

function bx_document_tax_summary(
    array $items,
    ?string $regime,
    ?float $retentionTotal = null,
    bool $roundToCents = false
): array
{
    $groups = [];
    $totalSum = 0.0;
    $totalDiscount = 0.0;
    $totalTax = 0.0;
    $calculatedRetention = 0.0;
    $retentionBase = 0.0;
    $usesLineRetention = $retentionTotal === null;

    foreach ($items as $item) {
        $base = (float)($item['unit_price'] ?? 0) * (float)($item['quantity'] ?? 0);
        $discount = min($base, $base * ((float)($item['discount'] ?? 0) / 100));
        $taxableBase = $base - $discount;
        $rate = bx_document_effective_tax_rate($item['tax'] ?? $item['tax_rate'] ?? 0, $regime);
        $tax = $taxableBase * ($rate / 100);
        $lineRetention = $usesLineRetention
            ? bx_document_line_retention($item, $taxableBase)
            : 0.0;
        if ($usesLineRetention && (float)($item['retention_rate'] ?? 0) > 0) {
            $retentionBase += max(0.0, $taxableBase);
        }
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
        $groups[$key]['retention'] += $lineRetention;
        $groups[$key]['net'] += $taxableBase + $tax - $lineRetention;
        $totalSum += $base;
        $totalDiscount += $discount;
        $totalTax += $tax;
        $calculatedRetention += $lineRetention;
    }

    if ($usesLineRetention) {
        $retentionTotal = $calculatedRetention;
    } else {
        $retentionTotal = max(0.0, $retentionTotal);
    }

    $rows = array_values($groups);
    usort($rows, static fn(array $a, array $b): int => $a['rate'] <=> $b['rate']);

    if (!$usesLineRetention && $retentionTotal > 0.0) {
        $baseTotal = array_sum(array_column($groups, 'base'));
        $retentionBase = $baseTotal;
        $allocatedRetention = 0.0;

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
    }

    if ($roundToCents) {
        $totalSum = round($totalSum, 2, PHP_ROUND_HALF_UP);
        $totalDiscount = round($totalDiscount, 2, PHP_ROUND_HALF_UP);
        $totalTax = round($totalTax, 2, PHP_ROUND_HALF_UP);
        $retentionTotal = round($retentionTotal, 2, PHP_ROUND_HALF_UP);
        $retentionBase = round($retentionBase, 2, PHP_ROUND_HALF_UP);

        $allocatedRetention = 0.0;
        foreach ($rows as $index => &$row) {
            $row['base'] = round($row['base'], 2, PHP_ROUND_HALF_UP);
            $row['iva'] = round($row['iva'], 2, PHP_ROUND_HALF_UP);
            $row['retention'] = $index === count($rows) - 1
                ? $retentionTotal - $allocatedRetention
                : round($row['retention'], 2, PHP_ROUND_HALF_UP);
            $allocatedRetention += $row['retention'];
            $row['net'] = round($row['base'] + $row['iva'] - $row['retention'], 2, PHP_ROUND_HALF_UP);
        }
        unset($row);
    }

    $subtotal = $totalSum - $totalDiscount;
    $finalTotal = max(0.0, $subtotal + $totalTax - $retentionTotal);
    if ($roundToCents) {
        $subtotal = round($subtotal, 2, PHP_ROUND_HALF_UP);
        $finalTotal = round($finalTotal, 2, PHP_ROUND_HALF_UP);
    }

    return [
        'rows' => $rows,
        'total_sum' => $totalSum,
        'total_discount' => $totalDiscount,
        'subtotal' => $subtotal,
        'total_tax' => $totalTax,
        'retention' => $retentionTotal,
        'retention_base' => $retentionBase,
        'final_total' => $finalTotal,
    ];
}

function bx_document_tax_withholding_types(): array
{
    return [
        'none' => 'Sem imposto adicional',
        'withholding_6_5' => 'Retenção na Fonte',
        'ipu_rent' => 'Imposto Predial sobre Rendas',
    ];
}

/**
 * Taxas legais usadas quando a empresa não registou uma regra própria em Definições da Empresa:
 * Retenção na Fonte 6,5% e IPU sobre rendas 15%, ambas sobre o valor sem IVA.
 * Uma regra registada (mesmo inactiva) tem sempre prioridade.
 */
function bx_document_tax_default_configuration(string $type): array
{
    $defaults = [
        'withholding_6_5' => 6.5,
        'ipu_rent' => 15.0,
    ];
    if (!isset($defaults[$type])) {
        throw new InvalidArgumentException('Tipo de imposto inválido.');
    }

    return ['rate' => $defaults[$type], 'base_type' => 'net_subtotal', 'enabled' => 1];
}

/** A linha entra na base do imposto: serviços, excepto os classificados para o outro imposto. */
function bx_document_tax_line_is_eligible(array $item, string $type): bool
{
    if (($item['item_type'] ?? '') !== 'service') {
        return false;
    }
    $classification = (string)($item['tax_classification'] ?? 'standard');
    $otherClassification = $type === 'ipu_rent' ? 'withholding_service' : 'rental_income';

    return $classification !== $otherClassification;
}

function bx_document_active_tax_configuration(PDO $pdo, int $companyId, string $type, string $date): array
{
    if (!in_array($type, ['withholding_6_5', 'ipu_rent'], true)) {
        throw new InvalidArgumentException('Tipo de imposto inválido.');
    }

    $stmt = $pdo->prepare(
        'SELECT rate, base_type, enabled
         FROM company_document_tax_rates
         WHERE company_id = ?
           AND tax_type = ?
           AND effective_from <= ?
           AND (effective_to IS NULL OR effective_to >= ?)
         ORDER BY effective_from DESC
         LIMIT 1'
    );
    $stmt->execute([$companyId, $type, $date, $date]);
    $configuration = $stmt->fetch(PDO::FETCH_ASSOC);
    // Sem regra própria da empresa, aplica a taxa legal por omissão.
    if (!$configuration) {
        $configuration = bx_document_tax_default_configuration($type);
    }
    if ((int)$configuration['enabled'] !== 1) {
        throw new RuntimeException('Este imposto não está configurado e validado para a data do documento.', 422);
    }

    return $configuration;
}

function bx_document_tax_withholding_summary(
    array $items,
    array $taxSummary,
    string $type,
    ?array $configuration,
    bool $eligibilityConfirmed,
    ?string $vatRegime
): array {
    if ($type === 'none') {
        $grossTotal = round(
            (float)$taxSummary['subtotal'] + (float)$taxSummary['total_tax'],
            2,
            PHP_ROUND_HALF_UP
        );
        return [
            'type' => 'none',
            'rate' => 0.0,
            'base' => 0.0,
            'amount' => 0.0,
            'description' => null,
            'confirmed' => false,
            'gross_total' => $grossTotal,
            'net_payable' => max(0.0, round($grossTotal - (float)$taxSummary['retention'], 2, PHP_ROUND_HALF_UP)),
        ];
    }

    if (!in_array($type, ['withholding_6_5', 'ipu_rent'], true)) {
        throw new InvalidArgumentException('Tipo de imposto inválido.');
    }
    if (!$eligibilityConfirmed) {
        throw new InvalidArgumentException('Confirme o enquadramento legal, incluindo a operação, a natureza do serviço ou renda, o regime fiscal e os intervenientes.');
    }
    if ($configuration === null || (int)($configuration['enabled'] ?? 0) !== 1) {
        throw new RuntimeException('Este imposto não está configurado e validado para a data do documento.', 422);
    }

    $rate = $configuration['rate'] ?? null;
    $baseType = (string)($configuration['base_type'] ?? '');
    if (!is_numeric($rate) || !is_finite((float)$rate) || (float)$rate <= 0 || (float)$rate > 100) {
        throw new InvalidArgumentException('A taxa configurada para o imposto é inválida.');
    }
    if (!in_array($baseType, ['net_subtotal', 'gross_including_vat'], true)) {
        throw new InvalidArgumentException('A base de incidência do imposto não está configurada.');
    }

    $base = 0.0;
    $eligibleCount = 0;
    foreach ($items as $item) {
        if (!bx_document_tax_line_is_eligible($item, $type)) {
            continue;
        }

        $eligibleCount++;
        $lineBase = bx_document_line_taxable_base($item);
        $lineVatRate = bx_document_effective_tax_rate($item['tax'] ?? 0, $vatRegime);
        $lineVat = $lineBase * ($lineVatRate / 100);
        $base += $baseType === 'gross_including_vat' ? $lineBase + $lineVat : $lineBase;
    }
    if ($eligibleCount === 0) {
        $message = $type === 'ipu_rent'
            ? 'O IPU só pode ser aplicado a linhas de serviço (rendas).'
            : 'A retenção na fonte só pode ser aplicada a linhas de serviço.';
        throw new InvalidArgumentException($message);
    }
    if (bx_document_retention_total($items) > 0) {
        throw new InvalidArgumentException('Existe retenção configurada por artigo. Remova a retenção duplicada antes de seleccionar a retenção documental.');
    }

    $base = round($base, 2, PHP_ROUND_HALF_UP);
    $amount = round($base * ((float)$rate / 100), 2, PHP_ROUND_HALF_UP);
    $grossTotal = round(
        (float)$taxSummary['subtotal'] + (float)$taxSummary['total_tax'],
        2,
        PHP_ROUND_HALF_UP
    );
    $description = $type === 'ipu_rent'
        ? sprintf('Imposto Predial sobre Rendas — taxa aplicável (%s%%)', number_format((float)$rate, 2, ',', '.'))
        : sprintf('Retenção na Fonte — %s%%', number_format((float)$rate, 2, ',', '.'));

    return [
        'type' => $type,
        'rate' => round((float)$rate, 4, PHP_ROUND_HALF_UP),
        'base' => $base,
        'amount' => $amount,
        'description' => $description,
        'confirmed' => true,
        'gross_total' => $grossTotal,
        'net_payable' => max(
            0.0,
            round($grossTotal - (float)$taxSummary['retention'] - $amount, 2, PHP_ROUND_HALF_UP)
        ),
    ];
}

function bx_document_company_tax_summary(PDO $pdo, array $items, int $companyId): array
{
    if ($companyId <= 0) {
        throw new InvalidArgumentException('Empresa inválida.');
    }

    $stmt = $pdo->prepare('SELECT vat_regime FROM companies WHERE id = ? LIMIT 1');
    $stmt->execute([$companyId]);
    $regime = $stmt->fetchColumn();
    if ($regime === false) {
        throw new RuntimeException('Empresa não encontrada.');
    }

    return bx_document_tax_summary($items, $regime === null ? null : (string)$regime, null, true);
}
