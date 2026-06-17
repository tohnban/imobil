<?php

namespace App\model;

class PropertyBoostRequest extends ManipularBanco
{
    protected $table = 'property_boost_requests';

    public static function hasActiveApprovedForProperty(int $propertyId): bool
    {
        $db = new self();
        $sql = "SELECT COUNT(*)
                FROM {$db->table}
                WHERE property_id = ?
                  AND status = 'aprovado'
                  AND expires_at IS NOT NULL
                  AND expires_at > NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([$propertyId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Boost pricing configuration (reads from settings table).
     */
    public static function getBoostPricingConfig(): array
    {
        $dailyFee    = (float) \Src\classes\ClassSettings::float('boost_daily_fee', 2000.0);
        $dailyFee    = max(0.0, $dailyFee);
        $minDays     = max(1, (int) \Src\classes\ClassSettings::int('boost_min_days', 7));
        $maxDays     = max($minDays, min(365, (int) \Src\classes\ClassSettings::int('boost_max_days', 90)));
        $defaultDays = max($minDays, min($maxDays, (int) \Src\classes\ClassSettings::int('boost_default_days', 30)));

        return [
            'daily_fee'    => $dailyFee,
            'min_days'     => $minDays,
            'max_days'     => $maxDays,
            'default_days' => $defaultDays,
        ];
    }

    /**
     * Calculate fee for a given number of days.
     */
    public static function calculateBoostFee(int $days): float
    {
        $config = self::getBoostPricingConfig();
        $days   = max($config['min_days'], min($config['max_days'], $days));
        return round($days * $config['daily_fee'], 2);
    }

    /**
     * Create a new boost request.
     *
     * @param int    $propertyId
     * @param int    $userId
     * @param string $boostType          'destaque' | 'premium'
     * @param int    $durationDays
     * @param float  $feeRequired
     * @param string $paymentProof       path to uploaded proof file
     * @return int|false  inserted ID or false on failure
     */
    public static function create(
        int $propertyId,
        int $userId,
        string $boostType = 'destaque',
        int $durationDays = 30,
        float $feeRequired = 0.0,
        string $paymentProof = ''
    ) {
        $db = new self();
        return $db->Salvar([
            'property_id'   => $propertyId,
            'user_id'       => $userId,
            'boost_type'    => in_array($boostType, ['destaque', 'premium'], true) ? $boostType : 'destaque',
            'duration_days' => max(1, $durationDays),
            'fee_required'  => $feeRequired,
            'payment_proof' => $paymentProof !== '' ? $paymentProof : null,
            'status'        => 'pendente',
        ], $db->table);
    }

    /**
     * Find a single request by id.
     */
    public static function find(int $id): ?array
    {
        $db  = new self();
        $sql = "SELECT pbr.*, p.title AS property_title, p.affiliate_id AS property_owner_id,
                       u.id AS requester_id, u.name AS requester_name, u.email AS requester_email
                FROM {$db->table} pbr
                LEFT JOIN properties p ON pbr.property_id = p.id
                LEFT JOIN users       u ON pbr.user_id     = u.id
                WHERE pbr.id = ?
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * All pending boost requests (for the financeiro queue).
     */
    public static function getPending(int $limit = 0, int $offset = 0): array
    {
        $db  = new self();
        $sql = "SELECT pbr.*, p.title AS property_title, p.affiliate_id AS property_owner_id,
                       u.id AS requester_id, u.name AS requester_name, u.email AS requester_email
                FROM {$db->table} pbr
                LEFT JOIN properties p ON pbr.property_id = p.id
                LEFT JOIN users       u ON pbr.user_id     = u.id
                WHERE pbr.status = 'pendente'
                ORDER BY pbr.requested_at ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countPending(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} WHERE status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * All boost requests for a specific property.
     */
    public static function getByProperty(int $propertyId): array
    {
        $db  = new self();
        $sql = "SELECT * FROM {$db->table}
                WHERE property_id = ?
                ORDER BY requested_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$propertyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function clearPaymentProofsForProperty(int $propertyId): int
    {
        if ($propertyId <= 0) {
            return 0;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET payment_proof = NULL
                WHERE property_id = ?
                  AND payment_proof IS NOT NULL
                  AND payment_proof <> ''";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$propertyId])) {
            return 0;
        }

        return $stmt->rowCount();
    }

    /**
     * Check if there is already an open (pendente) request for a property.
     */
    public static function alreadyPending(int $propertyId): bool
    {
        $db  = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} WHERE property_id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$propertyId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Approve a boost request and set the expiry date.
     */
    public static function approve(int $id, int $durationDays = 30): bool
    {
        $db  = new self();
        $boost = self::find($id);
        if (!$boost) {
            return false;
        }

        $sql = "UPDATE {$db->table}
                SET status = 'aprovado',
                    approved_at = NOW(),
                    expires_at  = DATE_ADD(NOW(), INTERVAL ? DAY)
                WHERE id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$durationDays, $id]);

        if ($result && $stmt->rowCount() > 0) {
            try {
                PaymentTransaction::create([
                    'transaction_type' => 'boost_fee',
                    'direction' => 'incoming',
                    'status' => 'confirmado',
                    'amount' => 0.00,
                    'currency' => 'AOA',
                    'counterparty_user_id' => (int) $boost['user_id'],
                    'related_entity_type' => 'property_boost_request',
                    'related_entity_id' => $id,
                    'reference_code' => $boost['payment_reference'],
                    'confirmed_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                // Log error but don't fail the transaction
            }
        }

        return $result;
    }

    /**
     * Reject a boost request with an optional note.
     */
    public static function reject(int $id, string $notes = ''): bool
    {
        $db  = new self();
        $sql = "UPDATE {$db->table}
                SET status = 'rejeitado',
                    rejected_at = NOW(),
                    notes = ?
                WHERE id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$notes !== '' ? $notes : null, $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Expire approved boosts that reached expires_at and disable featured when needed.
     *
     * @return array{expired_boosts:int, properties_unfeatured:int}
     */
    public static function expireDueBoosts(): array
    {
        $db = new self();

        $sql = "SELECT id, property_id
                FROM {$db->table}
                WHERE status = 'aprovado'
                  AND expires_at IS NOT NULL
                  AND expires_at <= NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $expired = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (empty($expired)) {
            return ['expired_boosts' => 0, 'properties_unfeatured' => 0];
        }

        $expiredIds = array_map(static function (array $row): int {
            return (int) ($row['id'] ?? 0);
        }, $expired);
        $expiredIds = array_values(array_filter($expiredIds));

        $properties = [];
        foreach ($expired as $row) {
            $pid = (int) ($row['property_id'] ?? 0);
            if ($pid > 0) {
                $properties[$pid] = true;
            }
        }

        $expiredCount = 0;
        if (!empty($expiredIds)) {
            $placeholders = implode(',', array_fill(0, count($expiredIds), '?'));
            $upSql = "UPDATE {$db->table}
                      SET status = 'expirado',
                          notes = TRIM(CONCAT(IFNULL(notes, ''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE ' | ' END, 'Expirado automaticamente.')),
                          expired_at = IFNULL(expired_at, NOW())
                      WHERE id IN ({$placeholders})";
            $upStmt = $db->prepare($upSql);
            $upStmt->execute($expiredIds);
            $expiredCount = $upStmt->rowCount();
        }

        $unfeaturedCount = 0;
        foreach (array_keys($properties) as $propertyId) {
            if (!self::hasActiveApprovedForProperty((int) $propertyId)) {
                if (Property::setFeatured((int) $propertyId, false)) {
                    $unfeaturedCount++;
                }
            }
        }

        return [
            'expired_boosts' => $expiredCount,
            'properties_unfeatured' => $unfeaturedCount,
        ];
    }
}
