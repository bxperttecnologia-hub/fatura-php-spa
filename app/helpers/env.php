<?php
// Variáveis de ambiente sem depender de putenv(): no aaPanel (e noutros alojamentos)
// putenv está em disable_functions, e getenv() deixa de ver o que o .env carregou.
// bx_putenv() guarda sempre em $_ENV/$_SERVER e só chama putenv se estiver disponível;
// bx_env() lê $_ENV, depois $_SERVER e por fim o ambiente do processo.

if (!function_exists('bx_putenv')) {
    function bx_putenv(string $assignment): bool
    {
        $pos = strpos($assignment, '=');
        if ($pos === false || $pos === 0) {
            return false;
        }
        $key = substr($assignment, 0, $pos);
        $value = substr($assignment, $pos + 1);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        if (function_exists('putenv')) {
            putenv($assignment);
        }
        return true;
    }
}

if (!function_exists('bx_load_env')) {
    /**
     * Carrega um ficheiro .env (CHAVE=valor por linha; # comenta). Se a chave
     * se repetir, fica a última. Linhas sem "=" são ignoradas.
     */
    function bx_load_env(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }
            bx_putenv($key . '=' . trim($value, " \t\n\r\0\x0B\"'"));
        }
    }
}

if (!function_exists('bx_env')) {
    /** @return string|false Igual a getenv(): false quando a variável não existe. */
    function bx_env(string $key)
    {
        if (isset($_ENV[$key]) && is_scalar($_ENV[$key])) {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && is_scalar($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }
        return getenv($key);
    }
}
