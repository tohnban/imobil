<?php

namespace App\model;

class RequestChatRead extends ManipularBanco
{
    protected $table = 'request_chat_reads';

    private static ?bool $hasCursorColumn = null;

    public static function ensureSchema(): void
    {
        if (self::$hasCursorColumn !== null) {
            return;
        }

        $db = new self();
        try {
            $check = $db->prepare("SHOW COLUMNS FROM {$db->table} LIKE 'last_read_message_id'");
            $check->execute();
            if ($check->fetch()) {
                self::$hasCursorColumn = true;
                return;
            }

            $db->prepare(
                "ALTER TABLE {$db->table}
                 ADD COLUMN last_read_message_id INT UNSIGNED NULL DEFAULT NULL AFTER last_read_at"
            )->execute();
            self::$hasCursorColumn = true;
        } catch (\Throwable $e) {
            self::$hasCursorColumn = false;
        }
    }

    public static function hasMessageCursorColumn(): bool
    {
        self::ensureSchema();

        return self::$hasCursorColumn === true;
    }

    /**
     * @return array{max_id: int, max_at: string}
     */
    public static function readCursorForThread(int $threadId): array
    {
        if ($threadId <= 0) {
            return ['max_id' => 0, 'max_at' => date('Y-m-d H:i:s')];
        }

        $db = new self();
        $stmt = $db->prepare(
            "SELECT COALESCE(MAX(id), 0) AS max_id, COALESCE(MAX(created_at), NOW()) AS max_at
             FROM request_chat_messages
             WHERE thread_id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$threadId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'max_id' => (int) ($row['max_id'] ?? 0),
            'max_at' => (string) ($row['max_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    public static function markThreadRead(int $threadId, int $userId): bool
    {
        if ($threadId <= 0 || $userId <= 0) {
            return false;
        }

        self::ensureSchema();

        $cursor = self::readCursorForThread($threadId);
        $maxId = (int) $cursor['max_id'];
        $maxAt = (string) $cursor['max_at'];

        $db = new self();
        if (self::hasMessageCursorColumn()) {
            $sql = "INSERT INTO {$db->table} (thread_id, user_id, last_read_at, last_read_message_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        last_read_at = VALUES(last_read_at),
                        last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), VALUES(last_read_message_id)),
                        updated_at = NOW()";
            $params = [$threadId, $userId, $maxAt, $maxId > 0 ? $maxId : null];
        } else {
            $sql = "INSERT INTO {$db->table} (thread_id, user_id, last_read_at, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        last_read_at = VALUES(last_read_at),
                        updated_at = NOW()";
            $params = [$threadId, $userId, $maxAt];
        }
        $stmt = $db->prepare($sql);
        $ok = (bool) $stmt->execute($params);
        if ($ok) {
            \App\services\HeaderShellService::invalidateChat($userId);
        }

        return $ok;
    }

    public static function markThreadUnread(int $threadId, int $userId): bool
    {
        if ($threadId <= 0 || $userId <= 0) {
            return false;
        }

        self::ensureSchema();

        $db = new self();
        $stmt = $db->prepare(
            "SELECT id
             FROM request_chat_messages
             WHERE thread_id = ? AND sender_user_id <> ? AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$threadId, $userId]);
        $lastOther = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$lastOther) {
            return false;
        }

        $maxOtherId = (int) ($lastOther['id'] ?? 0);
        $cursorId = max(0, $maxOtherId - 1);
        $cursorAt = '1970-01-01 00:00:00';

        if ($cursorId > 0) {
            $atStmt = $db->prepare(
                "SELECT created_at FROM request_chat_messages WHERE id = ? LIMIT 1"
            );
            $atStmt->execute([$cursorId]);
            $cursorAt = (string) ($atStmt->fetchColumn() ?: '1970-01-01 00:00:00');
        }

        if (self::hasMessageCursorColumn()) {
            $sql = "INSERT INTO {$db->table} (thread_id, user_id, last_read_at, last_read_message_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        last_read_at = VALUES(last_read_at),
                        last_read_message_id = VALUES(last_read_message_id),
                        updated_at = NOW()";
            $params = [
                $threadId,
                $userId,
                $cursorAt,
                $cursorId > 0 ? $cursorId : null,
            ];
        } else {
            $sql = "INSERT INTO {$db->table} (thread_id, user_id, last_read_at, created_at, updated_at)
                    VALUES (?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        last_read_at = VALUES(last_read_at),
                        updated_at = NOW()";
            $params = [$threadId, $userId, $cursorAt];
        }
        $stmt = $db->prepare($sql);
        $ok = (bool) $stmt->execute($params);
        if ($ok) {
            \App\services\HeaderShellService::invalidateChat($userId);
        }

        return $ok;
    }
}
