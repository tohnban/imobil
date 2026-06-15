<?php

namespace App\model;

class RequestChatMessage extends ManipularBanco
{
    protected $table = 'request_chat_messages';

    public const NEGOTIATION_CONTACT_POLICY_MARKER = '[[policy:negotiation_contact]]';

    public const NEGOTIATION_CONTACT_POLICY_TEXT =
        'É proibido partilhar informações de contacto (telefone, e-mail, redes sociais ou similares) '
        . 'através deste chat. O incumprimento pode resultar na desativação da conta e na aplicação '
        . 'de outras medidas mais rigorosas pela plataforma.';

    public const CLOSING_WON_PLATFORM_VISIT_MARKER = '[[policy:closing_won_platform_visit]]';

    private static function unreadMessageConditionSql(string $messageAlias, string $readsAlias): string
    {
        RequestChatRead::ensureSchema();
        if (RequestChatRead::hasMessageCursorColumn()) {
            return "(
                (COALESCE({$readsAlias}.last_read_message_id, 0) > 0 AND {$messageAlias}.id > {$readsAlias}.last_read_message_id)
                OR (
                    COALESCE({$readsAlias}.last_read_message_id, 0) = 0
                    AND {$messageAlias}.created_at > COALESCE({$readsAlias}.last_read_at, '1970-01-01 00:00:00')
                )
            )";
        }

        return "{$messageAlias}.created_at > COALESCE({$readsAlias}.last_read_at, '1970-01-01 00:00:00')";
    }

    public static function closingWonPlatformVisitChatText(): string
    {
        return 'O negócio foi marcado como fecho ganho. A plataforma entrará em contacto com ambas as partes '
            . 'através dos dados cadastrados no sistema para agendar a visita ao imóvel e efectuar a '
            . 'avaliação final. O solicitante deverá declarar o pagamento na plataforma apenas após visitar '
            . 'o imóvel e confirmar que tudo está conforme acordado.';
    }

    public static function closingWonPlatformVisitNotificationText(bool $forRequester, string $propertyTitle = ''): string
    {
        $propertyPart = $propertyTitle !== '' ? ' do imóvel "' . $propertyTitle . '"' : '';
        $base = 'A plataforma entrará em contacto consigo através dos dados cadastrados no sistema '
            . 'para agendar a visita ao imóvel' . $propertyPart . ' e efectuar a avaliação final do negócio.';

        if ($forRequester) {
            return $base . ' Após visitar o imóvel e confirmar que tudo está conforme acordado, '
                . 'poderá declarar o pagamento na plataforma.';
        }

        return $base;
    }

    public static function displayText(string $messageText): string
    {
        return trim((string) preg_replace('/\[\[policy:[^\]]+\]\]\s*/', '', $messageText));
    }

    public static function threadHasSystemMarker(int $threadId, string $marker): bool
    {
        if ($threadId <= 0 || trim($marker) === '') {
            return false;
        }

        $db = new self();
        $sql = "SELECT id FROM {$db->table}
                WHERE thread_id = ?
                  AND message_type = 'system'
                  AND deleted_at IS NULL
                  AND message_text LIKE ?
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$threadId, '%' . $marker . '%']);

        return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function ensureNegotiationContactPolicyMessage(int $requestId): void
    {
        if ($requestId <= 0) {
            return;
        }

        $thread = RequestChatThread::findByRequestId($requestId);
        if (!$thread || empty($thread['id'])) {
            return;
        }

        $threadId = (int) $thread['id'];
        if (self::threadHasSystemMarker($threadId, self::NEGOTIATION_CONTACT_POLICY_MARKER)) {
            return;
        }

        $request = Request::findById($requestId);
        $senderId = (int) ($request['user_id'] ?? 0);
        if ($senderId <= 0) {
            return;
        }

        self::createForThread(
            $threadId,
            $senderId,
            self::NEGOTIATION_CONTACT_POLICY_MARKER . ' ' . self::NEGOTIATION_CONTACT_POLICY_TEXT,
            'system'
        );
    }

    public static function postClosingWonPlatformVisitMessage(int $requestId, int $actorUserId): void
    {
        if ($requestId <= 0 || $actorUserId <= 0) {
            return;
        }

        $thread = RequestChatThread::getOrCreateByRequestId($requestId);
        if (!$thread || empty($thread['id'])) {
            return;
        }

        $threadId = (int) $thread['id'];
        if (self::threadHasSystemMarker($threadId, self::CLOSING_WON_PLATFORM_VISIT_MARKER)) {
            return;
        }

        self::createForThread(
            $threadId,
            $actorUserId,
            self::CLOSING_WON_PLATFORM_VISIT_MARKER . ' ' . self::closingWonPlatformVisitChatText(),
            'system'
        );
    }

    public static function findByAttachmentPath(string $attachmentPath): ?array
    {
        $attachmentPath = trim((string) $attachmentPath);
        if ($attachmentPath === '') {
            return null;
        }

        $db = new self();
        $sql = "SELECT m.*, t.request_id
                FROM {$db->table} m
                JOIN request_chat_threads t ON t.id = m.thread_id
                WHERE m.attachment_path = ?
                  AND m.deleted_at IS NULL
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$attachmentPath]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public static function getSummariesByRequestIds(array $requestIds, int $userId = 0): array
    {
        $requestIds = array_values(array_unique(array_map('intval', array_filter($requestIds, static function ($id): bool {
            return (int) $id > 0;
        }))));
        if (empty($requestIds)) {
            return [];
        }

        $db = new self();
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $params = $requestIds;
        $unreadSql = '0 AS unread_count';
        if ($userId > 0) {
            $unreadCondition = self::unreadMessageConditionSql('um', 'chat_reads');
            $unreadSql = "COALESCE((
                        SELECT COUNT(*)
                        FROM {$db->table} um
                        WHERE um.thread_id = t.id
                          AND um.sender_user_id <> ?
                          AND um.deleted_at IS NULL
                          AND {$unreadCondition}
                    ), 0) AS unread_count";
            $params = array_merge([$userId], $requestIds);
        }

        $sql = "SELECT
                    t.request_id,
                    t.id AS thread_id,
                    t.last_message_at,
                    COALESCE(cnt.total_messages, 0) AS total_messages,
                    last_msg.message_text AS last_message_text,
                    last_msg.message_type AS last_message_type,
                    sender.username AS last_sender_username,
                    sender.name AS last_sender_name,
                    {$unreadSql}
                FROM request_chat_threads t
                LEFT JOIN (
                    SELECT thread_id, COUNT(*) AS total_messages
                    FROM {$db->table}
                    WHERE deleted_at IS NULL
                    GROUP BY thread_id
                ) cnt ON cnt.thread_id = t.id
                LEFT JOIN request_chat_reads chat_reads
                    ON chat_reads.thread_id = t.id
                   AND chat_reads.user_id = " . (int) $userId . "
                LEFT JOIN {$db->table} last_msg
                    ON last_msg.id = (
                        SELECT m2.id
                        FROM {$db->table} m2
                        WHERE m2.thread_id = t.id
                          AND m2.deleted_at IS NULL
                        ORDER BY m2.created_at DESC, m2.id DESC
                        LIMIT 1
                    )
                LEFT JOIN users sender ON sender.id = last_msg.sender_user_id
                WHERE t.request_id IN ({$placeholders})";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) ($row['request_id'] ?? 0)] = $row;
        }

        return $summaries;
    }

    public static function getByThreadId(int $threadId, int $limit = 200): array
    {
        $db = new self();
        $sql = "SELECT m.*, u.username AS sender_username, u.name AS sender_name, u.email AS sender_email
                FROM {$db->table} m
                JOIN users u ON u.id = m.sender_user_id
                WHERE m.thread_id = ?
                  AND m.deleted_at IS NULL
                ORDER BY m.created_at ASC
                LIMIT ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$threadId, max(1, $limit)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function createForThread(int $threadId, int $senderUserId, string $messageText, string $messageType = 'text', ?string $attachmentPath = null): int|false
    {
        $db = new self();
        $data = [
            'thread_id' => $threadId,
            'sender_user_id' => $senderUserId,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($attachmentPath !== null && $attachmentPath !== '') {
            $data['attachment_path'] = (string) $attachmentPath;
        }

        $created = $db->Salvar($data, $db->table);
        if ($created) {
            RequestChatThread::touch($threadId);
            RequestChatRead::markThreadRead($threadId, $senderUserId);
            \App\services\HeaderShellService::invalidateChat($senderUserId);
        }

        return is_int($created) ? $created : false;
    }

    public static function createSystemForRequest(int $requestId, int $senderUserId, string $messageText, ?string $attachmentPath = null): int|false
    {
        $thread = RequestChatThread::getOrCreateByRequestId($requestId);
        if (!$thread || empty($thread['id'])) {
            return false;
        }

        return self::createForThread((int) $thread['id'], $senderUserId, $messageText, 'system', $attachmentPath);
    }

    public static function countUnreadByUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = new self();
        $unreadCondition = self::unreadMessageConditionSql('m', 'cr');

        $sql = "SELECT COALESCE(SUM(unread_in_thread), 0) AS total_unread
                FROM (
                    SELECT COUNT(*) AS unread_in_thread
                    FROM {$db->table} m
                    JOIN request_chat_reads cr
                      ON cr.thread_id = m.thread_id
                     AND cr.user_id = ?
                    WHERE m.sender_user_id <> ?
                      AND m.deleted_at IS NULL
                      AND {$unreadCondition}
                    GROUP BY m.thread_id
                ) unread_threads";

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($result['total_unread'] ?? 0);
    }

    public static function countUnreadForVisibleRequests(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = new self();
        $unreadCondition = self::unreadMessageConditionSql('m', 'cr');

        $sql = "SELECT COALESCE(SUM(unread_in_thread), 0) AS total_unread
                FROM (
                    SELECT COUNT(*) AS unread_in_thread
                    FROM {$db->table} m
                    JOIN request_chat_threads rt ON rt.id = m.thread_id
                    JOIN requests r ON r.id = rt.request_id
                    JOIN request_chat_reads cr
                      ON cr.thread_id = m.thread_id
                     AND cr.user_id = ?
                    WHERE m.sender_user_id <> ?
                      AND m.deleted_at IS NULL
                      AND (r.user_id = ? OR r.property_id IN (
                            SELECT p.id FROM properties p WHERE p.affiliate_id = ?
                          ))
                      AND {$unreadCondition}
                    GROUP BY m.thread_id
                ) unread_threads";

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($result['total_unread'] ?? 0);
    }
}
