<?php
/**
 * Autenticação da SPA sobre o modelo de sessão do ERP:
 *   users + company_has_user + companies (login) e tabela `sessions` (token com expiração deslizante).
 * Todas as datas da tabela `sessions` estão em UTC.
 */
class Auth {
    private static ?bool $valid = null;     // resultado da verificação neste pedido
    private static bool $renew = true;      // false => só verifica, não prolonga a sessão

    /** Chamar antes de user()/check() quando o pedido NÃO deve contar como actividade (ex.: auth/me). */
    public static function noRenew(): void { self::$renew = false; }

    /** Sessão válida? Confirma a sessão PHP, o token na BD, o dono e a expiração. Fecha a sessão se inválida. */
    public static function check(): bool {
        if (self::$valid !== null) return self::$valid;

        $u     = $_SESSION['user']  ?? null;
        $token = $_SESSION['token'] ?? null;

        // Nota: translation.php grava $_SESSION['user']['lang'] também para visitantes,
        // por isso "existe $_SESSION['user']" NÃO significa autenticado: exige-se id + token.
        if (!is_array($u) || empty($u['id']) || !is_string($token) || $token === '') {
            return self::$valid = self::reject(!empty($u['id']));
        }

        try {
            $st = Database::pdo()->prepare('SELECT user_id, created_at, expires_at FROM sessions WHERE session_token = ? LIMIT 1');
            $st->execute([$token]);
            $row = $st->fetch();
        } catch (Throwable $e) {
            error_log('Auth::check: ' . $e->getMessage());
            return self::$valid = self::reject(true);        // falha fechado
        }

        $now = time();
        $exp = self::ts($row['expires_at'] ?? null);
        $cre = self::ts($row['created_at'] ?? null);

        if (!$row || (int)$row['user_id'] !== (int)$u['id'] || $exp === null || $cre === null || $exp <= $now) {
            if ($row && ($exp === null || $exp <= $now)) self::deleteToken($token);
            return self::$valid = self::reject(true);
        }

        if (self::$renew) {
            // TTL fixo guardado no login. (A lógica antiga usava expires-created, que crescia a cada renovação.)
            $ttl = (int)($_SESSION['ttl'] ?? 0);
            if ($ttl <= 0) $ttl = max(1800, min(86400, $exp - $cre));
            if ($exp - $now < $ttl - 30) {                    // evita um UPDATE por pedido
                $new = $now + $ttl;
                try {
                    Database::pdo()->prepare('UPDATE sessions SET expires_at = ? WHERE session_token = ?')
                        ->execute([gmdate('Y-m-d H:i:s', $new), $token]);
                    $_SESSION['expires_at'] = gmdate('Y-m-d H:i:s', $new);
                } catch (Throwable $e) { error_log('Auth::renew: ' . $e->getMessage()); }
            }
        }
        return self::$valid = true;
    }

    public static function user(): ?array {
        return self::check() ? $_SESSION['user'] : null;
    }

    /** Subconjunto seguro para enviar ao browser (window.APP.user / resposta do login). */
    public static function publicUser(): ?array {
        $u = self::user();
        if (!$u) return null;
        $keys = ['id', 'nome', 'name', 'email', 'username', 'company_id', 'name_company', 'role', 'image'];
        return array_intersect_key($u, array_flip($keys));
    }

    public static function require(): void {
        if (!self::check()) throw new HttpException(401, 'Sessão expirada ou inexistente');
    }

    public static function login(string $identifier, string $password, bool $remember = false): array {
        $pdo = Database::pdo();

        // Telefone: só dígitos, últimos 9 (ex.: +244 923 456 789 -> 923456789)
        $phone = null;
        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $digits = preg_replace('/\D+/', '', $identifier);
            if (strlen($digits) >= 9) $phone = substr($digits, -9);
        }

        $st = $pdo->prepare(
            "SELECT u.*,
                    chu.company_id, chu.role, c.name name_company, c.registration_number, c.email email_company,
                    cc.iso_code, cc.currency, cc.symbol, cc.position
               FROM users u
               JOIN company_has_user chu ON chu.user_id = u.id
               JOIN companies c ON c.id = chu.company_id
          LEFT JOIN currencies cc ON cc.iso_code = u.currency
              WHERE u.username = :username
                 OR u.email = :email
                 OR RIGHT(REGEXP_REPLACE(u.phone, '[^0-9]', ''), 9) = :phone
              LIMIT 1"
        );
        $st->execute([':username' => $identifier, ':email' => $identifier, ':phone' => $phone]);
        $user = $st->fetch();

        if (!$user || !password_verify($password, (string)($user['password'] ?? ''))) {
            throw new HttpException(401, 'Usuário ou senha inválidos.');
        }
        unset($user['password']);

        $ttl     = $remember ? 86400 : 1800;
        $token   = bin2hex(random_bytes(16));
        $created = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + $ttl);

        $pdo->prepare('INSERT INTO sessions (user_id, session_token, expires_at, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $token, $expires, $created]);

        session_regenerate_id(true);                         // evita session fixation
        $user['nome'] = $user['name'] ?? $user['username'] ?? $user['email'] ?? '';   // alias usado pela SPA
        $user['lang'] = $_SESSION['user']['lang'] ?? 'angola';                       // mantém o idioma escolhido
        $_SESSION['user']       = $user;
        $_SESSION['token']      = $token;
        $_SESSION['expires_at'] = $expires;
        $_SESSION['ttl']        = $ttl;

        setcookie('session_token', $token, [
            'expires' => time() + $ttl, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true, 'samesite' => 'Lax',
        ]);

        self::$valid = true;
        return self::publicUser();
    }

    public static function logout(): void {
        if (is_string($_SESSION['token'] ?? null)) self::deleteToken($_SESSION['token']);
        self::clear();
        session_regenerate_id(true);   // mantém $_SESSION['csrf']: a página continua aberta e precisa dele
        self::$valid = false;
    }

    // ---- internos ----

    /** Limpa os dados de autenticação (sem destruir a sessão PHP, para manter o CSRF). */
    private static function reject(bool $clear): bool {
        if ($clear) self::clear();
        return false;
    }

    private static function clear(): void {
        unset($_SESSION['user'], $_SESSION['token'], $_SESSION['expires_at'], $_SESSION['ttl']);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie('session_token', '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
    }

    private static function deleteToken(string $token): void {
        try { Database::pdo()->prepare('DELETE FROM sessions WHERE session_token = ?')->execute([$token]); }
        catch (Throwable $e) { error_log('Auth::deleteToken: ' . $e->getMessage()); }
    }

    private static function ts(?string $s): ?int {
        if (!$s) return null;
        try { return (new DateTimeImmutable($s, new DateTimeZone('UTC')))->getTimestamp(); }
        catch (Throwable) { return null; }
    }
}
