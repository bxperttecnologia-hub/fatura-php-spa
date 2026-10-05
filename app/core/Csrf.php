<?php
class Csrf {
    public static function token(): string {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }
    public static function check(string $sent): void {
        if (!hash_equals(self::token(), $sent)) {
            throw new HttpException(403, 'Token CSRF inválido');
        }
    }
}
