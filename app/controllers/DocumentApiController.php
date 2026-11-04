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

        // Os PDFs são gerados pela própria app (GET /api/documents/..., ver public/document_pdf.php),
        // por isso o destino padrão é a própria origem. DOCUMENT_API_BASE_URL (.env) sobrepõe-no.
        $apiBaseUrl = trim((string)bx_env('DOCUMENT_API_BASE_URL'));
        if ($apiBaseUrl === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $apiBaseUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
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
