<?php

namespace Src\classes;

class ClassAccess
{
    /** @deprecated Use UserAccountState::STATUS_* */
    public const USER_STATUS_PENDENTE = UserAccountState::STATUS_PENDENTE;
    /** @deprecated Use UserAccountState::STATUS_* */
    public const USER_STATUS_ATIVO = UserAccountState::STATUS_ATIVO;
    /** @deprecated Use UserAccountState::STATUS_* */
    public const USER_STATUS_REJEITADO = UserAccountState::STATUS_REJEITADO;

    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'moderador' => ['dashboard.view', 'users.review', 'documents.review', 'properties.moderate'],
        'financeiro' => ['dashboard.view', 'payments.manage'],
        'suporte' => ['dashboard.view', 'requests.manage'],
        'utilizador' => [],
    ];

    private static function redirectWithError(string $path, string $message): void
    {
        $location = DIRPAGE . ltrim($path, '/');
        $separator = (strpos($location, '?') === false) ? '?' : '&';
        header('Location: ' . $location . $separator . 'error=' . urlencode($message));
        exit;
    }

    public static function getRole(?array $user = null): string
    {
        $user = $user ?? ClassAuth::user();
        $role = strtolower((string) ($user['role'] ?? ''));
        if ($role !== '' && isset(self::ROLE_PERMISSIONS[$role])) {
            return $role;
        }
        if (!empty($user['is_admin'])) {
            return 'super_admin';
        }
        return 'utilizador';
    }

    public static function can(string $permission, ?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();
        if (!$user) {
            return false;
        }

        $role = self::getRole($user);
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];

        if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
            return true;
        }

        return false;
    }

    public static function roleLabel(?array $user = null): string
    {
        $user = $user ?? ClassAuth::user();
        $role = self::getRole($user);

        if ($role === 'super_admin') {
            return 'Admin Total';
        }
        if ($role === 'moderador') {
            return 'Admin Moderação';
        }
        if ($role === 'financeiro') {
            return 'Admin Financeiro';
        }
        if ($role === 'suporte') {
            return 'Admin Suporte';
        }
        if (!empty($user['is_affiliate'])) {
            return 'Afiliado';
        }

        return 'Utilizador';
    }

    public static function isSuperAdmin(?array $user = null): bool
    {
        return self::getRole($user) === 'super_admin';
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();
        return !empty($user['is_admin']) || self::getRole($user) !== 'utilizador';
    }

    public static function isAffiliate(?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();
        return !empty($user['is_affiliate']);
    }

    public static function accountState(?array $user = null): array
    {
        $user = $user ?? ClassAuth::user();

        return UserAccountState::resolve(is_array($user) ? $user : null);
    }

    public static function getUserStatus(?array $user = null): string
    {
        $user = $user ?? ClassAuth::user();

        return UserAccountState::normalizeStatus(is_array($user) ? $user : null);
    }

    public static function userStatusLabel(?array $user = null): string
    {
        return self::accountState($user)['status_label'];
    }

    public static function isAccountBlocked(?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();
        if (!$user) {
            return true;
        }

        return UserAccountState::isSuspended($user);
    }

    public static function isAccountSuspended(?array $user = null): bool
    {
        return self::isAccountBlocked($user);
    }

    public static function hasFullPlatformAccess(?array $user = null): bool
    {
        return self::accountState($user)['can_full_platform'];
    }

    public static function hasLimitedPlatformAccess(?array $user = null): bool
    {
        return self::accountState($user)['show_limited_menu'];
    }

    public static function isPendingAccountDeletion(?array $user = null): bool
    {
        return self::accountState($user)['is_pending_deletion'] ?? false;
    }

    public static function canUseDeletionStatusPage(?array $user = null): bool
    {
        return self::accountState($user)['can_deletion_status_page'] ?? false;
    }

    public static function canUseAccountStatusPage(?array $user = null): bool
    {
        return self::accountState($user)['can_account_status_page'];
    }

    public static function canEditIdentificationOnAccountStatusPage(?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();

        return UserAccountState::canEditIdentificationOnAccountPage(is_array($user) ? $user : null);
    }

    public static function canSubmitDocumentsOnAccountStatusPage(?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();
        if (!is_array($user)) {
            return false;
        }

        $compliance = \App\model\Document::getComplianceStatus((int) ($user['id'] ?? 0));
        $rejectedCount = count(\App\model\Document::getRejectedByUser((int) ($user['id'] ?? 0)));

        return UserAccountState::resolveWithDocument($user, $compliance, $rejectedCount)['can_submit_documents_on_account_page'];
    }

    /** @deprecated Use canEditIdentificationOnAccountStatusPage */
    public static function canEditOnAccountStatusPage(?array $user = null): bool
    {
        return self::canEditIdentificationOnAccountStatusPage($user);
    }

    public static function canAccessAuthenticatedArea(?array $user = null): bool
    {
        $state = self::accountState($user);

        return $state['can_full_platform'] || $state['can_account_status_page'] || $state['can_deletion_status_page'];
    }

    /**
     * Contas da equipa (admin/moderação/financeiro/suporte) não actuam como compradores ou inquilinos.
     * Utilizadores com conta pendente de aprovação também não podem solicitar negócios.
     */
    public static function canSubmitPropertyRequest(?array $user = null): bool
    {
        $user = $user ?? ClassAuth::user();
        if (!$user) {
            return false;
        }

        return self::hasFullPlatformAccess($user) && !self::isAdmin($user);
    }

    public static function requireAuthenticatedAccount(
        string $redirectPath = 'login',
        string $message = 'Faça login para continuar'
    ): array {
        if (!ClassAuth::check()) {
            self::redirectWithError($redirectPath, $message);
        }

        $user = ClassAuth::user();
        if (!$user || self::isAccountBlocked($user)) {
            ClassAuth::logout();
            self::redirectWithError('login', 'O seu acesso está suspenso. Tente mais tarde ou contacte o suporte.');
        }

        return $user;
    }

    public static function requireFullPlatformAccess(
        string $redirectPath = 'dashboard/accountStatus',
        string $message = 'Isto fica disponível quando a sua conta estiver activa.'
    ): array {
        $user = self::requireAuthenticatedAccount();

        if (!self::hasFullPlatformAccess($user)) {
            self::redirectWithError($redirectPath, $message);
        }

        return $user;
    }

    public static function requireAdmin(
        string $redirectPath = 'dashboard',
        string $message = 'Acesso disponível apenas para administradores'
    ): array {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();
        if (!self::isAdmin($user)) {
            self::redirectWithError($redirectPath, $message);
        }
        return $user;
    }

    public static function requirePermission(
        string $permission,
        string $redirectPath = 'dashboard',
        string $message = 'Sem permissão para executar esta ação'
    ): array {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();
        if (!self::can($permission, $user)) {
            self::redirectWithError($redirectPath, $message);
        }
        return $user;
    }

    public static function requireSuperAdmin(
        string $redirectPath = 'dashboard',
        string $message = 'Acesso disponível apenas para Admin Total'
    ): array {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();
        if (!self::isSuperAdmin($user)) {
            self::redirectWithError($redirectPath, $message);
        }
        return $user;
    }

    public static function requireAffiliate(
        string $redirectPath = 'dashboard',
        string $message = 'Acesso disponível apenas para afiliados'
    ): array {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();
        if (!self::isAffiliate($user)) {
            self::redirectWithError($redirectPath, $message);
        }
        return $user;
    }

    public static function requireNonAdmin(
        string $redirectPath = 'dashboard',
        string $message = 'Administradores não podem executar esta ação'
    ): array {
        ClassAuth::requireAuth();
        $user = ClassAuth::user();
        if (self::isAdmin($user)) {
            self::redirectWithError($redirectPath, $message);
        }
        return $user;
    }

    public static function canManagePropertyRequests(array $user, array $property): bool
    {
        if (self::can('requests.manage', $user)) {
            return true;
        }
        return (int) ($property['affiliate_id'] ?? 0) === (int) ($user['id'] ?? 0);
    }
}
