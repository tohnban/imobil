<?php

namespace App\model;

class Document extends ManipularBanco
{
    private static ?bool $documentsTableExists = null;

    private static function hasDocumentsTable(): bool
    {
        if (self::$documentsTableExists !== null) {
            return self::$documentsTableExists;
        }

        try {
            $db = new self();
            $stmt = $db->prepare("SHOW TABLES LIKE 'documents'");
            $stmt->execute();
            self::$documentsTableExists = (bool) $stmt->fetch();
        } catch (\Throwable $e) {
            self::$documentsTableExists = false;
        }

        return self::$documentsTableExists;
    }

    /**
     * Create a new document record
     *
     * @param int $userId User ID (nullable for property documents)
     * @param int $propertyId Property ID (nullable for user documents)
     * @param string $type Document type
     * @param string $filename Stored filename
     * @return bool
     */
    public static function create(?int $userId, ?int $propertyId, string $type, string $filename, string $version = 'v1'): bool
    {
        if (!self::hasDocumentsTable()) {
            return false;
        }

        $db = new self();
        $sql = "INSERT INTO documents (user_id, property_id, type, filename, version, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pendente', NOW())";

        $stmt = $db->prepare($sql);
        return $stmt->execute([$userId, $propertyId, $type, $filename, $version]);
    }

    /**
     * Get latest document for a user
     *
     * @param int $userId
     * @param string $type Document type (optional)
     * @return array|null
     */
    public static function getLatestByUser(int $userId, ?string $type = null): ?array
    {
        if (!self::hasDocumentsTable()) {
            return null;
        }

        $db = new self();
        $sql = 'SELECT * FROM documents WHERE user_id = ?';
        $params = [$userId];

        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get all document versions for a user
     *
     * @param int $userId
     * @param string $type Document type (optional)
     * @return array
     */
    public static function getAllByUser(int $userId, ?string $type = null): array
    {
        if (!self::hasDocumentsTable()) {
            return [];
        }

        $db = new self();
        $sql = 'SELECT * FROM documents WHERE user_id = ?';
        $params = [$userId];

        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function deleteAllRecordsForUser(int $userId): int
    {
        if (!self::hasDocumentsTable() || $userId <= 0) {
            return 0;
        }

        $db = new self();
        $sql = 'DELETE FROM documents WHERE user_id = ?';
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$userId])) {
            return 0;
        }

        return $stmt->rowCount();
    }

    /**
     * Get document by ID
     *
     * @param int $documentId
     * @return array|null
     */
    public static function findById(int $documentId): ?array
    {
        if (!self::hasDocumentsTable()) {
            return null;
        }

        $db = new self();
        $sql = 'SELECT * FROM documents WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute([$documentId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public static function findByFilename(string $filename): ?array
    {
        if (!self::hasDocumentsTable()) {
            return null;
        }

        $filename = basename(str_replace('\\', '/', trim($filename)));
        if ($filename === '') {
            return null;
        }

        $db = new self();
        $sql = 'SELECT * FROM documents WHERE filename = ? ORDER BY id DESC LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([$filename]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get latest document for a property
     *
     * @param int $propertyId
     * @param string $type Document type (optional)
     * @return array|null
     */
    public static function getLatestByProperty(int $propertyId, ?string $type = null): ?array
    {
        if (!self::hasDocumentsTable()) {
            return null;
        }

        $db = new self();
        $sql = 'SELECT * FROM documents WHERE property_id = ?';
        $params = [$propertyId];

        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAllByProperty(int $propertyId, ?string $type = null): array
    {
        if (!self::hasDocumentsTable() || $propertyId <= 0) {
            return [];
        }

        $db = new self();
        $sql = 'SELECT * FROM documents WHERE property_id = ?';
        $params = [$propertyId];

        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function deleteAllRecordsForProperty(int $propertyId): int
    {
        if (!self::hasDocumentsTable() || $propertyId <= 0) {
            return 0;
        }

        $db = new self();
        $sql = 'DELETE FROM documents WHERE property_id = ?';
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$propertyId])) {
            return 0;
        }

        return $stmt->rowCount();
    }

    /**
     * Get all pending documents (for admin review)
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getPending(int $limit = 50, int $offset = 0): array
    {
        if (!self::hasDocumentsTable()) {
            return [];
        }

        $db = new self();
        $sql = "SELECT d.*, u.name as user_name, u.email as user_email, p.title as property_title
                FROM documents d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN properties p ON d.property_id = p.id
                WHERE d.status = 'pendente'
                ORDER BY d.created_at ASC
                LIMIT ? OFFSET ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$limit, $offset]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count pending documents
     *
     * @return int
     */
    public static function countPending(): int
    {
        if (!self::hasDocumentsTable()) {
            return 0;
        }

        $db = new self();
        $sql = "SELECT COUNT(*) as count FROM documents WHERE status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get all rejected documents (for re-submission)
     *
     * @param int $userId
     * @return array
     */
    public static function getRejectedByUser(int $userId): array
    {
        if (!self::hasDocumentsTable()) {
            return [];
        }

        $db = new self();
        $sql = "SELECT * FROM documents WHERE user_id = ? AND status = 'rejeitado' ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update document status to approved
     *
     * @param int $documentId
     * @param int $reviewedById Admin user ID
     * @return bool
     */
    public static function approve(int $documentId, int $reviewedById): bool
    {
        if (!self::hasDocumentsTable()) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE documents 
                SET status = 'aprovado', reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?";

        $stmt = $db->prepare($sql);
        return $stmt->execute([$reviewedById, $documentId]);
    }

    /**
     * Update document status to rejected with reason
     *
     * @param int $documentId
     * @param string $rejectionReason Reason for rejection (required)
     * @param int $reviewedById Admin user ID
     * @return bool
     */
    public static function reject(int $documentId, string $rejectionReason, int $reviewedById): bool
    {
        if (!self::hasDocumentsTable()) {
            return false;
        }

        $db = new self();
        if (empty(trim($rejectionReason))) {
            throw new \Exception('Motivo da rejeição é obrigatório');
        }

        $sql = "UPDATE documents 
                SET status = 'rejeitado', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?";

        $stmt = $db->prepare($sql);
        return $stmt->execute([$rejectionReason, $reviewedById, $documentId]);
    }

    /**
     * Get document status summary for a user
     *
     * @param int $userId
     * @return array ['total' => int, 'pendente' => int, 'aprovado' => int, 'rejeitado' => int]
     */
    public static function getStatusSummaryByUser(int $userId): array
    {
        if (!self::hasDocumentsTable()) {
            return [
                'total' => 0,
                'pendente' => 0,
                'aprovado' => 0,
                'rejeitado' => 0,
            ];
        }

        $db = new self();
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendente,
                    SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as aprovado,
                    SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as rejeitado
                FROM documents
                WHERE user_id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return [
            'total' => (int) ($result['total'] ?? 0),
            'pendente' => (int) ($result['pendente'] ?? 0),
            'aprovado' => (int) ($result['aprovado'] ?? 0),
            'rejeitado' => (int) ($result['rejeitado'] ?? 0),
        ];
    }

    /**
     * Get document compliance status for user account
     * Returns status based on document validation
     *
     * @param int $userId
     * @return string 'compliant', 'pending', 'rejected', 'missing'
     */
    public static function getComplianceStatus(int $userId): string
    {
        if (!self::hasDocumentsTable()) {
            return 'missing';
        }

        $latest = self::getLatestByUser($userId);

        if ($latest === null) {
            return 'missing';
        }

        return match($latest['status']) {
            'aprovado' => 'compliant',
            'pendente' => 'pending',
            'rejeitado' => 'rejected',
            default => 'missing'
        };
    }

    /**
     * Get statistics on document compliance
     *
     * @return array Statistics
     */
    public static function getComplianceStats(): array
    {
        if (!self::hasDocumentsTable()) {
            return [
                'total_users_with_docs' => 0,
                'total_pending' => 0,
                'total_approved' => 0,
                'total_rejected' => 0,
                'avg_review_time_days' => 0,
                'users_with_rejections' => 0,
            ];
        }

        $db = new self();
        $sql = "SELECT 
                    COUNT(DISTINCT user_id) as total_users_with_docs,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as total_pending,
                    SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as total_approved,
                    SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as total_rejected,
                    AVG(DATEDIFF(reviewed_at, created_at)) as avg_review_time_days,
                    COUNT(DISTINCT CASE WHEN status = 'rejeitado' THEN user_id END) as users_with_rejections
                FROM documents";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return [
            'total_users_with_docs' => (int) ($result['total_users_with_docs'] ?? 0),
            'total_pending' => (int) ($result['total_pending'] ?? 0),
            'total_approved' => (int) ($result['total_approved'] ?? 0),
            'total_rejected' => (int) ($result['total_rejected'] ?? 0),
            'avg_review_time_days' => round((float) ($result['avg_review_time_days'] ?? 0), 1),
            'users_with_rejections' => (int) ($result['users_with_rejections'] ?? 0),
        ];
    }
}
