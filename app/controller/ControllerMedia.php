<?php

namespace App\controller;

use Src\classes\ClassMediaUrl;

/**
 * Serve imagens públicas (perfil e imóveis) sem expor comprovativos ou anexos sensíveis.
 */
class ControllerMedia
{
    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if (strpos($path, "\0") !== false || strpos($path, '..') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Ex.: /media/serve?path=public/storage/uploads/properties/property_123.webp
     */
    public function serve(): void
    {
        $path = ClassMediaUrl::normalizeStoredPath((string) ($_GET['path'] ?? ''));
        if (!$this->isSafeRelativePath($path) || !ClassMediaUrl::isPublicPath($path)) {
            $this->notFound();
        }

        $basename = basename(str_replace('\\', '/', $path));
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $basename)) {
            $this->notFound();
        }

        $abs = rtrim((string) DIRREQ, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $real = realpath($abs);
        $root = realpath((string) DIRREQ);
        if ($real === false || $root === false || strpos($real, $root) !== 0 || !is_file($real)) {
            $this->notFound();
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? (string) finfo_file($finfo, $real) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime === '' || strpos($mime, 'image/') !== 0) {
            $this->notFound();
        }

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($real));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $basename) . '"');
        header('Cache-Control: public, max-age=86400, immutable');

        readfile($real);
        exit;
    }
}
