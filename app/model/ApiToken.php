<?php

namespace App\model;

use Src\classes\ClassSettings;

class ApiToken extends ManipularBanco
{
    protected $table = 'api_tokens';

    public static function findByToken(string $token): ?array
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE token = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function validateToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $row = self::findByToken($token);
        if (!$row) {
            return null;
        }
        if (($row['status'] ?? '') !== 'active') {
            return null;
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return null;
        }
        return $row;
    }

    public static function hasScope(array $tokenRow, string $requiredScope): bool
    {
        if (!isset($tokenRow['scopes']) || trim($tokenRow['scopes']) === '') {
            return false;
        }

        $scopes = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $tokenRow['scopes'])));
        return in_array($requiredScope, $scopes, true);
    }

    public static function markUsed(int $id): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET last_used_at = NOW(), updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public static function getByUser(int $userId): array
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function revoke(int $id): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'revoked', updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public static function revokeAllForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status = 'revoked', updated_at = NOW()
                WHERE user_id = ?
                  AND status = 'active'";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$userId])) {
            return 0;
        }

        return $stmt->rowCount();
    }

    public static function createToken(int $userId = 0, string $name = 'API Token', string $scopes = 'read:properties', ?string $expiresAt = null): ?string
    {
        $db = new self();
        $token = bin2hex(random_bytes(32));
        $ttlDays = max(1, ClassSettings::int('api_token_ttl_days', 365));
        $expires = $expiresAt ?: date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"));

        $sql = "INSERT INTO {$db->table} (user_id, token, name, scopes, status, expires_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'active', ?, NOW(), NOW())";
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute([$userId > 0 ? $userId : null, $token, $name, $scopes, $expires]);
        return $ok ? $token : null;
    }
}
