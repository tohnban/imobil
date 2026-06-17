<?php

namespace App\controller;

use App\model\Log;
use App\model\Property;
use App\model\Request;
use App\model\RequestChatMessage;
use App\services\CommissionClosingService;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\UploadLimits;

trait RequestControllerSupport
{

    private function resolvePropertyFinalStatus(array $property): ?string
    {
        return Property::resolveCommercialClosureStatus($property);
    }


    private function isPropertyCommerciallyClosed(array $property): bool
    {
        return CommissionClosingService::isPropertyCommerciallyClosed($property);
    }


    private function noteLength(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text);
        }

        return strlen($text);
    }


    private function appendChatSystemMessage(int $requestId, int $actorId, string $message, ?string $attachmentPath = null): void
    {
        if (trim($message) === '') {
            return;
        }

        try {
            RequestChatMessage::createSystemForRequest($requestId, $actorId, $message, $attachmentPath);
        } catch (\Throwable $e) {
            // Chat system messages must not break the request flow.
        }
    }


    private function systemMessageForStatusChange(string $status, array $request, array $property, array $actor, ?string $note = null): string
    {
        $actorName = trim((string) ($actor['name'] ?? 'Utilizador'));
        $note = trim((string) $note);

        if ($status === 'fechado_ganho') {
            $message = $actorName . ' marcou a solicitação como fecho ganho.';
        } elseif ($status === 'cancelado') {
            $message = $actorName . ' encerrou a solicitação como cancelada.';
        } elseif ($status === 'em_disputa') {
            $message = $actorName . ' enviou a solicitação para disputa.';
        } else {
            $message = $actorName . ' atualizou o estado da solicitação para ' . Request::statusLabel($status) . '.';
        }

        if ($note !== '') {
            $message .= ' Observação: ' . $note;
        }

        return $message;
    }


    private function processRequestImageUpload(array $file, int $userId): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => \Src\classes\ClassImageUpload::uploadErrorMessage($errorCode, 'O ficheiro')];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'error' => 'Arquivo inválido.'];
        }

        if ($size <= 0 || UploadLimits::exceedsServerMax($size)) {
            return ['path' => null, 'error' => UploadLimits::serverMaxError('O ficheiro')];
        }

        $mime = \Src\classes\ClassImageUpload::detectMime($tmpName);

        if (!\Src\classes\ClassImageUpload::isStandardMime($mime)) {
            return ['path' => null, 'error' => \Src\classes\ClassImageUpload::INVALID_STANDARD_FORMAT];
        }

        $ext = \Src\classes\ClassImageUpload::extensionForMime($mime);

        $uploadDirRelative = 'public/storage/uploads/request_chat_attachments/';
        $uploadDir = DIRREQ . $uploadDirRelative;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return ['path' => null, 'error' => 'Não foi possível preparar a pasta para anexos.'];
        }

        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Exception $e) {
            $suffix = substr(md5(uniqid('', true)), 0, 8);
        }

        $filename = 'chat_' . max(0, $userId) . '_' . time() . '_' . $suffix . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            return ['path' => null, 'error' => 'Falha ao salvar o arquivo.'];
        }

        return ['path' => $uploadDirRelative . $filename, 'error' => null];
    }


    private function processMessageAttachmentUpload(array $file, int $userId): array
    {
        return $this->processRequestImageUpload($file, $userId);
    }


    private function processActionImageUpload(array $file, int $userId): array
    {
        return $this->processRequestImageUpload($file, $userId);
    }


    private function collectActionContext(bool $noteRequired = false, bool $imageRequired = false): array
    {
        $note = trim((string) ($_POST['action_note'] ?? ''));
        $noteLength = $this->noteLength($note);

        if ($noteRequired && $noteLength < 8) {
            return [
                'note' => $note,
                'image_path' => null,
                'error' => 'Descreva o motivo da ação com pelo menos 8 caracteres.',
            ];
        }

        if ($noteLength > 2000) {
            return [
                'note' => $note,
                'image_path' => null,
                'error' => 'A descrição da ação deve ter no máximo 2000 caracteres.',
            ];
        }

        $actor = ClassAuth::user();
        $upload = $this->processActionImageUpload($_FILES['action_image'] ?? [], (int) ($actor['id'] ?? 0));
        if (!empty($upload['error'])) {
            return [
                'note' => $note,
                'image_path' => null,
                'error' => (string) $upload['error'],
            ];
        }

        $imagePath = $upload['path'] ?? null;
        if ($imageRequired && (!is_string($imagePath) || $imagePath === '')) {
            return [
                'note' => $note,
                'image_path' => null,
                'error' => 'Anexe o comprovativo de pagamento para declarar o pagamento.',
            ];
        }

        return [
            'note' => $note,
            'image_path' => $imagePath,
            'error' => null,
        ];
    }


    private function composeActionLogDetails(string $baseDetails, string $note, ?string $imagePath): string
    {
        $details = $baseDetails;

        if ($note !== '') {
            $details .= ' | Observação: ' . $note;
        }

        if (is_string($imagePath) && $imagePath !== '') {
            $details .= ' | Evidência: ' . $imagePath;
        }

        return $details;
    }


    private function userCanManageAllRequests(array $user): bool
    {
        return ClassAccess::can('requests.manage', $user);
    }


    private function redirectBackOr(string $fallbackPath, string $param, string $message, array $extra = []): void
    {
        $success = ($param === 'success');
        \Src\classes\ClassAjaxResponse::redirectOrJson($success, $message, $fallbackPath, $extra);
    }

}
