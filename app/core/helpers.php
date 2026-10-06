<?php
// Funções globais da SPA.
// t(), formatName() e currencySelects() vêm de app/helpers/translation.php e functions.php (ERP):
// redeclará-las aqui dava "Cannot redeclare" assim que uma página fazia require_once desses ficheiros.

/** Só devolve o URL do asset se o ficheiro existir em public/ (evita 404 em ficheiros opcionais). */
function asset_if_exists(string $path): ?string {
    $file = ROOT . '/public/' . ltrim($path, '/');
    return is_file($file) ? '/' . ltrim($path, '/') . '?v=' . filemtime($file) : null;
}

/** Destino pós-login seguro: só caminhos internos, nunca outro domínio nem o próprio /login. */
function safe_next(?string $next): ?string {
    $next = (string)$next;
    if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) return null;
    if (preg_match('~[\x00-\x1f]~', $next)) return null;
    $p = parse_url($next, PHP_URL_PATH);
    if ($p === null || $p === false || in_array(rtrim($p, '/'), ['/login', '/login.php'], true)) return null;
    return $next;
}
