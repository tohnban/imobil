<?php

namespace Src\classes;

use App\model\ManipularBanco;

class ClassSettings
{
    /** In-memory cache to avoid repeated DB reads in the same request. */
    private static array $cache = [];

    /**
     * Get a setting value, falling back to $default if not found.
     */
    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $db   = new ManipularBanco();
            $stmt = $db->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            $row  = $stmt->fetch(\PDO::FETCH_ASSOC);
            $val  = $row !== false ? (string) $row['value'] : $default;
        } catch (\Throwable $e) {
            $val = $default;
        }

        self::$cache[$key] = $val;
        return $val;
    }

    /**
     * Get a setting as float.
     */
    public static function float(string $key, float $default = 0.0): float
    {
        return (float) self::get($key, (string) $default);
    }

    /**
     * Get a setting as int.
     */
    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }

    /** Labels for runtime-only settings not exposed in the admin panel. */
    private static function runtimeLabel(string $key): string
    {
        $labels = [
            'deletion_overdue_alert_last_sent_at' => 'Último alerta de eliminação em atraso',
        ];

        return $labels[$key] ?? $key;
    }

    /**
     * Save (insert or update) a setting value.
     */
    public static function set(string $key, string $value): bool
    {
        try {
            $db   = new ManipularBanco();
            $stmt = $db->prepare(
                'INSERT INTO settings (`key`, value, label) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
            );
            $ok = $stmt->execute([$key, $value, self::runtimeLabel($key)]);
            if ($ok) {
                self::$cache[$key] = $value;
            }
            return $ok;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Return all settings rows (for admin panel listing).
     */
    public static function all(): array
    {
        try {
            $db   = new ManipularBanco();
            $stmt = $db->prepare('SELECT * FROM settings ORDER BY `key` ASC');
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Flush in-memory cache (useful in tests or after batch updates). */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
