<?php

namespace Src\classes;

/**
 * URLs para ficheiros em storage/uploads e documentos.
 * - Comprovativos e anexos sensíveis → /file/serve (autenticado)
 * - Fotos de perfil e imóveis → /media/serve (público, lista branca)
 */
final class ClassMediaUrl
{
    /** @var list<string> */
    private const PUBLIC_PREFIXES = [
        'public/storage/uploads/properties/',
        'public/storage/uploads/profiles/',
    ];

    /** @var list<string> */
    private const PROTECTED_PREFIXES = [
        'public/storage/uploads/commission_proofs/',
        'public/storage/uploads/commission_payout_proofs/',
        'public/storage/uploads/subscription_proofs/',
        'public/storage/uploads/trust_badge_proofs/',
        'public/storage/uploads/boost_proofs/',
        'public/storage/uploads/request_chat_attachments/',
        'storage/documents/',
    ];

    public static function normalizeStoredPath(string $stored): string
    {
        $stored = trim(str_replace('\\', '/', $stored));
        if ($stored === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $stored)) {
            $path = parse_url($stored, PHP_URL_PATH);
            $stored = is_string($path) ? ltrim($path, '/') : '';
        }

        $stored = ltrim($stored, '/');
        if ($stored === '') {
            return '';
        }

        if (strpos($stored, 'storage/uploads/') === 0 && strpos($stored, 'public/storage/uploads/') !== 0) {
            $stored = 'public/' . $stored;
        }

        return $stored;
    }

    /**
     * URL para qualquer caminho guardado (comprovativo, anexo, perfil, imóvel).
     */
    public static function upload(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }

        $normalized = self::normalizeStoredPath($stored);
        if ($normalized === '') {
            return '';
        }

        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                return rtrim(DIRPAGE, '/') . '/file/serve?path=' . rawurlencode($normalized);
            }
        }

        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                return rtrim(DIRPAGE, '/') . '/media/serve?path=' . rawurlencode($normalized);
            }
        }

        return rtrim(DIRPAGE, '/') . '/' . $normalized;
    }

    public static function propertyImage(?string $stored): string
    {
        return self::upload($stored);
    }

    public static function profilePhoto(?string $stored): string
    {
        return self::upload($stored);
    }

    public static function proof(?string $stored): string
    {
        return self::upload($stored);
    }

    /** @return list<string> */
    public static function publicPrefixes(): array
    {
        return self::PUBLIC_PREFIXES;
    }

    /** @return list<string> */
    public static function protectedPrefixes(): array
    {
        return self::PROTECTED_PREFIXES;
    }

    public static function isPublicPath(string $path): bool
    {
        $normalized = self::normalizeStoredPath($path);
        if ($normalized === '') {
            return false;
        }

        foreach (self::PUBLIC_PREFIXES as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    public static function isProtectedPath(string $path): bool
    {
        $normalized = self::normalizeStoredPath($path);
        if ($normalized === '') {
            return false;
        }

        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
