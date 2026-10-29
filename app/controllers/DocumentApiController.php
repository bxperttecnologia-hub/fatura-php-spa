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

        // Por omissão os PDFs são gerados pela própria aplicação (public/document_pdf.php).
        // DOCUMENT_API_BASE_URL só é preciso para usar um serviço externo.
        $apiBaseUrl = trim((string)getenv('DOCUMENT_API_BASE_URL'));
        if ($apiBaseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $apiBaseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
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
