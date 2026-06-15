<?php

namespace App\controller;

use Src\classes\AuthRegisterFeedback;
use Src\classes\ClassImageUpload;
use Src\classes\UploadLimits;

trait AuthRegisterSupport
{

    private function redirectRegisterError(string $code): void
    {
        $safeCode = AuthRegisterFeedback::isKnownCode($code) ? $code : AuthRegisterFeedback::CREATE_FAILED;
        header('Location: ' . DIRPAGE . 'register?error=' . urlencode($safeCode));
        exit;
    }


    private function processProfilePhotoUpload(?array $profilePhoto, int $userId): array
    {
        if (!$profilePhoto || (int) ($profilePhoto['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        $errorCode = (int) ($profilePhoto['error'] ?? UPLOAD_ERR_OK);
        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => ClassImageUpload::uploadErrorMessage($errorCode, 'A foto de perfil')];
        }

        $tmpName = (string) ($profilePhoto['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'error' => 'Arquivo de foto de perfil inválido.'];
        }

        if (UploadLimits::exceedsServerMax((int) ($profilePhoto['size'] ?? 0))) {
            return ['path' => null, 'error' => UploadLimits::serverMaxError('A foto de perfil')];
        }

        $detectedMime = ClassImageUpload::detectMime($tmpName);
        if (!ClassImageUpload::isStandardMime($detectedMime)) {
            return ['path' => null, 'error' => ClassImageUpload::INVALID_STANDARD_FORMAT];
        }

        $uploadDirRelative = 'public/storage/uploads/profiles/';
        $uploadDir = rtrim(DIRREQ, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDirRelative);
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Não foi possível preparar a pasta da foto de perfil.'];
        }

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            $suffix = substr(md5(uniqid('', true)), 0, 12);
        }
        $ext = ClassImageUpload::extensionForMime($detectedMime);
        $filename = 'profile_' . $userId . '_' . time() . '_' . $suffix . '.' . $ext;
        $targetPath = $uploadDir . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            return ['path' => null, 'error' => 'Falha ao salvar a foto de perfil.'];
        }

        return ['path' => $uploadDirRelative . $filename, 'error' => null];
    }

}
