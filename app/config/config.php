<?php
// Configuração da aplicação: os valores vêm todos do .env na raiz do projeto.
// Ao migrar de servidor basta alterar o .env; este ficheiro não tem dados do ambiente.
require_once __DIR__ . '/../helpers/env.php';

bx_load_env(dirname(__DIR__, 2) . '/.env');

if (!function_exists('bx_config')) {
    /** Valor do .env, ou $default se não existir. */
    function bx_config(string $key, string $default = ''): string
    {
        $value = bx_env($key);
        return $value === false ? $default : $value;
    }
}

define('APP_ENV', bx_config('APP_ENV', 'production'));

define('DB_HOST', bx_config('DB_HOST', '127.0.0.1'));
define('DB_PORT', bx_config('DB_PORT', '3306'));
define('DB_NAME', bx_config('DB_NAME'));
define('DB_USER', bx_config('DB_USER'));
define('DB_PASS', bx_config('DB_PASS'));
// sql_mode da ligação do BX. A app foi feita para o modo do XAMPP (sem STRICT_TRANS_TABLES);
// num servidor em modo estrito, INSERTs que omitem colunas NOT NULL sem DEFAULT falhavam
// (ex.: registo -> "Field 'identification_type' doesn't have a default value").
// Só afecta as ligações do BX, não o servidor MySQL. Vazio = usa o modo do servidor.
define('DB_SQL_MODE', bx_config('DB_SQL_MODE', 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'));

// Faturação eletrónica AGT (agt-facturacao-api)
define('APP_TIMEZONE', bx_config('APP_TIMEZONE', 'Africa/Luanda'));
define('AGT_API_URL', bx_config('AGT_API_URL', 'http://localhost:4000'));
define('AGT_API_KEY', bx_config('AGT_API_KEY'));
define('AGT_KEY_SECRET', bx_config('AGT_KEY_SECRET'));
define('AGT_ENVIRONMENT', bx_config('AGT_ENVIRONMENT', 'test'));
