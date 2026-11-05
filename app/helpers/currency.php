<?php

/** Moeda única do sistema por agora: Kwanza. */
if (!defined('BX_DEFAULT_CURRENCY')) {
  define('BX_DEFAULT_CURRENCY', 'AOA');
}

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
  // Por agora todos os documentos são emitidos em Kwanza, seja qual for a moeda enviada.
  return BX_DEFAULT_CURRENCY;
}
