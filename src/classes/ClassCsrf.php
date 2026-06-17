<?php

namespace Src\classes;

class ClassCsrf
{
    private static $tokenName = 'csrf_token';

    public static function generate()
    {
        $token = bin2hex(random_bytes(32));
        ClassSession::set(self::$tokenName, $token);
        return $token;
    }

    public static function get()
    {
        $token = ClassSession::get(self::$tokenName);
        if (!$token) {
            $token = self::generate();
        }
        return $token;
    }

    /**
     * Validates the submitted token. Does not rotate the session token so multiple
     * forms/tabs and AJAX calls on the same page stay valid until login/logout.
     */
    public static function validate($token)
    {
        $sessionToken = ClassSession::get(self::$tokenName);
        if (!$sessionToken || !hash_equals($sessionToken, (string) $token)) {
            return false;
        }
        return true;
    }

    /**
     * Safe in-app return URL: same host as DIRPAGE, or built from $fallback path.
     */
    public static function resolveReturnUrl(string $fallback = ''): string
    {
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $appBase = rtrim((string) DIRPAGE, '/');

        if ($referer !== '') {
            $refParts = parse_url($referer);
            $baseParts = parse_url($appBase !== '' ? $appBase : $referer);
            $refHost = strtolower((string) ($refParts['host'] ?? ''));
            $baseHost = strtolower((string) ($baseParts['host'] ?? ''));
            if ($refHost !== '' && $baseHost !== '' && $refHost === $baseHost) {
                return $referer;
            }
        }

        if ($fallback === '') {
            return $appBase !== '' ? $appBase . '/' : '/';
        }

        if (preg_match('#^https?://#i', $fallback)) {
            return $fallback;
        }

        return $appBase . '/' . ltrim($fallback, '/');
    }

    public static function isAjaxRequest(): bool
    {
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            return true;
        }

        if (strtolower((string) ($_SERVER['HTTP_X_IMOBIL_AJAX'] ?? '')) === '1') {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if ($accept !== '' && stripos($accept, 'application/json') !== false) {
            return true;
        }

        if (isset($_POST['_ajax']) && (string) $_POST['_ajax'] === '1') {
            return true;
        }

        if (isset($_GET['_ajax']) && (string) $_GET['_ajax'] === '1') {
            return true;
        }

        return false;
    }

    /**
     * Reject invalid CSRF on POST (JSON for AJAX, redirect otherwise).
     */
    public static function rejectPost(string $fallback = '', string $message = 'Token inválido'): void
    {
        if (self::isAjaxRequest()) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $message,
                'csrf_token' => self::get(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        self::failRedirect($fallback, $message);
    }

    /**
     * Validate CSRF token on POST; reject when invalid.
     */
    public static function enforcePostToken(string $fallback = ''): void
    {
        if (!self::validate($_POST['csrf_token'] ?? '')) {
            self::rejectPost($fallback);
        }
    }

    /**
     * Redirect back to referer (same host) or fallback with an error query param.
     */
    public static function failRedirect(string $fallback = '', string $message = 'Token inválido'): void
    {
        $url = self::resolveReturnUrl($fallback);
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        header('Location: ' . $url . $separator . 'error=' . urlencode($message));
        exit;
    }

    /**
     * Validate CSRF token and redirect on failure (for form POST handlers).
     */
    public static function verify(string $token, ?string $redirectUrl = null): void
    {
        if (self::validate($token)) {
            return;
        }

        if ($redirectUrl !== null && $redirectUrl !== '') {
            $separator = (strpos($redirectUrl, '?') === false) ? '?' : '&';
            if (strpos($redirectUrl, 'error=') === false) {
                $redirectUrl .= $separator . 'error=' . urlencode('Token inválido');
            }
            header('Location: ' . $redirectUrl);
            exit;
        }

        self::failRedirect('dashboard');
    }

    public static function field()
    {
        $token = self::get();
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
