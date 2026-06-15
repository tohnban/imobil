<?php

namespace App\controller;

use Src\classes\AuthRegisterFeedback;
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
            $errorMap = [
                UPLOAD_ERR_INI_SIZE => 'A foto de perfil excede o limite do servidor.',
                UPLOAD_ERR_FORM_SIZE => 'A foto de perfil excede o limite permitido no formulário.',
                UPLOAD_ERR_PARTIAL => 'A foto de perfil foi enviada parcialmente.',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária indisponível para a foto de perfil.',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar a foto de perfil no disco.',
                UPLOAD_ERR_EXTENSION => 'Upload da foto de perfil bloqueado pelo servidor.',
            ];

            return ['path' => null, 'error' => $errorMap[$errorCode] ?? 'Erro ao enviar a foto de perfil.'];
        }

        $tmpName = (string) ($profilePhoto['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'error' => 'Arquivo de foto de perfil inválido.'];
        }

        if (UploadLimits::exceedsServerMax((int) ($profilePhoto['size'] ?? 0))) {
            return ['path' => null, 'error' => UploadLimits::serverMaxError('A foto de perfil')];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMime = [
            'image/jpeg' => 'jpg',
        ];

        if (!isset($allowedMime[$detectedMime])) {
            return ['path' => null, 'error' => 'Formato de foto inválido. A foto final deve estar em JPG.'];
        }

        $uploadDir = rtrim(DIRREQ, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Não foi possível preparar a pasta da foto de perfil.'];
        }

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            $suffix = substr(md5(uniqid('', true)), 0, 12);
        }
        $filename = 'profile_' . $userId . '_' . time() . '_' . $suffix . '.' . $allowedMime[$detectedMime];
        $targetPath = $uploadDir . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            return ['path' => null, 'error' => 'Falha ao salvar a foto de perfil.'];
        }

        return ['path' => DIRPAGE . 'storage/uploads/profiles/' . $filename, 'error' => null];
    }

}
