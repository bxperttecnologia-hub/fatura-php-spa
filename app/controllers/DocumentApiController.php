<?php

final class DocumentApiController
{
    public function config(array $input): array
    {
        $user = Auth::user();
        $companyId = (int)($user['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new HttpException(403, 'Empresa inválida.');
        }

        $apiBaseUrl = trim((string)getenv('DOCUMENT_API_BASE_URL'));
        if ($apiBaseUrl === '') {
            $apiBaseUrl = APP_ENV === 'production'
                ? 'https://api-sandibox.bxpert.co.ao'
                : 'http://localhost:3004';
        }
        if (!filter_var($apiBaseUrl, FILTER_VALIDATE_URL) || !in_array(parse_url($apiBaseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new HttpException(503, 'A URL da API de documentos não está configurada corretamente.');
        }

        return [
            'data' => [
                'api_base_url' => rtrim($apiBaseUrl, '/'),
                'company_id' => $companyId,
            ],
        ];
    }
}
