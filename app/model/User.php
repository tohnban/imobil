<?php

namespace App\model;

class User extends ManipularBanco
{
    protected $table = 'users';

    public static function findById($id)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function findByEmail($email)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE email = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function findByPhone($phone)
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }

        $user = self::findByPhoneExact($phone);
        if ($user) {
            return $user;
        }

        $normalized = \Src\classes\PhoneHelper::normalize($phone);
        if ($normalized !== '' && $normalized !== $phone) {
            $user = self::findByPhoneExact($normalized);
            if ($user) {
                return $user;
            }
        }

        $inputDigits = \Src\classes\PhoneHelper::comparableDigits($phone);
        if (strlen($inputDigits) < 9) {
            return null;
        }

        $db = new self();
        $sql = "SELECT * FROM {$db->table}
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?
                   OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?";
        $localDigits = str_starts_with($inputDigits, '244') ? substr($inputDigits, 3) : $inputDigits;
        $stmt = $db->prepare($sql);
        $stmt->execute([$inputDigits, $localDigits]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private static function findByPhoneExact(string $phone): ?array
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE phone = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$phone]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findByEmailExceptId($email, $id)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE email = ? AND id <> ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$email, $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function findByPhoneExceptId($phone, $id)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE phone = ? AND id <> ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$phone, $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function findByDocumentNumberExceptId(string $number, int $id): ?array
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE document_number = ? AND id <> ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$number, $id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findByAffiliateCode($code)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE affiliate_code = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function findByUsername(string $username): ?array
    {
        $username = \Src\classes\UsernameHelper::normalize($username);
        if ($username === '') {
            return null;
        }

        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE username = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$username]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findByUsernameExceptId(string $username, int $id): ?array
    {
        $username = \Src\classes\UsernameHelper::normalize($username);
        if ($username === '') {
            return null;
        }

        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE username = ? AND id <> ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$username, $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function create($data)
    {
        $db = new self();
        if (empty($data['username'])) {
            $data['username'] = \Src\classes\UsernameHelper::generateUniqueFromName((string) ($data['name'] ?? ''));
        } else {
            $data['username'] = \Src\classes\UsernameHelper::normalize((string) $data['username']);
        }
        if (!isset($data['affiliate_code']) || empty($data['affiliate_code'])) {
            try {
                $data['affiliate_code'] = 'AFF' . strtoupper(bin2hex(random_bytes(4)));
            } catch (\Exception $e) {
                $data['affiliate_code'] = 'AFF' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
            }
        }
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['status'] = 'pendente'; // Usuários ficam pendentes até aprovação
        $data['created_at'] = date('Y-m-d H:i:s');
        unset($data['password_confirm']);

        $allowedColumns = [
            'email',
            'password',
            'name',
            'username',
            'user_type',
            'document_number',
            'phone',
            'is_affiliate',
            'affiliate_code',
            'status',
            'document_file',
            'profile_photo',
            'created_at',
        ];

        return $db->insert($db->table, $data, $allowedColumns);
    }

    public static function validateData($data)
    {
        $errors = [];
        if (empty($data['name'])) {
            $errors[] = \Src\classes\AuthRegisterFeedback::NAME_REQUIRED;
        }
        if (empty($data['email'])) {
            $errors[] = \Src\classes\AuthRegisterFeedback::EMAIL_REQUIRED;
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = \Src\classes\AuthRegisterFeedback::EMAIL_INVALID;
        }
        if (!empty($data['email']) && self::findByEmail($data['email'])) {
            $errors[] = \Src\classes\AuthRegisterFeedback::EMAIL_TAKEN;
        }
        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone !== '') {
            $phone = \Src\classes\PhoneHelper::normalize($phone);
        }
        if ($phone === '') {
            $errors[] = \Src\classes\AuthRegisterFeedback::PHONE_REQUIRED;
        } elseif (self::findByPhone($phone)) {
            $errors[] = \Src\classes\AuthRegisterFeedback::PHONE_TAKEN;
        }
        if (empty($data['password']) || strlen($data['password']) < 6) {
            $errors[] = \Src\classes\AuthRegisterFeedback::PASSWORD_SHORT;
        }
        if (($data['password'] ?? '') !== ($data['password_confirm'] ?? '')) {
            $errors[] = \Src\classes\AuthRegisterFeedback::PASSWORD_MISMATCH;
        }

        $userType = $data['user_type'] ?? '';
        $docNumber = trim((string) ($data['document_number'] ?? ''));

        if (empty($userType)) {
            $errors[] = \Src\classes\AuthRegisterFeedback::USER_TYPE_REQUIRED;
        }
        if (empty($docNumber)) {
            $errors[] = \Src\classes\AuthRegisterFeedback::DOCUMENT_REQUIRED;
        } elseif ($userType === 'pessoa_fisica') {
            $docCompact = preg_replace('/[^A-Za-z0-9]/', '', $docNumber);
            if (strlen($docCompact) < 5 || strlen($docCompact) > 24 || !preg_match('/^[A-Za-z0-9\s.\-\/]+$/', $docNumber)) {
                $errors[] = \Src\classes\AuthRegisterFeedback::DOCUMENT_BI_INVALID;
            }
        } elseif ($userType === 'pessoa_juridica' && !preg_match('/^\d{10}$/', $docNumber)) {
            $errors[] = \Src\classes\AuthRegisterFeedback::DOCUMENT_NIF_INVALID;
        }

        if (!empty($docNumber) && self::findByDocumentNumber($docNumber)) {
            $errors[] = \Src\classes\AuthRegisterFeedback::DOCUMENT_TAKEN;
        }

        return $errors;
    }

    public static function findByIdNumber($idNumber)
    {
        return self::findByDocumentNumber($idNumber);
    }

    public static function findByNif($nif)
    {
        return self::findByDocumentNumber($nif);
    }

    public static function findByDocumentNumber($number)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE document_number = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$number]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function getPendingUsers(int $limit = 0, int $offset = 0)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE status = 'pendente' ORDER BY created_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countPendingUsers(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} WHERE status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private static function manageableUsersSearchClause(string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return ['sql' => '', 'params' => []];
        }

        $wildcard = '%' . $search . '%';
        $usernameWildcard = '%' . strtolower(ltrim($search, '@')) . '%';

        return [
            'sql' => ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR document_number LIKE ? OR LOWER(username) LIKE ?)',
            'params' => [$wildcard, $wildcard, $wildcard, $wildcard, $usernameWildcard],
        ];
    }

    public static function getManageableUsers(
        int $limit = 20,
        int $offset = 0,
        string $statusFilter = 'all',
        string $search = ''
    ): array {
        $db = new self();
        $allowedFilters = ['all', 'ativo', 'rejeitado', 'pendente', 'suspenso', 'a_eliminar'];
        if (!in_array($statusFilter, $allowedFilters, true)) {
            $statusFilter = 'all';
        }

        $sql = "SELECT id, name, username, email, phone, role, status, suspended_until, deletion_requested_at, deletion_scheduled_at, created_at
                FROM {$db->table}
                WHERE is_admin = 0
                  AND role = 'utilizador'";
        $params = [];

        if ($statusFilter === 'a_eliminar') {
            $sql .= ' AND deletion_requested_at IS NOT NULL';
        } elseif ($statusFilter === 'ativo') {
            $sql .= ' AND status = ? AND (suspended_until IS NULL OR suspended_until <= NOW()) AND deletion_requested_at IS NULL';
            $params[] = 'ativo';
        } elseif ($statusFilter === 'suspenso') {
            $sql .= " AND status = 'ativo' AND suspended_until > NOW() AND deletion_requested_at IS NULL";
        } elseif ($statusFilter !== 'all') {
            $sql .= ' AND status = ? AND deletion_requested_at IS NULL';
            $params[] = $statusFilter;
        }

        $searchClause = self::manageableUsersSearchClause($search);
        $sql .= $searchClause['sql'];
        $params = array_merge($params, $searchClause['params']);

        $sql .= ' ORDER BY created_at DESC, id DESC
                  LIMIT ' . max(1, (int) $limit) . ' OFFSET ' . max(0, (int) $offset);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function countManageableUsers(string $statusFilter = 'all', string $search = ''): int
    {
        $db = new self();
        $allowedFilters = ['all', 'ativo', 'rejeitado', 'pendente', 'suspenso', 'a_eliminar'];
        if (!in_array($statusFilter, $allowedFilters, true)) {
            $statusFilter = 'all';
        }

        $sql = "SELECT COUNT(*)
                FROM {$db->table}
                WHERE is_admin = 0
                  AND role = 'utilizador'";
        $params = [];

        if ($statusFilter === 'a_eliminar') {
            $sql .= ' AND deletion_requested_at IS NOT NULL';
        } elseif ($statusFilter === 'ativo') {
            $sql .= ' AND status = ? AND (suspended_until IS NULL OR suspended_until <= NOW()) AND deletion_requested_at IS NULL';
            $params[] = 'ativo';
        } elseif ($statusFilter === 'suspenso') {
            $sql .= " AND status = 'ativo' AND suspended_until > NOW() AND deletion_requested_at IS NULL";
        } elseif ($statusFilter !== 'all') {
            $sql .= ' AND status = ? AND deletion_requested_at IS NULL';
            $params[] = $statusFilter;
        }

        $searchClause = self::manageableUsersSearchClause($search);
        $sql .= $searchClause['sql'];
        $params = array_merge($params, $searchClause['params']);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function setStatusByAdmin(int $userId, string $status): bool
    {
        $allowed = ['ativo', 'rejeitado'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET status = ?, suspended_until = NULL
                WHERE id = ?
                  AND is_admin = 0
                  AND role = 'utilizador'";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$status, $userId])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function blockAccessByAdmin(int $userId): bool
    {
        return self::suspendByAdmin($userId, 3650);
    }

    public static function suspendByAdmin(int $userId, int $days): bool
    {
        if ($days < 1) {
            return false;
        }

        $until = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET suspended_until = ?, status = 'ativo'
                WHERE id = ?
                  AND is_admin = 0
                  AND role = 'utilizador'";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$until, $userId])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function unsuspendByAdmin(int $userId): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET suspended_until = NULL
                WHERE id = ?
                  AND is_admin = 0
                  AND role = 'utilizador'";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$userId])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function getActiveAdminIds()
    {
        $db = new self();
        $sql = "SELECT id FROM {$db->table} WHERE status = 'ativo' AND (is_admin = 1 OR role IN ('super_admin', 'moderador', 'financeiro', 'suporte'))";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map('intval', array_column($rows, 'id'));
    }

    public static function assignRole(int $userId, string $role): bool
    {
        $allowedRoles = ['super_admin', 'moderador', 'financeiro', 'suporte', 'utilizador'];
        if (!in_array($role, $allowedRoles, true)) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table} SET role = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$role, $userId]);
    }

    public static function getAdministrativeUsers(): array
    {
        $db = new self();
        $sql = "SELECT id, name, email, phone, role, status, is_admin, suspended_until, created_at
                FROM {$db->table}
                WHERE is_admin = 1
                   OR role IN ('super_admin', 'moderador', 'financeiro', 'suporte')
                ORDER BY FIELD(role, 'super_admin', 'moderador', 'financeiro', 'suporte'), created_at ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function setAdministrativeRole(int $userId, string $role): bool
    {
        $allowedRoles = ['super_admin', 'moderador', 'financeiro', 'suporte'];
        if (!in_array($role, $allowedRoles, true)) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET role = ?, is_admin = 1
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$role, $userId])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    public static function countActiveSuperAdmins(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*)
                FROM {$db->table}
                WHERE role = 'super_admin'
                  AND status = 'ativo'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function isAdministrativeUser(?array $user): bool
    {
        if (!is_array($user)) {
            return false;
        }

        if (!empty($user['is_admin'])) {
            return true;
        }

        $role = strtolower(trim((string) ($user['role'] ?? '')));

        return in_array($role, ['super_admin', 'moderador', 'financeiro', 'suporte'], true);
    }

    /**
     * @return array{ok:bool, error?:string, user_id?:int}
     */
    public static function createAdministrativeUser(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = \Src\classes\PhoneHelper::normalize(trim((string) ($data['phone'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirm'] ?? '');
        $role = strtolower(trim((string) ($data['role'] ?? '')));

        if ($name === '') {
            return ['ok' => false, 'error' => 'Indique o nome do administrador.'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email inválido.'];
        }
        if (self::findByEmail($email)) {
            return ['ok' => false, 'error' => 'Este email já está registado.'];
        }
        if ($phone === '') {
            return ['ok' => false, 'error' => 'Indique um telefone válido.'];
        }
        if (self::findByPhone($phone)) {
            return ['ok' => false, 'error' => 'Este telefone já está registado.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'error' => 'A palavra-passe deve ter pelo menos 6 caracteres.'];
        }
        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'error' => 'As palavras-passe não coincidem.'];
        }

        $allowedRoles = ['super_admin', 'moderador', 'financeiro', 'suporte'];
        if (!in_array($role, $allowedRoles, true)) {
            return ['ok' => false, 'error' => 'Papel administrativo inválido.'];
        }

        $documentNumber = self::generateInternalAdminDocumentNumber();
        $now = date('Y-m-d H:i:s');

        $payload = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'username' => \Src\classes\UsernameHelper::generateUniqueFromName($name),
            'user_type' => 'pessoa_fisica',
            'document_number' => $documentNumber,
            'is_affiliate' => 0,
            'is_admin' => 1,
            'role' => $role,
            'status' => 'ativo',
            'email_verified_at' => $now,
            'created_at' => $now,
        ];

        $db = new self();
        $insertId = $db->insert($db->table, $payload, [
            'name',
            'email',
            'phone',
            'password',
            'username',
            'user_type',
            'document_number',
            'is_affiliate',
            'is_admin',
            'role',
            'status',
            'email_verified_at',
            'created_at',
        ]);

        if (!$insertId) {
            return ['ok' => false, 'error' => 'Não foi possível criar a conta administrativa.'];
        }

        return ['ok' => true, 'user_id' => (int) $insertId];
    }

    public static function revokeAdministrativeAccess(int $userId): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET role = 'utilizador',
                    is_admin = 0,
                    suspended_until = NULL,
                    status = 'ativo'
                WHERE id = ?
                  AND (is_admin = 1 OR role IN ('super_admin', 'moderador', 'financeiro', 'suporte'))";
        $stmt = $db->prepare($sql);

        return $stmt->execute([$userId]) && $stmt->rowCount() > 0;
    }

    public static function suspendAdministrativeAccess(int $userId, int $days): bool
    {
        if ($days < 1) {
            return false;
        }

        $until = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET suspended_until = ?, status = 'ativo'
                WHERE id = ?
                  AND (is_admin = 1 OR role IN ('super_admin', 'moderador', 'financeiro', 'suporte'))";
        $stmt = $db->prepare($sql);

        return $stmt->execute([$until, $userId]) && $stmt->rowCount() > 0;
    }

    public static function unsuspendAdministrativeAccess(int $userId): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET suspended_until = NULL
                WHERE id = ?
                  AND (is_admin = 1 OR role IN ('super_admin', 'moderador', 'financeiro', 'suporte'))";
        $stmt = $db->prepare($sql);

        return $stmt->execute([$userId]) && $stmt->rowCount() > 0;
    }

    private static function generateInternalAdminDocumentNumber(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            try {
                $suffix = strtoupper(bin2hex(random_bytes(6)));
            } catch (\Throwable $e) {
                $suffix = strtoupper(substr(md5(uniqid('', true)), 0, 12));
            }
            $candidate = 'ADM' . substr($suffix, 0, 11);
            if (!self::findByDocumentNumber($candidate)) {
                return $candidate;
            }
        }

        return 'ADM' . strtoupper(substr((string) time(), -11));
    }

    public static function approveUser($id)
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'ativo' WHERE id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int) $id]);

        return $stmt->rowCount() > 0;
    }

    public static function rejectUser($id)
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET status = 'rejeitado' WHERE id = ? AND status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute([(int) $id]);

        return $stmt->rowCount() > 0;
    }

    public static function getAffiliateStats($userId)
    {
        $db = new self();
        $sql = "SELECT COUNT(*) as total_properties FROM property_affiliates WHERE user_id = ? AND status = 'ativo'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $properties = $stmt->fetch(\PDO::FETCH_ASSOC)['total_properties'];

        $sql = "SELECT SUM(affiliate_amount) as total_commissions FROM commissions WHERE affiliate_id = ? AND status = 'pago'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $commissions = $stmt->fetch(\PDO::FETCH_ASSOC)['total_commissions'] ?? 0;

        return ['properties' => $properties, 'commissions' => $commissions];
    }

    /**
     * SQL fragment: 1 when owner has an active trust badge (approved + payment confirmed).
     */
    public static function sqlOwnerTrustedColumn(string $userAlias = 'u'): string
    {
        $u = $userAlias;
        return "CASE WHEN {$u}.status = 'ativo'
                    AND {$u}.trust_badge_status IN ('aprovado', 'approved')
                    AND {$u}.trust_badge_fee_paid = 1
                THEN 1 ELSE 0 END AS owner_trusted";
    }

    /** SQL WHERE clause for trusted-only property filters. */
    public static function sqlTrustedOnlyCondition(string $userAlias = 'u'): string
    {
        $u = $userAlias;
        return " AND {$u}.trust_badge_status IN ('aprovado', 'approved')"
            . " AND {$u}.trust_badge_fee_paid = 1";
    }

    /** Whether the user currently has an active public trust badge. */
    public static function isTrustBadgeActive(?array $user): bool
    {
        if (!$user || ($user['status'] ?? '') !== 'ativo') {
            return false;
        }

        $badgeStatus = strtolower((string) ($user['trust_badge_status'] ?? ''));
        if (!in_array($badgeStatus, ['aprovado', 'approved'], true)) {
            return false;
        }

        if ((int) ($user['trust_badge_fee_paid'] ?? 0) !== 1) {
            return false;
        }

        $months = (int) ($user['trust_badge_duration_months'] ?? 0);
        $approvedAt = trim((string) ($user['trust_badge_approved_at'] ?? ''));
        if ($months > 0 && $approvedAt !== '') {
            $expiresTs = strtotime($approvedAt . ' +' . $months . ' months');
            if ($expiresTs !== false && time() > $expiresTs) {
                return false;
            }
        }

        return true;
    }

    public static function getTrustedBadgeEligibilityConfig(): array
    {
        return \Src\classes\ClassTrustBadgeEligibility::getConfig();
    }

    public static function countWonDealsForUser(int $userId, bool $requireConfirmedClosing = true): int
    {
        $stats = \Src\classes\ClassTrustBadgeEligibility::getWonDealsStats($userId, $requireConfirmedClosing);
        return (int) ($stats['eligible'] ?? 0);
    }

    public static function getWonDealsStatsForUser(int $userId, bool $requireConfirmedClosing = true): array
    {
        return \Src\classes\ClassTrustBadgeEligibility::getWonDealsStats($userId, $requireConfirmedClosing);
    }

    public static function getAccountAgeDays(?array $user): int
    {
        $createdAt = trim((string) ($user['created_at'] ?? ''));
        if ($createdAt === '') {
            return 0;
        }

        $createdTs = strtotime($createdAt);
        if ($createdTs === false) {
            return 0;
        }

        return max(0, (int) floor((time() - $createdTs) / 86400));
    }

    /**
     * @return array{won_deals: array{current:int,required:int,met:bool},account_age_days: array{current:int,required:int,met:bool},met:bool}
     */
    public static function evaluateTrustedBadgeEligibility(int $userId, ?array $user = null): array
    {
        return \Src\classes\ClassTrustBadgeEligibility::evaluateEligibility($userId, $user);
    }

    public static function getTrustedBadgeEligibilityBlockers(array $eligibility): array
    {
        return \Src\classes\ClassTrustBadgeEligibility::getBlockers($eligibility);
    }

    public static function canRequestTrustedBadge(int $userId): array
    {
        $gate = \Src\classes\ClassTrustBadgeEligibility::assertCanRequest((int) $userId);
        $trust = self::getTrustMetrics((int) $userId);

        return [
            'allowed' => ($gate['allowed'] ?? false) === true,
            'blockers' => $gate['blockers'] ?? [],
            'trust' => $trust,
        ];
    }

    public static function getTrustMetrics($userId)
    {
        $user = self::findById($userId);
        if (!$user) {
            return [
                'verified' => false,
                'trusted' => false,
                'badge_status' => 'nenhum',
                'fee_required' => 0,
                'fee_paid' => false,
                'duration_months' => 0,
                'can_request' => false,
                'eligibility' => null,
                'blockers' => [],
            ];
        }
        $verified = ($user['status'] ?? '') === 'ativo';
        $badgeStatus = \Src\classes\ClassTrustBadgeEligibility::normalizeBadgeStatus($user);
        $trusted = $verified && self::isTrustBadgeActive($user);
        $feeRequired = (float) ($user['trust_badge_fee_required'] ?? 0);
        $feePaid = (int) ($user['trust_badge_fee_paid'] ?? 0) === 1;
        $durationMonths = (int) ($user['trust_badge_duration_months'] ?? 0);

        $gate = \Src\classes\ClassTrustBadgeEligibility::assertCanRequest((int) $userId);
        $eligibility = is_array($gate['eligibility'] ?? null) ? $gate['eligibility'] : [];
        $blockers = $gate['blockers'] ?? [];
        $canRequest = ($gate['allowed'] ?? false) === true;

        return [
            'verified' => $verified,
            'trusted' => $trusted,
            'badge_status' => $badgeStatus,
            'fee_required' => $feeRequired,
            'fee_paid' => $feePaid,
            'duration_months' => $durationMonths,
            'can_request' => $canRequest,
            'eligibility' => $eligibility,
            'blockers' => $blockers,
        ];
    }

    public static function getTrustedBadgePricingConfig(): array
    {
        $monthlyFee = (float) \Src\classes\ClassSettings::float('trust_badge_monthly_fee', 5000.0);
        $monthlyFee = max(0.0, $monthlyFee);

        $minMonths = (int) \Src\classes\ClassSettings::int('trust_badge_min_months', 1);
        $maxMonths = (int) \Src\classes\ClassSettings::int('trust_badge_max_months', 12);
        $defaultMonths = (int) \Src\classes\ClassSettings::int('trust_badge_default_months', 6);

        $minMonths = max(1, $minMonths);
        $maxMonths = max($minMonths, min(60, $maxMonths));
        $defaultMonths = max($minMonths, min($maxMonths, $defaultMonths));

        $options = [];
        for ($month = $minMonths; $month <= $maxMonths; $month++) {
            $options[] = [
                'months' => $month,
                'fee' => (float) ($month * $monthlyFee),
            ];
        }

        return [
            'monthly_fee' => $monthlyFee,
            'min_months' => $minMonths,
            'max_months' => $maxMonths,
            'default_months' => $defaultMonths,
            'options' => $options,
        ];
    }

    public static function calculateTrustedBadgeFeeByMonths(int $months): float
    {
        $pricing = self::getTrustedBadgePricingConfig();
        if ($months < (int) $pricing['min_months'] || $months > (int) $pricing['max_months']) {
            return 0.0;
        }

        return (float) ($months * (float) $pricing['monthly_fee']);
    }

    public static function requestTrustedBadge($userId, int $months, float $feeRequired, string $proofPath = '')
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return false;
        }

        $gate = \Src\classes\ClassTrustBadgeEligibility::assertCanRequest($userId);
        if (($gate['allowed'] ?? false) !== true) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET trust_badge_status = 'pendente',
                    trust_badge_requested_at = NOW(),
                    trust_badge_duration_months = ?,
                    trust_badge_fee_required = ?,
                    trust_badge_payment_proof = ?,
                    trust_badge_fee_paid = FALSE
                WHERE id = ?
                  AND status = 'ativo'
                  AND (
                    trust_badge_status IS NULL
                    OR TRIM(trust_badge_status) = ''
                    OR LOWER(TRIM(trust_badge_status)) IN ('nenhum', 'none', 'rejeitado', 'rejected')
                  )";
        $stmt = $db->prepare($sql);
        $ok = $stmt->execute([
            (int) $months,
            (float) max(0.0, $feeRequired),
            $proofPath !== '' ? $proofPath : null,
            $userId,
        ]);

        return $ok && $stmt->rowCount() > 0;
    }

    public static function getTrustBadgePendingUsers(int $limit = 0, int $offset = 0)
    {
        $db = new self();
        $sql = "SELECT * FROM {$db->table} WHERE trust_badge_status = 'pendente' ORDER BY trust_badge_requested_at DESC";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countTrustBadgePendingUsers(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*) FROM {$db->table} WHERE trust_badge_status = 'pendente'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function approveTrustedBadge($userId)
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET trust_badge_status = 'aprovado',
                    trust_badge_approved_at = NOW()
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    public static function setTrustBadgeFeeRequired(int $userId, float $fee): bool
    {
        $db = new self();
        $sql = "UPDATE {$db->table} SET trust_badge_fee_required = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([(float) max(0.0, $fee), $userId]);
    }

    public static function rejectTrustedBadge($userId)
    {
        $db = new self();
        $sql = "UPDATE {$db->table}
                SET trust_badge_status = 'rejeitado',
                    trust_badge_duration_months = NULL,
                    trust_badge_payment_proof = NULL,
                    trust_badge_fee_required = 0,
                    trust_badge_fee_paid = FALSE
                WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$userId]);
    }

    public static function markTrustedBadgeFeePaid($userId)
    {
        $db = new self();
        $user = self::findById($userId);
        if (!$user) {
            return false;
        }

        $sql = "UPDATE {$db->table} SET trust_badge_fee_paid = TRUE WHERE id = ?";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$userId]);

        if ($result && $stmt->rowCount() > 0) {
            try {
                $fee = (float) ($user['trust_badge_fee_required'] ?? 0);
                if ($fee > 0) {
                    PaymentTransaction::create([
                        'transaction_type' => 'trust_badge_fee',
                        'direction' => 'incoming',
                        'status' => 'confirmado',
                        'amount' => $fee,
                        'currency' => 'AOA',
                        'counterparty_user_id' => (int) $userId,
                        'related_entity_type' => 'user',
                        'related_entity_id' => (int) $userId,
                        'confirmed_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Throwable $e) {
                // Log error but don't fail the transaction
            }
        }

        return $result;
    }

    public static function updateProfile($id, $data)
    {
        $db = new self();
        $allowedColumns = ['name', 'email', 'phone', 'password', 'profile_photo', 'document_number'];
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $payload['email'] = $data['email'];
        }
        if (array_key_exists('phone', $data)) {
            $payload['phone'] = $data['phone'];
        }
        if (array_key_exists('password', $data)) {
            $payload['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (array_key_exists('profile_photo', $data)) {
            $payload['profile_photo'] = $data['profile_photo'];
        }
        if (array_key_exists('document_number', $data)) {
            $payload['document_number'] = $data['document_number'];
        }

        if ($payload === []) {
            return false;
        }

        return $db->updateWhere($db->table, $payload, $allowedColumns, 'id = ?', [(int) $id]);
    }

    public static function updateUsername(int $id, string $username): bool
    {
        $username = \Src\classes\UsernameHelper::normalize($username);
        if ($username === '') {
            return false;
        }

        $user = self::findById($id);
        if (!$user) {
            return false;
        }

        $current = \Src\classes\UsernameHelper::normalize((string) ($user['username'] ?? ''));
        if ($current === $username) {
            return true;
        }

        if (!\Src\classes\UsernameHelper::canChangeUsername($user)) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table} SET username = ?, username_changed_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);

        return $stmt->execute([$username, $id]);
    }

    public static function getRegistrationStats(): array
    {
        $db = new self();
        $sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN deletion_requested_at IS NOT NULL THEN 1 ELSE 0 END) AS pending_deletion,
            SUM(CASE WHEN is_affiliate = 1 THEN 1 ELSE 0 END) AS affiliates,
            SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN 1 ELSE 0 END) AS new_this_month
        FROM {$db->table} WHERE is_admin = 0";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all active users with a specific role.
     * Used to notify financeiro/admin users of certain events.
     */
    public static function getByRole(string $role): array
    {
        $db   = new self();
        $sql  = "SELECT id, name, email FROM {$db->table} WHERE role = ? AND status = 'ativo'";
        $stmt = $db->prepare($sql);
        $stmt->execute([$role]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get active admin users (is_admin = 1 OR role = super_admin).
     */
    public static function getAdminUsers(): array
    {
        $db   = new self();
        $sql  = "SELECT id, name, email FROM {$db->table} WHERE (is_admin = 1 OR role = 'super_admin') AND status = 'ativo'";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getPromoterProgramTerms(string $userType = 'pessoa_fisica'): array
    {
        $isCompany = $userType === 'pessoa_juridica';
        $profileLabel = $isCompany ? 'Pessoa jurídica' : 'Pessoa singular';

        $sections = [
            [
                'heading' => '1. Objeto',
                'content' => $isCompany
                    ? 'O perfil de Promotor permite que a sua empresa indique imóveis de outros proprietários na plataforma Imobil Fácil e receba comissões quando os negócios forem concluídos com sucesso, nos termos operacionais vigentes.'
                    : 'O perfil de Promotor permite que, enquanto pessoa singular, indique imóveis de outros proprietários na plataforma Imobil Fácil e receba comissões quando os negócios forem concluídos com sucesso, nos termos operacionais vigentes.',
            ],
            [
                'heading' => '2. Elegibilidade',
                'content' => $isCompany
                    ? 'Podem activar o perfil de Promotor contas de pessoa jurídica registadas, activas e verificadas na plataforma, com representante autorizado. A empresa não pode indicar imóveis de que seja proprietária directa na mesma conta.'
                    : 'Podem activar o perfil de Promotor utilizadores de pessoa singular registados, activos e verificados na plataforma. O promotor não pode indicar imóveis que lhe pertençam directamente.',
            ],
            [
                'heading' => '3. Identificação e Representação',
                'content' => $isCompany
                    ? 'A activação vincula-se aos dados da empresa (nome, NIF/documento e contactos) registados na conta. Quem activa o perfil declara ter poderes para representar a entidade perante a plataforma.'
                    : 'A activação vincula-se aos seus dados pessoais (nome, documento de identificação e contactos) registados na conta, que devem estar correctos e actualizados.',
            ],
            [
                'heading' => '4. Comissões e Pagamentos',
                'content' => $isCompany
                    ? 'As comissões são calculadas conforme as regras da plataforma e creditadas/processadas após o fecho comercial confirmado. Pagamentos à empresa dependem dos canais e dados de recebimento configurados na conta.'
                    : 'As comissões são calculadas conforme as regras da plataforma e pagas após confirmação do fecho comercial. Os valores são atribuídos ao titular da conta, nos canais de pagamento configurados.',
            ],
            [
                'heading' => '5. Conduta e Responsabilidade',
                'content' => $isCompany
                    ? 'A empresa e o seu representante comprometem-se a divulgar imóveis de forma honesta, sem informação falsa ou enganosa, a cumprir a legislação aplicável e a política da plataforma, e a não praticar spam, fraude ou concorrência desleal.'
                    : 'Compromete-se a divulgar imóveis de forma honesta, sem informação falsa ou enganosa, a cumprir a legislação aplicável e a política da plataforma, e a não praticar spam, fraude ou concorrência desleal, assumindo responsabilidade pessoal pela sua actividade de promotor.',
            ],
            [
                'heading' => '6. Afiliação por Imóvel',
                'content' => 'A activação do perfil de Promotor não garante afiliação automática a todos os imóveis. Cada anúncio pode exigir termos específicos de afiliação, aprovação automática ou validação pelo proprietário.',
            ],
            [
                'heading' => '7. Suspensão e Rescisão',
                'content' => 'A plataforma pode suspender ou retirar o perfil de Promotor em caso de violação destes termos, incumprimento de obrigações financeiras, conduta abusiva ou decisão de moderação fundamentada.',
            ],
            [
                'heading' => '8. Aceitação',
                'content' => $isCompany
                    ? 'Ao activar o perfil de Promotor, a empresa, através do representante que confirma nesta conta, declara que leu, compreendeu e aceita estes Termos e Condições, bem como as regras operacionais e de comissões em vigor.'
                    : 'Ao activar o perfil de Promotor, declara que leu, compreendeu e aceita integralmente estes Termos e Condições, bem como as regras operacionais e de comissões em vigor.',
            ],
        ];

        return [
            'title' => 'Programa de Promotor — Termos (' . $profileLabel . ')',
            'user_type' => $isCompany ? 'pessoa_juridica' : 'pessoa_fisica',
            'user_type_label' => $profileLabel,
            'sections' => $sections,
            'last_updated' => '2026-05-24',
        ];
    }

    /**
     * Enable affiliate/promoter status for a user (terms acceptance validated at activation).
     */
    public static function enableAffiliate(int $id): bool
    {
        $db  = new self();
        $sql = "UPDATE {$db->table} SET is_affiliate = 1 WHERE id = ? AND is_admin = 0 AND status = 'ativo' AND (is_affiliate = 0 OR is_affiliate IS NULL)";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public static function isPendingDeletion(?array $user): bool
    {
        if (!is_array($user)) {
            return false;
        }

        return !empty($user['deletion_requested_at']);
    }

    public static function getAccountDeletionGraceDays(): int
    {
        return max(1, (int) \Src\classes\ClassSettings::int('account_deletion_grace_days', 60));
    }

    public static function sqlExcludePendingDeletionOwners(string $userAlias = 'u'): string
    {
        $alias = self::assertIdentifier($userAlias);

        return "({$alias}.deletion_requested_at IS NULL)";
    }

    /**
     * @return int|null Número de imóveis marcados para eliminação, ou null se o pedido falhou.
     */
    public static function requestAccountDeletion(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $days = self::getAccountDeletionGraceDays();
        $scheduledAt = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET deletion_requested_at = NOW(),
                    deletion_scheduled_at = ?,
                    deletion_reminder_sent_at = NULL
                WHERE id = ?
                  AND is_admin = 0
                  AND role = 'utilizador'
                  AND deletion_requested_at IS NULL";
        $stmt = $db->prepare($sql);

        if (!$stmt->execute([$scheduledAt, $userId])) {
            return null;
        }

        if ($stmt->rowCount() <= 0) {
            return null;
        }

        return \App\services\ComplianceDeletionService::afterAccountDeletionRequested($userId);
    }

    public static function cancelAccountDeletion(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET deletion_requested_at = NULL,
                    deletion_scheduled_at = NULL,
                    deletion_reminder_sent_at = NULL
                WHERE id = ?
                  AND is_admin = 0
                  AND role = 'utilizador'
                  AND deletion_requested_at IS NOT NULL";
        $stmt = $db->prepare($sql);

        if (!$stmt->execute([$userId])) {
            return false;
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Elimina/anonymiza a conta após o período de conformidade ou por acção admin.
     */
    public static function purgeAccountPermanently(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = self::findById($userId);
        if (!$user || !empty($user['is_admin']) || (string) ($user['role'] ?? '') !== 'utilizador') {
            return false;
        }

        \App\services\ComplianceDeletionService::purgeUserResidualData($user);

        $db = new self();

        $token = 'removed_' . $userId . '_' . time();
        $username = 'removed_' . $userId;
        $documentNumber = 'REM' . str_pad((string) $userId, 8, '0', STR_PAD_LEFT);
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $sql = "UPDATE {$db->table}
                SET email = ?,
                    phone = NULL,
                    name = 'Conta removida',
                    username = ?,
                    document_number = ?,
                    password = ?,
                    profile_photo = NULL,
                    document_file = NULL,
                    is_affiliate = 0,
                    affiliate_code = NULL,
                    status = 'rejeitado',
                    suspended_until = '2099-12-31 23:59:59',
                    deletion_requested_at = NULL,
                    deletion_scheduled_at = NULL,
                    deletion_reminder_sent_at = NULL
                WHERE id = ?
                  AND is_admin = 0
                  AND role = 'utilizador'";
        $stmt = $db->prepare($sql);
        if (!$stmt->execute([$token . '@removed.local', $username, $documentNumber, $password, $userId])) {
            return false;
        }

        if ($stmt->rowCount() <= 0) {
            return false;
        }

        \App\services\HeaderShellService::invalidateForUser($userId);

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getDueAccountDeletions(int $limit = 50): array
    {
        $db = new self();
        $sql = "SELECT id, name, email, deletion_requested_at, deletion_scheduled_at
                FROM {$db->table}
                WHERE is_admin = 0
                  AND role = 'utilizador'
                  AND deletion_requested_at IS NOT NULL
                  AND deletion_scheduled_at IS NOT NULL
                  AND deletion_scheduled_at <= NOW()
                ORDER BY deletion_scheduled_at ASC, id ASC
                LIMIT " . max(1, (int) $limit);
        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function processDueAccountDeletions(int $limit = 50): int
    {
        $processed = 0;
        foreach (self::getDueAccountDeletions($limit) as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId > 0 && self::purgeAccountPermanently($userId)) {
                $processed++;
            }
        }

        return $processed;
    }

    public static function countOverdueDeletions(): int
    {
        $db = new self();
        $sql = "SELECT COUNT(*)
                FROM {$db->table}
                WHERE is_admin = 0
                  AND role = 'utilizador'
                  AND deletion_requested_at IS NOT NULL
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
        $sql = "SELECT id, name, email, deletion_scheduled_at
                FROM {$db->table}
                WHERE is_admin = 0
                  AND role = 'utilizador'
                  AND deletion_requested_at IS NOT NULL
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

    public static function markDeletionReminderSent(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $db = new self();
        $sql = "UPDATE {$db->table}
                SET deletion_reminder_sent_at = NOW()
                WHERE id = ?
                  AND deletion_reminder_sent_at IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->rowCount() > 0;
    }
}
