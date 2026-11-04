<?php
require_once __DIR__ . '/../helpers/env.php';

final class DocumentApiController
{
    public function config(array $input): array
    {
        $user = Auth::user();
        $companyId = (int)($user['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new HttpException(403, 'Empresa inválida.');
        }

        // DOCUMENT_API_BASE_URL sobrepõe o destino padrão da API de documentos.
        $apiBaseUrl = trim((string)bx_env('DOCUMENT_API_BASE_URL'));
        if ($apiBaseUrl === '') {
            $isProduction = defined('APP_ENV') && APP_ENV === 'production';
            $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
            $isProductionHost = in_array($host, [
                'api-crm.bxpert.co.ao',
                'www.api-crm.bxpert.co.ao',
            ], true);
            $apiBaseUrl = $isProduction || $isProductionHost
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
