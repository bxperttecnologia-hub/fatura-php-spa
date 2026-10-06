<?php

declare(strict_types=1);

if (!function_exists('ai_api_base_url')) {
    function ai_api_base_url(): string
    {
        if (defined('AI_API_BASE_URL') && AI_API_BASE_URL !== '') {
            return (string) AI_API_BASE_URL;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
        $env = getenv('APP_ENV') ?: (str_contains($host, 'api-crm.bxpert.co.ao') ? 'production' : 'development');

        return $env === 'production' ? 'https://api-sandibox.bxpert.co.ao' : 'http://localhost:3000';
    }
}

if (!function_exists('ai_api_url')) {
    function ai_api_url(string $path, array $query = []): string
    {
        $base = rtrim(ai_api_base_url(), '/');
        $normalizedPath = '/' . ltrim($path, '/');
        $queryString = http_build_query($query);

        if ($queryString === '') {
            return $base . $normalizedPath;
        }

        return $base . $normalizedPath . '?' . $queryString;
    }
}

if (!function_exists('ai_api_json')) {
    function ai_api_json(string $path, array $query = [], array $headers = [], int $timeout = 12): array
    {
        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'error' => 'A extensão cURL do PHP não está ativa.',
                'detail' => 'Ativa "extension=curl" no php.ini e reinicia o Apache.',
            ];
        }

        $url = ai_api_url($path, $query);
        $ch = curl_init($url);

        $requestHeaders = ['Accept: application/json'];
        foreach ($headers as $key => $value) {
            $requestHeaders[] = $key . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'error' => 'Serviço de IA indisponível',
                'detail' => $curlError ?: 'HTTP ' . $httpCode,
                'http_code' => $httpCode,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'error' => 'Resposta inválida do serviço de IA',
                'detail' => $response,
                'http_code' => $httpCode,
            ];
        }

        $decoded['success'] = $httpCode >= 200 && $httpCode < 300;
        $decoded['http_code'] = $httpCode;
        $decoded['ai_base_url'] = ai_api_base_url();

        return $decoded;
    }
}
