<?php
declare(strict_types=1);

final class ProfileApiController
{
    private const PROFILE_FIELDS = [
        'username',
        'name',
        'phone',
        'email',
        'address',
        'address_district',
        'country',
        'lang',
    ];

    public function update(array $input): array
    {
        $user = Auth::user();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new HttpException(401, 'Sessão expirada. Faça login novamente.');
        }

        $profile = [];
        foreach (self::PROFILE_FIELDS as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if (!is_string($input[$field])) {
                throw new HttpException(400, "O campo $field deve ser texto.");
            }

            $value = trim($input[$field]);
            if ($value === '' && $field !== 'address') {
                continue;
            }
            if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new HttpException(422, 'E-mail inválido.');
            }
            if ($field === 'lang' && !in_array($value, ['angola', 'brasil'], true)) {
                continue;
            }
            $profile[$field] = $value;
        }

        if ($profile === []) {
            throw new HttpException(400, 'Nenhum campo de perfil válido para atualizar.');
        }

        if (!function_exists('curl_init')) {
            throw new HttpException(503, 'A extensão cURL do PHP é necessária para atualizar o perfil.');
        }

        $sharedSecret = trim((string)getenv('DOCUMENT_API_TOKEN_SECRET'));
        if ($sharedSecret === '') {
            $sharedSecret = defined('DB_PASS') ? (string)DB_PASS : trim((string)getenv('DB_PASS'));
        }
        if ($sharedSecret === '') {
            $sharedSecret = trim((string)getenv('DB_PASSWORD'));
        }
        if ($sharedSecret === '') {
            throw new HttpException(503, 'A integração segura com a API não está configurada.');
        }

        $apiBaseUrl = trim((string)getenv('DOCUMENT_API_BASE_URL'));
        if ($apiBaseUrl === '') {
            $apiBaseUrl = defined('APP_ENV') && APP_ENV === 'production'
                ? 'https://api-sandibox.bxpert.co.ao'
                : 'http://localhost:3004';
        }
        if (
            !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)
            || !in_array(parse_url($apiBaseUrl, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new HttpException(503, 'A URL da API não está configurada corretamente.');
        }

        $body = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            throw new HttpException(400, 'Não foi possível serializar os dados do perfil.');
        }

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $key = hash_hmac('sha256', 'bxpert:profile-update:v1', $sharedSecret, true);
        $message = $userId . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
        $signature = hash_hmac('sha256', $message, $key);

        $curl = curl_init(rtrim($apiBaseUrl, '/') . '/api/profile');
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Profile-User-Id: ' . $userId,
                'X-Profile-Timestamp: ' . $timestamp,
                'X-Profile-Nonce: ' . $nonce,
                'X-Profile-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            error_log('Profile API connection failed: ' . $curlError);
            throw new HttpException(502, 'Não foi possível ligar à API para atualizar o perfil.');
        }

        $response = json_decode((string)$responseBody, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($response)) {
            $message = is_array($response)
                ? (string)($response['error'] ?? 'Erro ao atualizar perfil.')
                : 'Resposta inválida da API ao atualizar perfil.';
            error_log(sprintf('Profile API returned HTTP %d: %s', $statusCode, $message));
            throw new HttpException(
                in_array($statusCode, [400, 409, 422], true) ? $statusCode : 502,
                in_array($statusCode, [400, 409, 422], true)
                    ? $message
                    : 'A API não conseguiu atualizar o perfil.'
            );
        }

        foreach ($profile as $field => $value) {
            $_SESSION['user'][$field] = $value;
            if ($field === 'name') {
                $_SESSION['user']['nome'] = $value;
            }
        }

        return [
            'status' => 'success',
            'rowsAffected' => (int)($response['rowsAffected'] ?? 0),
        ];
    }
}
