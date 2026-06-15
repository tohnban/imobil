<?php

namespace Src\classes;

/**
 * Limites de upload em duas camadas:
 * - SERVER_MAX_BYTES: tecto absoluto aceite pelo servidor (3 MB)
 * - TARGET_*_BYTES: metas de optimização no cliente (podem ser menores)
 */
final class UploadLimits
{
    public const SERVER_MAX_BYTES = 3 * 1024 * 1024;

    public const TARGET_PROFILE_PHOTO_BYTES = 512 * 1024;
    public const TARGET_IMAGE_PROOF_BYTES = 512 * 1024;
    public const TARGET_CHAT_ATTACHMENT_BYTES = 512 * 1024;
    public const TARGET_PROPERTY_IMAGE_BYTES = 2 * 1024 * 1024;
    public const TARGET_DOCUMENT_BYTES = 3 * 1024 * 1024;

    public static function serverMaxBytes(): int
    {
        return self::SERVER_MAX_BYTES;
    }

    public static function exceedsServerMax(int $size): bool
    {
        return $size > self::SERVER_MAX_BYTES;
    }

    public static function serverMaxError(string $subject = 'O ficheiro'): string
    {
        return $subject . ' excede o tamanho máximo de ' . self::formatShort(self::SERVER_MAX_BYTES) . '.';
    }

    public static function formatShort(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            $mb = $bytes / (1024 * 1024);
            $formatted = abs($mb - round($mb)) < 0.05 ? (string) (int) round($mb) : number_format($mb, 1, ',', '');

            return $formatted . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /**
     * @return array<string, int>
     */
    public static function clientTargets(): array
    {
        return [
            'profilePhoto' => self::TARGET_PROFILE_PHOTO_BYTES,
            'imageProof' => self::TARGET_IMAGE_PROOF_BYTES,
            'chatAttachment' => self::TARGET_CHAT_ATTACHMENT_BYTES,
            'propertyImage' => self::TARGET_PROPERTY_IMAGE_BYTES,
            'document' => self::TARGET_DOCUMENT_BYTES,
        ];
    }

    /**
     * @return array{serverMaxBytes:int,target:array<string,int>}
     */
    public static function clientConfig(): array
    {
        return [
            'serverMaxBytes' => self::SERVER_MAX_BYTES,
            'target' => self::clientTargets(),
        ];
    }
}
