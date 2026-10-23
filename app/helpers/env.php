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
