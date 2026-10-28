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
