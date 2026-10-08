<?php
class FileController
{
    private const ALLOWED = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf'];

    public function show(array $in, array $p): array
    {
        $company = (int)$p['company'];
        $name    = basename($p['name']);
        $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $dir  = realpath(ROOT . "public/assets/companies/");
        $path = $dir ? realpath("$dir/$name") : false;

        if (
            !in_array($ext, self::ALLOWED, true)
            || !$path
            || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR)
            || !is_file($path)
        ) {
            throw new HttpException(404, 'Ficheiro não encontrado');
        }

        header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');   // remove se só usares <img>

        session_write_close();
        readfile($path);
        exit;
    }
}
