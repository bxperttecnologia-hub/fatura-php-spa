<?php

if (!function_exists('company_logo_api_base_url')) {
    function company_logo_api_base_url(): string
    {
        $apiBaseUrl = trim((string) getenv('DOCUMENT_API_BASE_URL'));
        if ($apiBaseUrl === '') {
            $isProduction = defined('APP_ENV') && APP_ENV === 'production';
            $apiBaseUrl = $isProduction
                ? 'https://api-sandibox.bxpert.co.ao'
                : 'http://localhost:3004';
        }

        if (
            !filter_var($apiBaseUrl, FILTER_VALIDATE_URL)
            || !in_array(parse_url($apiBaseUrl, PHP_URL_SCHEME), ['http', 'https'], true)
        ) {
            throw new RuntimeException('A URL da API de documentos não está configurada corretamente.', 503);
        }

        return rtrim($apiBaseUrl, '/');
    }
}

if (!function_exists('company_logo_src')) {
    function company_logo_src($logoUrl, string $publicBasePath = ''): string
    {
        $logoUrl = trim((string) ($logoUrl ?? ''));
        if ($logoUrl === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $logoUrl)) {
            return $logoUrl;
        }

        $filename = basename((string) parse_url($logoUrl, PHP_URL_PATH));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return '';
        }

        return rtrim($publicBasePath, '/') . '/assets/img/companies/' . rawurlencode($filename);
    }
}

if (!function_exists('company_logo_mime_type')) {
    function company_logo_mime_type(string $signature): ?string
    {
        if (str_starts_with($signature, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($signature, "\xff\xd8\xff")) {
            return 'image/jpeg';
        }
        if (str_starts_with($signature, 'RIFF') && substr($signature, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        if (in_array(substr($signature, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            return 'image/gif';
        }
        return null;
    }
}

if (!function_exists('company_logo_signing_key')) {
    function company_logo_signing_key(): string
    {
        $sharedSecret = trim((string) getenv('COMPANY_LOGO_UPLOAD_TOKEN'));
        if ($sharedSecret === '') {
            $sharedSecret = trim((string) getenv('DOCUMENT_API_TOKEN_SECRET'));
        }
        if ($sharedSecret === '' && defined('DB_PASS')) {
            $sharedSecret = (string) DB_PASS;
        }

        if ($sharedSecret === '') {
            throw new RuntimeException('Não foi possível obter uma chave segura para enviar o logótipo.', 503);
        }

        return hash_hmac('sha256', 'bxpert:company-logo-upload:v1', $sharedSecret, true);
    }
}

if (!function_exists('upload_company_logo_to_api')) {
    function upload_company_logo_to_api(array $file, int $companyId): string
    {
        if (!function_exists('curl_init') || !class_exists('CURLFile')) {
            throw new RuntimeException('A extensão cURL do PHP é necessária para enviar o logótipo.', 503);
        }

        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $status = $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE ? 413 : 400;
            throw new RuntimeException('Não foi possível receber o ficheiro do logótipo.', $status);
        }

        $filePath = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        if ($filePath === '' || !is_uploaded_file($filePath)) {
            throw new RuntimeException('Ficheiro de logótipo inválido.', 400);
        }
        if ($fileSize <= 0 || $fileSize > 2 * 1024 * 1024) {
            throw new RuntimeException('O logótipo deve ter no máximo 2 MB.', $fileSize > 2 * 1024 * 1024 ? 413 : 400);
        }

        $signature = file_get_contents($filePath, false, null, 0, 12);
        $mimeType = is_string($signature) ? company_logo_mime_type($signature) : null;
        $extensionByMime = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if ($mimeType === null || !isset($extensionByMime[$mimeType])) {
            throw new RuntimeException('Formato de logótipo inválido.', 400);
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $fileHash = hash_file('sha256', $filePath);
        if ($fileHash === false) {
            throw new RuntimeException('Não foi possível validar o logótipo.', 400);
        }
        $message = $companyId . "\n" . $timestamp . "\n" . $nonce . "\n" . $fileHash;
        $signature = hash_hmac('sha256', $message, company_logo_signing_key());

        $apiBaseUrl = company_logo_api_base_url();
        $curl = curl_init($apiBaseUrl . '/api/company-logos');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'company_id' => (string) $companyId,
                'logo' => new CURLFile($filePath, $mimeType, 'logo.' . $extensionByMime[$mimeType]),
            ],
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Company-Logo-Timestamp: ' . $timestamp,
                'X-Company-Logo-Nonce: ' . $nonce,
                'X-Company-Logo-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            error_log('Company logo API connection failed: ' . $curlError);
            throw new RuntimeException('Não foi possível ligar à API para enviar o logótipo.', 502);
        }

        $response = json_decode((string) $responseBody, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($response) || empty($response['success'])) {
            $apiMessage = is_array($response) ? (string) ($response['error'] ?? '') : '';
            error_log(sprintf('Company logo API returned HTTP %d: %s', $statusCode, $apiMessage));
            $status = in_array($statusCode, [400, 413], true) ? $statusCode : 502;
            throw new RuntimeException(
                $status === 502 ? 'A API não conseguiu guardar o logótipo.' : ($apiMessage ?: 'Logótipo inválido.'),
                $status
            );
        }

        $logoPath = (string) ($response['logo_url'] ?? '');
        $expectedPattern = '~^/api/company-logos/company-logo-' . preg_quote((string) $companyId, '~') . '-[a-f0-9]{32}\.(png|jpg|webp|gif)$~';
        if (!preg_match($expectedPattern, $logoPath)) {
            error_log('Company logo API returned an invalid logo path.');
            throw new RuntimeException('A API devolveu uma referência de logótipo inválida.', 502);
        }

        return $apiBaseUrl . $logoPath;
    }
}
