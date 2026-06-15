<?php

namespace Src\classes;

/**
 * Validação e mensagens partilhadas para uploads de imagem (comprovativos, perfil, imóveis).
 */
final class ClassImageUpload
{
    /** Formatos aceites em comprovativos e foto de perfil (sem HEIC). */
    public const STANDARD_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** Formatos aceites em galeria de imóveis (inclui HEIC de iPhone). */
    public const PROPERTY_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];

    /** @var array<string, string> */
    public const STANDARD_EXT_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public const INVALID_STANDARD_FORMAT = 'Formato inválido. Use JPG, PNG, WebP ou GIF.';

    public const INVALID_PROPERTY_FORMAT = 'Formato de imagem inválido. Use JPG, PNG, GIF, WebP ou HEIC (iPhone).';

    public static function isStandardMime(string $mime): bool
    {
        return in_array($mime, self::STANDARD_MIMES, true);
    }

    public static function isPropertyMime(string $mime): bool
    {
        return in_array($mime, self::PROPERTY_MIMES, true);
    }

    public static function extensionForMime(string $mime, ?array $map = null): string
    {
        $map = $map ?? self::STANDARD_EXT_MAP;

        return $map[$mime] ?? 'jpg';
    }

    public static function uploadErrorMessage(int $errorCode, string $subject): string
    {
        $errorMap = [
            UPLOAD_ERR_INI_SIZE => $subject . ' excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => $subject . ' excede o limite permitido no formulário.',
            UPLOAD_ERR_PARTIAL => $subject . ' foi enviado parcialmente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária de upload indisponível.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar ' . strtolower($subject) . ' no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado pelo servidor.',
        ];

        return $errorMap[$errorCode] ?? ('Erro ao enviar ' . strtolower($subject) . '.');
    }

    public static function detectMime(string $tmpPath): string
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime;
    }

    public static function isHeicByName(string $originalName): bool
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return in_array($ext, ['heic', 'heif'], true);
    }
}
