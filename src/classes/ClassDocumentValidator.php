<?php

namespace Src\classes;

/**
 * ClassDocumentValidator
 *
 * Centralized validation rules for document uploads.
 * Ensures consistent file type checks, size limits, and MIME validation.
 */
class ClassDocumentValidator
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    // Document types
    public const TYPE_USER_REGISTRATION = 'user_registration';
    public const TYPE_USER_KYC = 'user_kyc';
    public const TYPE_PROPERTY_OWNERSHIP = 'property_ownership';
    public const TYPE_PROPERTY_LICENSING = 'property_licensing';

    /**
     * @deprecated Use UploadLimits::serverMaxBytes()
     */
    public const MAX_FILE_SIZE = UploadLimits::SERVER_MAX_BYTES;

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES entry
     * @param string $docType Document type (see TYPE_* constants)
     * @return array ['valid' => bool, 'error' => string (if invalid)]
     */
    public static function validateFile(array $file, string $docType = self::TYPE_USER_REGISTRATION): array
    {
        // Check for upload errors
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return ['valid' => false, 'error' => 'Nenhum ficheiro foi enviado'];
            }
            return ['valid' => false, 'error' => 'Erro ao fazer upload do ficheiro'];
        }

        $maxSize = self::maxFileSizeForType($docType);
        if (($file['size'] ?? 0) > $maxSize) {
            return [
                'valid' => false,
                'error' => UploadLimits::serverMaxError('O ficheiro'),
            ];
        }

        // Check file extension
        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['valid' => false, 'error' => 'Tipo de ficheiro inválido. Use PDF, JPG ou PNG'];
        }

        // Check if file is uploaded file
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpPath)) {
            return ['valid' => false, 'error' => 'Upload inválido'];
        }

        // Check MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return ['valid' => false, 'error' => 'Conteúdo do ficheiro inválido (MIME type: ' . $mime . ')'];
        }

        return ['valid' => true];
    }

    /**
     * Generate unique filename for document
     *
     * @param string $originalName Original filename
     * @param string $version Version (e.g., 'v1', 'v2')
     * @return string Safe filename with version and hash
     */
    public static function generateFilename(string $originalName, string $version = 'v1'): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $hash = bin2hex(random_bytes(16));
        return 'doc_' . $version . '_' . $hash . '.' . $extension;
    }

    /**
     * Extract version from filename
     *
     * @param string $filename Stored filename
     * @return string Version string (e.g., 'v1', 'v2') or 'v1' if not found
     */
    public static function extractVersion(string $filename): string
    {
        // Format: doc_v1_hash.ext
        if (preg_match('/doc_(v\d+)_/', $filename, $matches)) {
            return $matches[1];
        }
        return 'v1';
    }

    /**
     * Get next version number
     *
     * @param string $currentVersion Current version (e.g., 'v1')
     * @return string Next version (e.g., 'v2')
     */
    public static function getNextVersion(string $currentVersion): string
    {
        $num = (int) substr($currentVersion, 1);
        return 'v' . ($num + 1);
    }

    public static function maxFileSizeForType(string $docType): int
    {
        return UploadLimits::serverMaxBytes();
    }

    /**
     * Get validation rules by document type
     *
     * @param string $docType Document type
     * @return array Validation rules specific to document type
     */
    public static function getRulesByType(string $docType): array
    {
        $baseRules = [
            'max_size' => self::maxFileSizeForType($docType),
            'allowed_extensions' => self::ALLOWED_EXTENSIONS,
            'allowed_mimes' => self::ALLOWED_MIME_TYPES,
        ];

        // Type-specific rules can be added here
        switch ($docType) {
            case self::TYPE_USER_REGISTRATION:
            case self::TYPE_USER_KYC:
                return array_merge($baseRules, [
                    'required' => true,
                    'business_logic' => 'Government-issued ID or passport scan',
                ]);

            case self::TYPE_PROPERTY_OWNERSHIP:
                return array_merge($baseRules, [
                    'required' => true,
                    'business_logic' => 'Property deed or ownership certificate',
                ]);

            case self::TYPE_PROPERTY_LICENSING:
                return array_merge($baseRules, [
                    'required' => false,
                    'business_logic' => 'Business license or operating permit',
                ]);

            default:
                return $baseRules;
        }
    }
}
