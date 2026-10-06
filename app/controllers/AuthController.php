<?php
class AuthController {
    private const MAX_FAILS = 5;
    private const WINDOW    = 300;   // segundos

    public function login(array $in): array {
        $id = trim((string)($in['user_email'] ?? $in['email'] ?? ''));

        // Palavra-passe: cifrada com a chave pública (como no login antigo) ou em claro (fallback sem JSEncrypt)
        $pass = (string)($in['password'] ?? '');
        if (!empty($in['password_enc'])) {
            $pass = $this->decrypt((string)$in['password_enc']);
        }
        if ($id === '' || $pass === '') throw new HttpException(422, 'Preencha todos os campos.');

        // Limite simples de tentativas por sessão (não substitui um rate-limit no servidor web)
        $f = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
        if (time() - $f['t'] > self::WINDOW) $f = ['n' => 0, 't' => time()];
        if ($f['n'] >= self::MAX_FAILS) {
            throw new HttpException(429, 'Demasiadas tentativas. Aguarde alguns minutos e tente de novo.');
        }

        try {
            $user = Auth::login($id, $pass, !empty($in['remember_me']));
        } catch (HttpException $e) {
            if ($e->status === 401) {
                $f['n']++; $f['t'] = $f['t'] ?: time();
                $_SESSION['login_fail'] = $f;
            }
            throw $e;
        }
        unset($_SESSION['login_fail']);
        return ['data' => ['user' => $user]];
    }

    public function logout(): array {
        Auth::logout();
        return ['message' => 'Sessão terminada'];
    }

    public function me(): array {
        return ['data' => ['user' => Auth::publicUser()]];
    }

    private function decrypt(string $b64): string {
        $file = ROOT . '/app/keys/private.key';
        $key  = is_file($file) ? openssl_pkey_get_private((string)file_get_contents($file)) : false;
        $out  = '';
        if (!$key || !openssl_private_decrypt((string)base64_decode($b64, true), $out, $key) || $out === '') {
            throw new HttpException(422, 'Erro na descriptografia da senha.');
        }
        return $out;
    }
}
