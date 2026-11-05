<?php

function bx_currency_iso_code(?string $currencyCode): string
{
  $code = strtoupper(trim((string)$currencyCode));
  return $code === 'AKZ' ? 'AOA' : $code;
}

function bx_currency_display_symbol(?string $currencyCode, ?string $configuredSymbol): string
{
  if (bx_currency_iso_code($currencyCode) === 'AOA') {
    return 'Kz';
  }

  return trim((string)($configuredSymbol ?? ''));
}

/**
 * Moeda de um documento a gravar: vazio → AOA, "AKZ" → AOA; tem de existir em currencies.
 * Evita faturas com moeda inválida, que deixavam de aparecer na lista e na pré-visualização.
 */
function bx_document_currency(PDO $pdo, ?string $currencyCode): string
{
  $code = bx_currency_iso_code($currencyCode) ?: 'AOA';
  $stmt = $pdo->prepare('SELECT 1 FROM currencies WHERE iso_code = ? LIMIT 1');
  $stmt->execute([$code]);
  if (!$stmt->fetchColumn()) {
    throw new InvalidArgumentException('Moeda inválida: ' . $code . '. Escolha uma moeda da lista.');
  }
  return $code;
}
