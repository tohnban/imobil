<?php

namespace App\controller;

use App\model\Commission;
use App\model\Log;
use App\model\Notification;
use App\model\Property;
use App\model\PropertyAffiliate;
use App\model\Request;
use App\model\RequestChatMessage;
use Src\classes\ClassAccess;
use Src\classes\ClassAuth;
use Src\classes\ClassCsrf;
use Src\classes\UploadLimits;

trait RequestControllerSupport
{

    private function resolvePropertyFinalStatus(array $property): ?string
    {
        $purpose = (string) ($property['purpose'] ?? '');
        if ($purpose === 'venda') {
            return 'vendido';
        }
        if (strpos($purpose, 'aluguer') === 0) {
            return 'alugado';
        }

        return null;
    }


    private function isPropertyCommerciallyClosed(array $property): bool
    {
        return in_array((string) ($property['status'] ?? ''), ['vendido', 'alugado'], true);
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

        $errorMap = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite permitido no formulário.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária de upload indisponível.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco.',
            UPLOAD_ERR_EXTENSION => 'Upload de arquivo bloqueado pelo servidor.',
        ];

        if ($errorCode !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => $errorMap[$errorCode] ?? 'Erro ao enviar arquivo.'];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['path' => null, 'error' => 'Arquivo inválido.'];
        }

        if ($size <= 0 || UploadLimits::exceedsServerMax($size)) {
            return ['path' => null, 'error' => UploadLimits::serverMaxError('O ficheiro')];
        }

        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($mime, $allowedMime, true)) {
            return ['path' => null, 'error' => 'Formato inválido. Use JPG, PNG, WebP ou GIF.'];
        }

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $ext = $extMap[$mime] ?? 'jpg';

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


    private function createCommissionFromRequest(int $requestId, array $request, array $property, int $actorId): void
    {
        if ($requestId <= 0 || empty($property) || Commission::existsByRequest($requestId)) {
            return;
        }

        $commissionBase = (float) ($request['modality_total_amount'] ?? 0);
        if ($commissionBase <= 0) {
            $commissionBase = (float) ($property['price'] ?? 0);
        }

        $requestAffiliateId = (int) ($request['affiliate_id'] ?? 0);
        $ownerId = (int) ($property['affiliate_id'] ?? 0);
        $propertyId = (int) ($request['property_id'] ?? 0);
        $hasValidAffiliate = $requestAffiliateId > 0
            && $requestAffiliateId !== $ownerId
            && PropertyAffiliate::isActiveAffiliate($requestAffiliateId, $propertyId);

        if ($hasValidAffiliate && Commission::hasActiveAffiliateCommissionForProperty($propertyId, $requestAffiliateId)) {
            $hasValidAffiliate = false;
        }

        $commissionId = Commission::createFromRequest(
            $requestId,
            $hasValidAffiliate ? $requestAffiliateId : 0,
            (int) ($request['property_id'] ?? 0),
            $commissionBase,
            $ownerId
        );

        $commissionRecordId = is_numeric($commissionId) ? (int) $commissionId : 0;
        if ($ownerId > 0 && $commissionRecordId > 0) {
            $commissionRecord = Commission::findById($commissionRecordId);
            $dueAt = (string) ($commissionRecord['due_at'] ?? '');
            $dueLabel = $dueAt !== '' ? date('d/m/Y', strtotime($dueAt)) : date('d/m/Y', strtotime('+' . max(1, (int) \Src\classes\ClassSettings::int('commission_due_days', 7)) . ' days'));
            $amountFormatted = number_format(max(0, (float) ($commissionRecord['amount'] ?? 0)), 0, ',', '.');
            Notification::notifyUser(
                $ownerId,
                'commission_payment_due',
                'Comissão a pagar',
                'Foi registada uma comissão de ' . $amountFormatted . ' Kz pelo fecho do imóvel "' . ((string) ($property['title'] ?? '')) . '". Pague até ' . $dueLabel . '.',
                [
                    'request_id' => $requestId,
                    'property_id' => (int) ($request['property_id'] ?? 0),
                    'commission_id' => $commissionRecordId,
                ],
                $actorId
            );
        }

        if ($hasValidAffiliate) {
            Notification::notifyUser(
                $requestAffiliateId,
                'commission_created',
                'Nova comissão registada',
                'Uma comissão foi registada para o imóvel "' . ((string) ($property['title'] ?? '')) . '". Verifique os detalhes na área de Afiliados.',
                ['request_id' => $requestId, 'property_id' => (int) ($request['property_id'] ?? 0)],
                $actorId
            );
        }
    }


    private function redirectBackOr(string $fallbackPath, string $param, string $message): void
    {
        $url = ClassCsrf::resolveReturnUrl($fallbackPath);
        $separator = strpos($url, '?') === false ? '?' : '&';
        header('Location: ' . $url . $separator . $param . '=' . urlencode($message));
        exit;
    }

}
