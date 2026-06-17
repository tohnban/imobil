<?php

namespace Src\classes;

/**
 * Respostas JSON para pedidos AJAX (X-Requested-With: XMLHttpRequest), com fallback a redirect.
 */
final class ClassAjaxResponse
{
    public static function json(bool $success, array $data = [], int $httpCode = 200): void
    {
        $payload = array_merge(['success' => $success], $data);
        if (!isset($payload['csrf_token'])) {
            $payload['csrf_token'] = ClassCsrf::get();
        }

        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirectOrJson(bool $success, string $message, string $fallbackPath, array $extra = []): void
    {
        if (ClassCsrf::isAjaxRequest()) {
            $data = $extra;
            if ($success) {
                $data['message'] = $message;
            } else {
                $data['error'] = $message;
            }
            self::json($success, $data, $success ? 200 : 422);
        }

        $url = ClassCsrf::resolveReturnUrl($fallbackPath);
        $param = $success ? 'success' : 'error';
        $separator = strpos($url, '?') === false ? '?' : '&';
        header('Location: ' . $url . $separator . $param . '=' . urlencode($message));
        exit;
    }
}
