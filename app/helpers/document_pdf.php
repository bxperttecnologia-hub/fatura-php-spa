<?php

require_once __DIR__ . '/../../vendor/autoload.php';

function bx_local_file_uri(string $path): string
{
    $normalizedPath = str_replace('\\', '/', $path);

    if (preg_match('/^[A-Za-z]:\//', $normalizedPath)) {
        return 'file:///' . $normalizedPath;
    }

    if (str_starts_with($normalizedPath, '/')) {
        return 'file://' . $normalizedPath;
    }

    throw new RuntimeException('Caminho local inválido para gerar o PDF.');
}

function bx_generate_document_pdf(
    string $templatePath,
    int $documentId,
    string $filePrefix,
    bool $showPageNumbers = false
): string
{
    $publicRoot = realpath(dirname($templatePath, 3));
    if ($publicRoot === false) {
        throw new RuntimeException('Não foi possível localizar os ficheiros públicos.');
    }

    $previousGet = $_GET;
    $previousBufferLevel = ob_get_level();
    $_GET['id'] = $documentId;

    ob_start();
    try {
        require $templatePath;
        $html = ob_get_clean();
    } catch (Throwable $error) {
        while (ob_get_level() > $previousBufferLevel) {
            ob_end_clean();
        }
        $_GET = $previousGet;
        throw $error;
    }
    $_GET = $previousGet;

    if (!is_string($html) || $html === '') {
        throw new RuntimeException('Não foi possível preparar o documento para PDF.');
    }

    $html = preg_replace_callback(
        '~<link\b[^>]*>~i',
        static function (array $match) use ($publicRoot): string {
            if (!preg_match('~\bhref=["\']([^"\']+)["\']~i', $match[0], $hrefMatch)) {
                return $match[0];
            }

            $path = parse_url(html_entity_decode($hrefMatch[1], ENT_QUOTES, 'UTF-8'), PHP_URL_PATH);
            $filename = basename((string)$path);
            if (!in_array($filename, ['invoice.css', 'invoice_footer.css'], true)) {
                return $match[0];
            }

            $cssPath = $publicRoot . DIRECTORY_SEPARATOR . 'invoices' . DIRECTORY_SEPARATOR . $filename;
            $css = file_get_contents($cssPath);
            if ($css === false) {
                throw new RuntimeException('Não foi possível carregar os estilos do documento.');
            }

            return '<style>' . str_ireplace('</style', '<\/style', $css) . '</style>';
        },
        $html
    );

    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicBasePath = str_replace('\\', '/', dirname(dirname(dirname($scriptPath))));
    $publicBasePath = ($publicBasePath === '/' || $publicBasePath === '.') ? '' : rtrim($publicBasePath, '/');
    $assetPrefix = htmlspecialchars($publicBasePath . '/assets/img/companies/', ENT_QUOTES, 'UTF-8');
    $companyAssetsPath = realpath($publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'companies');

    if ($companyAssetsPath !== false) {
        $html = str_replace($assetPrefix, bx_local_file_uri($companyAssetsPath) . '/', $html);
    }

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $options->set('defaultMediaType', 'print');
    $options->setChroot($publicRoot);

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->render();
    if ($showPageNumbers) {
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');
        $dompdf->getCanvas()->page_text(520, 820, '{PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0, 0, 0]);
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), $filePrefix);
    if ($temporaryPath === false || file_put_contents($temporaryPath, $dompdf->output()) === false) {
        if (is_string($temporaryPath) && is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
        throw new RuntimeException('Não foi possível guardar o PDF temporário.');
    }

    return $temporaryPath;
}
