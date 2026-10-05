<?php
class Auth {
    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }
    public static function require(): void {
        if (!self::user()) throw new HttpException(401, 'Não autenticado');
    }
    public static function login(string $email, string $password): array {
        $st = Database::pdo()->prepare('SELECT id,nome,email,password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            throw new HttpException(401, 'Credenciais inválidas');
        }
        session_regenerate_id(true); // evita session fixation
        $_SESSION['user'] = [
            'id'    => (int)$u['id'],
            'nome'  => $u['nome'],
            'name'  => $u['nome'],   // alias usado por nav.php
            'email' => $u['email'],
        ];
        return $_SESSION['user'];
    }
    public static function logout(): void {
        // Mantém o token CSRF: o JS da página continua aberto e precisa dele para o próximo login.
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }
}
