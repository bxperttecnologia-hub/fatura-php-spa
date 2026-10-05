<?php
// Funções globais usadas pelos templates (nav.php, modais, etc.)

/** Tradução (passthrough por agora; ponto único para i18n futuro). */
function t(?string $text): string {
    return (string)($text ?? '');
}

/** "joão  pedro da silva" -> "João Silva" (primeiro + último nome). */
function formatName(?string $name): string {
    $name = trim(preg_replace('/\s+/', ' ', (string)$name));
    if ($name === '') return '';
    // mbstring pode não estar activo no alojamento: fallback sem acentos correctos
    $titled = function_exists('mb_convert_case')
        ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8')
        : ucwords(strtolower($name));
    $parts = explode(' ', $titled);
    return count($parts) > 1 ? $parts[0] . ' ' . end($parts) : $parts[0];
}

/** <option>s de moedas. */
function currencySelects(string $selected = 'AOA'): string {
    $moedas = ['AOA' => 'Kwanza (AOA)', 'USD' => 'Dólar (USD)', 'EUR' => 'Euro (EUR)'];
    $html = '';
    foreach ($moedas as $code => $label) {
        $sel = $code === $selected ? ' selected' : '';
        $html .= '<option value="' . $code . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES) . '</option>';
    }
    return $html;
}

/** Só devolve o URL do asset se o ficheiro existir em public/ (evita 404 em ficheiros opcionais). */
function asset_if_exists(string $path): ?string {
    $file = ROOT . '/public/' . ltrim($path, '/');
    return is_file($file) ? '/' . ltrim($path, '/') . '?v=' . filemtime($file) : null;
}
