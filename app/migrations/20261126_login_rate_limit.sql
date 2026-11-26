-- Limite de tentativas de login no servidor (por IP e por identificador), em vez de só por sessão:
-- apagar o cookie deixava fazer tentativas ilimitadas. Usado por app/helpers/rate_limit.php.
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket       CHAR(64)     NOT NULL,          -- sha256 de "<tipo>:<valor>" (não guarda o IP/email em claro)
    hits         INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME     NOT NULL,
    PRIMARY KEY (bucket),
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
