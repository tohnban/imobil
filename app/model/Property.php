<?php

namespace App\model;

use Src\classes\ClassSettings;
use Src\classes\DiscoveryEngine;
use Src\classes\PropertyTypeHelper;

class Property extends ManipularBanco
{
    protected $table = 'properties';

    public const MODERATION_STATUS_PENDING = 'pendente';
    public const MODERATION_STATUS_IN_REVIEW = 'em_analise';
    public const MODERATION_STATUS_APPROVED = 'disponivel';
    public const MODERATION_STATUS_REJECTED = 'rejeitado';

    public const AFFILIATE_APPROVAL_MANUAL = 'manual';
    public const AFFILIATE_APPROVAL_AUTO = 'auto';
    public const AFFILIATE_APPROVAL_DISABLED = 'disabled';

    private static function getFilterCacheVersion(): int
    {
        return (int) \Src\classes\Cache::remember('properties:filter:version', 86400, function () {
            return 1;
        });
    }

    private static function bumpFilterCacheVersion(): void
    {
        \Src\classes\Cache::increment('properties:filter:version');
    }

    private static function buildFilterCacheKey(array $filters): string
    {
        $viewerUserId = isset($filters['viewer_user_id']) ? (int) $filters['viewer_user_id'] : 0;
        $viewerVisitorKey = trim((string) ($filters['viewer_visitor_key'] ?? ''));
        $fingerprint = DiscoveryEngine::viewerFingerprint($viewerUserId, $viewerVisitorKey);
        $payload = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return 'properties:filter:' . sha1($payload . ':' . self::getFilterCacheVersion() . ':' . $fingerprint);
    }

    private static function buildFilteredQuery(array $filters = [], bool $countOnly = false): array
    {
        $db = new self();
        $viewerUserId = isset($filters['viewer_user_id']) ? (int) $filters['viewer_user_id'] : 0;
        $viewerVisitorKey = trim((string) ($filters['viewer_visitor_key'] ?? ''));
        $behaviorEnabled = DiscoveryEngine::isActive($viewerUserId, $viewerVisitorKey);
        $behaviorScoreParams = [];
        $select = $countOnly
            ? 'COUNT(*) AS total'
            : 'p.*, u.username AS owner_username, u.name AS owner_name, r.name AS region_name, c.name AS country_name,
                COALESCE((
                    SELECT sp.ranking_weight
                    FROM user_subscriptions us
                    INNER JOIN subscription_plans sp ON sp.id = us.plan_id
                    WHERE us.user_id = p.affiliate_id
                      AND ' . UserSubscription::sqlEffectiveBenefitsCondition('us') . "
                      AND sp.is_active = 1
                    ORDER BY us.starts_at DESC, us.id DESC
                    LIMIT 1
                ), CASE WHEN p.visibility = 'premium' THEN 50 ELSE 0 END) AS owner_plan_weight,
                CASE WHEN u.status = 'ativo' THEN 1 ELSE 0 END AS owner_verified,
                " . User::sqlOwnerTrustedColumn('u');

        if (!$countOnly) {
            if ($behaviorEnabled) {
                $behaviorFragment = DiscoveryEngine::behaviorScoreSql($viewerUserId, $viewerVisitorKey);
                $select .= ', ' . $behaviorFragment['sql'];
                $behaviorScoreParams = $behaviorFragment['params'];
            } else {
                $select .= ', 0 AS viewer_behavior_score';
            }
        }

        $sql = "SELECT {$select}
                FROM {$db->table} p
                LEFT JOIN users u ON p.affiliate_id = u.id
                LEFT JOIN countries c ON p.country_id = c.id
            LEFT JOIN regions r ON p.region_id = r.id
                WHERE p.status = 'disponivel'
                  AND " . User::sqlExcludePendingDeletionOwners('u');
        $params = $behaviorScoreParams;

        if (!empty($filters['type'])) {
            $sql .= ' AND p.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['purpose'])) {
            $sql .= ' AND p.purpose = ?';
            $params[] = $filters['purpose'];
        }
        if (!empty($filters['min_price'])) {
            $sql .= ' AND p.price >= ?';
            $params[] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $sql .= ' AND p.price <= ?';
            $params[] = $filters['max_price'];
        }
        if (!empty($filters['location'])) {
            $sql .= ' AND p.location LIKE ?';
            $params[] = '%' . $filters['location'] . '%';
        }
        if (!empty($filters['country_id'])) {
            $sql .= ' AND p.country_id = ?';
            $params[] = (int) $filters['country_id'];
        }
        if (!empty($filters['region_id'])) {
            $sql .= ' AND p.region_id = ?';
            $params[] = (int) $filters['region_id'];
        }
        $ownerFilter = trim((string) ($filters['owner_username'] ?? $filters['owner_name'] ?? ''));
        if ($ownerFilter !== '') {
            $sql .= ' AND LOWER(u.username) LIKE ?';
            $params[] = '%' . strtolower(ltrim($ownerFilter, '@')) . '%';
        }
        if (!empty($filters['bedrooms'])) {
            $sql .= ' AND p.bedrooms >= ?';
            $params[] = (int) $filters['bedrooms'];
        }
        if (!empty($filters['bathrooms'])) {
            $sql .= ' AND p.bathrooms >= ?';
            $params[] = (int) $filters['bathrooms'];
        }
        if (!empty($filters['min_area'])) {
            $sql .= ' AND p.area >= ?';
            $params[] = (float) $filters['min_area'];
        }
        if (!empty($filters['max_area'])) {
            $sql .= ' AND p.area <= ?';
            $params[] = (float) $filters['max_area'];
        }

        $featureFields = [
            'has_garage'   => 'has_garage',
            'has_pool'     => 'has_pool',
            'has_elevator' => 'has_elevator',
            'has_security' => 'has_security',
        ];
        foreach ($featureFields as $requestKey => $columnName) {
            if (array_key_exists($requestKey, $filters)) {
                $sql .= " AND p.{$columnName} = ?";
                $params[] = $filters[$requestKey] ? 1 : 0;
            }
        }

        if (!empty($filters['latitude']) && !empty($filters['longitude']) && !empty($filters['radius_km'])) {
            $latitude = (float) $filters['latitude'];
            $longitude = (float) $filters['longitude'];
            $radiusKm = (float) $filters['radius_km'];
            $sql .= ' AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL';
            $sql .= ' AND (6371 * acos(
                cos(radians(?)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(?))
                + sin(radians(?)) * sin(radians(p.latitude))
            )) <= ?';
            $params[] = $latitude;
            $params[] = $longitude;
            $params[] = $latitude;
            $params[] = $radiusKm;
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            if ($keyword !== '') {
                $sql .= ' AND (';
                if (ClassSettings::int('property_search_fulltext_enabled', 1) === 1) {
                    $sql .= 'MATCH(p.title, p.description, p.location) AGAINST (? IN NATURAL LANGUAGE MODE) OR ';
                    $params[] = $keyword;
                }
                $sql .= 'p.title LIKE ? OR p.description LIKE ? OR p.location LIKE ? OR LOWER(u.username) LIKE ?)';
                $likeKeyword = '%' . $keyword . '%';
                $usernameKeyword = '%' . strtolower(ltrim($keyword, '@')) . '%';
                $params[] = $likeKeyword;
                $params[] = $likeKeyword;
                $params[] = $likeKeyword;
                $params[] = $usernameKeyword;
            }
        }
        if (!empty($filters['trusted_only'])) {
            $sql .= User::sqlTrustedOnlyCondition('u');
        }

        return [$db, $sql, $params];
    }

    public static function create($data)
    {
        $db = new self();
        $ok = $db->Salvar($data, $db->table);
        if ($ok) {
            $insertedId = (int) $db->ConexaoDB()->lastInsertId();
            $property = self::find($insertedId);
            $owner = null;
            if (!empty($data['affiliate_id'])) {
                $owner = User::findById((int) $data['affiliate_id']);
            }

            // Quick notify matching saved searches (enqueue background jobs)
            try {
                $matches = SavedSearch::matchForProperty($property ?: [], (array) $owner);
                foreach ($matches as $m) {
                    $filters = json_decode((string) ($m['filters'] ?? '[]'), true) ?: [];
                    BackgroundJob::enqueue('notify_new_property', [
                        'user_id' => $m['user_id'],
                        'saved_search_id' => $m['id'],
                        'property_id' => $insertedId,
                        'filters' => $filters,
                    ], 5, 3);
                }
            } catch (\Throwable $_) {
                // swallow errors to not break creation flow
            }

            self::bumpFilterCacheVersion();
            return true;
        }

        return false;
    }

    public static function getFeatured($limit = null, $offset = null, ?int $viewerUserId = null, ?string $viewerVisitorKey = null)
    {
        $viewerUserId = (int) ($viewerUserId ?? 0);
        $viewerVisitorKey = trim((string) ($viewerVisitorKey ?? ''));
        if ($limit === null && $offset !== null) {
            $limit = max(1, (int) \Src\classes\ClassSettings::int('property_offset_default_limit', 50));
        }
        $ttl = max(60, \Src\classes\ClassSettings::int('cache_featured_ttl_seconds', 3600));
        $cacheKey = 'properties:featured:' . md5(serialize([
            $limit,
            $offset,
            $viewerUserId,
            $viewerVisitorKey,
            DiscoveryEngine::viewerFingerprint($viewerUserId, $viewerVisitorKey),
        ]));
        $cached = \Src\classes\Cache::remember($cacheKey, $ttl, function () use ($limit, $offset, $viewerUserId, $viewerVisitorKey) {
            $db = new self();
            $behaviorEnabled = DiscoveryEngine::isActive($viewerUserId, $viewerVisitorKey);
            $params = [];

            $behaviorSelect = '0 AS viewer_behavior_score';
            $behaviorOrder = '';
            if ($behaviorEnabled) {
                $behaviorFragment = DiscoveryEngine::behaviorScoreSql($viewerUserId, $viewerVisitorKey);
                $behaviorSelect = $behaviorFragment['sql'];
                $params = $behaviorFragment['params'];
                $behaviorOrder = ', viewer_behavior_score DESC, ' . DiscoveryEngine::discoveryOrderSql() . ' DESC';
            }

            $sql = 'SELECT p.*, u.username AS owner_username, u.name AS owner_name, u.phone AS owner_phone,
                COALESCE((
                    SELECT sp.ranking_weight
                    FROM user_subscriptions us
                    INNER JOIN subscription_plans sp ON sp.id = us.plan_id
                    WHERE us.user_id = p.affiliate_id
                      AND ' . UserSubscription::sqlEffectiveBenefitsCondition('us') . "
                      AND sp.is_active = 1
                    ORDER BY us.starts_at DESC, us.id DESC
                    LIMIT 1
                ), CASE WHEN p.visibility = 'premium' THEN 50 ELSE 0 END) AS owner_plan_weight,
                CASE WHEN u.status = 'ativo' THEN 1 ELSE 0 END AS owner_verified,
                " . User::sqlOwnerTrustedColumn('u') . ",
                {$behaviorSelect}
                FROM {$db->table} p
                LEFT JOIN users u ON p.affiliate_id = u.id
                WHERE p.featured = 1 AND p.status = 'disponivel'
                  AND " . User::sqlExcludePendingDeletionOwners('u') . "
                ORDER BY owner_plan_weight DESC, (p.visibility = 'premium') DESC{$behaviorOrder}, p.created_at DESC";
            if ($limit !== null) {
                $sql .= ' LIMIT ' . max(1, (int) $limit);
                if ($offset !== null) {
                    $sql .= ' OFFSET ' . max(0, (int) $offset);
                }
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        });

        return $cached;
    }

    public static function countFeatured(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) AS total FROM {$db->table} p
                LEFT JOIN users u ON p.affiliate_id = u.id
                WHERE p.featured = 1 AND p.status = 'disponivel'
                  AND " . User::sqlExcludePendingDeletionOwners('u');
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public static function getFiltered($filters = [], $limit = null, $offset = null)
    {
        list($db, $sql, $params) = self::buildFilteredQuery($filters, false);
        if ($limit === null && $offset !== null) {
            $limit = max(1, (int) \Src\classes\ClassSettings::int('property_offset_default_limit', 50));
        }

        $sortMap = [
            'price_asc'  => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'newest'     => 'p.created_at DESC',
            'oldest'     => 'p.created_at ASC',
        ];
        $sortKey = $filters['sort'] ?? 'newest';
        $orderBy = $sortMap[$sortKey] ?? 'p.created_at DESC';
        $viewerUserId = isset($filters['viewer_user_id']) ? (int) $filters['viewer_user_id'] : 0;
        $viewerVisitorKey = trim((string) ($filters['viewer_visitor_key'] ?? ''));
        $discoveryTiebreak = DiscoveryEngine::isActive($viewerUserId, $viewerVisitorKey)
            ? (', ' . DiscoveryEngine::discoveryOrderSql() . ' DESC')
            : '';
        $featuredFirst = !empty($filters['discovery_blend'])
            ? ''
            : '(p.featured = 1) DESC, ';
        $sql .= " ORDER BY {$featuredFirst}owner_plan_weight DESC, (p.visibility = 'premium') DESC, viewer_behavior_score DESC{$discoveryTiebreak}, {$orderBy}";

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, (int) $limit);
            if ($offset !== null) {
                $sql .= ' OFFSET ' . max(0, (int) $offset);
            }
        }

        $cacheKey = self::buildFilterCacheKey(array_merge($filters, [
            'sort' => $sortKey,
            'limit' => $limit ?? 'none',
            'offset' => $offset ?? 0,
            'counting' => false,
        ]));
        $ttl = max(30, \Src\classes\ClassSettings::int('cache_property_list_ttl_seconds', 120));

        return \Src\classes\Cache::remember($cacheKey, $ttl, function () use ($db, $sql, $params) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        });
    }

    /**
     * Cursor (keyset) pagination for property listing.
     * This mode enforces a stable order by created_at/id for scalability.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getFilteredCursor(array $filters = [], int $limit = 20, ?string $cursorCreatedAt = null, ?int $cursorId = null): array
    {
        $limit = min(50, max(1, (int) $limit));
        $filters['sort'] = 'newest';
        $filters['discovery_blend'] = 1;

        list($db, $sql, $params) = self::buildFilteredQuery($filters, false);

        // Enforce keyset on created_at/id (descending).
        if ($cursorCreatedAt !== null && trim($cursorCreatedAt) !== '' && $cursorId !== null && $cursorId > 0) {
            $sql .= ' AND (p.created_at < ? OR (p.created_at = ? AND p.id < ?))';
            $params[] = $cursorCreatedAt;
            $params[] = $cursorCreatedAt;
            $params[] = (int) $cursorId;
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
        $sql .= ' LIMIT ' . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function countFiltered(array $filters = []): int
    {
        list($db, $sql, $params) = self::buildFilteredQuery($filters, true);

        $cacheKey = self::buildFilterCacheKey(array_merge($filters, ['counting' => true]));
        $ttl = max(60, \Src\classes\ClassSettings::int('cache_property_count_ttl_seconds', 300));

        return (int) \Src\classes\Cache::remember($cacheKey, $ttl, function () use ($db, $sql, $params) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($row['total'] ?? 0);
        });
    }

    /**
     * Public property URLs for XML sitemap (disponivel, vendido, alugado).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getPublicSitemapEntries(int $limit = 50000): array
    {
        $limit = max(1, min(50000, $limit));
        $db = new self();
        $sql = "SELECT id, title, images, status, created_at
                FROM {$db->table}
                WHERE status IN ('disponivel', 'vendido', 'alugado')
                ORDER BY created_at DESC
                LIMIT {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function find($id)
    {
        $db = new self();
        $sql = "SELECT p.*, u.username AS owner_username, u.name AS owner_name,
                CASE WHEN u.status = 'ativo' THEN 1 ELSE 0 END AS owner_verified,
                " . User::sqlOwnerTrustedColumn('u') . "
                FROM {$db->table} p
                LEFT JOIN users u ON p.affiliate_id = u.id
                WHERE p.id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $db = new self();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$db->table} WHERE id IN ({$placeholders}) ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getByAffiliate($affiliateId)
    {
        return self::getPortfolioByAffiliate((int) $affiliateId);
    }

    public static function getPortfolioByAffiliate(int $affiliateId): array
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table}
                WHERE affiliate_id = ?
                  AND deletion_purged_at IS NULL
                ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$affiliateId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAllByAffiliateIncludingPurged(int $affiliateId): array
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE affiliate_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$affiliateId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function getActiveAffiliationsForUser(int $userId): array
    {
        $db = new self();
        $sql = "SELECT pa.id, pa.property_id, p.title, p.price, p.location, p.status AS property_status,
                                             u_owner.id AS owner_id, u_owner.username AS owner_username, u_owner.name AS owner_name, u_owner.phone AS owner_phone,
                       COUNT(r.id) AS referral_count
                FROM property_affiliates pa
                JOIN properties p       ON pa.property_id = p.id
                JOIN users u_owner      ON p.affiliate_id = u_owner.id
                LEFT JOIN requests r    ON r.affiliate_id = ? AND r.property_id = p.id
                WHERE pa.user_id = ? AND pa.status = 'ativo'
                  AND p.status NOT IN ('eliminado')
                  AND p.deletion_purged_at IS NULL
                GROUP BY pa.id, pa.property_id, p.title, p.price, p.location, p.status,
                                                 u_owner.id, u_owner.username, u_owner.name, u_owner.phone
                ORDER BY p.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getPending(int $limit = 0, int $offset = 0): array
    {
        $db = new self();
        $sql = "SELECT p.*, u.name as owner_name FROM {$db->table} p
                LEFT JOIN users u ON p.affiliate_id = u.id
                WHERE p.status IN ('pendente', 'em_analise')
                ORDER BY (p.status = 'em_analise') DESC, p.created_at DESC";
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
        $sql = "SELECT COUNT(*) FROM {$db->table} p WHERE p.status IN ('pendente', 'em_analise')";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function startReview(int $id): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'em_analise' WHERE id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public static function approve($id)
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'disponivel' WHERE id = ? AND status = 'em_analise'";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public static function reject($id)
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'rejeitado' WHERE id = ? AND status = 'em_analise'";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public static function validateData($data)
    {
        $errors = [];
        $allowedTypes = PropertyTypeHelper::getAllowedTypes();
        $allowedPurposes = ['venda', 'aluguer_curto', 'aluguer_longo'];
        $allowedTerms = ['mensal', 'trimestral', 'semestral', 'anual'];
        $allowedAffiliateModes = [
            self::AFFILIATE_APPROVAL_MANUAL,
            self::AFFILIATE_APPROVAL_AUTO,
            self::AFFILIATE_APPROVAL_DISABLED,
        ];

        if (empty($data['title'])) {
            $errors[] = 'Título é obrigatório';
        } elseif (mb_strlen((string) $data['title']) > 255) {
            $errors[] = 'Título excede 255 caracteres';
        }

        if (empty($data['description'])) {
            $errors[] = 'Descrição é obrigatória';
        }

        if (!empty($data['country_id']) && !Country::exists((int) $data['country_id'])) {
            $errors[] = 'País inválido';
        }

        if (!empty($data['region_id']) && !Region::exists((int) $data['region_id'])) {
            $errors[] = 'Região inválida';
        }
        if (!empty($data['country_id']) && !empty($data['region_id']) && !Region::belongsToCountry((int) $data['region_id'], (int) $data['country_id'])) {
            $errors[] = 'Região não pertence ao país selecionado';
        }

        if (empty($data['type']) || !PropertyTypeHelper::isValid((string) $data['type'])) {
            $errors[] = 'Tipo de imóvel inválido';
        }

        if (empty($data['purpose']) || !in_array((string) $data['purpose'], $allowedPurposes, true)) {
            $errors[] = 'Finalidade inválida';
        }

        if (!isset($data['price']) || !is_numeric($data['price']) || (float) $data['price'] <= 0) {
            $errors[] = 'Preço deve ser numérico e maior que zero';
        }

        if (empty($data['location'])) {
            $errors[] = 'Localização é obrigatória';
        } elseif (mb_strlen((string) $data['location']) > 255) {
            $errors[] = 'Localização excede 255 caracteres';
        }

        if (isset($data['bedrooms']) && (int) $data['bedrooms'] < 0) {
            $errors[] = 'Quartos não pode ser negativo';
        }

        if (isset($data['bathrooms']) && (int) $data['bathrooms'] < 0) {
            $errors[] = 'Casas de banho não pode ser negativo';
        }

        if (isset($data['area']) && $data['area'] !== null && $data['area'] !== '' && (float) $data['area'] < 0) {
            $errors[] = 'Área não pode ser negativa';
        }

        if (isset($data['images']) && $data['images'] !== '' && $data['images'] !== null) {
            $decoded = json_decode((string) $data['images'], true);
            if (!is_array($decoded)) {
                $errors[] = 'Formato de imagens inválido';
            }
        }

        if (array_key_exists('rent_payment_terms', $data) && $data['rent_payment_terms'] !== null && $data['rent_payment_terms'] !== '') {
            $terms = json_decode((string) $data['rent_payment_terms'], true);
            if (!is_array($terms)) {
                $errors[] = 'Formato de modalidades de pagamento inválido';
            } else {
                foreach ($terms as $term) {
                    if (!in_array((string) $term, $allowedTerms, true)) {
                        $errors[] = 'Modalidade de pagamento inválida';
                        break;
                    }
                }
            }
        }

        if (array_key_exists('affiliate_approval_mode', $data)
            && !in_array((string) $data['affiliate_approval_mode'], $allowedAffiliateModes, true)) {
            $errors[] = 'Modo de afiliação inválido';
        }

        if (($data['purpose'] ?? '') === 'aluguer_longo') {
            $terms = [];
            if (array_key_exists('rent_payment_terms', $data) && $data['rent_payment_terms'] !== null && $data['rent_payment_terms'] !== '') {
                $terms = json_decode((string) $data['rent_payment_terms'], true);
                if (!is_array($terms)) {
                    $terms = [];
                }
            }

            if (empty($terms)) {
                $errors[] = 'Selecione pelo menos uma modalidade de pagamento para aluguer longo';
            }
        }

        return $errors;
    }

    public static function countByOwner($ownerId)
    {
        $db = new self();
        $sql = "SELECT COUNT(*) AS total FROM {$db->table} WHERE affiliate_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        return (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public static function countActiveByOwner(int $ownerId): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) AS total
                FROM {$db->table}
                WHERE affiliate_id = ?
                  AND status IN ('pendente', 'em_analise', 'disponivel')";
        $stmt = $db->prepare($sql);
        $stmt->execute([$ownerId]);
        return (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * Returns aggregated stats for a property owner's portfolio.
     * Used by the Professional/Enterprise reports feature.
     */
    public static function getStatsForOwner(int $ownerId): array
    {
        $db  = new self();
        $conn = $db->ConexaoDB();

        // Portfolio summary
        $summarySql = "SELECT
                COUNT(*)                                                             AS total_properties,
                SUM(CASE WHEN p.status = 'disponivel' THEN 1 ELSE 0 END)           AS available,
                SUM(CASE WHEN p.status = 'vendido'    THEN 1 ELSE 0 END)           AS sold,
                SUM(CASE WHEN p.status = 'alugado'    THEN 1 ELSE 0 END)           AS rented,
                SUM(CASE WHEN p.status = 'pendente'   THEN 1 ELSE 0 END)           AS pending,
                SUM(CASE WHEN p.status = 'rejeitado'  THEN 1 ELSE 0 END)           AS rejected,
                SUM(CASE WHEN p.status = 'eliminado' AND p.deletion_purged_at IS NULL THEN 1 ELSE 0 END) AS pending_deletion,
                SUM(CASE WHEN p.featured = 1 AND p.status = 'disponivel' THEN 1 ELSE 0 END) AS featured,
                SUM(CASE WHEN p.purpose = 'venda'     THEN 1 ELSE 0 END)           AS for_sale,
                SUM(CASE WHEN p.purpose LIKE 'aluguer%' THEN 1 ELSE 0 END)         AS for_rent,
                SUM(CASE WHEN p.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 ELSE 0 END) AS new_this_month
            FROM {$db->table} p
            WHERE p.affiliate_id = ?";
        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->execute([$ownerId]);
        $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        // Requests per property (last 90 days)
        $requestsSql = "SELECT
                p.id, p.title, p.status AS property_status, p.purpose,
                COUNT(r.id)                                                                        AS total_requests,
                SUM(CASE WHEN r.status = 'confirmado'  THEN 1 ELSE 0 END)                        AS confirmed_requests,
                SUM(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS requests_last_30d,
                SUM(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS requests_last_90d
            FROM {$db->table} p
            LEFT JOIN requests r ON r.property_id = p.id
            WHERE p.affiliate_id = ?
            GROUP BY p.id, p.title, p.status, p.purpose
            ORDER BY total_requests DESC, p.created_at DESC
            LIMIT 50";
        $requestsStmt = $conn->prepare($requestsSql);
        $requestsStmt->execute([$ownerId]);
        $perProperty = $requestsStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Monthly new requests trend (last 6 months)
        $trendSql = "SELECT
                DATE_FORMAT(r.created_at, '%Y-%m') AS month_label,
                COUNT(r.id)                         AS request_count
            FROM requests r
            JOIN {$db->table} p ON r.property_id = p.id
            WHERE p.affiliate_id = ?
              AND r.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
            ORDER BY month_label ASC";
        $trendStmt = $conn->prepare($trendSql);
        $trendStmt->execute([$ownerId]);
        $trend = $trendStmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'summary'      => $summary,
            'per_property' => $perProperty,
            'trend'        => $trend,
        ];
    }

    public static function getStatusStats(): array
    {
        $db = new self();
        $sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendente,
            SUM(CASE WHEN status = 'em_analise' THEN 1 ELSE 0 END) AS em_analise,
            SUM(CASE WHEN status = 'disponivel' THEN 1 ELSE 0 END) AS disponivel,
            SUM(CASE WHEN status = 'vendido' THEN 1 ELSE 0 END) AS vendido,
            SUM(CASE WHEN status = 'alugado' THEN 1 ELSE 0 END) AS alugado,
            SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) AS rejeitado,
            SUM(CASE WHEN status = 'eliminado' AND deletion_purged_at IS NULL THEN 1 ELSE 0 END) AS eliminado,
            SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 ELSE 0 END) AS new_this_month,
            ROUND(
                SUM(CASE WHEN status = 'disponivel' THEN 1 ELSE 0 END) /
                NULLIF(SUM(CASE WHEN status IN ('disponivel','rejeitado') THEN 1 ELSE 0 END), 0) * 100, 1
            ) AS approval_rate
        FROM {$db->table}";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update editable fields of a property by owner.
     * Returns true on success.
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'title', 'description', 'type', 'purpose', 'price',
            'country_id', 'region_id', 'location', 'bedrooms', 'bathrooms', 'area',
            'rental_days', 'rental_months',
            'images', 'video_url', 'rent_payment_terms', 'affiliate_approval_mode', 'status',
        ];
        $set    = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[]    = "`{$field}` = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($set)) {
            return false;
        }
        $params[] = $id;
        $db       = new self();
        $sql      = "UPDATE {$db->table} SET " . implode(', ', $set) . ' WHERE id = ?';
        $stmt     = $db->prepare($sql);
        $stmt->execute($params);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            self::bumpFilterCacheVersion();
        }
        return $ok;
    }

    /**
     * Estado final após fecho comercial (venda ou aluguer).
     */
    public static function resolveCommercialClosureStatus(array $property): ?string
    {
        $purpose = (string) ($property['purpose'] ?? '');
        if ($purpose === 'venda') {
            return 'vendido';
        }
        if (str_starts_with($purpose, 'aluguer')) {
            return 'alugado';
        }

        return null;
    }

    /**
     * Marca o imóvel como vendido/alugado após fecho na plataforma.
     */
    public static function setCommercialClosureStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['vendido', 'alugado'], true)) {
            return false;
        }

        $property = self::find($id);
        if (!$property || (string) ($property['status'] ?? '') === 'eliminado') {
            return false;
        }

        if ((string) ($property['status'] ?? '') === $status) {
            return true;
        }

        $purpose = (string) ($property['purpose'] ?? '');
        if ($status === 'vendido' && $purpose !== 'venda') {
            return false;
        }
        if ($status === 'alugado' && !str_starts_with($purpose, 'aluguer')) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table} SET status = ? WHERE id = ? AND status NOT IN ('eliminado')";
        $stmt = $db->prepare($sql);
        $stmt->execute([$status, $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            self::bumpFilterCacheVersion();
        }

        return $ok;
    }

    /**
     * Set only the status of a property.
     */
    public static function setStatus(int $id, string $status): bool
    {
        $allowed = ['pendente', 'disponivel', 'vendido', 'alugado', 'rejeitado'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $db   = new self();
        $sql  = "UPDATE {$db->table} SET status = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$status, $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            self::bumpFilterCacheVersion();
        }
        return $ok;
    }

    /**
     * Set the featured flag of a property.
     */
    public static function setFeatured(int $id, bool $featured): bool
    {
        $db   = new self();
        $sql  = "UPDATE {$db->table} SET featured = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$featured ? 1 : 0, $id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) {
            self::bumpFilterCacheVersion();
        }
        return $ok;
    }

    public static function statusLabel(string $status): string
    {
        $map = [
            'disponivel' => 'Disponível',
            'vendido' => 'Vendido',
            'alugado' => 'Alugado',
            'pendente' => 'Pendente',
            'em_analise' => 'Em análise',
            'rejeitado' => 'Rejeitado',
            'eliminado' => 'Eliminado',
        ];

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function isPendingDeletion(?array $property): bool
    {
        if (!is_array($property)) {
            return false;
        }

        return (string) ($property['status'] ?? '') === 'eliminado'
            && empty($property['deletion_purged_at']);
    }

    public static function isPubliclyListed(?array $property): bool
    {
        if (!is_array($property)) {
            return false;
        }

        return (string) ($property['status'] ?? '') === 'disponivel';
    }

    public static function getPropertyDeletionGraceDays(): int
    {
        return max(1, (int) \Src\classes\ClassSettings::int('property_deletion_grace_days', 30));
    }

    /**
     * @return array<int, string>
     */
    public static function deletableStatuses(): array
    {
        return ['disponivel', 'pendente', 'em_analise', 'rejeitado'];
    }

    public static function canRequestDeletion(?array $property): bool
    {
        if (!is_array($property) || self::isPendingDeletion($property)) {
            return false;
        }

        return in_array((string) ($property['status'] ?? ''), self::deletableStatuses(), true);
    }

    public static function requestDeletion(int $propertyId, int $ownerId, ?string $scheduledAt = null): bool
    {
        if ($propertyId <= 0 || $ownerId <= 0) {
            return false;
        }

        $property = self::find($propertyId);
        if (!$property
            || (int) ($property['affiliate_id'] ?? 0) !== $ownerId
            || !self::canRequestDeletion($property)) {
            return false;
        }

        if ($scheduledAt === null || trim($scheduledAt) === '' || strtotime($scheduledAt) === false) {
            $days = self::getPropertyDeletionGraceDays();
            $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        } else {
            $scheduledAt = date('Y-m-d H:i:s', strtotime($scheduledAt));
        }
        $previousStatus = (string) ($property['status'] ?? 'disponivel');

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status = 'eliminado',
                    featured = 0,
                    deletion_previous_status = ?,
                    deletion_requested_at = NOW(),
                    deletion_scheduled_at = ?,
                    deletion_reminder_sent_at = NULL,
                    deletion_purged_at = NULL
                WHERE id = ?
                  AND affiliate_id = ?
                  AND status = ?
                  AND deletion_purged_at IS NULL";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$previousStatus, $scheduledAt, $propertyId, $ownerId, $previousStatus])) {
            return false;
        }

        if ($stmt->rowCount() <= 0) {
            return false;
        }

        self::bumpFilterCacheVersion();

        return true;
    }

    public static function cancelDeletion(int $propertyId, int $ownerId): bool
    {
        if ($propertyId <= 0 || $ownerId <= 0) {
            return false;
        }

        $property = self::find($propertyId);
        if (!$property
            || (int) ($property['affiliate_id'] ?? 0) !== $ownerId
            || !self::isPendingDeletion($property)) {
            return false;
        }

        $restoreStatus = (string) ($property['deletion_previous_status'] ?? 'disponivel');
        $allowedRestore = array_merge(self::deletableStatuses(), ['eliminado']);
        if (!in_array($restoreStatus, $allowedRestore, true) || $restoreStatus === 'eliminado') {
            $restoreStatus = 'disponivel';
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status = ?,
                    deletion_previous_status = NULL,
                    deletion_requested_at = NULL,
                    deletion_scheduled_at = NULL,
                    deletion_reminder_sent_at = NULL
                WHERE id = ?
                  AND affiliate_id = ?
                  AND status = 'eliminado'
                  AND deletion_purged_at IS NULL";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$restoreStatus, $propertyId, $ownerId])) {
            return false;
        }

        if ($stmt->rowCount() <= 0) {
            return false;
        }

        self::bumpFilterCacheVersion();

        return true;
    }

    public static function purgePermanently(int $propertyId, bool $force = false): bool
    {
        if ($propertyId <= 0) {
            return false;
        }

        $property = self::find($propertyId);
        if (!$property) {
            return false;
        }

        if (!empty($property['deletion_purged_at'])) {
            return false;
        }

        if (!$force) {
            if (!self::isPendingDeletion($property)) {
                return false;
            }

            $scheduledAt = strtotime((string) ($property['deletion_scheduled_at'] ?? ''));
            if ($scheduledAt > time()) {
                return false;
            }
        } elseif ((string) ($property['status'] ?? '') !== 'eliminado') {
            $ownerId = (int) ($property['affiliate_id'] ?? 0);
            if ($ownerId > 0 && self::canRequestDeletion($property)) {
                self::requestDeletion($propertyId, $ownerId);
                $property = self::find($propertyId) ?: $property;
            }
        }

        \App\services\ComplianceDeletionService::purgePropertyResidualData($property);

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET title = 'Imóvel removido',
                    description = NULL,
                    images = '[]',
                    video_url = NULL,
                    featured = 0,
                    status = 'eliminado',
                    deletion_purged_at = NOW()
                WHERE id = ?
                  AND deletion_purged_at IS NULL";
        if (!$force) {
            $sql .= " AND status = 'eliminado'
                      AND deletion_scheduled_at IS NOT NULL
                      AND deletion_scheduled_at <= NOW()";
        }

        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$propertyId])) {
            return false;
        }

        if ($stmt->rowCount() <= 0) {
            return false;
        }

        self::bumpFilterCacheVersion();

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getDueDeletions(int $limit = 50): array
    {
        $db = new self();
        $sql = "SELECT id, title, affiliate_id, deletion_requested_at, deletion_scheduled_at
                FROM {$db->table}
                WHERE status = 'eliminado'
                  AND deletion_purged_at IS NULL
                  AND deletion_scheduled_at IS NOT NULL
                  AND deletion_scheduled_at <= NOW()
                ORDER BY deletion_scheduled_at ASC, id ASC
                LIMIT " . max(1, (int) $limit);
        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function processDueDeletions(int $limit = 50): int
    {
        $processed = 0;
        foreach (self::getDueDeletions($limit) as $row) {
            $propertyId = (int) ($row['id'] ?? 0);
            if ($propertyId > 0 && self::purgePermanently($propertyId)) {
                $processed++;
            }
        }

        return $processed;
    }

    public static function cancelAllDeletionsForOwner(int $ownerId): int
    {
        if ($ownerId <= 0) {
            return 0;
        }

        $cancelled = 0;
        foreach (self::getPortfolioByAffiliate($ownerId) as $property) {
            $propertyId = (int) ($property['id'] ?? 0);
            if ($propertyId > 0 && self::cancelDeletion($propertyId, $ownerId)) {
                $cancelled++;
            }
        }

        return $cancelled;
    }

    public static function countOverdueDeletions(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*)
                FROM {$db->table}
                WHERE status = 'eliminado'
                  AND deletion_purged_at IS NULL
                  AND deletion_scheduled_at IS NOT NULL
                  AND deletion_scheduled_at < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getDeletionReminderCandidates(int $daysBefore, int $limit = 50): array
    {
        $db = new self();
        $sql = "SELECT id, title, affiliate_id, deletion_scheduled_at
                FROM {$db->table}
                WHERE status = 'eliminado'
                  AND deletion_purged_at IS NULL
                  AND deletion_scheduled_at IS NOT NULL
                  AND deletion_reminder_sent_at IS NULL
                  AND deletion_scheduled_at > NOW()
                  AND deletion_scheduled_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
                ORDER BY deletion_scheduled_at ASC, id ASC
                LIMIT " . max(1, (int) $limit);
        $stmt = $db->prepare($sql);
        $stmt->execute([max(1, $daysBefore)]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function markDeletionReminderSent(int $propertyId): bool
    {
        if ($propertyId <= 0) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET deletion_reminder_sent_at = NOW()
                WHERE id = ?
                  AND deletion_reminder_sent_at IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute([$propertyId]);

        return $stmt->rowCount() > 0;
    }
}
