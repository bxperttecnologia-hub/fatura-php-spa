<?php
class AuthController {
    public function login(array $in): array {
        $email = trim($in['email'] ?? '');
        $pass  = (string)($in['password'] ?? '');
        if ($email === '' || $pass === '') {
            throw new HttpException(422, 'Preenche email e palavra-passe');
        }
        return ['data' => ['user' => Auth::login($email, $pass)]];
    }
    public function logout(): array {
        Auth::logout();
        return ['message' => 'Sessão terminada'];
    }
    public function me(): array {
        return ['data' => ['user' => Auth::user()]];
    }
}
