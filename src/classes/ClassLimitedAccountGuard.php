<?php

namespace Src\classes;

class ClassLimitedAccountGuard
{
    private const EXEMPT_ROUTE_KEYS = [
        'logout',
        'authenticate',
        'login',
        'register',
        'store',
        'recover',
        'reset',
        'verify',
        'cookies',
        'privacidade',
        'termos',
        'sitemap',
        'robots.txt',
        '404',
    ];

    private const PROPERTY_READ_METHODS = [
        'index',
        'properties',
        'show',
        'featured',
        'agency',
        'list',
    ];

    private const ALLOWED_DASHBOARD_METHODS = [
        'accountStatus',
        'accountDeletionStatus',
        'update',
        'resubmitDocument',
        'submitAccountDocument',
        'cancelAccountDeletion',
    ];

    public static function enforce(string $controller, array $url): void
    {
        if (!ClassAuth::check()) {
            return;
        }

        $user = ClassAuth::user();
        if (!$user) {
            return;
        }

        if (ClassAccess::isAdmin($user)) {
            return;
        }

        if (ClassAccess::isPendingAccountDeletion($user)) {
            if (self::isExemptRoute($controller, $url)) {
                return;
            }

            if ($controller === 'ControllerLegal') {
                return;
            }

            if (self::isDashboardController($controller) && self::isAllowedDashboardRoute($url)) {
                return;
            }

            self::redirectDeletionPending();
        }

        if (ClassAccess::hasFullPlatformAccess($user)) {
            return;
        }

        if (ClassAccess::isAccountBlocked($user)) {
            ClassAuth::logout();
            header('Location: ' . DIRPAGE . 'login?error=' . rawurlencode('A sua conta está bloqueada. Contacte o suporte se precisar de ajuda.'));
            exit;
        }

        if (!ClassAccess::hasLimitedPlatformAccess($user)) {
            return;
        }

        if (self::isExemptRoute($controller, $url)) {
            return;
        }

        if ($controller === 'ControllerHome') {
            return;
        }

        if (self::isPropertyController($controller) && self::isPropertyReadRoute($url)) {
            return;
        }

        if (self::isDashboardController($controller) && self::isAllowedDashboardRoute($url)) {
            return;
        }

        if (self::isDashboardController($controller) && self::isAllowedProfileRoute($url)) {
            return;
        }

        if ($controller === 'ControllerLegal') {
            return;
        }

        self::redirectLimited('Isto fica disponível quando a sua conta estiver activa.');
    }

    public static function redirectDeletionPending(string $message = ''): void
    {
        $location = DIRPAGE . 'dashboard/accountDeletionStatus';
        if ($message !== '') {
            $location .= '?error=' . rawurlencode($message);
        }
        header('Location: ' . $location);
        exit;
    }

    public static function redirectLimited(string $message = ''): void
    {
        $location = DIRPAGE . 'dashboard/accountStatus';
        if ($message !== '') {
            $location .= '?error=' . rawurlencode($message);
        }
        header('Location: ' . $location);
        exit;
    }

    private static function isExemptRoute(string $controller, array $url): bool
    {
        $routeKey = strtolower((string) ($url[0] ?? ''));
        return in_array($routeKey, self::EXEMPT_ROUTE_KEYS, true);
    }

    private static function isPropertyReadRoute(array $url): bool
    {
        $routeKey = strtolower((string) ($url[0] ?? ''));
        if ($routeKey === 'properties' || $routeKey === 'featured') {
            return true;
        }

        if ($routeKey !== 'property') {
            return false;
        }

        $method = self::resolveUrlMethod($url[1] ?? '');
        if ($method === '' || is_numeric($method)) {
            return true;
        }

        return in_array($method, self::PROPERTY_READ_METHODS, true);
    }

    private static function isAllowedProfileRoute(array $url): bool
    {
        $routeKey = strtolower((string) ($url[0] ?? ''));
        if ($routeKey !== 'profile') {
            return false;
        }

        $method = self::resolveUrlMethod($url[1] ?? 'index');

        return $method === 'update';
    }

    private static function isAllowedDashboardRoute(array $url): bool
    {
        $routeKey = strtolower((string) ($url[0] ?? ''));
        if ($routeKey !== 'dashboard') {
            return false;
        }

        $method = self::resolveUrlMethod($url[1] ?? 'index');
        if ($method === '' || $method === 'index') {
            return false;
        }

        return in_array($method, self::ALLOWED_DASHBOARD_METHODS, true);
    }

    private static function isDashboardController(string $controller): bool
    {
        return str_starts_with($controller, 'ControllerDashboard');
    }

    private static function isPropertyController(string $controller): bool
    {
        return $controller === 'ControllerProperty' || str_starts_with($controller, 'ControllerProperty');
    }

    private static function resolveUrlMethod(string $segment): string
    {
        $segment = trim($segment);
        if ($segment === '') {
            return '';
        }

        if (strpos($segment, '_') !== false) {
            return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $segment))));
        }

        return $segment;
    }
}
