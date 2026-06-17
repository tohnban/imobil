<?php

namespace App\model;

use Src\classes\ClassMailer;

class Notification extends ManipularBanco
{
    protected $table = 'notifications';

    private const DEDUP_WINDOWS = [
        'request_sla_reminder' => 43200,
        'document_resubmitted' => 1800,
        'trusted_badge_requested' => 1800,
        'boost_request' => 900,
    ];

    private static function decodeMetadata($rawMetadata): array
    {
        if (is_array($rawMetadata)) {
            return $rawMetadata;
        }

        if (!is_string($rawMetadata) || trim($rawMetadata) === '') {
            return [];
        }

        $decoded = json_decode($rawMetadata, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function typeLabel(string $type): string
    {
        $labels = [
            'new_request' => 'Solicitações',
            'request_status_updated' => 'Solicitações',
            'request_cancelled' => 'Solicitações',
            'request_closing_confirmed' => 'Solicitações',
            'request_closing_contested' => 'Disputas',
            'request_payment_declared' => 'Pagamentos',
            'request_payment_receipt_confirmed' => 'Pagamentos',
            'request_payment_contested' => 'Disputas',
            'request_chat_message' => 'Chat',
            'request_sla_reminder' => 'Acompanhamento',
            'commission_paid' => 'Pagamentos',
            'trusted_badge_requested' => 'Confiança',
            'trusted_badge_approved' => 'Confiança',
            'trusted_badge_rejected' => 'Confiança',
            'trusted_badge_payment_confirmed' => 'Confiança',
            'user_approved' => 'Conta',
            'user_rejected' => 'Conta',
            'user_blocked' => 'Conta',
            'user_unblocked' => 'Conta',
            'admin_role_updated' => 'Conta',
            'document_approved' => 'Documentos',
            'document_rejected' => 'Documentos',
            'document_resubmitted' => 'Documentos',
            'boost_request' => 'Destaque',
            'boost_approved' => 'Destaque',
            'boost_rejected' => 'Destaque',
            'affiliate_approved' => 'Afiliação',
            'affiliate_rejected' => 'Afiliação',
            'commission_created' => 'Comissões',
            'commission_payment_due' => 'Comissões',
            'commission_owner_payment_submitted' => 'Pagamentos',
            'commission_owner_payment_confirmed' => 'Comissões',
            'commission_owner_payment_rejected' => 'Comissões',
            'commission_payout_pending' => 'Comissões',
            'subscription_renewed' => 'Plano',
            'subscription_payment_failed' => 'Plano',
            'subscription_downgraded' => 'Plano',
        ];

        return $labels[$type] ?? 'Notificação';
    }

    public static function typeIcon(string $type): string
    {
        $icons = [
            'new_request' => 'fa-inbox',
            'request_status_updated' => 'fa-exchange',
            'request_cancelled' => 'fa-ban',
            'request_closing_confirmed' => 'fa-check-circle',
            'request_closing_contested' => 'fa-exclamation-triangle',
            'request_payment_declared' => 'fa-credit-card',
            'request_payment_receipt_confirmed' => 'fa-check-circle',
            'request_payment_contested' => 'fa-exclamation-triangle',
            'request_chat_message' => 'fa-comments',
            'request_sla_reminder' => 'fa-clock-o',
            'commission_paid' => 'fa-money',
            'trusted_badge_requested' => 'fa-shield',
            'trusted_badge_approved' => 'fa-shield',
            'trusted_badge_rejected' => 'fa-shield',
            'trusted_badge_payment_confirmed' => 'fa-shield',
            'user_approved' => 'fa-user-circle',
            'user_rejected' => 'fa-user-times',
            'user_blocked' => 'fa-lock',
            'user_unblocked' => 'fa-unlock',
            'admin_role_updated' => 'fa-user',
            'document_approved' => 'fa-file-text-o',
            'document_rejected' => 'fa-file-text-o',
            'document_resubmitted' => 'fa-file-text-o',
            'boost_request' => 'fa-star',
            'boost_approved' => 'fa-star',
            'boost_rejected' => 'fa-star',
            'affiliate_approved' => 'fa-users',
            'affiliate_rejected' => 'fa-users',
            'commission_created' => 'fa-percent',
            'commission_payment_due' => 'fa-calendar-o',
            'commission_owner_payment_submitted' => 'fa-upload',
            'commission_owner_payment_confirmed' => 'fa-check',
            'commission_owner_payment_rejected' => 'fa-times',
            'commission_payout_pending' => 'fa-clock-o',
            'subscription_renewed' => 'fa-refresh',
            'subscription_payment_failed' => 'fa-exclamation-circle',
            'subscription_downgraded' => 'fa-level-down',
        ];

        return $icons[$type] ?? 'fa-bell';
    }

    public static function typeTone(string $type): string
    {
        $tones = [
            'request_chat_message' => 'tone-chat',
            'new_request' => 'tone-request',
            'request_status_updated' => 'tone-request',
            'request_cancelled' => 'tone-request',
            'request_closing_confirmed' => 'tone-request',
            'request_closing_contested' => 'tone-alert',
            'request_payment_declared' => 'tone-payment',
            'request_payment_receipt_confirmed' => 'tone-payment',
            'request_payment_contested' => 'tone-alert',
            'request_sla_reminder' => 'tone-alert',
            'commission_paid' => 'tone-payment',
            'commission_created' => 'tone-payment',
            'commission_payment_due' => 'tone-payment',
            'commission_owner_payment_submitted' => 'tone-payment',
            'commission_owner_payment_confirmed' => 'tone-payment',
            'commission_owner_payment_rejected' => 'tone-alert',
            'commission_payout_pending' => 'tone-payment',
            'trusted_badge_requested' => 'tone-trust',
            'trusted_badge_approved' => 'tone-trust',
            'trusted_badge_rejected' => 'tone-trust',
            'trusted_badge_payment_confirmed' => 'tone-trust',
            'user_approved' => 'tone-account',
            'user_rejected' => 'tone-account',
            'user_blocked' => 'tone-account',
            'user_unblocked' => 'tone-account',
            'admin_role_updated' => 'tone-account',
            'document_approved' => 'tone-document',
            'document_rejected' => 'tone-document',
            'document_resubmitted' => 'tone-document',
            'boost_request' => 'tone-boost',
            'boost_approved' => 'tone-boost',
            'boost_rejected' => 'tone-boost',
            'affiliate_approved' => 'tone-affiliate',
            'affiliate_rejected' => 'tone-affiliate',
            'subscription_renewed' => 'tone-plan',
            'subscription_payment_failed' => 'tone-alert',
            'subscription_downgraded' => 'tone-plan',
        ];

        return $tones[$type] ?? 'tone-default';
    }

    public static function relativeTime(?string $datetime): string
    {
        $datetime = $datetime !== null ? trim($datetime) : '';
        if ($datetime === '') {
            return '';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '';
        }

        $diff = time() - $timestamp;
        if ($diff < 45) {
            return 'agora';
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return 'há ' . $minutes . ' min';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return 'há ' . $hours . ' h';
        }
        if ($diff < 172800) {
            return 'ontem';
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return 'há ' . $days . ' d';
        }
        if ($diff < 2592000) {
            $weeks = (int) floor($diff / 604800);
            return 'há ' . max(1, $weeks) . ' sem';
        }

        return date('j M', $timestamp);
    }

    public static function resolveTargetUrl(array $notification): string
    {
        $type = (string) ($notification['type'] ?? '');
        $metadata = self::decodeMetadata($notification['metadata'] ?? null);
        $requestId = (int) ($metadata['request_id'] ?? 0);
        $propertyId = (int) ($metadata['property_id'] ?? 0);
        $commissionId = (int) ($metadata['commission_id'] ?? 0);
        $boostId = (int) ($metadata['boost_id'] ?? 0);
        $userId = (int) ($metadata['user_id'] ?? 0);
        $documentId = (int) ($metadata['document_id'] ?? 0);
        $docType = trim((string) ($metadata['doc_type'] ?? ''));

        if ($requestId > 0 && in_array($type, [
            'request_chat_message',
            'new_request',
            'request_status_updated',
            'request_cancelled',
            'request_closing_confirmed',
            'request_closing_contested',
            'request_payment_declared',
            'request_payment_receipt_confirmed',
            'request_payment_contested',
            'request_sla_reminder',
        ], true)) {
            return DIRPAGE . 'dashboard/requestChat/' . $requestId;
        }

        if ($type === 'commission_paid') {
            if ($commissionId > 0) {
                return DIRPAGE . 'commissions?highlight=' . $commissionId;
            }
            return DIRPAGE . 'commissions';
        }

        if (in_array($type, ['boost_request'], true)) {
            if ($boostId > 0) {
                return DIRPAGE . 'dashboard/payments?boost_id=' . $boostId;
            }
            return DIRPAGE . 'dashboard/payments';
        }

        if (in_array($type, ['boost_approved', 'boost_rejected'], true)) {
            if ($propertyId > 0) {
                return DIRPAGE . 'property/' . $propertyId;
            }
            return DIRPAGE . 'dashboard/myProperties';
        }

        if (in_array($type, ['affiliate_approved', 'affiliate_rejected'], true)) {
            return DIRPAGE . 'dashboard/afiliados?tab=referrals';
        }

        if ($type === 'commission_created') {
            return DIRPAGE . 'dashboard/afiliados?tab=commissions';
        }

        if ($type === 'commission_payment_due') {
            if ($commissionId > 0) {
                return DIRPAGE . 'dashboard/commissionPayment/' . $commissionId;
            }
            return DIRPAGE . 'dashboard/commissionPayments';
        }

        if (in_array($type, ['commission_owner_payment_confirmed', 'commission_owner_payment_rejected'], true)) {
            return DIRPAGE . 'dashboard/commissionPayments';
        }

        if ($type === 'commission_payout_pending') {
            return DIRPAGE . 'dashboard/afiliados?tab=commissions';
        }

        if ($type === 'commission_owner_payment_submitted') {
            if ($commissionId > 0) {
                return DIRPAGE . 'dashboard/payments?highlight=' . $commissionId;
            }
            return DIRPAGE . 'dashboard/payments';
        }

        if (in_array($type, ['trusted_badge_requested'], true)) {
            if ($userId > 0) {
                return DIRPAGE . 'dashboard/moderateUsers?user=' . $userId;
            }
            return DIRPAGE . 'dashboard/moderateUsers';
        }

        if (in_array($type, ['trusted_badge_approved', 'trusted_badge_rejected', 'trusted_badge_payment_confirmed', 'user_approved', 'user_rejected', 'user_blocked', 'user_unblocked', 'admin_role_updated'], true)) {
            return DIRPAGE . 'profile';
        }

        if ($type === 'document_resubmitted') {
            if ($documentId > 0) {
                return DIRPAGE . 'dashboard/reviewDocuments?document=' . $documentId;
            }
            if ($userId > 0 || $docType !== '') {
                $query = [];
                if ($userId > 0) {
                    $query[] = 'user=' . $userId;
                }
                if ($docType !== '') {
                    $query[] = 'doc_type=' . urlencode($docType);
                }
                if (!empty($query)) {
                    return DIRPAGE . 'dashboard/reviewDocuments?' . implode('&', $query);
                }
            }
            return DIRPAGE . 'dashboard/reviewDocuments';
        }

        if (in_array($type, ['subscription_renewed', 'subscription_payment_failed', 'subscription_downgraded'], true)) {
            return DIRPAGE . 'dashboard/subscription';
        }

        if (in_array($type, ['document_approved', 'document_rejected'], true)) {
            if ($type === 'document_rejected') {
                return DIRPAGE . 'dashboard#rejected-documents';
            }
            return DIRPAGE . 'notification/inbox';
        }

        return DIRPAGE . 'notification/inbox';
    }

    public static function actionLabel(string $type): string
    {
        $labels = [
            'request_chat_message' => 'Abrir chat',
            'new_request' => 'Ver solicitações',
            'request_status_updated' => 'Ver solicitações',
            'request_cancelled' => 'Ver solicitações',
            'request_closing_confirmed' => 'Ver solicitações',
            'request_closing_contested' => 'Ver disputa',
            'request_payment_declared' => 'Abrir negociação',
            'request_payment_receipt_confirmed' => 'Abrir negociação',
            'request_payment_contested' => 'Ver disputa',
            'request_sla_reminder' => 'Acompanhar',
            'commission_paid' => 'Ver histórico',
            'boost_request' => 'Ver pagamentos',
            'boost_approved' => 'Ver imóvel',
            'boost_rejected' => 'Ver imóvel',
            'trusted_badge_requested' => 'Abrir moderação',
            'trusted_badge_approved' => 'Ver perfil',
            'trusted_badge_rejected' => 'Ver perfil',
            'trusted_badge_payment_confirmed' => 'Ver perfil',
            'user_approved' => 'Ver perfil',
            'user_rejected' => 'Ver perfil',
            'user_blocked' => 'Ver perfil',
            'user_unblocked' => 'Ver perfil',
            'admin_role_updated' => 'Ver perfil',
            'document_approved' => 'Ver notificações',
            'document_rejected' => 'Ver pendências',
            'document_resubmitted' => 'Rever documentos',
            'affiliate_approved' => 'Ver indicações',
            'affiliate_rejected' => 'Ver indicações',
            'commission_created' => 'Ver comissões',
            'commission_payment_due' => 'Pagar comissão',
            'commission_owner_payment_submitted' => 'Validar pagamento',
            'commission_owner_payment_confirmed' => 'Ver comissões',
            'commission_owner_payment_rejected' => 'Reenviar comprovativo',
            'commission_payout_pending' => 'Ver comissões',
            'subscription_renewed' => 'Ver plano',
            'subscription_payment_failed' => 'Ver plano',
            'subscription_downgraded' => 'Ver plano',
        ];

        return $labels[$type] ?? 'Abrir';
    }

    private static function shouldDeduplicate(string $type): bool
    {
        return isset(self::DEDUP_WINDOWS[$type]);
    }

    private static function hasRecentDuplicate(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $metadata = []
    ): bool {
        if (!self::shouldDeduplicate($type)) {
            return false;
        }

        $windowSeconds = (int) (self::DEDUP_WINDOWS[$type] ?? 0);
        if ($windowSeconds <= 0) {
            return false;
        }

        try {
            $db = new self();
            $sql = "SELECT title, message, metadata
                    FROM {$db->table}
                    WHERE user_id = ?
                      AND type = ?
                      AND created_at >= DATE_SUB(NOW(), INTERVAL " . $windowSeconds . ' SECOND)
                    ORDER BY created_at DESC
                    LIMIT 10';
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $type]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                if (
                    (string) ($row['title'] ?? '') === $title
                    && (string) ($row['message'] ?? '') === $message
                    && self::decodeMetadata($row['metadata'] ?? null) == $metadata
                ) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    public static function enrichRow(array $notification): array
    {
        $type = (string) ($notification['type'] ?? '');
        $createdAt = (string) ($notification['created_at'] ?? '');

        $notification['metadata'] = self::decodeMetadata($notification['metadata'] ?? null);
        $notification['type_label'] = self::typeLabel($type);
        $notification['type_icon'] = self::typeIcon($type);
        $notification['type_tone'] = self::typeTone($type);
        $notification['target_url'] = self::resolveTargetUrl($notification);
        $notification['action_label'] = self::actionLabel($type);
        $notification['relative_time'] = self::relativeTime($createdAt);
        $notification['created_at_label'] = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '';

        return $notification;
    }

    private static function requestStatusLabel(string $status, ?string $closingConfirmationStatus = null): string
    {
        return Request::statusLabel($status, $closingConfirmationStatus);
    }

    public static function requestStatusCopy(string $status, string $propertyTitle = '', ?string $closingConfirmationStatus = null): array
    {
        $label = self::requestStatusLabel($status, $closingConfirmationStatus);
        $propertyPart = $propertyTitle !== '' ? ' no imovel "' . $propertyTitle . '"' : '';

        $title = 'Atualizacao de solicitacao';
        $message = 'A sua solicitacao' . $propertyPart . ' foi atualizada para: ' . $label . '.';

        if ($status === 'fechado_ganho') {
            if ($closingConfirmationStatus === Request::CLOSING_CONFIRMATION_PENDING) {
                $title = 'Fecho ganho aguardando pagamento';
                $message = 'O negocio' . $propertyPart . ' foi marcado como fecho ganho e aguarda declaracao de pagamento.';
            } else {
                $title = 'Fecho ganho confirmado';
                $message = 'O negocio' . $propertyPart . ' foi concluido como fecho ganho.';
            }
        } elseif ($status === 'em_disputa') {
            $title = 'Solicitacao em disputa';
            $message = 'A solicitacao' . $propertyPart . ' entrou em disputa e sera analisada pela equipa.';
        } elseif ($status === 'expirado') {
            $title = 'Solicitacao expirada';
            $message = 'A solicitacao' . $propertyPart . ' expirou por falta de atualizacao.';
        }

        return ['title' => $title, 'message' => $message];
    }

    public static function notifyClosingWonPlatformVisit(
        int $userId,
        int $requestId,
        string $propertyTitle,
        bool $forRequester,
        ?int $actorId = null
    ) {
        $title = 'Fecho ganho — visita e avaliação final';
        $message = RequestChatMessage::closingWonPlatformVisitNotificationText($forRequester, $propertyTitle);

        return self::notifyUser(
            $userId,
            'request_closing_won_platform_visit',
            $title,
            $message,
            [
                'request_id' => $requestId,
                'property_title' => $propertyTitle,
                'for_requester' => $forRequester ? 1 : 0,
            ],
            $actorId
        );
    }

    public static function notifyRequestStatusChanged(
        int $userId,
        int $requestId,
        string $status,
        string $propertyTitle = '',
        ?int $actorId = null,
        ?string $closingConfirmationStatus = null
    ) {
        $copy = self::requestStatusCopy($status, $propertyTitle, $closingConfirmationStatus);

        return self::notifyUser(
            $userId,
            'request_status_updated',
            $copy['title'],
            $copy['message'],
            [
                'request_id' => $requestId,
                'status' => $status,
                'property_title' => $propertyTitle,
            ],
            $actorId
        );
    }

    public static function notifyRequestSlaReminder(
        int $userId,
        int $requestId,
        string $propertyTitle,
        string $status,
        int $daysWithoutUpdate,
        ?int $actorId = null
    ) {
        $title = 'Acompanhamento de solicitacao';
        $message = 'A solicitacao do imovel "' . $propertyTitle . '" esta em "' . self::requestStatusLabel($status)
            . '" ha ' . max(0, $daysWithoutUpdate) . ' dia(s) sem atualizacao. Atualize o desfecho para evitar expiracao.';

        return self::notifyUser(
            $userId,
            'request_sla_reminder',
            $title,
            $message,
            [
                'request_id' => $requestId,
                'status' => $status,
                'days_without_update' => max(0, $daysWithoutUpdate),
            ],
            $actorId
        );
    }

    public static function create(array $data)
    {
        try {
            $db = new self();
            return $db->Salvar($data, $db->table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $metadata = [],
        ?int $actorId = null
    ) {
        if (self::hasRecentDuplicate($userId, $type, $title, $message, $metadata)) {
            return true;
        }

        $created = self::create([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'metadata' => empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $targetUser = User::findById($userId);
        if (!empty($targetUser['email'])) {
            ClassMailer::sendNotification((string) $targetUser['email'], (string) ($targetUser['name'] ?? ''), $title, $message);
        }

        if ($created) {
            \App\services\HeaderShellService::invalidateNotifications($userId);
        }

        return $created;
    }

    public static function notifyUsers(
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $metadata = [],
        ?int $actorId = null
    ): void {
        $ids = array_unique(array_map('intval', $userIds));
        foreach ($ids as $id) {
            if ($id > 0) {
                self::notifyUser($id, $type, $title, $message, $metadata, $actorId);
            }
        }
    }

    /**
     * @return array{unread_count:int,preview:array<int,array>}
     */
    public static function getHeaderPayload(int $userId, int $limit = 5): array
    {
        if ($userId <= 0) {
            return ['unread_count' => 0, 'preview' => []];
        }

        return [
            'unread_count' => self::countUnreadByUser($userId),
            'preview' => self::getLatestByUser($userId, max(1, min(10, $limit))),
        ];
    }

    public static function getLatestByUser(int $userId, int $limit = 8): array
    {
        try {
            $db = new self();
            $sql = "SELECT * FROM {$db->table}
                    WHERE user_id = ? AND is_archived = 0
                    ORDER BY is_read ASC, created_at DESC
                    LIMIT " . (int) $limit;
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return array_map([self::class, 'enrichRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function countUnreadByUser(int $userId): int
    {
        try {
            $db = new self();
            $sql = "SELECT COUNT(*) AS total
                    FROM {$db->table}
                    WHERE user_id = ? AND is_read = 0 AND is_archived = 0";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function markAllAsReadByUser(int $userId): bool
    {
        try {
            $db = new self();
            $sql = "UPDATE {$db->table} SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
            $stmt = $db->prepare($sql);
            $ok = (bool) $stmt->execute([$userId]);
            if ($ok) {
                \App\services\HeaderShellService::invalidateNotifications($userId);
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function markAsReadByUser(int $notificationId, int $userId): bool
    {
        try {
            $db = new self();
            $sql = "UPDATE {$db->table}
                    SET is_read = 1, read_at = NOW()
                    WHERE id = ? AND user_id = ? AND is_read = 0";
            $stmt = $db->prepare($sql);
            $ok = (bool) $stmt->execute([(int) $notificationId, (int) $userId]);
            if ($ok) {
                \App\services\HeaderShellService::invalidateNotifications((int) $userId);
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function markAsUnreadByUser(int $notificationId, int $userId): bool
    {
        try {
            $db = new self();
            $sql = "UPDATE {$db->table}
                    SET is_read = 0, read_at = NULL
                    WHERE id = ? AND user_id = ? AND is_read = 1";
            $stmt = $db->prepare($sql);
            $ok = (bool) $stmt->execute([(int) $notificationId, (int) $userId]);
            if ($ok) {
                \App\services\HeaderShellService::invalidateNotifications((int) $userId);
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function buildNotificationGroup(string $type, array $metadata = []): string
    {
        $metaStr = isset($metadata['entity_id'])
            ? ($type . ':' . (int) $metadata['entity_id'])
            : $type;
        return sha1($metaStr);
    }

    public static function getInboxByUser(int $userId, int $limit = 10, int $offset = 0): array
    {
        try {
            $db = new self();
            $sql = "SELECT * FROM {$db->table}
                    WHERE user_id = ? AND is_archived = 0
                    ORDER BY is_read ASC, created_at DESC
                    LIMIT ? OFFSET ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $limit, $offset]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return array_map([self::class, 'enrichRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Cursor (keyset) pagination for inbox notifications.
     * Enforces order by created_at/id for stability (no is_read ordering in cursor mode).
     */
    public static function getInboxByUserCursor(int $userId, int $limit = 10, ?string $cursorCreatedAt = null, ?int $cursorId = null): array
    {
        try {
            $db = new self();
            $limit = min(50, max(1, (int) $limit));
            $sql = "SELECT * FROM {$db->table}
                    WHERE user_id = ? AND is_archived = 0";
            $params = [$userId];

            if ($cursorCreatedAt !== null && trim($cursorCreatedAt) !== '' && $cursorId !== null && $cursorId > 0) {
                $sql .= ' AND (created_at < ? OR (created_at = ? AND id < ?))';
                $params[] = $cursorCreatedAt;
                $params[] = $cursorCreatedAt;
                $params[] = (int) $cursorId;
            }

            $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ?';
            $params[] = $limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return array_map([self::class, 'enrichRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function countInboxByUser(int $userId): int
    {
        try {
            $db = new self();
            $sql = "SELECT COUNT(*) AS total FROM {$db->table} WHERE user_id = ? AND is_archived = 0";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function getArchiveByUser(int $userId, int $limit = 20, int $offset = 0, ?string $typeFilter = null): array
    {
        try {
            $db = new self();
            $sql = "SELECT * FROM {$db->table}
                    WHERE user_id = ? AND is_archived = 1";
            $params = [$userId];

            if ($typeFilter !== null && $typeFilter !== '') {
                $sql .= ' AND type = ?';
                $params[] = $typeFilter;
            }

            $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return array_map([self::class, 'enrichRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Cursor (keyset) pagination for archived notifications.
     */
    public static function getArchiveByUserCursor(int $userId, int $limit = 20, ?string $cursorCreatedAt = null, ?int $cursorId = null, ?string $typeFilter = null): array
    {
        try {
            $db = new self();
            $limit = min(50, max(1, (int) $limit));
            $sql = "SELECT * FROM {$db->table}
                    WHERE user_id = ? AND is_archived = 1";
            $params = [$userId];

            if ($typeFilter !== null && $typeFilter !== '') {
                $sql .= ' AND type = ?';
                $params[] = $typeFilter;
            }

            if ($cursorCreatedAt !== null && trim($cursorCreatedAt) !== '' && $cursorId !== null && $cursorId > 0) {
                $sql .= ' AND (created_at < ? OR (created_at = ? AND id < ?))';
                $params[] = $cursorCreatedAt;
                $params[] = $cursorCreatedAt;
                $params[] = (int) $cursorId;
            }

            $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ?';
            $params[] = $limit;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return array_map([self::class, 'enrichRow'], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function countArchiveByUser(int $userId, ?string $typeFilter = null): int
    {
        try {
            $db = new self();
            $sql = "SELECT COUNT(*) AS total FROM {$db->table} WHERE user_id = ? AND is_archived = 1";
            $params = [$userId];

            if ($typeFilter !== null && $typeFilter !== '') {
                $sql .= ' AND type = ?';
                $params[] = $typeFilter;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function archiveByUser(int $notificationId, int $userId): bool
    {
        try {
            $db = new self();
            $sql = "UPDATE {$db->table}
                    SET is_archived = 1
                    WHERE id = ? AND user_id = ? AND is_archived = 0";
            $stmt = $db->prepare($sql);
            $stmt->execute([(int) $notificationId, (int) $userId]);
            $ok = $stmt->rowCount() > 0;
            if ($ok) {
                \App\services\HeaderShellService::invalidateNotifications((int) $userId);
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function archiveAllByUser(int $userId): bool
    {
        try {
            $db = new self();
            $sql = "UPDATE {$db->table}
                    SET is_archived = 1
                    WHERE user_id = ? AND is_archived = 0";
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $ok = $stmt->rowCount() > 0;
            if ($ok) {
                \App\services\HeaderShellService::invalidateNotifications((int) $userId);
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function unarchiveByUser(int $notificationId, int $userId): bool
    {
        try {
            $db = new self();
            $sql = "UPDATE {$db->table}
                    SET is_archived = 0
                    WHERE id = ? AND user_id = ? AND is_archived = 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([(int) $notificationId, (int) $userId]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function deleteArchivedByUser(int $notificationId, int $userId): bool
    {
        try {
            $db = new self();
            $sql = "DELETE FROM {$db->table}
                    WHERE id = ? AND user_id = ? AND is_archived = 1";
            $stmt = $db->prepare($sql);
            return (bool) $stmt->execute([(int) $notificationId, (int) $userId]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function deleteArchivedOlderThan(int $daysAgo = 90): int
    {
        try {
            $db = new self();
            $sql = "DELETE FROM {$db->table}
                    WHERE is_archived = 1
                      AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $db->prepare($sql);
            $stmt->execute([max(1, $daysAgo)]);
            return (int) $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function deleteAllForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            $db = new self();
            $sql = "DELETE FROM {$db->table} WHERE user_id = ?";
            $stmt = $db->prepare($sql);
            if (!$stmt->execute([$userId])) {
                return 0;
            }

            return (int) $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
